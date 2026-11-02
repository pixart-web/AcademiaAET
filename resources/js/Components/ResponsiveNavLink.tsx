import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active?: boolean }) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-accent bg-accent text-accent focus:border-accent focus:bg-accent focus:text-accent'
                    : 'border-transparent text-ink-muted hover:border-border hover:bg-bg-alt hover:text-ink focus:border-border focus:bg-bg-alt focus:text-ink'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
