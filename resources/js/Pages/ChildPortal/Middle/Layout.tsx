import { useChildIdentity } from '@/Child/useChildIdentity';
import Icon from '@/Components/Icon';
import { TrailScene } from '@/Components/art/Scenes';
import { PropsWithChildren, ReactNode, useEffect } from 'react';

/**
 * 7–13 years: a discovery landscape above, the actual task on a clean sheet
 * below. `hero` carries the greeting/next-mission text (real HTML over the
 * sky area); `compact` shrinks the landscape during an attempt so the task
 * is never pushed off a small screen. No rankings, no comparison.
 */
export default function MiddleLayout({
    children,
    hero,
    compact = false,
}: PropsWithChildren<{ hero?: ReactNode; compact?: boolean }>) {
    const { logout } = useChildIdentity();

    useEffect(() => {
        document.documentElement.dataset.shell = 'middle';
    }, []);

    return (
        <div className="min-h-screen bg-bg pb-[env(safe-area-inset-bottom)] text-ink" data-shell="middle">
            <div className={`relative overflow-hidden bg-[linear-gradient(to_bottom,#9fd3cf_0%,#e9f1dc_50%,#a8d6d8_100%)] ${compact ? 'h-28 sm:h-40' : 'h-72 sm:h-96'}`}>
                <TrailScene className="absolute inset-0 mx-auto h-full w-full max-w-4xl [mask-image:linear-gradient(to_right,transparent,black_12%,black_88%,transparent)]" />
                <button
                    onClick={logout}
                    aria-label="Trocar de perfil (sair)"
                    className="absolute right-4 top-[max(1rem,env(safe-area-inset-top))] flex h-11 w-11 items-center justify-center rounded-full bg-surface/90 text-ink-muted shadow-soft"
                >
                    <Icon name="logout" size={20} />
                </button>
                {hero && <div className="absolute inset-x-0 top-0 px-5 pt-[max(1.25rem,env(safe-area-inset-top))] sm:px-10">{hero}</div>}
            </div>

            <main className="relative z-10 mx-auto -mt-8 w-full max-w-2xl px-3 pb-12 sm:px-6">
                <div className="aet-rise rounded-t-[2rem] rounded-b-shell bg-surface px-5 py-6 shadow-lift sm:px-8">{children}</div>
            </main>
        </div>
    );
}
