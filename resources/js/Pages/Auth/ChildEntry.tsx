import Mascot from '@/Components/Mascot';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

export default function ChildEntry({ deviceActivated }: { deviceActivated: boolean }) {
    useEffect(() => {
        document.documentElement.dataset.shell = 'middle';
    }, []);

    const activateForm = useForm({ device_code: '', pin: '' });
    const unlockForm = useForm({ pin: '' });

    const submitActivate: FormEventHandler = (e) => {
        e.preventDefault();
        activateForm.post('/crianca/entrar/ativar');
    };

    const submitUnlock: FormEventHandler = (e) => {
        e.preventDefault();
        unlockForm.post('/crianca/entrar/pin', { onFinish: () => unlockForm.reset() });
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-bg px-4 text-center" data-shell="middle">
            <Head title="Entrar" />

            <Mascot state="welcome" size={110} />

            {deviceActivated ? (
                <>
                    <h1 className="mt-6 text-2xl font-semibold text-ink">Olá! Introduz o teu PIN</h1>
                    <form onSubmit={submitUnlock} className="mt-6 w-full max-w-xs space-y-4">
                        <input
                            inputMode="numeric"
                            autoFocus
                            maxLength={6}
                            placeholder="PIN"
                            value={unlockForm.data.pin}
                            onChange={(e) => unlockForm.setData('pin', e.target.value)}
                            className="block w-full rounded-shell border-border py-3 text-center text-2xl tracking-[0.5em] focus:border-accent focus:ring-accent"
                        />
                        {unlockForm.errors.pin && <p className="text-sm text-danger">{unlockForm.errors.pin}</p>}
                        <button
                            type="submit"
                            disabled={unlockForm.processing}
                            className="w-full rounded-shell bg-accent py-3 text-lg font-medium text-accent-ink disabled:opacity-60"
                        >
                            Entrar
                        </button>
                    </form>
                </>
            ) : (
                <>
                    <h1 className="mt-6 text-2xl font-semibold text-ink">Bem-vindo à Academia AET</h1>
                    <p className="mt-1 text-sm text-ink-muted">Peça a um adulto para introduzir o código do dispositivo.</p>
                    <form onSubmit={submitActivate} className="mt-6 w-full max-w-xs space-y-4">
                        <input
                            autoFocus
                            placeholder="Código do dispositivo"
                            value={activateForm.data.device_code}
                            onChange={(e) => activateForm.setData('device_code', e.target.value)}
                            className="block w-full rounded-shell border-border py-3 text-center uppercase tracking-widest focus:border-accent focus:ring-accent"
                        />
                        <input
                            inputMode="numeric"
                            placeholder="PIN"
                            value={activateForm.data.pin}
                            onChange={(e) => activateForm.setData('pin', e.target.value)}
                            className="block w-full rounded-shell border-border py-3 text-center text-xl tracking-widest focus:border-accent focus:ring-accent"
                        />
                        {(activateForm.errors.pin || activateForm.errors.device_code) && (
                            <p className="text-sm text-danger">{activateForm.errors.pin ?? activateForm.errors.device_code}</p>
                        )}
                        <button
                            type="submit"
                            disabled={activateForm.processing}
                            className="w-full rounded-shell bg-accent py-3 text-lg font-medium text-accent-ink disabled:opacity-60"
                        >
                            Associar dispositivo
                        </button>
                    </form>
                </>
            )}
        </div>
    );
}
