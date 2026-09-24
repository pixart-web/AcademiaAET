import { useChildIdentity } from '@/Child/useChildIdentity';
import { AchievementSummary, AssignmentSummary, CompletedAssignmentSummary } from '@/Child/types';
import EarlyHome from './Early/Home';
import MiddleHome from './Middle/Home';
import TeenHome from './Teen/Home';

/**
 * Picks the shell the professional selected for this child (visual_experience,
 * stored on the profile — never inferred here, never switched by birthday).
 */
export default function Home(props: {
    assignments: AssignmentSummary[];
    completed: CompletedAssignmentSummary[];
    achievements: AchievementSummary[];
    totalPoints: number;
}) {
    const { child } = useChildIdentity();

    if (child.visual_experience === '3-6') {
        return <EarlyHome assignments={props.assignments} />;
    }

    if (child.visual_experience === '14-18') {
        return <TeenHome assignments={props.assignments} completed={props.completed} />;
    }

    return <MiddleHome {...props} />;
}
