import Mascot from '@/Components/Mascot';
import { useChildIdentity } from '@/Child/useChildIdentity';
import { PropsWithChildren, useEffect } from 'react';

/**
 * 3–6 years: one screen, one task, minimal reading, adult-assisted. No menu,
 * no navigation chrome beyond "sair" — the child never has to find their way
 * around, only look at what's in front of them.
 */
export default function EarlyLayout({ children }: PropsWithChildren) {
    const { logout } = useChildIdentity();

    useEffect(() => {
        document.documentElement.dataset.shell = 'early';
    }, []);

    return (
        <div className="flex min-h-screen flex-col bg-bg text-ink" data-shell="early">
            <header className="flex items-center justify-between px-4 py-4">
                <Mascot state="welcome" size={48} />
                <button
                    onClick={logout}
                    aria-label="Sair (para um adulto)"
                    className="rounded-full border border-border bg-surface p-3 text-ink-muted"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" strokeLinecap="round" strokeLinejoin="round" />
                        <path d="M16 17l5-5-5-5M21 12H9" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                </button>
            </header>

            <main className="flex flex-1 flex-col items-center justify-center px-4 pb-10 text-center">{children}</main>
        </div>
    );
}
