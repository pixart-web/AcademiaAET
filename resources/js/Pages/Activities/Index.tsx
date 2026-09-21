import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link } from '@inertiajs/react';

interface ActivityRow {
    id: number;
    title: string;
    status: string;
    category: string | null;
    is_demo: boolean;
    current_version: { version_number: number } | null;
}

export default function Index({ activities }: { activities: ActivityRow[] }) {
    return (
        <ProfessionalLayout title="Atividades">
            <Head title="Atividades" />

            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-ink-muted">{activities.length} atividades</p>
                <Link href={route('activities.create')} className="rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink">
                    Nova atividade
                </Link>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {activities.map((activity) => (
                    <Link
                        key={activity.id}
                        href={route('activities.edit', activity.id)}
                        className="rounded-shell border border-border bg-surface p-4 hover:border-accent"
                    >
                        <div className="mb-2 flex items-center justify-between">
                            <span className={`rounded-shell px-2 py-0.5 text-xs capitalize ${
                                activity.status === 'published' ? 'bg-accent-soft text-accent' : 'bg-bg text-ink-muted'
                            }`}>
                                {activity.status}
                            </span>
                            {activity.is_demo && <span className="text-xs text-ink-muted">demonstração</span>}
                        </div>
                        <p className="font-medium text-ink">{activity.title}</p>
                        <p className="text-sm text-ink-muted">{activity.category ?? 'Sem categoria'}</p>
                    </Link>
                ))}

                {activities.length === 0 && <p className="text-ink-muted">Ainda sem atividades.</p>}
            </div>
        </ProfessionalLayout>
    );
}
