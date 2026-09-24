import { useChildIdentity } from '@/Child/useChildIdentity';
import { Link } from '@inertiajs/react';
import { PropsWithChildren, useEffect } from 'react';

/**
 * 14–18 years: contemporary and understated. No mascot in the chrome, no
 * "gamified" reward chrome up front, direct language, activities organized
 * by goal/progress like any other self-directed app they already use.
 */
export default function TeenLayout({ children }: PropsWithChildren) {
    const { displayName, logout } = useChildIdentity();

    useEffect(() => {
        document.documentElement.dataset.shell = 'teen';
    }, []);

    return (
        <div className="min-h-screen bg-bg text-ink" data-shell="teen">
            <header className="flex items-center justify-between border-b border-border px-4 py-4 sm:px-8">
                <Link href="/crianca" className="font-semibold tracking-tight">
                    Academia AET
                </Link>
                <div className="flex items-center gap-4 text-sm text-ink-muted">
                    <span>{displayName}</span>
                    <button onClick={logout} className="underline">
                        Terminar sessão
                    </button>
                </div>
            </header>

            <main className="mx-auto max-w-2xl px-4 py-8 sm:px-8">{children}</main>
        </div>
    );
}
