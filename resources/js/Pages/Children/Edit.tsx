import InputError from '@/Components/InputError';
import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Child {
    id: number;
    first_name: string;
    preferred_name: string | null;
    birth_date: string;
    visual_experience: string;
    status: string;
    care_notes: string | null;
}

export default function Edit({ child }: { child: Child }) {
    const { data, setData, put, processing, errors } = useForm({
        first_name: child.first_name,
        preferred_name: child.preferred_name ?? '',
        birth_date: child.birth_date.slice(0, 10),
        care_notes: child.care_notes ?? '',
        status: child.status,
        visual_experience: child.visual_experience,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('children.update', child.id));
    };

    return (
        <ProfessionalLayout title="Editar perfil">
            <Head title="Editar perfil" />

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
                    <label className="block text-sm font-medium text-ink">Nome preferido</label>
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
                </div>

                <div>
                    <label className="block text-sm font-medium text-ink">Experiência visual</label>
                    <select
                        value={data.visual_experience}
                        onChange={(e) => setData('visual_experience', e.target.value)}
                        className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    >
                        <option value="3-6">3–6 anos</option>
                        <option value="7-13">7–13 anos</option>
                        <option value="14-18">14–18 anos</option>
                    </select>
                    <p className="mt-1 text-xs text-ink-muted">A profissional pode escolher outra experiência conforme a necessidade individual.</p>
                </div>

                <div>
                    <label className="block text-sm font-medium text-ink">Estado</label>
                    <select
                        value={data.status}
                        onChange={(e) => setData('status', e.target.value)}
                        className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    >
                        <option value="active">Ativo</option>
                        <option value="paused">Em pausa</option>
                        <option value="archived">Arquivado</option>
                    </select>
                </div>

                <div>
                    <label className="block text-sm font-medium text-ink">Notas de acompanhamento</label>
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
                    Guardar
                </button>
            </form>
        </ProfessionalLayout>
    );
}
