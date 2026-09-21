import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, router } from '@inertiajs/react';

interface NotificationItem {
    id: string;
    data: { type: string };
    read_at: string | null;
    created_at: string;
}

const LABEL: Record<string, string> = {
    assignment_created: 'Nova atividade atribuída',
    evaluation_available: 'Nova avaliação disponível',
};

export default function Index({ notifications }: { notifications: { data: NotificationItem[] } }) {
    return (
        <ProfessionalLayout title="Notificações">
            <Head title="Notificações" />

            <ul className="divide-y divide-border rounded-shell border border-border bg-surface">
                {notifications.data.map((n) => (
                    <li key={n.id} className={`flex items-center justify-between px-5 py-4 ${n.read_at ? 'text-ink-muted' : 'text-ink'}`}>
                        <span>{LABEL[n.data.type] ?? n.data.type}</span>
                        {!n.read_at && (
                            <button onClick={() => router.post(route('notifications.read', n.id))} className="text-sm text-accent">
                                Marcar como lida
                            </button>
                        )}
                    </li>
                ))}
                {notifications.data.length === 0 && <li className="px-5 py-4 text-ink-muted">Sem notificações.</li>}
            </ul>
        </ProfessionalLayout>
    );
}
