import Mascot from '@/Components/Mascot';
import { useChildIdentity } from '@/Child/useChildIdentity';
import { Link } from '@inertiajs/react';
import { PropsWithChildren, useEffect } from 'react';

/**
 * 7–13 years: a sense of discovery and a mission list, more autonomy than
 * the 3–6 shell but still no public ranking, no comparison between
 * children, no streak-loss pressure — see master prompt constraints.
 */
export default function MiddleLayout({ children, totalPoints }: PropsWithChildren<{ totalPoints?: number }>) {
    const { displayName, logout } = useChildIdentity();

    useEffect(() => {
        document.documentElement.dataset.shell = 'middle';
    }, []);

    return (
        <div className="min-h-screen bg-bg text-ink" data-shell="middle">
            <header className="flex items-center justify-between border-b border-border bg-surface px-4 py-3 sm:px-8">
                <Link href="/crianca" className="flex items-center gap-2">
                    <Mascot state="welcome" size={36} />
                    <span className="font-semibold">Olá, {displayName}!</span>
                </Link>

                <div className="flex items-center gap-4 text-sm">
                    {totalPoints !== undefined && (
                        <span className="rounded-full bg-accent-soft px-3 py-1 font-medium text-accent">★ {totalPoints} pts</span>
                    )}
                    <button onClick={logout} className="text-ink-muted underline">
                        Trocar
                    </button>
                </div>
            </header>

            <main className="px-4 py-6 sm:px-8">{children}</main>
        </div>
    );
}
