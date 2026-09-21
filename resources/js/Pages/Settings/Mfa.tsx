import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Mfa({ mfaEnabled, setupQrCodeDataUri }: { mfaEnabled: boolean; setupQrCodeDataUri: string | null }) {
    const { post, delete: destroy, data, setData, processing } = useForm({ code: '' });

    const beginSetup: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('mfa.enable'));
    };

    const confirmSetup: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('mfa.enable'));
    };

    return (
        <ProfessionalLayout title="Autenticação em dois passos">
            <Head title="Autenticação em dois passos" />

            <div className="max-w-md rounded-shell border border-border bg-surface p-6">
                {mfaEnabled ? (
                    <>
                        <p className="text-ink">Autenticação em dois passos está ativa nesta conta.</p>
                        <button
                            onClick={() => destroy(route('mfa.disable'))}
                            className="mt-4 rounded-shell border border-danger px-4 py-2 text-sm text-danger"
                        >
                            Desativar
                        </button>
                    </>
                ) : setupQrCodeDataUri ? (
                    <form onSubmit={confirmSetup} className="space-y-4">
                        <p className="text-sm text-ink-muted">Digitalize este código na sua aplicação de autenticação e confirme com o código gerado.</p>
                        <img
                            src={setupQrCodeDataUri}
                            alt="Código QR para configurar autenticação em dois passos"
                            className="mx-auto"
                        />
                        <input
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            placeholder="Código de 6 dígitos"
                            className="block w-full rounded-shell border-border text-center focus:border-accent focus:ring-accent"
                        />
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink"
                        >
                            Confirmar e ativar
                        </button>
                    </form>
                ) : (
                    <form onSubmit={beginSetup}>
                        <p className="text-sm text-ink-muted">Adicione uma camada extra de segurança à sua conta.</p>
                        <button type="submit" className="mt-4 rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink">
                            Ativar autenticação em dois passos
                        </button>
                    </form>
                )}
            </div>
        </ProfessionalLayout>
    );
}
