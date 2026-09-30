<?php

namespace Tests\Feature\Concurrency;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * AET-RC01 finding 4 — genuine concurrency proof, deliberately NOT built on
 * Laravel's TestCase/RefreshDatabase: those wrap each test in a single
 * outer transaction on a single connection, which can only ever prove
 * sequential logic, never a real race between two independent database
 * sessions. This test opens two raw PDO connections to a real PostgreSQL
 * server (the same one docker-compose starts for local dev — see
 * docker-compose.yml) and interleaves their transactions by hand.
 *
 * It is skipped (not failed) when that Postgres isn't reachable, so the
 * default SQLite-backed `php artisan test` run — and CI's fast job — never
 * depend on it. The dedicated Postgres CI job (see .github/workflows/ci.yml)
 * always has it available and must not skip it.
 */
class AttemptConcurrencyPostgresTest extends TestCase
{
    private const HOST = '127.0.0.1';

    private const PORT = '55432';

    private const USER = 'academia_aet';

    private const PASSWORD = 'academia_aet';

    private const DB_NAME = 'academia_aet_concurrency_test';

    private ?PDO $maintenance = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->maintenance = new PDO(
                sprintf('pgsql:host=%s;port=%s;dbname=academia_aet', self::HOST, self::PORT),
                self::USER,
                self::PASSWORD,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        } catch (PDOException $e) {
            $this->markTestSkipped('PostgreSQL not reachable at '.self::HOST.':'.self::PORT.' — start it with `docker-compose up -d pgsql` to run this test. ('.$e->getMessage().')');
        }

        // Postgres has no CREATE DATABASE IF NOT EXISTS — drop any leftover
        // from a previous interrupted run, then start clean.
        $this->maintenance->exec('DROP DATABASE IF EXISTS '.self::DB_NAME);
        $this->maintenance->exec('CREATE DATABASE '.self::DB_NAME);
    }

    protected function tearDown(): void
    {
        if ($this->maintenance !== null) {
            $this->maintenance->exec('DROP DATABASE IF EXISTS '.self::DB_NAME);
        }

        parent::tearDown();
    }

    private function connect(): PDO
    {
        return new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', self::HOST, self::PORT, self::DB_NAME),
            self::USER,
            self::PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    /**
     * Only the two tables this test actually exercises — the real
     * migrations are covered by the ordinary SQLite suite and by the
     * Postgres CI job running the full `php artisan test`. This test's job
     * is narrowly to prove the locking/constraint *mechanism*, against the
     * same DDL the real migrations produce for these two tables and the
     * partial unique index added in
     * 2026_09_30_201646_add_one_in_progress_attempt_constraint_to_attempts_table.
     */
    private function createSchema(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE assignments (
                id BIGSERIAL PRIMARY KEY,
                status VARCHAR(16) NOT NULL DEFAULT \'assigned\',
                max_attempts INTEGER
            )
        ');

        $pdo->exec('
            CREATE TABLE attempts (
                id BIGSERIAL PRIMARY KEY,
                assignment_id BIGINT NOT NULL REFERENCES assignments(id),
                attempt_number SMALLINT NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'in_progress\',
                started_at TIMESTAMP NOT NULL DEFAULT now(),
                UNIQUE (assignment_id, attempt_number)
            )
        ');

        $pdo->exec("CREATE UNIQUE INDEX one_in_progress_attempt_per_assignment ON attempts (assignment_id) WHERE status = 'in_progress'");
    }

    /**
     * A committed-then-conflicting insert from a second, wholly independent
     * connection — proves the partial unique index itself rejects a
     * duplicate in_progress row across two real database sessions. It does
     * not by itself prove two *simultaneously open* transactions race
     * correctly — that's test_two_simultaneously_open_transactions_serialize_on_the_row_lock()
     * below, which uses a real forked OS process to hold a transaction open
     * while this one is mid-flight.
     */
    public function test_the_partial_unique_index_rejects_a_second_committed_in_progress_attempt(): void
    {
        $setup = $this->connect();
        $this->createSchema($setup);
        $setup->exec('INSERT INTO assignments (id, status, max_attempts) VALUES (1, \'assigned\', NULL)');
        $setup = null;

        $connA = $this->connect();
        $connB = $this->connect();

        $connA->beginTransaction();
        $connB->beginTransaction();

        $connA->exec("INSERT INTO attempts (assignment_id, attempt_number, status) VALUES (1, 1, 'in_progress')");
        $connA->commit();

        $rejected = false;
        try {
            $connB->exec("INSERT INTO attempts (assignment_id, attempt_number, status) VALUES (1, 2, 'in_progress')");
            $connB->commit();
        } catch (PDOException $e) {
            $rejected = true;
            $this->assertSame('23505', $e->getCode(), 'Expected a unique_violation on the partial index, got: '.$e->getMessage());
            $connB->rollBack();
        }

        $this->assertTrue($rejected, 'The second concurrent in_progress attempt was not rejected — the partial unique index did not do its job.');

        $verify = $this->connect();
        $count = (int) $verify->query("SELECT COUNT(*) FROM attempts WHERE assignment_id = 1 AND status = 'in_progress'")->fetchColumn();
        $this->assertSame(1, $count, 'More than one in_progress attempt exists for the same assignment.');
    }

    /**
     * The genuine race: a forked child process holds `SELECT ... FOR
     * UPDATE` on the assignment row open (uncommitted) while the parent
     * process — running concurrently, as a real separate OS process, not
     * merely "later" in the same script — tries to acquire the same lock.
     * This is exactly what AttemptService::startOrResume()'s
     * `lockForUpdate()` does in production: the parent's lock attempt must
     * genuinely block until the child commits, and only then proceed —
     * never both succeed in believing "no in_progress attempt exists yet".
     */
    public function test_two_simultaneously_open_transactions_serialize_on_the_row_lock(): void
    {
        $setup = $this->connect();
        $this->createSchema($setup);
        $setup->exec('INSERT INTO assignments (id, status, max_attempts) VALUES (1, \'assigned\', NULL)');
        $setup = null;

        [$readPipe, $writePipe] = [null, null];
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->assertNotFalse($sockets, 'Could not create a socket pair for parent/child synchronization.');
        [$parentSocket, $childSocket] = $sockets;

        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid, 'pcntl_fork() failed.');

        if ($pid === 0) {
            // Child: acquires the lock first, signals the parent, holds it
            // open for a bit, then commits — mirrors "child A calls
            // startOrResume() first and is mid-transaction".
            fclose($parentSocket);
            $conn = $this->connect();
            $conn->beginTransaction();
            $conn->query('SELECT * FROM assignments WHERE id = 1 FOR UPDATE');
            fwrite($childSocket, 'locked');
            usleep(400_000);
            $conn->exec("INSERT INTO attempts (assignment_id, attempt_number, status) VALUES (1, 1, 'in_progress')");
            $conn->commit();
            fclose($childSocket);
            // A plain exit() would run this process's destructors,
            // including the setUp() maintenance PDO connection — fork()
            // duplicated that connection's underlying socket into this
            // process, so a clean libpq disconnect here sends a Postgres
            // wire-protocol Terminate that kills the connection for the
            // PARENT's copy too (same TCP session, not just the same fd
            // number). SIGKILL-ing self skips all destructors.
            posix_kill(posix_getpid(), SIGKILL);
            exit(0); // unreachable, kept so static analysis sees a return path
        }

        // Parent: waits for the child to confirm it holds the lock, then
        // tries to acquire the same lock — this call must genuinely block
        // (proven by timing it) until the child's commit above releases it.
        fclose($childSocket);
        $signal = fread($parentSocket, 6);
        $this->assertSame('locked', $signal, 'Child never confirmed it held the row lock.');
        fclose($parentSocket);

        $conn = $this->connect();
        $conn->beginTransaction();
        $start = microtime(true);
        $conn->query('SELECT * FROM assignments WHERE id = 1 FOR UPDATE');
        $blockedFor = microtime(true) - $start;

        // By the time our lock request returns, the child has already
        // committed its attempt — so we must see it and must NOT insert a
        // second one, exactly like startOrResume()'s "attempt already
        // exists" branch.
        $existing = $conn->query("SELECT COUNT(*) FROM attempts WHERE assignment_id = 1 AND status = 'in_progress'")->fetchColumn();
        if ((int) $existing === 0) {
            $conn->exec("INSERT INTO attempts (assignment_id, attempt_number, status) VALUES (1, 1, 'in_progress')");
        }
        $conn->commit();

        pcntl_waitpid($pid, $status);

        $this->assertGreaterThan(
            0.35,
            $blockedFor,
            "The parent's lock acquisition returned in {$blockedFor}s — it should have blocked for ~0.4s waiting for the child's transaction, meaning the row lock is not actually serializing access."
        );

        $verify = $this->connect();
        $count = (int) $verify->query("SELECT COUNT(*) FROM attempts WHERE assignment_id = 1 AND status = 'in_progress'")->fetchColumn();
        $this->assertSame(1, $count, 'Both processes created their own in_progress attempt — the lock did not prevent the race.');
    }

    /**
     * The companion case: once the first attempt is no longer in_progress
     * (submitted), a second in_progress row for the same assignment is
     * allowed — multiple *submitted* attempts are legitimate (retries up to
     * max_attempts), it's only ever-concurrent in_progress rows that must
     * be impossible.
     */
    public function test_a_second_attempt_is_allowed_once_the_first_is_no_longer_in_progress(): void
    {
        $setup = $this->connect();
        $this->createSchema($setup);
        $setup->exec('INSERT INTO assignments (id, status, max_attempts) VALUES (1, \'assigned\', 5)');
        $setup->exec("INSERT INTO attempts (assignment_id, attempt_number, status) VALUES (1, 1, 'submitted')");
        $setup = null;

        $conn = $this->connect();
        $conn->exec("INSERT INTO attempts (assignment_id, attempt_number, status) VALUES (1, 2, 'in_progress')");

        $count = (int) $conn->query('SELECT COUNT(*) FROM attempts WHERE assignment_id = 1')->fetchColumn();
        $this->assertSame(2, $count);
    }
}
