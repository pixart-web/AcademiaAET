import { AssignmentSummary, CompletedAssignmentSummary } from '@/Child/types';
import Icon, { IconName } from '@/Components/Icon';
import { Head, Link, router } from '@inertiajs/react';
import TeenLayout from './Layout';

const GLYPHS: IconName[] = ['leaf', 'sun', 'chat', 'flag', 'star', 'list'];

export default function Home({
    assignments,
    completed,
}: {
    assignments: AssignmentSummary[];
    completed: CompletedAssignmentSummary[];
}) {
    const open = (assignment: AssignmentSummary) => {
        if (assignment.in_progress_attempt_id) {
            router.get(`/crianca/tentativas/${assignment.in_progress_attempt_id}`);
        } else {
            router.post(`/crianca/atribuicoes/${assignment.id}/iniciar`);
        }
    };

    // Shown only when feedback really exists — never as an empty promise.
    const withFeedback = completed.find((a) => a.status === 'reviewed' && a.has_feedback);
    const next = assignments[0];

    return (
        <TeenLayout nav>
            <Head title="O teu espaço" />

            <h1 className="font-display text-4xl">O teu espaço</h1>
            <p className="mt-2 text-ink-muted">Um lugar para aprenderes, refletires e evoluíres, ao teu ritmo.</p>

            {withFeedback && (
                <Link
                    href={`/crianca/atribuicoes/${withFeedback.id}/feedback`}
                    className="mt-6 flex items-center gap-4 rounded-shell border border-border bg-accent-soft/60 p-4 hover:bg-accent-soft"
                >
                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-surface text-accent">
                        <Icon name="chat" size={22} />
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block font-bold">Tens feedback disponível</span>
                        <span className="block truncate text-sm text-ink-muted">Sobre “{withFeedback.title}”</span>
                    </span>
                    <Icon name="chevronRight" className="text-ink-muted" />
                </Link>
            )}

            <section id="atividades" className="mt-8 scroll-mt-4" aria-labelledby="atividades-t">
                <h2 id="atividades-t" className="font-display text-2xl">As tuas atividades</h2>

                {assignments.length === 0 ? (
                    <p className="mt-3 rounded-shell border border-dashed border-border px-4 py-8 text-center text-sm text-ink-muted">
                        Sem atividades por agora.
                    </p>
                ) : (
                    <ul className="mt-3 space-y-3">
                        {assignments.map((a) => (
                            <li key={a.id}>
                                <button
                                    onClick={() => open(a)}
                                    className="flex w-full items-center gap-4 rounded-shell border border-border bg-surface p-4 text-left shadow-soft transition hover:shadow-lift motion-reduce:transition-none"
                                >
                                    <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-shell bg-accent-soft text-accent">
                                        <Icon name={GLYPHS[a.id % GLYPHS.length]} size={24} />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-bold">{a.title}</span>
                                        <span className="block truncate text-sm text-ink-muted">
                                            {a.category ?? (a.in_progress_attempt_id ? 'Em curso' : 'Por começar')}
                                        </span>
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
                        className="mt-5 flex h-12 w-full items-center justify-center gap-2 rounded-shell bg-accent font-bold text-accent-ink shadow-soft hover:brightness-110"
                    >
                        {next.in_progress_attempt_id ? 'Continuar' : 'Começar'}
                        <Icon name="arrowRight" size={20} />
                    </button>
                )}
            </section>

            {completed.length > 0 && (
                <section id="historico" className="mt-10 scroll-mt-4" aria-labelledby="historico-t">
                    <h2 id="historico-t" className="font-display text-2xl">Histórico</h2>
                    <ul className="mt-3 divide-y divide-border overflow-hidden rounded-shell border border-border bg-surface">
                        {completed.map((a) => (
                            <li key={a.id}>
                                <Link href={`/crianca/atribuicoes/${a.id}/feedback`} className="flex items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-bg-alt/50">
                                    <span className="font-semibold">{a.title}</span>
                                    <span className="text-ink-muted">
                                        {a.status === 'submitted' && 'A aguardar avaliação'}
                                        {a.status === 'reviewed' && a.has_feedback && 'Feedback disponível'}
                                        {a.status === 'reviewed' && !a.has_feedback && 'Avaliada'}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </TeenLayout>
    );
}
