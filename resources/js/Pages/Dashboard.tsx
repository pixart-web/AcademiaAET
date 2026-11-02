import Icon, { IconName } from '@/Components/Icon';
import Avatar from '@/Components/art/Avatar';
import ProfessionalLayout, { greeting } from '@/Layouts/ProfessionalLayout';
import { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

interface ChildRef {
    id: number;
    first_name: string;
    preferred_name: string | null;
    birth_date?: string;
}

interface Attempt {
    id: number;
    submitted_at: string | null;
    assignment: {
        child_profile: ChildRef;
        activity_version: { activity: { title: string } };
    };
}

interface Assignment {
    id: number;
    status: string;
    due_at: string | null;
    is_overdue: boolean;
    child_profile: ChildRef;
    activity_version: { activity: { id: number; title: string } };
    progress: { answered: number; total: number };
}

// Decorative per-activity glyphs, picked by id so the same activity always
// looks the same. They carry no meaning and are never the only cue.
const GLYPHS: IconName[] = ['leaf', 'chat', 'star', 'flag', 'sun', 'list'];

function age(birth?: string): string | null {
    if (!birth) return null;
    const years = Math.floor((Date.now() - new Date(birth).getTime()) / 31_557_600_000);
    return years >= 0 ? `${years} anos` : null;
}

function when(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    const time = d.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
    const sameDay = d.toDateString() === new Date().toDateString();
    return `Concluída ${sameDay ? 'hoje' : d.toLocaleDateString('pt-PT', { day: 'numeric', month: 'short' })} às ${time}`;
}

export default function Dashboard({
    pendingEvaluations,
    inProgressAssignments,
    childCount,
}: {
    pendingEvaluations: Attempt[];
    inProgressAssignments: Assignment[];
    childCount: number;
}) {
    const { auth } = usePage<PageProps>().props;
    const firstName = auth.user!.name.split(' ')[0];

    return (
        <ProfessionalLayout
            title={`${greeting()}, ${firstName}`}
            description="Vamos acompanhar cada pequeno passo."
            actions={
                <Link
                    href={route('activities.create')}
                    className="inline-flex h-11 items-center gap-2 rounded-shell bg-accent px-5 text-sm font-bold text-accent-ink shadow-soft hover:brightness-110"
                >
                    <Icon name="plus" size={18} />
                    Criar atividade
                </Link>
            }
        >
            <Head title="Visão geral" />

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-shell border border-border bg-surface p-5 shadow-soft" aria-labelledby="por-avaliar">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 id="por-avaliar" className="flex items-center gap-2 font-display text-xl">
                            Respostas por avaliar
                            {pendingEvaluations.length > 0 && (
                                <span className="rounded-full bg-highlight px-2 py-0.5 text-sm font-bold text-highlight-ink">{pendingEvaluations.length}</span>
                            )}
                        </h2>
                        <Link href={route('evaluations.index')} className="inline-flex items-center gap-1 text-sm font-semibold text-accent hover:underline">
                            Ver todas <Icon name="arrowRight" size={16} />
                        </Link>
                    </div>

                    {pendingEvaluations.length === 0 ? (
                        <p className="rounded-shell bg-bg-alt/50 px-4 py-6 text-center text-sm text-ink-muted">
                            Tudo em dia — não há respostas à espera de avaliação.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {pendingEvaluations.map((attempt) => {
                                const c = attempt.assignment.child_profile;
                                return (
                                    <li key={attempt.id} className="flex items-center gap-3 py-3">
                                        <Avatar seed={c.id} size={44} />
                                        <div className="min-w-0 flex-1">
                                            <p className="font-bold">{c.preferred_name ?? c.first_name}</p>
                                            {age(c.birth_date) && <p className="text-xs text-ink-muted">{age(c.birth_date)}</p>}
                                        </div>
                                        <div className="hidden min-w-0 flex-1 sm:block">
                                            <p className="truncate text-sm font-semibold">{attempt.assignment.activity_version.activity.title}</p>
                                            <p className="text-xs text-ink-muted">{when(attempt.submitted_at)}</p>
                                        </div>
                                        <Link
                                            href={route('evaluations.show', attempt.id)}
                                            aria-label={`Avaliar ${c.preferred_name ?? c.first_name}: ${attempt.assignment.activity_version.activity.title}`}
                                            className="inline-flex h-10 items-center rounded-shell bg-highlight px-5 text-sm font-bold text-highlight-ink hover:brightness-95"
                                        >
                                            Avaliar
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>

                <section className="rounded-shell border border-border bg-surface p-5 shadow-soft" aria-labelledby="em-curso">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 id="em-curso" className="font-display text-xl">Atividades em curso</h2>
                        <Link href={route('children.index')} className="inline-flex items-center gap-1 text-sm font-semibold text-accent hover:underline">
                            Ver todas <Icon name="arrowRight" size={16} />
                        </Link>
                    </div>

                    {inProgressAssignments.length === 0 ? (
                        <div className="rounded-shell bg-bg-alt/50 px-4 py-6 text-center text-sm text-ink-muted">
                            <p>Sem atividades atribuídas em curso.</p>
                            <Link href={route('children.index')} className="mt-2 inline-block font-semibold text-accent hover:underline">
                                Atribuir a partir do perfil de uma criança
                            </Link>
                        </div>
                    ) : (
                        <ul className="divide-y divide-border">
                            {inProgressAssignments.map((a) => {
                                const c = a.child_profile;
                                const { answered, total } = a.progress;
                                const pct = total > 0 ? Math.round((answered / total) * 100) : 0;
                                return (
                                    <li key={a.id}>
                                        <Link href={route('children.show', c.id)} className="flex items-center gap-3 py-3 hover:bg-bg-alt/30">
                                            <Avatar seed={c.id} size={44} />
                                            <div className="min-w-0 flex-1">
                                                <p className="font-bold">{c.preferred_name ?? c.first_name}</p>
                                                {age(c.birth_date) && <p className="text-xs text-ink-muted">{age(c.birth_date)}</p>}
                                            </div>
                                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-shell bg-accent-soft text-accent">
                                                <Icon name={GLYPHS[a.activity_version.activity.id % GLYPHS.length]} />
                                            </span>
                                            <div className="min-w-0 flex-[1.4]">
                                                <p className="flex items-center gap-2 truncate text-sm font-semibold">
                                                    <span className="truncate">{a.activity_version.activity.title}</span>
                                                    {a.is_overdue && <span className="shrink-0 rounded-full bg-danger-soft px-2 py-0.5 text-xs font-bold text-danger">Atrasada</span>}
                                                </p>
                                                <p className="text-xs text-ink-muted">
                                                    {answered} de {total} {total === 1 ? 'passo' : 'passos'}
                                                </p>
                                                <div
                                                    className="mt-1 h-1.5 overflow-hidden rounded-full bg-bg-alt"
                                                    role="progressbar"
                                                    aria-valuemin={0}
                                                    aria-valuemax={total}
                                                    aria-valuenow={answered}
                                                    aria-label="Progresso da atividade"
                                                >
                                                    <div className="h-full rounded-full bg-accent" style={{ width: `${pct}%` }} />
                                                </div>
                                            </div>
                                            <Icon name="chevronRight" className="shrink-0 text-ink-muted" />
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            </div>

            <p className="mt-6 text-sm text-ink-muted">
                {childCount} {childCount === 1 ? 'criança/jovem acompanhado' : 'crianças/jovens acompanhados'} ·{' '}
                <Link href={route('children.create')} className="font-semibold text-accent hover:underline">
                    Novo perfil
                </Link>
            </p>
        </ProfessionalLayout>
    );
}
