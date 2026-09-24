import Mascot from '@/Components/Mascot';
import { useChildIdentity } from '@/Child/useChildIdentity';
import { AssignmentSummary } from '@/Child/types';
import { Head, router } from '@inertiajs/react';
import EarlyLayout from './Layout';

/**
 * One big card, one activity at a time — the adult picks by tapping the
 * single visible option; if there is more than one activity we still show
 * just one at a time to keep the choice simple, oldest first.
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
        <EarlyLayout>
            <Head title="As minhas atividades" />
            <Mascot state={next ? 'explain' : 'waiting'} size={140} />

            <h1 className="mt-6 text-3xl font-bold text-ink">Olá, {displayName}!</h1>

            {next ? (
                <>
                    <p className="mt-2 text-xl text-ink-muted">Vamos brincar?</p>
                    <button
                        onClick={() => open(next)}
                        className="mt-8 w-full max-w-xs rounded-shell bg-accent px-8 py-6 text-2xl font-bold text-accent-ink shadow-sm"
                    >
                        {next.title}
                    </button>
                </>
            ) : (
                <p className="mt-4 max-w-xs text-xl text-ink-muted">Ainda não há nada novo. Volta mais tarde!</p>
            )}
        </EarlyLayout>
    );
}
