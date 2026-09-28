import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link } from '@inertiajs/react';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    return (
        <ProfessionalLayout title="Perfil">
            <Head title="Perfil" />

            <div className="max-w-2xl space-y-6">
                <div className="rounded-shell border border-border bg-surface p-6">
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} className="max-w-xl" />
                </div>

                <div className="rounded-shell border border-border bg-surface p-6">
                    <UpdatePasswordForm className="max-w-xl" />
                </div>

                <div className="rounded-shell border border-border bg-surface p-6">
                    <h2 className="text-lg font-medium text-ink">Autenticação em dois passos</h2>
                    <p className="mt-1 text-sm text-ink-muted">Adicione uma camada extra de segurança à sua conta.</p>
                    <Link href={route('mfa.edit')} className="mt-4 inline-block rounded-shell border border-border px-4 py-2 text-sm text-ink hover:bg-bg">
                        Gerir autenticação em dois passos
                    </Link>
                </div>

                <div className="rounded-shell border border-border bg-surface p-6">
                    <h2 className="text-lg font-medium text-ink">Sessões ativas</h2>
                    <p className="mt-1 text-sm text-ink-muted">Veja e termine o acesso de outros dispositivos com sessão iniciada.</p>
                    <Link href={route('sessions.index')} className="mt-4 inline-block rounded-shell border border-border px-4 py-2 text-sm text-ink hover:bg-bg">
                        Ver sessões ativas
                    </Link>
                </div>
            </div>
        </ProfessionalLayout>
    );
}
