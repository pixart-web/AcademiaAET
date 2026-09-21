import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface StaffMember {
    id: number;
    name: string;
    email: string;
    role: string;
}

export default function Edit({ staffMember }: { staffMember: StaffMember }) {
    const { data, setData, put, processing } = useForm({ name: staffMember.name, role: staffMember.role });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('staff.update', staffMember.id));
    };

    return (
        <ProfessionalLayout title="Editar conta">
            <Head title="Editar conta" />

            <form onSubmit={submit} className="max-w-lg space-y-4 rounded-shell border border-border bg-surface p-6">
                <div>
                    <label className="block text-sm font-medium text-ink">Email</label>
                    <p className="mt-1 text-sm text-ink-muted">{staffMember.email}</p>
                </div>

                <div>
                    <label className="block text-sm font-medium text-ink">Nome</label>
                    <input
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-ink">Função</label>
                    <select
                        value={data.role}
                        onChange={(e) => setData('role', e.target.value)}
                        className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    >
                        <option value="professional">Terapeuta</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>

                <div className="flex gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink disabled:opacity-60"
                    >
                        Guardar
                    </button>
                    <button
                        type="button"
                        onClick={() => router.post(route('staff.resend-activation', staffMember.id))}
                        className="rounded-shell border border-border px-4 py-2 text-sm text-ink"
                    >
                        Reenviar convite
                    </button>
                </div>
            </form>
        </ProfessionalLayout>
    );
}
