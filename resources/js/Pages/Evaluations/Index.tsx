import Avatar from '@/Components/art/Avatar';
import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link } from '@inertiajs/react';

interface Attempt {
    id: number;
    submitted_at: string;
    assignment: {
        child_profile: { id: number; first_name: string; preferred_name: string | null };
        activity_version: { activity: { title: string } };
    };
}

export default function Index({ pending }: { pending: Attempt[] }) {
    return (
        <ProfessionalLayout title="Avaliações" description="Respostas submetidas que aguardam a sua avaliação.">
            <Head title="Avaliações" />

            {pending.length === 0 ? (
                <p className="rounded-shell border border-dashed border-border bg-surface px-6 py-10 text-center text-ink-muted">
                    Tudo em dia — não há respostas à espera de avaliação.
                </p>
            ) : (
                <ul className="divide-y divide-border overflow-hidden rounded-shell border border-border bg-surface shadow-soft">
                    {pending.map((attempt) => {
                        const c = attempt.assignment.child_profile;
                        const name = c.preferred_name ?? c.first_name;
                        return (
                            <li key={attempt.id} className="flex items-center gap-4 px-5 py-4">
                                <Avatar seed={c.id} size={44} />
                                <div className="min-w-0 flex-1">
                                    <p className="font-bold">{name}</p>
                                    <p className="truncate text-sm text-ink-muted">{attempt.assignment.activity_version.activity.title}</p>
                                </div>
                                <span className="hidden text-sm text-ink-muted sm:inline">{new Date(attempt.submitted_at).toLocaleDateString('pt-PT')}</span>
                                <Link
                                    href={route('evaluations.show', attempt.id)}
                                    aria-label={`Avaliar ${name}: ${attempt.assignment.activity_version.activity.title}`}
                                    className="inline-flex h-10 items-center rounded-shell bg-highlight px-5 text-sm font-bold text-highlight-ink hover:brightness-95"
                                >
                                    Avaliar
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </ProfessionalLayout>
    );
}
