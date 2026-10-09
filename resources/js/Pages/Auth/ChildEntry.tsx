import Logo from '@/Components/art/Logo';
import Mascot from '@/Components/Mascot';
import { ForestScene } from '@/Components/art/Scenes';
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
        <div className="min-h-screen bg-bg text-center text-ink" data-shell="middle">
            <Head title="Entrar" />

            <div className="relative h-44 overflow-hidden sm:h-56">
                <ForestScene className="absolute inset-0 h-full w-full" />
            </div>
            <div className="relative z-10 -mt-20 flex justify-center">
                <Mascot state="welcome" size={130} />
            </div>
            <div className="mt-2 flex flex-col items-center px-4 pb-12">
            <Logo size="md" />

            {deviceActivated ? (
                <>
                    <h1 className="mt-6 text-2xl font-extrabold text-ink">Olá! Introduz o teu PIN</h1>
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
                            className="h-14 w-full rounded-shell bg-accent text-lg font-extrabold text-accent-ink shadow-lift disabled:opacity-60"
                        >
                            Entrar
                        </button>
                    </form>
                </>
            ) : (
                <>
                    <h1 className="mt-6 text-2xl font-extrabold text-ink">Bem-vindo à Academia AET</h1>
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
                            className="h-14 w-full rounded-shell bg-accent text-lg font-extrabold text-accent-ink shadow-lift disabled:opacity-60"
                        >
                            Associar dispositivo
                        </button>
                    </form>
                </>
            )}
            </div>
        </div>
    );
}
