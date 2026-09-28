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
    is_overdue: boolean;
    activity_version: { activity: { id: number; title: string } };
    attempts: { id: number; status: string }[];
}

interface DeviceAssociation {
    id: number;
    device_identifier: string;
    status: string;
    expires_at: string;
    last_used_at: string | null;
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
    device_associations: DeviceAssociation[];
}

const STATUS_LABEL: Record<string, string> = {
    assigned: 'Atribuída',
    started: 'Iniciada',
    submitted: 'Por avaliar',
    reviewed: 'Avaliada',
    cancelled: 'Cancelada',
};

export default function Show({
    child,
    canManageClinical,
    publishableActivities,
}: {
    child: Child;
    canManageClinical: boolean;
    publishableActivities: { id: number; title: string }[];
}) {
    const { flash } = usePage<PageProps>().props;

    const guardianForm = useForm({ name: '', email: '', relationship_type: 'encarregado de educação' });
    const submitGuardian: FormEventHandler = (e) => {
        e.preventDefault();
        guardianForm.post(route('children.guardians.store', child.id), { onSuccess: () => guardianForm.reset() });
    };

    const assignForm = useForm({ activity_id: '', due_at: '' });
    const submitAssign: FormEventHandler = (e) => {
        e.preventDefault();
        assignForm.post(route('children.assignments.store', child.id), { onSuccess: () => assignForm.reset() });
    };

    const cancelAssignment = (assignmentId: number) => {
        router.delete(route('assignments.cancel', assignmentId));
    };

    const generateDevice = () => router.post(route('children.devices.store', child.id));
    const revokeDevice = (deviceId: number) => router.patch(route('children.devices.revoke', [child.id, deviceId]));

    return (
        <ProfessionalLayout title={child.preferred_name ?? child.first_name} description={`Experiência: ${child.visual_experience} anos`}>
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
                        <h2 className="mb-3 font-semibold text-ink">Atribuições</h2>

                        {child.assignments.length === 0 ? (
                            <p className="text-sm text-ink-muted">Ainda sem atividades atribuídas.</p>
                        ) : (
                            <ul className="mb-4 divide-y divide-border">
                                {child.assignments.map((a) => (
                                    <li key={a.id} className="flex items-center justify-between py-2 text-sm">
                                        <span>{a.activity_version.activity.title}</span>
                                        <span className="flex items-center gap-3">
                                            {a.is_overdue && (
                                                <span className="rounded-shell bg-danger/10 px-2 py-0.5 text-xs font-medium text-danger">Atrasada</span>
                                            )}
                                            <span className="text-ink-muted">{STATUS_LABEL[a.status] ?? a.status}</span>
                                            {canManageClinical && ['assigned', 'started'].includes(a.status) && (
                                                <button onClick={() => cancelAssignment(a.id)} className="text-danger">
                                                    Cancelar
                                                </button>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {canManageClinical && (
                            <form onSubmit={submitAssign} className="flex flex-wrap items-end gap-2 border-t border-border pt-3">
                                <div className="flex-1">
                                    <label className="block text-xs text-ink-muted">Nova atribuição</label>
                                    <select
                                        value={assignForm.data.activity_id}
                                        onChange={(e) => assignForm.setData('activity_id', e.target.value)}
                                        required
                                        className="mt-1 block w-full rounded-shell border-border text-sm focus:border-accent focus:ring-accent"
                                    >
                                        <option value="">Escolher atividade publicada…</option>
                                        {publishableActivities.map((a) => (
                                            <option key={a.id} value={a.id}>{a.title}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs text-ink-muted">Prazo (opcional)</label>
                                    <input
                                        type="date"
                                        value={assignForm.data.due_at}
                                        onChange={(e) => assignForm.setData('due_at', e.target.value)}
                                        className="mt-1 rounded-shell border-border text-sm focus:border-accent focus:ring-accent"
                                    />
                                </div>
                                <button type="submit" className="rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink">
                                    Atribuir
                                </button>
                            </form>
                        )}
                        {publishableActivities.length === 0 && canManageClinical && (
                            <p className="mt-2 text-xs text-ink-muted">
                                Sem atividades publicadas nesta organização. <Link href={route('activities.create')} className="text-accent">Criar uma</Link>.
                            </p>
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

                            {child.device_associations.length > 0 && (
                                <ul className="mt-4 divide-y divide-border border-t border-border pt-3">
                                    {child.device_associations.map((d) => (
                                        <li key={d.id} className="flex items-center justify-between py-2 text-sm">
                                            <span>
                                                {d.device_identifier}
                                                <span className="ml-2 text-xs text-ink-muted capitalize">{d.status}</span>
                                            </span>
                                            {d.status === 'active' && (
                                                <button onClick={() => revokeDevice(d.id)} className="text-danger">
                                                    Revogar
                                                </button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    )}
                </div>

                <div className="space-y-6">
                    <section className="rounded-shell border border-border bg-surface p-5">
                        <h2 className="mb-2 font-semibold text-ink">Perfil</h2>
                        <dl className="space-y-1 text-sm">
                            <div className="flex justify-between"><dt className="text-ink-muted">Nascimento</dt><dd>{new Date(child.birth_date).toLocaleDateString('pt-PT')}</dd></div>
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

                        {canManageClinical && (
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
                        )}
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
