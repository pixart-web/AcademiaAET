import Icon from '@/Components/Icon';
import SpeakButton from '@/Child/SpeakButton';
import StepInput, { MediaPreview } from '@/Child/StepInput';
import { useAttempt } from '@/Child/useAttempt';
import { Step } from '@/Child/types';
import EarlyLayout from './Layout';

/**
 * Exactly one task fills the screen. No step counter, no "passo 2 de 5" —
 * that's reading load a 3–6 year old doesn't need. Repeating the
 * instruction is always free (SpeakButton has no attempt limit).
 */
export default function Attempt({ attempt, steps }: { attempt: { id: number }; steps: Step[] }) {
    const { step, isLast, saving, error, saveValue, saveFile, goNext } = useAttempt(attempt.id, steps);
    const instructionText = [step.title, step.body].filter(Boolean).join('. ');

    return (
        <EarlyLayout mascot="explain">
            {step.title && <h1 className="text-2xl font-extrabold text-ink">{step.title}</h1>}
            {step.body && <p className="mt-2 text-lg text-ink-muted">{step.body}</p>}

            <div className="mt-5 flex justify-center gap-2">
                <SpeakButton text={instructionText} label="Ouvir" variant="big" />
            </div>

            {step.instruction_media && <MediaPreview media={step.instruction_media} />}

            {error && (
                <p role="alert" className="mt-4 rounded-shell bg-danger-soft px-4 py-2 text-base text-danger">
                    {error}
                </p>
            )}

            <div className="mt-6 w-full max-w-xs">
                <StepInput
                    key={step.id}
                    step={step}
                    saving={saving}
                    onValue={saveValue}
                    onFile={saveFile}
                    buttonClassName="w-full rounded-shell border-2 px-6 py-5 text-xl font-semibold"
                />
            </div>

            <button
                onClick={goNext}
                disabled={saving}
                className="mt-8 flex min-h-[4rem] w-full max-w-xs items-center justify-center gap-2 rounded-shell bg-accent px-8 py-4 text-xl font-extrabold text-accent-ink shadow-lift disabled:opacity-60"
            >
                {isLast ? 'Terminar' : 'Continuar'}
                <Icon name={isLast ? 'check' : 'arrowRight'} size={26} />
            </button>
        </EarlyLayout>
    );
}
