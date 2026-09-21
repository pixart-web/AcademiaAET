import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link } from '@inertiajs/react';

interface Attempt {
    id: number;
    assignment: {
        child_profile: { id: number; first_name: string; preferred_name: string | null };
        activity_version: { activity: { title: string } };
    };
}

interface Assignment {
    id: number;
    status: string;
    due_at: string | null;
    child_profile: { id: number; first_name: string; preferred_name: string | null };
    activity_version: { activity: { title: string } };
}

export default function Dashboard({
    pendingEvaluations,
    inProgressAssignments,
    childCount,
}: {
    pendingEvaluations: Attempt[];
    inProgressAssignments: Assignment[];
    childCount: number;
}) {
    return (
        <ProfessionalLayout title="Painel">
            <Head title="Painel" />

            <div className="grid gap-6 lg:grid-cols-2">
                <section className="rounded-shell border border-border bg-surface p-5">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="font-semibold text-ink">Respostas por avaliar</h2>
                        <span className="text-sm text-ink-muted">{pendingEvaluations.length}</span>
                    </div>

                    {pendingEvaluations.length === 0 ? (
                        <p className="text-sm text-ink-muted">Sem respostas pendentes de avaliação.</p>
                    ) : (
                        <ul className="space-y-2">
                            {pendingEvaluations.map((attempt) => (
                                <li key={attempt.id}>
                                    <Link
                                        href={route('evaluations.show', attempt.id)}
                                        className="flex items-center justify-between rounded-shell px-3 py-2 text-sm hover:bg-bg"
                                    >
                                        <span>
                                            {attempt.assignment.child_profile.preferred_name ?? attempt.assignment.child_profile.first_name}
                                            <span className="text-ink-muted"> — {attempt.assignment.activity_version.activity.title}</span>
                                        </span>
                                        <span className="text-accent">Avaliar</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-shell border border-border bg-surface p-5">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="font-semibold text-ink">Atividades em curso</h2>
                        <span className="text-sm text-ink-muted">{inProgressAssignments.length}</span>
                    </div>

                    {inProgressAssignments.length === 0 ? (
                        <p className="text-sm text-ink-muted">Sem atividades atribuídas em curso.</p>
                    ) : (
                        <ul className="space-y-2">
                            {inProgressAssignments.map((assignment) => (
                                <li key={assignment.id}>
                                    <Link
                                        href={route('children.show', assignment.child_profile.id)}
                                        className="flex items-center justify-between rounded-shell px-3 py-2 text-sm hover:bg-bg"
                                    >
                                        <span>
                                            {assignment.child_profile.preferred_name ?? assignment.child_profile.first_name}
                                            <span className="text-ink-muted"> — {assignment.activity_version.activity.title}</span>
                                        </span>
                                        <span className="text-ink-muted capitalize">{assignment.status}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <div className="mt-6 flex gap-3">
                <Link href={route('children.create')} className="rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink">
                    Novo perfil de criança/jovem
                </Link>
                <Link href={route('activities.create')} className="rounded-shell border border-border bg-surface px-4 py-2 text-sm font-medium text-ink">
                    Nova atividade
                </Link>
                <span className="ml-auto self-center text-sm text-ink-muted">{childCount} crianças/jovens acompanhados</span>
            </div>
        </ProfessionalLayout>
    );
}
