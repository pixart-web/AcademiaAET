import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link } from '@inertiajs/react';

interface Attempt {
    id: number;
    submitted_at: string;
    assignment: {
        child_profile: { first_name: string; preferred_name: string | null };
        activity_version: { activity: { title: string } };
    };
}

export default function Index({ pending }: { pending: Attempt[] }) {
    return (
        <ProfessionalLayout title="Por avaliar">
            <Head title="Por avaliar" />

            {pending.length === 0 ? (
                <p className="text-sm text-ink-muted">Sem respostas pendentes de avaliação.</p>
            ) : (
                <ul className="divide-y divide-border rounded-shell border border-border bg-surface">
                    {pending.map((attempt) => (
                        <li key={attempt.id}>
                            <Link href={route('evaluations.show', attempt.id)} className="flex items-center justify-between px-5 py-4 hover:bg-bg">
                                <span>
                                    <span className="font-medium">
                                        {attempt.assignment.child_profile.preferred_name ?? attempt.assignment.child_profile.first_name}
                                    </span>
                                    <span className="text-ink-muted"> — {attempt.assignment.activity_version.activity.title}</span>
                                </span>
                                <span className="text-sm text-ink-muted">{new Date(attempt.submitted_at).toLocaleDateString('pt-PT')}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </ProfessionalLayout>
    );
}
