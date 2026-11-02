import { Sprig } from '@/Components/art/Botanicals';
import Logo from '@/Components/art/Logo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren, useEffect } from 'react';

/** Shared frame for every signed-out staff screen (login, recovery, MFA...). */
export default function Guest({ children }: PropsWithChildren) {
    useEffect(() => {
        document.documentElement.dataset.shell = 'professional';
    }, []);

    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-bg px-4 py-10" data-shell="professional">
            <Sprig className="pointer-events-none absolute -left-4 bottom-0 hidden opacity-90 sm:block" size={170} />
            <Sprig className="pointer-events-none absolute -right-4 top-10 hidden opacity-70 sm:block" size={140} flip />

            <Link href="/" aria-label="academia AET">
                <Logo size="lg" />
            </Link>

            <div className="relative mt-8 w-full overflow-hidden rounded-shell border border-border bg-surface px-6 py-6 shadow-soft sm:max-w-md">
                {children}
            </div>
        </div>
    );
}
