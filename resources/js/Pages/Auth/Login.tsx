import InputError from '@/Components/InputError';
import { Sprig } from '@/Components/art/Botanicals';
import Logo from '@/Components/art/Logo';
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
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-bg px-4" data-shell="professional">
            <Sprig className="pointer-events-none absolute -left-4 bottom-0 hidden opacity-90 sm:block" size={190} />
            <Sprig className="pointer-events-none absolute -right-4 top-8 hidden opacity-70 sm:block" size={150} flip />
            <Head title="Entrar" />

            <div className="relative w-full max-w-sm rounded-shell border border-border bg-surface p-8 shadow-soft">
                <div className="mb-6 flex flex-col items-center text-center">
                    <Logo size="lg" />
                    <h1 className="mt-5 font-display text-2xl text-ink">Portal profissional</h1>
                    <p className="text-sm text-ink-muted">Entre para acompanhar cada pequeno passo.</p>
                </div>

                {status && <div className="mb-4 rounded-shell bg-success-soft px-3 py-2 text-sm text-success">{status}</div>}

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
                        className="w-full h-11 rounded-shell bg-accent px-4 text-sm font-bold text-accent-ink shadow-soft hover:brightness-110 disabled:opacity-60"
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
