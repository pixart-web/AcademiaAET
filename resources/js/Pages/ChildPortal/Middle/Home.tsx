import { AchievementSummary, AssignmentSummary, CompletedAssignmentSummary } from '@/Child/types';
import { useChildIdentity } from '@/Child/useChildIdentity';
import Icon, { IconName } from '@/Components/Icon';
import { Head, Link, router } from '@inertiajs/react';
import MiddleLayout from './Layout';

const ACHIEVEMENT_LABEL: Record<string, string> = {
    participation: 'Participação',
    effort: 'Esforço',
    milestone: 'Conquista',
};

const GLYPHS: IconName[] = ['leaf', 'chat', 'star', 'flag', 'sun', 'list'];

export default function Home({
    assignments,
    completed,
    achievements,
    totalPoints,
}: {
    assignments: AssignmentSummary[];
    completed: CompletedAssignmentSummary[];
    achievements: AchievementSummary[];
    totalPoints: number;
}) {
    const { displayName } = useChildIdentity();
    const next = assignments[0];
    const total = assignments.length + completed.length;
    const done = completed.length;

    const open = (assignment: AssignmentSummary) => {
        if (assignment.in_progress_attempt_id) {
            router.get(`/crianca/tentativas/${assignment.in_progress_attempt_id}`);
        } else {
            router.post(`/crianca/atribuicoes/${assignment.id}/iniciar`);
        }
    };

    return (
        <MiddleLayout
            hero={
                <>
                    <h1 className="text-3xl font-extrabold text-ink">Olá, {displayName}!</h1>
                    <p className="mt-1 max-w-[16rem] text-base font-semibold text-ink/80">
                        {next ? 'A tua próxima missão está à tua espera.' : 'Sem missões novas por agora.'}
                    </p>
                </>
            }
        >
            <Head title="As minhas missões" />

            {total > 0 && (
                <div className="mb-5">
                    <p className="text-sm font-bold text-ink-muted">
                        {done} de {total} {total === 1 ? 'atividade' : 'atividades'}
                    </p>
                    <div
                        className="mt-1.5 h-2.5 overflow-hidden rounded-full bg-bg-alt"
                        role="progressbar"
                        aria-valuemin={0}
                        aria-valuemax={total}
                        aria-valuenow={done}
                        aria-label="Atividades concluídas"
                    >
                        <div className="h-full rounded-full bg-accent transition-all motion-reduce:transition-none" style={{ width: `${(done / total) * 100}%` }} />
                    </div>
                </div>
            )}

            {assignments.length === 0 ? (
                <p className="rounded-shell border border-dashed border-border py-8 text-center text-ink-muted">Volta em breve — a tua terapeuta vai preparar novas missões.</p>
            ) : (
                <ul className="space-y-3" aria-label="Missões por fazer">
                    {assignments.map((a) => (
                        <li key={a.id}>
                            <button
                                onClick={() => open(a)}
                                className="flex w-full items-center gap-3 rounded-shell border border-border bg-bg/60 p-3 text-left transition hover:bg-accent-soft/60 motion-reduce:transition-none"
                            >
                                <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-shell bg-accent-soft text-accent">
                                    <Icon name={GLYPHS[a.id % GLYPHS.length]} size={26} />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block font-extrabold">{a.title}</span>
                                    <span className="block truncate text-sm text-ink-muted">{a.category ?? (a.in_progress_attempt_id ? 'Em curso' : 'Missão nova')}</span>
                                </span>
                                <Icon name="chevronRight" className="text-ink-muted" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {next && (
                <button
                    onClick={() => open(next)}
                    className="mt-5 flex h-14 w-full items-center justify-center gap-2 rounded-shell bg-accent text-lg font-extrabold text-accent-ink shadow-lift transition active:scale-[0.98] motion-reduce:transition-none"
                >
                    {next.in_progress_attempt_id ? 'Continuar missão' : 'Começar missão'}
                    <Icon name="arrowRight" size={22} />
                </button>
            )}

            {achievements.some((a) => a.count > 0) && (
                <section className="mt-8" aria-labelledby="conquistas">
                    <h2 id="conquistas" className="mb-3 text-lg font-extrabold">
                        As tuas conquistas <span className="text-sm font-bold text-ink-muted">· {totalPoints} pts</span>
                    </h2>
                    <div className="flex flex-wrap gap-3">
                        {achievements
                            .filter((a) => a.count > 0)
                            .map((a) => (
                                <div key={a.type} className="flex items-center gap-2 rounded-full bg-highlight/50 px-4 py-2 text-sm font-bold text-highlight-ink">
                                    <Icon name="star" size={18} />
                                    {ACHIEVEMENT_LABEL[a.type]} ×{a.count}
                                </div>
                            ))}
                    </div>
                </section>
            )}

            {completed.length > 0 && (
                <section className="mt-8" aria-labelledby="concluidas">
                    <h2 id="concluidas" className="mb-3 text-lg font-extrabold">Missões concluídas</h2>
                    <ul className="space-y-2">
                        {completed.map((a) => (
                            <li key={a.id}>
                                <Link
                                    href={`/crianca/atribuicoes/${a.id}/feedback`}
                                    className="flex items-center justify-between gap-3 rounded-shell border border-border px-4 py-3 text-sm hover:bg-bg"
                                >
                                    <span className="flex items-center gap-2 font-bold">
                                        <Icon name="checkCircle" size={18} className="text-success" />
                                        {a.title}
                                    </span>
                                    <span className="text-ink-muted">
                                        {a.status === 'submitted' && 'A aguardar avaliação'}
                                        {a.status === 'reviewed' && a.has_feedback && 'Feedback disponível →'}
                                        {a.status === 'reviewed' && !a.has_feedback && 'Avaliada'}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </MiddleLayout>
    );
}
