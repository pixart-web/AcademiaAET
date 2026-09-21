import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link } from '@inertiajs/react';

interface ChildRow {
    id: number;
    first_name: string;
    preferred_name: string | null;
    birth_date: string;
    visual_experience: string;
    status: string;
    is_demo: boolean;
}

const EXPERIENCE_LABEL: Record<string, string> = {
    '3-6': '3–6 anos',
    '7-13': '7–13 anos',
    '14-18': '14–18 anos',
};

export default function Index({ children }: { children: ChildRow[] }) {
    return (
        <ProfessionalLayout title="Crianças e jovens">
            <Head title="Crianças e jovens" />

            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-ink-muted">{children.length} perfis</p>
                <Link href={route('children.create')} className="rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink">
                    Novo perfil
                </Link>
            </div>

            <div className="overflow-hidden rounded-shell border border-border bg-surface">
                <table className="w-full text-left text-sm">
                    <thead className="bg-bg text-ink-muted">
                        <tr>
                            <th className="px-4 py-3 font-medium">Nome</th>
                            <th className="px-4 py-3 font-medium">Experiência</th>
                            <th className="px-4 py-3 font-medium">Estado</th>
                            <th className="px-4 py-3 font-medium" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {children.map((child) => (
                            <tr key={child.id}>
                                <td className="px-4 py-3">
                                    {child.preferred_name ?? child.first_name}
                                    {child.is_demo && (
                                        <span className="ml-2 rounded-shell bg-accent-soft px-2 py-0.5 text-xs text-accent">demonstração</span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-ink-muted">{EXPERIENCE_LABEL[child.visual_experience]}</td>
                                <td className="px-4 py-3 capitalize text-ink-muted">{child.status}</td>
                                <td className="px-4 py-3 text-right">
                                    <Link href={route('children.show', child.id)} className="text-accent hover:underline">
                                        Ver
                                    </Link>
                                </td>
                            </tr>
                        ))}
                        {children.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-4 py-6 text-center text-ink-muted">
                                    Ainda sem perfis criados.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </ProfessionalLayout>
    );
}
