import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link, router } from '@inertiajs/react';

interface StaffRow {
    id: number;
    name: string;
    email: string;
    role: string;
    mfa_enabled: boolean;
    disabled_at: string | null;
}

export default function Index({ users }: { users: StaffRow[] }) {
    return (
        <ProfessionalLayout title="Equipa">
            <Head title="Equipa" />

            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-ink-muted">{users.length} contas</p>
                <Link href={route('staff.create')} className="rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink">
                    Convidar
                </Link>
            </div>

            <div className="overflow-hidden rounded-shell border border-border bg-surface">
                <table className="w-full text-left text-sm">
                    <thead className="bg-bg text-ink-muted">
                        <tr>
                            <th className="px-4 py-3 font-medium">Nome</th>
                            <th className="px-4 py-3 font-medium">Função</th>
                            <th className="px-4 py-3 font-medium">Estado</th>
                            <th className="px-4 py-3 font-medium" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {users.map((user) => (
                            <tr key={user.id}>
                                <td className="px-4 py-3">{user.name}<div className="text-xs text-ink-muted">{user.email}</div></td>
                                <td className="px-4 py-3 capitalize">{user.role}</td>
                                <td className="px-4 py-3">{user.disabled_at ? 'Desativada' : 'Ativa'}</td>
                                <td className="space-x-3 px-4 py-3 text-right text-sm">
                                    <Link href={route('staff.edit', user.id)} className="text-accent">Editar</Link>
                                    {user.disabled_at ? (
                                        <button onClick={() => router.patch(route('staff.reactivate', user.id))} className="text-accent">Reativar</button>
                                    ) : (
                                        <button onClick={() => router.delete(route('staff.destroy', user.id))} className="text-danger">Desativar</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </ProfessionalLayout>
    );
}
