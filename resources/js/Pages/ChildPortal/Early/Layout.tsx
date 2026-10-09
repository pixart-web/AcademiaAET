import Icon from '@/Components/Icon';
import Mascot from '@/Components/Mascot';
import { ForestScene } from '@/Components/art/Scenes';
import { SquirrelState } from '@/Components/art/Squirrel';
import { useChildIdentity } from '@/Child/useChildIdentity';
import { PropsWithChildren, useEffect } from 'react';

/**
 * 3–6 years: a forest clearing frames ONE task. Illustration lives in the
 * band above; words and controls sit on a clean card, never on texture.
 * No menu — only a round "sair" for the adult.
 */
export default function EarlyLayout({ children, mascot = 'welcome' }: PropsWithChildren<{ mascot?: SquirrelState }>) {
    const { logout } = useChildIdentity();

    useEffect(() => {
        document.documentElement.dataset.shell = 'early';
    }, []);

    return (
        <div className="min-h-screen bg-bg pb-[env(safe-area-inset-bottom)] text-ink" data-shell="early">
            <div className="relative h-52 overflow-hidden sm:h-64">
                <ForestScene className="absolute inset-0 mx-auto h-full w-full max-w-4xl [mask-image:linear-gradient(to_right,transparent,black_12%,black_88%,transparent)]" />
                <button
                    onClick={logout}
                    aria-label="Sair (para um adulto)"
                    className="absolute right-4 top-[max(1rem,env(safe-area-inset-top))] flex h-12 w-12 items-center justify-center rounded-full bg-surface/90 text-ink-muted shadow-soft"
                >
                    <Icon name="logout" size={22} />
                </button>
            </div>

            <div className="relative z-20 -mt-24 flex justify-center">
                <Mascot state={mascot} size={150} className="drop-shadow-sm" />
            </div>

            <main className="relative z-10 mx-auto -mt-6 w-full max-w-lg px-4 pb-12">
                <div className="aet-rise flex flex-col items-center rounded-shell bg-surface px-5 pb-8 pt-10 text-center shadow-soft sm:px-8">{children}</div>
            </main>
        </div>
    );
}
