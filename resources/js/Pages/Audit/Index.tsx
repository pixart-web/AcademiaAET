import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface AuditEventRow {
    id: number;
    action: string;
    auditable_type: string | null;
    auditable_id: number | null;
    metadata: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    total: number;
}

export default function Index({ events, filters }: { events: Paginated<AuditEventRow>; filters: { action: string } }) {
    const [action, setAction] = useState(filters.action ?? '');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('audit.index'), { action }, { preserveState: true });
    };

    return (
        <ProfessionalLayout title="Auditoria" description="Registo de ações sensíveis nesta organização — só administradores veem esta página.">
            <Head title="Auditoria" />

            <form onSubmit={submit} className="mb-4 flex items-end gap-3">
                <div>
                    <label htmlFor="action" className="mb-1 block text-sm font-medium text-ink">Filtrar por ação</label>
                    <input
                        id="action"
                        type="text"
                        value={action}
                        onChange={(e) => setAction(e.target.value)}
                        placeholder="ex.: child.profile_updated"
                        className="rounded-shell border border-border bg-surface px-3 py-2 text-sm"
                    />
                </div>
                <button type="submit" className="rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink">Filtrar</button>
            </form>

            <p className="mb-2 text-sm text-ink-muted">{events.total} eventos</p>

            <div className="overflow-x-auto rounded-shell border border-border bg-surface">
                <table className="w-full text-left text-sm">
                    <thead className="bg-bg text-ink-muted">
                        <tr>
                            <th className="px-4 py-3 font-medium">Data</th>
                            <th className="px-4 py-3 font-medium">Ação</th>
                            <th className="px-4 py-3 font-medium">Autor</th>
                            <th className="px-4 py-3 font-medium">Sujeito</th>
                            <th className="px-4 py-3 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {events.data.map((event) => (
                            <tr key={event.id}>
                                <td className="whitespace-nowrap px-4 py-3 text-ink-muted">{event.created_at}</td>
                                <td className="px-4 py-3 font-mono text-xs">{event.action}</td>
                                <td className="px-4 py-3">{event.user?.name ?? '—'}</td>
                                <td className="px-4 py-3 text-ink-muted">
                                    {event.auditable_type ? `${event.auditable_type.split('\\').pop()} #${event.auditable_id}` : '—'}
                                </td>
                                <td className="px-4 py-3 text-ink-muted">{event.ip_address ?? '—'}</td>
                            </tr>
                        ))}
                        {events.data.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-6 text-center text-ink-muted">Nenhum evento encontrado.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {events.links.length > 3 && (
                <nav className="mt-4 flex flex-wrap gap-1" aria-label="Paginação">
                    {events.links.map((link, i) => (
                        <Link
                            key={i}
                            href={link.url ?? '#'}
                            preserveState
                            className={`rounded-shell px-3 py-1.5 text-sm ${
                                link.active ? 'bg-accent text-accent-ink' : 'text-ink-muted hover:bg-bg'
                            } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </nav>
            )}
        </ProfessionalLayout>
    );
}
