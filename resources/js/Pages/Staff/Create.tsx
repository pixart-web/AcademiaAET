import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({ name: '', email: '', role: 'professional' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('staff.store'));
    };

    return (
        <ProfessionalLayout title="Convidar conta">
            <Head title="Convidar conta" />

            <form onSubmit={submit} className="max-w-lg space-y-4 rounded-shell border border-border bg-surface p-6">
                <div>
                    <label className="block text-sm font-medium text-ink">Nome</label>
                    <input
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    />
                    {errors.name && <p className="mt-1 text-sm text-danger">{errors.name}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-ink">Email</label>
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    />
                    {errors.email && <p className="mt-1 text-sm text-danger">{errors.email}</p>}
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

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink disabled:opacity-60"
                >
                    Enviar convite
                </button>
                <p className="text-xs text-ink-muted">
                    É enviado um email com uma ligação para definir a palavra-passe. Nenhuma credencial é partilhada em texto simples.
                </p>
            </form>
        </ProfessionalLayout>
    );
}
