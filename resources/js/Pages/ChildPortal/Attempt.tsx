import { useChildIdentity } from '@/Child/useChildIdentity';
import { Step } from '@/Child/types';
import EarlyAttempt from './Early/Attempt';
import MiddleAttempt from './Middle/Attempt';
import TeenAttempt from './Teen/Attempt';

export default function Attempt(props: { attempt: { id: number; status: string; attempt_number: number }; steps: Step[] }) {
    const { child } = useChildIdentity();

    if (child.visual_experience === '3-6') {
        return <EarlyAttempt {...props} />;
    }

    if (child.visual_experience === '14-18') {
        return <TeenAttempt {...props} />;
    }

    return <MiddleAttempt {...props} />;
}
