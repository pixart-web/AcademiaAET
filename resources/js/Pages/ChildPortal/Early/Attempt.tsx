import Mascot from '@/Components/Mascot';
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
    const { step, isLast, saving, saveValue, saveFile, goNext } = useAttempt(attempt.id, steps);
    const instructionText = [step.title, step.body].filter(Boolean).join('. ');

    return (
        <EarlyLayout>
            <Mascot state="explain" size={96} />

            {step.title && <h1 className="mt-4 text-2xl font-bold text-ink">{step.title}</h1>}
            {step.body && <p className="mt-2 text-lg text-ink-muted">{step.body}</p>}

            <div className="mt-3 flex justify-center gap-2">
                <SpeakButton text={instructionText} label="Ouvir de novo" />
            </div>

            {step.instruction_media_url && <MediaPreview url={step.instruction_media_url} />}

            <div className="mt-6 w-full max-w-xs">
                <StepInput
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
                className="mt-8 w-full max-w-xs rounded-shell bg-accent px-8 py-5 text-xl font-bold text-accent-ink disabled:opacity-60"
            >
                {isLast ? 'Terminar' : 'Próximo'}
            </button>
        </EarlyLayout>
    );
}
