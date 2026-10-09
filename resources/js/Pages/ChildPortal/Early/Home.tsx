import { useChildIdentity } from '@/Child/useChildIdentity';
import { AssignmentSummary } from '@/Child/types';
import Icon from '@/Components/Icon';
import { Head, router } from '@inertiajs/react';
import EarlyLayout from './Layout';

/**
 * One big button, one activity at a time — oldest first. More than one
 * activity may exist; the adult/child just sees the next one.
 */
export default function Home({ assignments }: { assignments: AssignmentSummary[] }) {
    const { displayName } = useChildIdentity();
    const next = assignments[0];

    const open = (assignment: AssignmentSummary) => {
        if (assignment.in_progress_attempt_id) {
            router.get(`/crianca/tentativas/${assignment.in_progress_attempt_id}`);
        } else {
            router.post(`/crianca/atribuicoes/${assignment.id}/iniciar`);
        }
    };

    return (
        <EarlyLayout mascot={next ? 'explain' : 'waiting'}>
            <Head title="As minhas atividades" />

            <h1 className="text-3xl font-extrabold text-ink">Olá, {displayName}!</h1>

            {next ? (
                <>
                    <p className="mt-2 text-xl text-ink-muted">Vamos brincar?</p>
                    <button
                        onClick={() => open(next)}
                        className="mt-8 flex min-h-[4.5rem] w-full items-center justify-center gap-3 rounded-shell bg-accent px-6 py-4 text-2xl font-extrabold text-accent-ink shadow-lift transition active:scale-[0.98] motion-reduce:transition-none"
                    >
                        {next.title}
                        <Icon name="arrowRight" size={28} />
                    </button>
                </>
            ) : (
                <p className="mt-4 max-w-xs text-xl text-ink-muted">Ainda não há nada novo. Volta mais tarde!</p>
            )}
        </EarlyLayout>
    );
}
