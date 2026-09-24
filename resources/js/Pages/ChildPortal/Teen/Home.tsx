import { AssignmentSummary, CompletedAssignmentSummary } from '@/Child/types';
import { Link, router } from '@inertiajs/react';
import TeenLayout from './Layout';

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

    return (
        <TeenLayout>
            <h1 className="text-xl font-semibold">As tuas atividades</h1>

            {assignments.length === 0 ? (
                <p className="mt-4 text-sm text-ink-muted">Sem atividades por agora.</p>
            ) : (
                <ul className="mt-4 divide-y divide-border rounded-shell border border-border">
                    {assignments.map((assignment) => (
                        <li key={assignment.id}>
                            <button onClick={() => open(assignment)} className="flex w-full items-center justify-between px-4 py-4 text-left hover:bg-surface">
                                <span>
                                    <span className="font-medium">{assignment.title}</span>
                                    {assignment.category && <span className="ml-2 text-xs text-ink-muted">{assignment.category}</span>}
                                </span>
                                <span className="text-sm text-ink-muted">
                                    {assignment.in_progress_attempt_id ? 'Continuar' : 'Começar'}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {completed.length > 0 && (
                <div className="mt-8">
                    <h2 className="text-sm font-medium text-ink-muted">Histórico</h2>
                    <ul className="mt-3 divide-y divide-border rounded-shell border border-border">
                        {completed.map((a) => (
                            <li key={a.id}>
                                <Link href={`/crianca/atribuicoes/${a.id}/feedback`} className="flex items-center justify-between px-4 py-3 text-sm hover:bg-surface">
                                    <span>{a.title}</span>
                                    <span className="text-ink-muted">
                                        {a.status === 'submitted' && 'A aguardar avaliação'}
                                        {a.status === 'reviewed' && a.has_feedback && 'Feedback disponível'}
                                        {a.status === 'reviewed' && !a.has_feedback && 'Avaliada'}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </TeenLayout>
    );
}
