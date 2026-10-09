import Avatar from '@/Components/art/Avatar';
import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link } from '@inertiajs/react';

interface ChildHit {
    id: number;
    first_name: string;
    preferred_name: string | null;
    visual_experience: string;
}
interface ActivityHit {
    id: number;
    title: string;
    status: string;
}

const STATUS: Record<string, string> = { draft: 'Rascunho', published: 'Publicada', archived: 'Arquivada' };

export default function Index({ q, children, activities }: { q: string; children: ChildHit[]; activities: ActivityHit[] }) {
    return (
        <ProfessionalLayout title="Pesquisa" description={q ? `Resultados para “${q}”` : 'Escreva um nome ou título na barra de pesquisa.'}>
            <Head title="Pesquisa" />

            {q && children.length === 0 && activities.length === 0 && (
                <p className="rounded-shell border border-dashed border-border bg-surface p-8 text-center text-ink-muted">
                    Nada encontrado. A pesquisa só mostra crianças e jovens que acompanha e atividades da sua organização.
                </p>
            )}

            <div className="grid gap-6 lg:grid-cols-2">
                {children.length > 0 && (
                    <section className="rounded-shell border border-border bg-surface p-5 shadow-soft">
                        <h2 className="mb-3 font-display text-xl">Crianças e jovens</h2>
                        <ul className="divide-y divide-border">
                            {children.map((c) => (
                                <li key={c.id}>
                                    <Link href={route('children.show', c.id)} className="flex items-center gap-3 py-3 hover:text-accent">
                                        <Avatar seed={c.id} size={36} />
                                        <span className="font-semibold">{c.preferred_name ?? c.first_name}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                {activities.length > 0 && (
                    <section className="rounded-shell border border-border bg-surface p-5 shadow-soft">
                        <h2 className="mb-3 font-display text-xl">Atividades</h2>
                        <ul className="divide-y divide-border">
                            {activities.map((a) => (
                                <li key={a.id}>
                                    <Link href={route('activities.edit', a.id)} className="flex items-center justify-between py-3 hover:text-accent">
                                        <span className="font-semibold">{a.title}</span>
                                        <span className="text-sm text-ink-muted">{STATUS[a.status] ?? a.status}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </ProfessionalLayout>
    );
}
