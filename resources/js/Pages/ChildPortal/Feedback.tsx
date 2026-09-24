import Mascot from '@/Components/Mascot';
import { useChildIdentity } from '@/Child/useChildIdentity';
import { Head, Link } from '@inertiajs/react';
import EarlyLayout from './Early/Layout';
import MiddleLayout from './Middle/Layout';
import TeenLayout from './Teen/Layout';

interface Achievement {
    type: 'participation' | 'effort' | 'milestone';
    points: number;
}

interface FeedbackData {
    assignment: { id: number; status: string; title: string };
    evaluation: { shared_feedback: string | null; evaluated_at: string } | null;
    achievements: Achievement[];
}

const ACHIEVEMENT_LABEL: Record<string, string> = {
    participation: 'Participação',
    effort: 'Esforço',
    milestone: 'Conquista',
};

function FeedbackBody({ assignment, evaluation, achievements }: FeedbackData) {
    if (assignment.status === 'submitted') {
        return (
            <>
                <Mascot state="waiting" size={90} />
                <p className="mt-4 text-lg">A tua resposta foi enviada e está a aguardar avaliação.</p>
            </>
        );
    }

    return (
        <>
            <Mascot state="celebrate" size={90} />
            <h1 className="mt-4 text-xl font-semibold">{assignment.title}</h1>

            <div className="mt-4 rounded-shell border border-border bg-surface p-5 text-left">
                <p className="text-sm font-medium text-ink-muted">Feedback da tua terapeuta</p>
                <p className="mt-2">
                    {evaluation?.shared_feedback
                        ? evaluation.shared_feedback
                        : 'A tua terapeuta ainda não deixou uma mensagem para esta atividade.'}
                </p>
            </div>

            {achievements.length > 0 && (
                <div className="mt-4 flex flex-wrap justify-center gap-3">
                    {achievements.map((a, i) => (
                        <div key={i} className="rounded-shell border border-border bg-surface px-4 py-3 text-center">
                            <p className="text-2xl">🏅</p>
                            <p className="text-sm font-medium">{ACHIEVEMENT_LABEL[a.type]}</p>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}

export default function Feedback(props: FeedbackData) {
    const { child } = useChildIdentity();

    if (child.visual_experience === '3-6') {
        return (
            <EarlyLayout>
                <Head title={props.assignment.title} />
                <FeedbackBody {...props} />
            </EarlyLayout>
        );
    }

    if (child.visual_experience === '14-18') {
        return (
            <TeenLayout>
                <Head title={props.assignment.title} />
                <Link href="/crianca" className="text-sm text-ink-muted underline">
                    ← Voltar
                </Link>
                <div className="mt-4">
                    <FeedbackBody {...props} />
                </div>
            </TeenLayout>
        );
    }

    return (
        <MiddleLayout>
            <Head title={props.assignment.title} />
            <div className="mx-auto max-w-lg text-center">
                <Link href="/crianca" className="text-sm text-ink-muted underline">
                    ← Voltar às missões
                </Link>
                <div className="mt-4">
                    <FeedbackBody {...props} />
                </div>
            </div>
        </MiddleLayout>
    );
}
