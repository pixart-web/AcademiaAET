import Mascot from '@/Components/Mascot';
import { Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';

export default function Welcome() {
    useEffect(() => {
        document.documentElement.dataset.shell = 'professional';
    }, []);

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-bg px-4 text-center text-ink" data-shell="professional">
            <Head title="Academia AET" />

            <Mascot state="welcome" size={96} />

            <h1 className="mt-6 text-2xl font-semibold tracking-tight">
                Academia <span className="text-accent">AET</span>
            </h1>
            <p className="mt-2 max-w-md text-sm text-ink-muted">
                Plataforma de acompanhamento terapêutico infantil e juvenil.
                Identidade visual provisória.
            </p>

            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <Link href={route('login')} className="rounded-shell bg-accent px-5 py-2.5 text-sm font-medium text-accent-ink">
                    Entrar como profissional
                </Link>
                <Link href={route('child.login')} className="rounded-shell border border-border bg-surface px-5 py-2.5 text-sm font-medium text-ink">
                    Entrar como criança/jovem
                </Link>
            </div>
        </div>
    );
}
