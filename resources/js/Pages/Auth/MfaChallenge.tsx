import InputError from '@/Components/InputError';
import Mascot from '@/Components/Mascot';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

export default function MfaChallenge() {
    useEffect(() => {
        document.documentElement.dataset.shell = 'professional';
    }, []);

    const { data, setData, post, processing, errors } = useForm({ code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/mfa-challenge');
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-bg px-4" data-shell="professional">
            <Head title="Verificação em dois passos" />

            <div className="w-full max-w-sm rounded-shell border border-border bg-surface p-8 text-center">
                <Mascot state="waiting" size={64} className="mx-auto" />
                <h1 className="mt-4 text-lg font-semibold text-ink">Verificação em dois passos</h1>
                <p className="mt-1 text-sm text-ink-muted">Introduza o código da sua aplicação de autenticação.</p>

                <form onSubmit={submit} className="mt-6 space-y-4 text-left">
                    <div>
                        <label htmlFor="code" className="block text-sm font-medium text-ink">Código</label>
                        <input
                            id="code"
                            inputMode="numeric"
                            autoFocus
                            maxLength={6}
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            className="mt-1 block w-full rounded-shell border-border text-center text-lg tracking-widest focus:border-accent focus:ring-accent"
                        />
                        <InputError message={errors.code} className="mt-2" />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-shell bg-accent px-4 py-2.5 text-sm font-medium text-accent-ink disabled:opacity-60"
                    >
                        Confirmar
                    </button>
                </form>
            </div>
        </div>
    );
}
