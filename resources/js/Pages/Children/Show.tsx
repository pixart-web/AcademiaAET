import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { PageProps } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface GuardianRelationship {
    id: number;
    relationship_type: string;
    status: string;
    user: { name: string; email: string };
}

interface Professional {
    id: number;
    name: string;
    email: string;
    pivot?: { started_at: string };
}

interface AssignmentRow {
    id: number;
    status: string;
    due_at: string | null;
    activity_version: { activity: { id: number; title: string } };
    attempts: { id: number; status: string }[];
}

interface Child {
    id: number;
    first_name: string;
    preferred_name: string | null;
    birth_date: string;
    visual_experience: string;
    status: string;
    care_notes: string | null;
    guardian_relationships: GuardianRelationship[];
    assigned_professionals: Professional[];
    assignments: AssignmentRow[];
}

export default function Show({ child, canManageClinical }: { child: Child; canManageClinical: boolean }) {
    const { flash } = usePage<PageProps>().props;

    const guardianForm = useForm({ name: '', email: '', relationship_type: 'encarregado de educação' });
    const submitGuardian: FormEventHandler = (e) => {
        e.preventDefault();
        guardianForm.post(route('children.guardians.store', child.id), { onSuccess: () => guardianForm.reset() });
    };

    const generateDevice = () => router.post(route('children.devices.store', child.id));

    return (
        <ProfessionalLayout title={child.preferred_name ?? child.first_name}>
            <Head title={child.preferred_name ?? child.first_name} />

            {flash.newDeviceCode && (
                <div className="mb-4 rounded-shell border border-accent bg-accent-soft px-4 py-3 text-sm text-accent">
                    <p className="font-medium">Código: {flash.newDeviceCode} · PIN: {flash.newDevicePin}</p>
                    <p>Introduza no dispositivo da criança. Estes valores não voltam a ser mostrados.</p>
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="rounded-shell border border-border bg-surface p-5">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="font-semibold text-ink">Atribuições</h2>
                            <Link href={route('activities.index')} className="text-sm text-accent">Atribuir atividade →</Link>
                        </div>

                        {child.assignments.length === 0 ? (
                            <p className="text-sm text-ink-muted">Ainda sem atividades atribuídas.</p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {child.assignments.map((a) => (
                                    <li key={a.id} className="flex items-center justify-between py-2 text-sm">
                                        <span>{a.activity_version.activity.title}</span>
                                        <span className="capitalize text-ink-muted">{a.status}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    {canManageClinical && (
                        <section className="rounded-shell border border-border bg-surface p-5">
                            <h2 className="mb-3 font-semibold text-ink">Acesso do dispositivo</h2>
                            <p className="mb-3 text-sm text-ink-muted">
                                Gere um código de ativação e PIN de utilização única para associar um tablet ou telemóvel a este perfil.
                            </p>
                            <button onClick={generateDevice} className="rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink">
                                Gerar novo acesso
                            </button>
                        </section>
                    )}
                </div>

                <div className="space-y-6">
                    <section className="rounded-shell border border-border bg-surface p-5">
                        <h2 className="mb-2 font-semibold text-ink">Perfil</h2>
                        <dl className="space-y-1 text-sm">
                            <div className="flex justify-between"><dt className="text-ink-muted">Nascimento</dt><dd>{child.birth_date}</dd></div>
                            <div className="flex justify-between"><dt className="text-ink-muted">Experiência</dt><dd>{child.visual_experience}</dd></div>
                            <div className="flex justify-between"><dt className="text-ink-muted">Estado</dt><dd className="capitalize">{child.status}</dd></div>
                        </dl>
                        <Link href={route('children.edit', child.id)} className="mt-3 inline-block text-sm text-accent">Editar</Link>
                    </section>

                    <section className="rounded-shell border border-border bg-surface p-5">
                        <h2 className="mb-2 font-semibold text-ink">Encarregados de educação</h2>
                        <ul className="mb-3 space-y-1 text-sm">
                            {child.guardian_relationships.map((rel) => (
                                <li key={rel.id} className="flex justify-between">
                                    <span>{rel.user.name}</span>
                                    <span className="text-ink-muted">{rel.relationship_type}</span>
                                </li>
                            ))}
                            {child.guardian_relationships.length === 0 && <li className="text-ink-muted">Nenhum associado.</li>}
                        </ul>

                        <form onSubmit={submitGuardian} className="space-y-2">
                            <input
                                placeholder="Nome"
                                value={guardianForm.data.name}
                                onChange={(e) => guardianForm.setData('name', e.target.value)}
                                className="block w-full rounded-shell border-border text-sm focus:border-accent focus:ring-accent"
                            />
                            <input
                                placeholder="Email"
                                type="email"
                                value={guardianForm.data.email}
                                onChange={(e) => guardianForm.setData('email', e.target.value)}
                                className="block w-full rounded-shell border-border text-sm focus:border-accent focus:ring-accent"
                            />
                            <button type="submit" className="w-full rounded-shell border border-border py-1.5 text-sm text-ink hover:bg-bg">
                                Associar
                            </button>
                        </form>
                    </section>

                    <section className="rounded-shell border border-border bg-surface p-5">
                        <h2 className="mb-2 font-semibold text-ink">Terapeutas associadas</h2>
                        <ul className="space-y-1 text-sm">
                            {child.assigned_professionals.map((p) => (
                                <li key={p.id}>{p.name}</li>
                            ))}
                            {child.assigned_professionals.length === 0 && <li className="text-ink-muted">Nenhuma associada.</li>}
                        </ul>
                    </section>
                </div>
            </div>
        </ProfessionalLayout>
    );
}
