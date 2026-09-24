import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useEffect } from 'react';

const NAV = [
    { href: 'dashboard', label: 'Painel' },
    { href: 'children.index', label: 'Crianças e jovens' },
    { href: 'activities.index', label: 'Atividades' },
    { href: 'evaluations.index', label: 'Por avaliar' },
    { href: 'media.index', label: 'Conteúdos' },
    { href: 'staff.index', label: 'Equipa', adminOnly: true },
];

const ROLE_LABEL: Record<string, string> = {
    admin: 'Administrador',
    professional: 'Terapeuta',
};

export default function ProfessionalLayout({
    title,
    description,
    actions,
    children,
}: PropsWithChildren<{ title: string; description?: string; actions?: ReactNode }>) {
    const { auth, flash } = usePage<PageProps>().props;
    const user = auth.user!;
    const unread = typeof auth.unreadNotifications === 'number' ? auth.unreadNotifications : 0;

    useEffect(() => {
        document.documentElement.dataset.shell = 'professional';
    }, []);

    return (
        <div className="min-h-screen bg-bg text-ink" data-shell="professional">
            <div className="mx-auto flex min-h-screen max-w-7xl">
                <aside className="relative hidden w-64 shrink-0 border-r border-border bg-surface px-4 py-6 md:block">
                    <Link href={route('dashboard')} className="mb-8 block px-2 text-lg font-semibold tracking-tight text-ink">
                        Academia <span className="text-accent">AET</span>
                    </Link>

                    <nav className="space-y-1">
                        {NAV.filter((item) => !item.adminOnly || user.role === 'admin').map((item) => (
                            <Link
                                key={item.href}
                                href={route(item.href)}
                                className={`block rounded-shell px-3 py-2 text-sm font-medium transition-colors ${
                                    route().current(item.href.split('.')[0] + '.*')
                                        ? 'bg-accent-soft text-accent'
                                        : 'text-ink-muted hover:bg-bg hover:text-ink'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="absolute bottom-6 left-4 right-4 rounded-shell bg-bg px-3 py-2 text-xs text-ink-muted">
                        {ROLE_LABEL[user.role] ?? user.role}
                    </div>
                </aside>

                <div className="flex flex-1 flex-col">
                    <header className="flex items-center justify-between border-b border-border bg-surface px-4 py-4 sm:px-6">
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight text-ink">{title}</h1>
                            {description && <p className="mt-0.5 text-sm text-ink-muted">{description}</p>}
                        </div>

                        <div className="flex items-center gap-4 text-sm">
                            {actions}
                            <Link href={route('notifications.index')} className="relative text-ink-muted hover:text-ink" aria-label={`Notificações${unread > 0 ? ` (${unread} por ler)` : ''}`}>
                                Notificações
                                {unread > 0 && (
                                    <span className="absolute -right-2.5 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold text-white">
                                        {unread}
                                    </span>
                                )}
                            </Link>
                            <Link href={route('profile.edit')} className="text-ink-muted hover:text-ink">
                                {user.name}
                            </Link>
                            <Link href={route('logout')} method="post" as="button" className="text-ink-muted hover:text-ink">
                                Sair
                            </Link>
                        </div>
                    </header>

                    {flash?.status && (
                        <div className="mx-4 mt-4 rounded-shell bg-accent-soft px-4 py-3 text-sm text-accent sm:mx-6" role="status">
                            {flash.status}
                        </div>
                    )}

                    <main className="flex-1 px-4 py-6 sm:px-6">{children}</main>
                </div>
            </div>
        </div>
    );
}
