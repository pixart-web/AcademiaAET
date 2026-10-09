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

interface ConsentRecord {
    id: number;
    type: string;
    text_version: string;
    granted_at: string;
    revoked_at: string | null;
    granted_by: { name: string };
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
    consent_records: ConsentRecord[];
}

const STATUS_LABEL: Record<string, string> = {
    assigned: 'Atribuída',
    started: 'Iniciada',
    submitted: 'Por avaliar',
    reviewed: 'Avaliada',
    cancelled: 'Cancelada',
};

interface Summary {
    total: number;
    completed: number;
    pending_evaluation: number;
    overdue: number;
}

export default function Show({
    child,
    canManageClinical,
    canDelete,
    publishableActivities,
    summary,
}: {
    child: Child;
    canManageClinical: boolean;
    canDelete: boolean;
    publishableActivities: { id: number; title: string }[];
    summary: Summary;
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

    const consentForm = useForm({ type: 'tratamento_dados', text_version: '' });
    const submitConsent: FormEventHandler = (e) => {
        e.preventDefault();
        consentForm.post(route('children.consents.store', child.id), { onSuccess: () => consentForm.reset('text_version') });
    };
    const revokeConsent = (consentId: number) => router.patch(route('children.consents.revoke', [child.id, consentId]));

    return (
        <ProfessionalLayout title={child.preferred_name ?? child.first_name} description={`Experiência: ${child.visual_experience} anos`}>
            <Head title={child.preferred_name ?? child.first_name} />

            {flash.newDeviceCode && (
                <div className="mb-4 rounded-shell border border-accent bg-accent-soft px-4 py-3 text-sm text-accent">
                    <p className="font-medium">Código: {flash.newDeviceCode} · PIN: {flash.newDevicePin}</p>
                    <p>Introduza no dispositivo da criança. Estes valores não voltam a ser mostrados.</p>
                </div>
            )}

            <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div className="rounded-shell border border-border bg-surface p-4 text-center">
                    <p className="text-2xl font-semibold text-ink">{summary.total}</p>
                    <p className="text-xs text-ink-muted">Atividades atribuídas</p>
                </div>
                <div className="rounded-shell border border-border bg-surface p-4 text-center">
                    <p className="text-2xl font-semibold text-ink">{summary.completed}</p>
                    <p className="text-xs text-ink-muted">Concluídas</p>
                </div>
                <div className="rounded-shell border border-border bg-surface p-4 text-center">
                    <p className="text-2xl font-semibold text-accent">{summary.pending_evaluation}</p>
                    <p className="text-xs text-ink-muted">Por avaliar</p>
                </div>
                <div className="rounded-shell border border-border bg-surface p-4 text-center">
                    <p className={`text-2xl font-semibold ${summary.overdue > 0 ? 'text-danger' : 'text-ink'}`}>{summary.overdue}</p>
                    <p className="text-xs text-ink-muted">Atrasadas</p>
                </div>
            </div>

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
                                                <span className="rounded-shell bg-danger-soft px-2 py-0.5 text-xs font-medium text-danger">Atrasada</span>
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

                    {canDelete && (
                        <section className="rounded-shell border border-danger/30 bg-surface p-5">
                            <h2 className="mb-2 font-semibold text-danger">Dados e privacidade</h2>
                            <a
                                href={route('children.export', child.id)}
                                className="inline-block rounded-shell border border-border px-4 py-2 text-sm text-ink hover:bg-bg"
                            >
                                Exportar todos os dados (JSON)
                            </a>

                            <div className="mt-4 border-t border-border pt-4">
                                <p className="mb-2 text-sm text-ink-muted">
                                    Elimina permanentemente este perfil e todos os dados associados (atribuições, respostas,
                                    avaliações, notas, consentimentos, dispositivos). <strong>Não pode ser desfeito.</strong>
                                </p>
                                <EraseForm childId={child.id} confirmName={child.first_name} />
                            </div>
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

                    <section className="rounded-shell border border-border bg-surface p-5">
                        <h2 className="mb-2 font-semibold text-ink">Consentimentos</h2>
                        <p className="mb-3 text-xs text-ink-muted">
                            Regista aqui que um adulto autorizado deu consentimento (presencial ou em papel) — não é um texto legal, só o registo de qual versão foi usada.
                        </p>
                        <ul className="mb-3 space-y-2 text-sm">
                            {child.consent_records.map((c) => (
                                <li key={c.id} className="flex items-center justify-between">
                                    <span>
                                        {c.type} <span className="text-ink-muted">(v{c.text_version})</span>
                                        {c.revoked_at && <span className="ml-1 text-xs text-danger">revogado</span>}
                                    </span>
                                    {!c.revoked_at && canManageClinical && (
                                        <button onClick={() => revokeConsent(c.id)} className="text-xs text-danger">
                                            Revogar
                                        </button>
                                    )}
                                </li>
                            ))}
                            {child.consent_records.length === 0 && <li className="text-ink-muted">Nenhum consentimento registado.</li>}
                        </ul>

                        {canManageClinical && (
                            <form onSubmit={submitConsent} className="space-y-2">
                                <select
                                    value={consentForm.data.type}
                                    onChange={(e) => consentForm.setData('type', e.target.value)}
                                    className="block w-full rounded-shell border-border text-sm focus:border-accent focus:ring-accent"
                                >
                                    <option value="tratamento_dados">Tratamento de dados</option>
                                    <option value="gravacoes">Gravações (voz/vídeo)</option>
                                    <option value="comunicacoes">Comunicações por email</option>
                                </select>
                                <input
                                    placeholder="Versão do texto (ex.: 2026-01)"
                                    value={consentForm.data.text_version}
                                    onChange={(e) => consentForm.setData('text_version', e.target.value)}
                                    className="block w-full rounded-shell border-border text-sm focus:border-accent focus:ring-accent"
                                />
                                <button type="submit" className="w-full rounded-shell border border-border py-1.5 text-sm text-ink hover:bg-bg">
                                    Registar consentimento
                                </button>
                            </form>
                        )}
                    </section>
                </div>
            </div>
        </ProfessionalLayout>
    );
}

function EraseForm({ childId, confirmName }: { childId: number; confirmName: string }) {
    const { data, setData, delete: destroy, processing, errors } = useForm({ confirm_name: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!window.confirm('Tem a certeza? Esta ação elimina tudo permanentemente e não pode ser desfeita.')) {
            return;
        }
        destroy(route('children.erase', childId));
    };

    return (
        <form onSubmit={submit} className="space-y-2">
            <label className="block text-xs text-ink-muted">
                Escreva o primeiro nome ("{confirmName}") para confirmar
            </label>
            <input
                value={data.confirm_name}
                onChange={(e) => setData('confirm_name', e.target.value)}
                className="block w-full rounded-shell border-border text-sm focus:border-danger focus:ring-danger"
            />
            {errors.confirm_name && <p className="text-sm text-danger">{errors.confirm_name}</p>}
            <button
                type="submit"
                disabled={processing || data.confirm_name !== confirmName}
                className="w-full rounded-shell bg-danger px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
            >
                Eliminar permanentemente
            </button>
        </form>
    );
}
