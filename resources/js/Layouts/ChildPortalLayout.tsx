import Mascot from '@/Components/Mascot';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { PropsWithChildren, useEffect } from 'react';

const SHELL_BY_EXPERIENCE: Record<string, string> = {
    '3-6': 'early',
    '7-13': 'middle',
    '14-18': 'teen',
};

export default function ChildPortalLayout({ children }: PropsWithChildren) {
    const { auth } = usePage<PageProps>().props;
    const child = auth.child!;
    const shell = SHELL_BY_EXPERIENCE[child.visual_experience] ?? 'middle';

    useEffect(() => {
        document.documentElement.dataset.shell = shell;
    }, [shell]);

    const logout = () => router.post('/crianca/sair');

    return (
        <div className="min-h-screen bg-bg text-ink" data-shell={shell}>
            <header className="flex items-center justify-between px-4 py-4 sm:px-8">
                <div className="flex items-center gap-2">
                    <Mascot state="welcome" size={40} />
                    <span className="font-semibold">
                        Olá, {child.preferred_name ?? child.first_name}!
                    </span>
                </div>
                <button onClick={logout} className="text-sm text-ink-muted underline">
                    Trocar
                </button>
            </header>

            <main className="px-4 pb-12 sm:px-8">{children}</main>

            <Link href="/crianca" className="sr-only">Voltar ao início</Link>
        </div>
    );
}
