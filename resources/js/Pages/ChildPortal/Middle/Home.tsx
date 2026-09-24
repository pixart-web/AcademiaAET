import Mascot from '@/Components/Mascot';
import { AchievementSummary, AssignmentSummary, CompletedAssignmentSummary } from '@/Child/types';
import { Link, router } from '@inertiajs/react';
import MiddleLayout from './Layout';

const ACHIEVEMENT_LABEL: Record<string, string> = {
    participation: 'Participação',
    effort: 'Esforço',
    milestone: 'Conquista',
};

export default function Home({
    assignments,
    completed,
    achievements,
    totalPoints,
}: {
    assignments: AssignmentSummary[];
    completed: CompletedAssignmentSummary[];
    achievements: AchievementSummary[];
    totalPoints: number;
}) {
    const open = (assignment: AssignmentSummary) => {
        if (assignment.in_progress_attempt_id) {
            router.get(`/crianca/tentativas/${assignment.in_progress_attempt_id}`);
        } else {
            router.post(`/crianca/atribuicoes/${assignment.id}/iniciar`);
        }
    };

    return (
        <MiddleLayout totalPoints={totalPoints}>
            <h2 className="mb-3 text-lg font-semibold">As tuas missões</h2>

            {assignments.length === 0 ? (
                <div className="flex flex-col items-center rounded-shell border border-dashed border-border py-10 text-center">
                    <Mascot state="waiting" size={80} />
                    <p className="mt-3 text-ink-muted">Sem missões novas agora. Volta em breve!</p>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    {assignments.map((assignment) => (
                        <button
                            key={assignment.id}
                            onClick={() => open(assignment)}
                            className="rounded-shell border border-border bg-surface p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow motion-reduce:transform-none"
                        >
                            <span className="text-xs font-medium uppercase tracking-wide text-accent">
                                {assignment.category ?? 'Missão'}
                            </span>
                            <p className="mt-1 text-lg font-semibold">{assignment.title}</p>
                            <span className="mt-3 inline-block text-sm font-medium text-accent">
                                {assignment.in_progress_attempt_id ? 'Continuar missão' : 'Começar missão'} →
                            </span>
                        </button>
                    ))}
                </div>
            )}

            {achievements.some((a) => a.count > 0) && (
                <div className="mt-8">
                    <h2 className="mb-3 text-lg font-semibold">As tuas conquistas</h2>
                    <div className="flex flex-wrap gap-3">
                        {achievements.filter((a) => a.count > 0).map((a) => (
                            <div key={a.type} className="rounded-shell border border-border bg-surface px-4 py-3 text-center">
                                <p className="text-2xl">🏅</p>
                                <p className="text-sm font-medium">{ACHIEVEMENT_LABEL[a.type]}</p>
                                <p className="text-xs text-ink-muted">×{a.count}</p>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {completed.length > 0 && (
                <div className="mt-8">
                    <h2 className="mb-3 text-lg font-semibold">Missões concluídas</h2>
                    <ul className="space-y-2">
                        {completed.map((a) => (
                            <li key={a.id}>
                                <Link
                                    href={`/crianca/atribuicoes/${a.id}/feedback`}
                                    className="flex items-center justify-between rounded-shell border border-border bg-surface px-4 py-3 text-sm hover:bg-bg"
                                >
                                    <span>{a.title}</span>
                                    <span className="text-ink-muted">
                                        {a.status === 'submitted' && 'A aguardar avaliação'}
                                        {a.status === 'reviewed' && a.has_feedback && 'Feedback disponível →'}
                                        {a.status === 'reviewed' && !a.has_feedback && 'Avaliada'}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </MiddleLayout>
    );
}
