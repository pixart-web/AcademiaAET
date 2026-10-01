<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * AET-RC01 finding 1/8: "para dados legados sem proprietário seguro,
 * produz relatório de reconciliação e mantém acesso restrito. Não faças
 * limpeza destrutiva baseada apenas em suposições."
 *
 * Read-only by design — this never deletes or modifies anything. It
 * exists so an admin can see, organization by organization, exactly which
 * media rows the migration backfill (2026_09_30_204121) couldn't classify
 * (purpose IS NULL — MediaAssetPolicy already restricts these to
 * admin-only review, see its docblock) and, optionally, which active
 * rows point at a file that no longer exists on disk. What to do about
 * either list is a judgment call for the clinic, not something to guess
 * at here.
 */
#[Signature('media:reconcile-orphans {--check-files : Also verify each active row\'s file still exists on its disk (slower — reads every file\'s metadata)}')]
#[Description('Reports media_assets rows with unresolved purpose or ownership, and (optionally) missing files, for manual review — makes no changes.')]
class MediaReconcileOrphans extends Command
{
    public function handle(): int
    {
        $unclassified = MediaAsset::query()
            ->whereNull('purpose')
            ->orderBy('organization_id')
            ->get(['id', 'organization_id', 'kind', 'path', 'created_at']);

        if ($unclassified->isEmpty()) {
            $this->info('No media_assets rows with an unresolved purpose — nothing needs manual classification.');
        } else {
            $this->warn("{$unclassified->count()} media_assets row(s) have no determinable purpose (visible only to an admin, via MediaAssetPolicy, until classified):");
            $this->table(
                ['id', 'organization_id', 'kind', 'path', 'created_at'],
                $unclassified->map(fn (MediaAsset $m) => [$m->id, $m->organization_id, $m->kind->value, $m->path, $m->created_at])->all(),
            );
        }

        if ($this->option('check-files')) {
            $this->newLine();
            $this->checkMissingFiles();
        }

        return self::SUCCESS;
    }

    private function checkMissingFiles(): void
    {
        $missing = [];

        MediaAsset::query()->where('status', 'active')->orderBy('organization_id')
            ->chunkById(200, function ($chunk) use (&$missing) {
                foreach ($chunk as $media) {
                    if (! Storage::disk($media->disk)->exists($media->path)) {
                        $missing[] = [$media->id, $media->organization_id, $media->purpose?->value ?? 'NULL', $media->path];
                    }
                }
            });

        if (empty($missing)) {
            $this->info('No active media_assets row points at a missing file.');

            return;
        }

        $this->error(count($missing).' active media_assets row(s) point at a file that no longer exists on disk:');
        $this->table(['id', 'organization_id', 'purpose', 'path'], $missing);
    }
}
