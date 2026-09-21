import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, useEffect } from 'react';

const NAV = [
    { href: 'dashboard', label: 'Painel' },
    { href: 'children.index', label: 'Crianças e jovens' },
    { href: 'activities.index', label: 'Atividades' },
    { href: 'evaluations.index', label: 'Por avaliar' },
    { href: 'media.index', label: 'Conteúdos' },
    { href: 'staff.index', label: 'Equipa', adminOnly: true },
];

export default function ProfessionalLayout({
    title,
    children,
}: PropsWithChildren<{ title: string }>) {
    const { auth, flash } = usePage<PageProps>().props;
    const user = auth.user!;

    useEffect(() => {
        document.documentElement.dataset.shell = 'professional';
    }, []);

    return (
        <div className="min-h-screen bg-bg text-ink" data-shell="professional">
            <div className="mx-auto flex min-h-screen max-w-7xl">
                <aside className="hidden w-64 shrink-0 border-r border-border bg-surface px-4 py-6 md:block">
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
                </aside>

                <div className="flex flex-1 flex-col">
                    <header className="flex items-center justify-between border-b border-border bg-surface px-4 py-4 sm:px-6">
                        <h1 className="text-lg font-semibold text-ink">{title}</h1>

                        <div className="flex items-center gap-4 text-sm">
                            <Link href={route('notifications.index')} className="text-ink-muted hover:text-ink">
                                Notificações
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
                        <div className="mx-4 mt-4 rounded-shell bg-accent-soft px-4 py-3 text-sm text-accent sm:mx-6">
                            {flash.status}
                        </div>
                    )}

                    <main className="flex-1 px-4 py-6 sm:px-6">{children}</main>
                </div>
            </div>
        </div>
    );
}
