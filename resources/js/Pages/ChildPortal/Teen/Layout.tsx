import { useChildIdentity } from '@/Child/useChildIdentity';
import Icon from '@/Components/Icon';
import Logo from '@/Components/art/Logo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren, useEffect } from 'react';

/**
 * 14–18 years: "O teu espaço". Light, quiet, serif titles, no mascot and no
 * childish chrome. The bottom bar (phones only) lists just destinations that
 * really exist on the home page: activities, history, and ending the session.
 */
export default function TeenLayout({ children, nav = false }: PropsWithChildren<{ nav?: boolean }>) {
    const { displayName, logout } = useChildIdentity();

    useEffect(() => {
        document.documentElement.dataset.shell = 'teen';
    }, []);

    return (
        <div className="min-h-screen bg-bg pb-[calc(env(safe-area-inset-bottom)+4.5rem)] text-ink sm:pb-0" data-shell="teen">
            <header className="mx-auto flex max-w-2xl items-center justify-between px-5 pb-2 pt-[max(1.25rem,env(safe-area-inset-top))] sm:px-8">
                <Link href="/crianca" aria-label="academia AET — início">
                    <Logo size="sm" />
                </Link>
                <div className="flex items-center gap-3 text-sm text-ink-muted">
                    <span className="hidden sm:inline">{displayName}</span>
                    <button onClick={logout} className="inline-flex items-center gap-1.5 rounded-full border border-border bg-surface px-3 py-1.5 font-semibold hover:bg-bg-alt">
                        <Icon name="logout" size={16} />
                        <span className="hidden sm:inline">Terminar sessão</span>
                        <span className="sm:hidden">Sair</span>
                    </button>
                </div>
            </header>

            <main className="aet-rise mx-auto max-w-2xl px-5 pb-10 pt-4 sm:px-8">{children}</main>

            {nav && (
                <nav
                    aria-label="Secções"
                    className="fixed inset-x-0 bottom-0 z-20 flex justify-around border-t border-border bg-surface/95 pb-[env(safe-area-inset-bottom)] pt-2 sm:hidden"
                >
                    <a href="#atividades" className="flex min-w-20 flex-col items-center gap-0.5 px-3 py-1 text-xs font-bold text-accent">
                        <Icon name="list" size={22} />
                        Atividades
                    </a>
                    <a href="#historico" className="flex min-w-20 flex-col items-center gap-0.5 px-3 py-1 text-xs font-bold text-ink-muted">
                        <Icon name="checkCircle" size={22} />
                        Histórico
                    </a>
                    <button onClick={logout} className="flex min-w-20 flex-col items-center gap-0.5 px-3 py-1 text-xs font-bold text-ink-muted">
                        <Icon name="user" size={22} />
                        Sair
                    </button>
                </nav>
            )}
        </div>
    );
}
