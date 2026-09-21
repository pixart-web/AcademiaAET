import InputError from '@/Components/InputError';
import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        first_name: '',
        preferred_name: '',
        birth_date: '',
        care_notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('children.store'));
    };

    return (
        <ProfessionalLayout title="Novo perfil">
            <Head title="Novo perfil" />

            <form onSubmit={submit} className="max-w-lg space-y-4 rounded-shell border border-border bg-surface p-6">
                <div>
                    <label className="block text-sm font-medium text-ink">Primeiro nome</label>
                    <input
                        value={data.first_name}
                        onChange={(e) => setData('first_name', e.target.value)}
                        className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    />
                    <InputError message={errors.first_name} className="mt-2" />
                </div>

                <div>
                    <label className="block text-sm font-medium text-ink">Nome preferido (opcional)</label>
                    <input
                        value={data.preferred_name}
                        onChange={(e) => setData('preferred_name', e.target.value)}
                        className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-ink">Data de nascimento</label>
                    <input
                        type="date"
                        value={data.birth_date}
                        onChange={(e) => setData('birth_date', e.target.value)}
                        className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    />
                    <InputError message={errors.birth_date} className="mt-2" />
                    <p className="mt-1 text-xs text-ink-muted">Sugere automaticamente a experiência visual adequada.</p>
                </div>

                <div>
                    <label className="block text-sm font-medium text-ink">Notas de acompanhamento (opcional)</label>
                    <textarea
                        value={data.care_notes}
                        onChange={(e) => setData('care_notes', e.target.value)}
                        rows={3}
                        className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    />
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink disabled:opacity-60"
                >
                    Criar perfil
                </button>
            </form>
        </ProfessionalLayout>
    );
}
