import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, router } from '@inertiajs/react';

interface SessionRow {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_activity: string;
    is_current: boolean;
}

function describeAgent(agent: string | null): string {
    if (!agent) return 'Dispositivo desconhecido';
    if (/iphone|ipad/i.test(agent)) return 'iPhone/iPad';
    if (/android/i.test(agent)) return 'Android';
    if (/macintosh/i.test(agent)) return 'Mac';
    if (/windows/i.test(agent)) return 'Windows';
    return 'Outro dispositivo';
}

export default function Sessions({ sessions }: { sessions: SessionRow[] }) {
    const revoke = (id: string) => {
        if (window.confirm('Terminar esta sessão? O dispositivo terá de iniciar sessão novamente.')) {
            router.delete(route('sessions.destroy', id));
        }
    };

    return (
        <ProfessionalLayout title="Sessões ativas" description="Termine o acesso de um dispositivo que já não usa ou que perdeu.">
            <Head title="Sessões ativas" />

            <div className="max-w-2xl divide-y divide-border rounded-shell border border-border bg-surface">
                {sessions.map((s) => (
                    <div key={s.id} className="flex items-center justify-between p-4 text-sm">
                        <div>
                            <p className="font-medium text-ink">
                                {describeAgent(s.user_agent)} {s.is_current && <span className="text-accent">(esta sessão)</span>}
                            </p>
                            <p className="text-ink-muted">
                                {s.ip_address} · última atividade {new Date(s.last_activity).toLocaleString('pt-PT')}
                            </p>
                        </div>
                        {!s.is_current && (
                            <button onClick={() => revoke(s.id)} className="text-danger">
                                Terminar
                            </button>
                        )}
                    </div>
                ))}
                {sessions.length === 0 && <p className="p-4 text-ink-muted">Sem sessões registadas.</p>}
            </div>
        </ProfessionalLayout>
    );
}
