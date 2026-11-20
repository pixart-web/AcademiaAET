import Icon from '@/Components/Icon';
import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Row {
    id: number;
    child_name: string;
    institution_name: string;
    age: string;
    age_block: string;
    status: 'in_progress' | 'completed';
    screened_on: string;
    attention: boolean | null;
    total_percent: number | null;
    guardian_sent: boolean;
    clinic_sent: boolean;
    report_error: string | null;
}

export default function Index({ sessions }: { sessions: Row[] }) {
    return (
        <ProfessionalLayout
            title="Rastreios"
            description="Rastreio automático de linguagem e fala, dos 3 aos 7 anos."
            actions={
                <Link href={route('screenings.create')} className="inline-flex h-11 items-center gap-2 rounded-shell bg-accent px-5 text-sm font-bold text-accent-ink shadow-soft hover:brightness-110">
                    <Icon name="plus" size={18} />
                    Novo rastreio
                </Link>
            }
        >
            <Head title="Rastreios" />

            {sessions.length === 0 ? (
                <p className="rounded-shell border border-dashed border-border bg-surface px-6 py-10 text-center text-ink-muted">
                    Ainda não há rastreios. Comece um novo rastreio para uma criança.
                </p>
            ) : (
                <div className="overflow-x-auto rounded-shell border border-border bg-surface shadow-soft">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-bg-alt/60 text-ink-muted">
                            <tr>
                                <th className="px-4 py-3 font-semibold">Criança</th>
                                <th className="px-4 py-3 font-semibold">Instituição</th>
                                <th className="px-4 py-3 font-semibold">Idade</th>
                                <th className="px-4 py-3 font-semibold">Estado</th>
                                <th className="px-4 py-3 font-semibold">Relatório</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {sessions.map((s) => (
                                <tr key={s.id}>
                                    <td className="px-4 py-3 font-bold">{s.child_name}</td>
                                    <td className="px-4 py-3 text-ink-muted">{s.institution_name}</td>
                                    <td className="px-4 py-3">{s.age} <span className="text-ink-muted">· bloco {s.age_block}</span></td>
                                    <td className="px-4 py-3">
                                        {s.status === 'in_progress' ? (
                                            <span className="rounded-full bg-info-soft px-2.5 py-0.5 text-xs font-bold text-info">Em curso</span>
                                        ) : s.attention ? (
                                            <span className="rounded-full bg-warning-soft px-2.5 py-0.5 text-xs font-bold text-warning">Atenção · {s.total_percent !== null ? `${Math.round(s.total_percent)}%` : '—'}</span>
                                        ) : (
                                            <span className="rounded-full bg-success-soft px-2.5 py-0.5 text-xs font-bold text-success">Dentro do esperado · {s.total_percent !== null ? `${Math.round(s.total_percent)}%` : '—'}</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-ink-muted">
                                        {s.status === 'completed' ? (
                                            <>
                                                {s.guardian_sent ? 'Enviado ao encarregado' : 'Não enviado ao encarregado'}
                                                <br />
                                                {s.clinic_sent ? 'Enviado à clínica' : 'Não enviado à clínica'}
                                                {s.report_error && <span className="block text-warning">{s.report_error}</span>}
                                            </>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={route('screenings.run', s.id)} className="font-semibold text-accent hover:underline">
                                            {s.status === 'in_progress' ? 'Continuar' : 'Ver resultado'}
                                        </Link>
                                        {s.status === 'completed' && (
                                            <button onClick={() => router.post(route('screenings.resend', s.id))} className="ml-4 font-semibold text-ink-muted hover:text-ink">
                                                Reenviar
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </ProfessionalLayout>
    );
}
