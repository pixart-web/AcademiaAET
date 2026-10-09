import Icon from '@/Components/Icon';
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

function FeedbackBody({ assignment, evaluation, achievements, showMascot = false }: FeedbackData & { showMascot?: boolean }) {
    if (assignment.status === 'submitted') {
        return (
            <>
                {showMascot && <Mascot state="waiting" size={90} />}
                <p className="mt-4 text-lg">A tua resposta foi enviada e está a aguardar avaliação.</p>
            </>
        );
    }

    return (
        <>
            {showMascot && <Mascot state="celebrate" size={90} />}
            <h1 className="mt-4 text-2xl font-extrabold">{assignment.title}</h1>

            <div className="mt-4 w-full rounded-shell border border-border bg-bg/60 p-5 text-left">
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
                        <div key={i} className="flex items-center gap-2 rounded-full bg-highlight/50 px-4 py-2 text-sm font-bold text-highlight-ink">
                            <Icon name="star" size={18} />
                            {ACHIEVEMENT_LABEL[a.type]}
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
            <EarlyLayout mascot={props.assignment.status === 'submitted' ? 'waiting' : 'celebrate'}>
                <Head title={props.assignment.title} />
                <FeedbackBody {...props} />
            </EarlyLayout>
        );
    }

    if (child.visual_experience === '14-18') {
        return (
            <TeenLayout>
                <Head title={props.assignment.title} />
                <Link href="/crianca" className="text-sm font-bold text-accent hover:underline">
                    ← Voltar
                </Link>
                <div className="mt-4">
                    <FeedbackBody {...props} />
                </div>
            </TeenLayout>
        );
    }

    return (
        <MiddleLayout compact>
            <Head title={props.assignment.title} />
            <div className="mx-auto max-w-lg text-center">
                <Link href="/crianca" className="text-sm font-bold text-accent hover:underline">
                    ← Voltar às missões
                </Link>
                <div className="mt-4 flex flex-col items-center">
                    <FeedbackBody {...props} showMascot />
                </div>
            </div>
        </MiddleLayout>
    );
}
