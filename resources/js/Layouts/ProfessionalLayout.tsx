import Icon, { IconName } from '@/Components/Icon';
import Avatar from '@/Components/art/Avatar';
import Logo from '@/Components/art/Logo';
import { Sprig } from '@/Components/art/Botanicals';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { FormEventHandler, PropsWithChildren, ReactNode, useEffect, useState } from 'react';

interface NavItem {
    href: string;
    label: string;
    icon: IconName;
    adminOnly?: boolean;
}

const NAV: NavItem[] = [
    { href: 'dashboard', label: 'Visão geral', icon: 'home' },
    { href: 'children.index', label: 'Crianças e jovens', icon: 'users' },
    { href: 'activities.index', label: 'Atividades', icon: 'grid' },
    { href: 'evaluations.index', label: 'Avaliações', icon: 'checkCircle' },
    { href: 'media.index', label: 'Conteúdos', icon: 'folder' },
    { href: 'staff.index', label: 'Equipa', icon: 'users2', adminOnly: true },
    { href: 'audit.index', label: 'Auditoria', icon: 'shieldCheck', adminOnly: true },
];

const ROLE_LABEL: Record<string, string> = {
    admin: 'Administrador',
    professional: 'Terapeuta',
};

function SearchBox({ initial = '' }: { initial?: string }) {
    const [q, setQ] = useState(initial);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (q.trim() !== '') router.get(route('search'), { q: q.trim() });
    };

    return (
        <form onSubmit={submit} role="search" className="relative w-full max-w-md">
            <label htmlFor="global-search" className="sr-only">
                Pesquisar crianças, jovens e atividades
            </label>
            <Icon name="search" size={18} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
            <input
                id="global-search"
                type="search"
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder="Pesquisar crianças, jovens e atividades…"
                className="block h-10 w-full rounded-full border-border bg-bg-alt/60 pl-10 pr-4 text-sm placeholder:text-ink-muted focus:border-accent focus:bg-surface focus:ring-accent"
            />
        </form>
    );
}

function greeting(): string {
    const h = new Date().getHours();
    if (h < 6) return 'Boa noite';
    if (h < 13) return 'Bom dia';
    if (h < 20) return 'Boa tarde';
    return 'Boa noite';
}

export { greeting };

export default function ProfessionalLayout({
    title,
    description,
    actions,
    children,
}: PropsWithChildren<{ title: string; description?: string; actions?: ReactNode }>) {
    const { auth, flash } = usePage<PageProps>().props;
    const user = auth.user!;
    const unread = typeof auth.unreadNotifications === 'number' ? auth.unreadNotifications : 0;
    const [open, setOpen] = useState(false);

    useEffect(() => {
        document.documentElement.dataset.shell = 'professional';
    }, []);

    const nav = (
        <nav aria-label="Principal" className="space-y-1">
            {NAV.filter((item) => !item.adminOnly || user.role === 'admin').map((item) => {
                const active = route().current(item.href.split('.')[0] + '.*') || route().current(item.href);
                return (
                    <Link
                        key={item.href}
                        href={route(item.href)}
                        aria-current={active ? 'page' : undefined}
                        onClick={() => setOpen(false)}
                        className={`relative flex items-center gap-3 rounded-shell px-3 py-2.5 text-sm font-semibold transition-colors ${
                            active ? 'bg-accent-soft text-accent' : 'text-ink-muted hover:bg-bg-alt/60 hover:text-ink'
                        }`}
                    >
                        {active && <span className="absolute -left-4 top-2 bottom-2 w-1 rounded-r-full bg-accent" aria-hidden="true" />}
                        <Icon name={item.icon} size={20} />
                        {item.label}
                    </Link>
                );
            })}
        </nav>
    );

    return (
        <div className="min-h-screen bg-bg text-ink" data-shell="professional">
            <a href="#conteudo" className="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded-shell focus:bg-surface focus:px-3 focus:py-2">
                Saltar para o conteúdo
            </a>

            <div className="mx-auto flex min-h-screen max-w-[1500px]">
                {/* Sidebar — desktop */}
                <aside className="relative hidden w-64 shrink-0 flex-col overflow-hidden border-r border-border bg-surface px-4 py-6 lg:flex">
                    <Link href={route('dashboard')} className="mb-8 block px-2" aria-label="academia AET — visão geral">
                        <Logo />
                    </Link>
                    {nav}
                    <Sprig className="pointer-events-none absolute -bottom-4 -left-3 opacity-80" size={110} />
                    <div className="relative mt-auto rounded-shell bg-bg-alt/70 px-3 py-2 text-xs text-ink-muted">
                        {ROLE_LABEL[user.role] ?? user.role}
                    </div>
                </aside>

                {/* Drawer — mobile/tablet */}
                {open && (
                    <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
                        <button className="absolute inset-0 bg-ink/40" aria-label="Fechar menu" onClick={() => setOpen(false)} />
                        <div className="relative h-full w-72 max-w-[85%] bg-surface px-4 py-6 shadow-lift aet-rise">
                            <div className="mb-6 flex items-center justify-between px-2">
                                <Logo size="sm" />
                                <button onClick={() => setOpen(false)} aria-label="Fechar menu" className="rounded-full p-2 text-ink-muted hover:bg-bg-alt">
                                    <Icon name="close" />
                                </button>
                            </div>
                            {nav}
                        </div>
                    </div>
                )}

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="flex items-center gap-3 border-b border-border bg-bg/90 px-4 py-3 sm:px-6">
                        <button
                            onClick={() => setOpen(true)}
                            aria-label="Abrir menu"
                            className="rounded-full p-2 text-ink hover:bg-bg-alt lg:hidden"
                        >
                            <Icon name="menu" />
                        </button>
                        <div className="lg:hidden">
                            <LogoCompact />
                        </div>
                        <div className="flex flex-1 justify-center lg:justify-start">
                            <div className="hidden w-full max-w-md sm:block">
                                <SearchBox />
                            </div>
                        </div>
                        <Link
                            href={route('notifications.index')}
                            className="relative rounded-full p-2 text-ink-muted hover:bg-bg-alt hover:text-ink"
                            aria-label={`Notificações${unread > 0 ? ` (${unread} por ler)` : ''}`}
                        >
                            <Icon name="bell" size={22} />
                            {unread > 0 && (
                                <span className="absolute right-0 top-0 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white">
                                    {unread}
                                </span>
                            )}
                        </Link>
                        <div className="flex items-center gap-2">
                            <Link href={route('profile.edit')} className="flex items-center gap-2 rounded-full p-1 hover:bg-bg-alt" aria-label={`Perfil de ${user.name}`}>
                                <Avatar seed={user.id} size={36} />
                                <span className="hidden text-sm font-semibold md:inline">{user.name}</span>
                            </Link>
                            <Link href={route('logout')} method="post" as="button" className="rounded-full p-2 text-ink-muted hover:bg-bg-alt hover:text-ink" aria-label="Terminar sessão">
                                <Icon name="logout" />
                            </Link>
                        </div>
                    </header>

                    <div className="px-4 pt-3 sm:hidden">
                        <SearchBox />
                    </div>

                    {flash?.status && (
                        <div className="mx-4 mt-4 flex items-center gap-2 rounded-shell bg-success-soft px-4 py-3 text-sm text-success sm:mx-6" role="status">
                            <Icon name="check" size={18} />
                            {flash.status}
                        </div>
                    )}

                    <main id="conteudo" className="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                        <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                            <div className="min-w-0">
                                <h1 className="font-display text-3xl text-ink sm:text-4xl">{title}</h1>
                                {description && <p className="mt-1 text-ink-muted">{description}</p>}
                            </div>
                            {actions && <div className="flex items-center gap-3">{actions}</div>}
                        </div>
                        <div className="aet-rise">{children}</div>
                    </main>
                </div>
            </div>
        </div>
    );
}

function LogoCompact() {
    return <Logo size="sm" />;
}
