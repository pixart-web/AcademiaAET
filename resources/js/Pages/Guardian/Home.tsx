import Mascot from '@/Components/Mascot';
import { Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';

export default function Home() {
    useEffect(() => {
        document.documentElement.dataset.shell = 'professional';
    }, []);

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-bg px-4 text-center" data-shell="professional">
            <Head title="Área do encarregado de educação" />

            <Mascot state="waiting" size={90} />
            <h1 className="mt-6 text-xl font-semibold text-ink">Ainda a preparar esta área</h1>
            <p className="mt-2 max-w-md text-sm text-ink-muted">
                O portal do encarregado de educação está planeado para uma fase seguinte. Por agora, receberá emails
                sobre atividades atribuídas e avaliações disponíveis.
            </p>
            <Link href={route('logout')} method="post" as="button" className="mt-6 text-sm text-ink-muted underline">
                Sair
            </Link>
        </div>
    );
}
