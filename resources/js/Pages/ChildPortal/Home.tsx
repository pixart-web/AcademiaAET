import Mascot from '@/Components/Mascot';
import ChildPortalLayout from '@/Layouts/ChildPortalLayout';
import { Head, router } from '@inertiajs/react';

interface AssignmentRow {
    id: number;
    status: string;
    title: string;
    in_progress_attempt_id: number | null;
}

export default function Home({
    assignments,
    completed,
}: {
    assignments: AssignmentRow[];
    completed: { id: number; status: string; title: string }[];
}) {
    const open = (assignment: AssignmentRow) => {
        if (assignment.in_progress_attempt_id) {
            router.get(`/crianca/tentativas/${assignment.in_progress_attempt_id}`);
        } else {
            router.post(`/crianca/atribuicoes/${assignment.id}/iniciar`);
        }
    };

    return (
        <ChildPortalLayout>
            <Head title="As minhas atividades" />

            {assignments.length === 0 ? (
                <div className="mt-12 flex flex-col items-center text-center">
                    <Mascot state="waiting" size={120} />
                    <p className="mt-4 text-lg text-ink-muted">Ainda não tens atividades novas. Volta mais tarde!</p>
                </div>
            ) : (
                <div className="mt-6 grid gap-4 sm:grid-cols-2">
                    {assignments.map((assignment) => (
                        <button
                            key={assignment.id}
                            onClick={() => open(assignment)}
                            className="rounded-shell border border-border bg-surface p-6 text-left text-lg font-medium shadow-sm transition hover:-translate-y-0.5 hover:shadow motion-reduce:transform-none"
                        >
                            {assignment.title}
                            <span className="mt-2 block text-sm font-normal text-accent">
                                {assignment.in_progress_attempt_id ? 'Continuar' : 'Começar'} →
                            </span>
                        </button>
                    ))}
                </div>
            )}

            {completed.length > 0 && (
                <div className="mt-10">
                    <h2 className="mb-3 text-sm font-medium text-ink-muted">Já concluídas</h2>
                    <ul className="space-y-2 text-sm text-ink-muted">
                        {completed.map((a) => (
                            <li key={a.id}>{a.title}</li>
                        ))}
                    </ul>
                </div>
            )}
        </ChildPortalLayout>
    );
}
