import InputError from '@/Components/InputError';
import Mascot from '@/Components/Mascot';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    useEffect(() => {
        document.documentElement.dataset.shell = 'professional';
    }, []);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-bg px-4" data-shell="professional">
            <Head title="Entrar" />

            <div className="w-full max-w-sm rounded-shell border border-border bg-surface p-8">
                <div className="mb-6 flex flex-col items-center text-center">
                    <Mascot state="welcome" size={64} />
                    <h1 className="mt-4 text-lg font-semibold text-ink">Portal profissional</h1>
                    <p className="text-sm text-ink-muted">Academia AET</p>
                </div>

                {status && <div className="mb-4 rounded-shell bg-accent-soft px-3 py-2 text-sm text-accent">{status}</div>}

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label htmlFor="email" className="block text-sm font-medium text-ink">Email</label>
                        <input
                            id="email"
                            type="email"
                            autoComplete="username"
                            autoFocus
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div>
                        <label htmlFor="password" className="block text-sm font-medium text-ink">Palavra-passe</label>
                        <input
                            id="password"
                            type="password"
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <label className="flex items-center gap-2 text-sm text-ink-muted">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded border-border text-accent focus:ring-accent"
                        />
                        Manter sessão iniciada
                    </label>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-shell bg-accent px-4 py-2.5 text-sm font-medium text-accent-ink disabled:opacity-60"
                    >
                        Entrar
                    </button>

                    {canResetPassword && (
                        <Link href={route('password.request')} className="block text-center text-sm text-ink-muted hover:text-ink">
                            Esqueceu-se da palavra-passe?
                        </Link>
                    )}
                </form>
            </div>
        </div>
    );
}
