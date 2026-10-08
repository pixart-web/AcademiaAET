import SpeakButton from '@/Child/SpeakButton';
import StepInput, { MediaPreview } from '@/Child/StepInput';
import { useAttempt } from '@/Child/useAttempt';
import { Step } from '@/Child/types';
import TeenLayout from './Layout';

export default function Attempt({ attempt, steps }: { attempt: { id: number }; steps: Step[] }) {
    const { step, index, isLast, saving, error, saveValue, saveFile, goNext, goBack } = useAttempt(attempt.id, steps);
    const instructionText = [step.title, step.body].filter(Boolean).join('. ');

    return (
        <TeenLayout>
            <div className="mb-6 flex items-center justify-between text-xs text-ink-muted">
                <span>{index + 1} / {steps.length}</span>
                {index > 0 && (
                    <button onClick={goBack} className="underline">
                        Voltar
                    </button>
                )}
            </div>

            {step.title && <h1 className="text-lg font-semibold">{step.title}</h1>}
            {step.body && <p className="mt-2 text-sm text-ink-muted">{step.body}</p>}

            <div className="mt-3">
                <SpeakButton text={instructionText} label="Ouvir instrução" />
            </div>

            {step.instruction_media && <MediaPreview media={step.instruction_media} />}

            {error && (
                <p role="alert" className="mt-4 rounded-shell bg-danger-soft px-3 py-2 text-sm text-danger">
                    {error}
                </p>
            )}

            <div className="mt-6">
                <StepInput
                    key={step.id}
                    step={step}
                    saving={saving}
                    onValue={saveValue}
                    onFile={saveFile}
                    buttonClassName="rounded-shell border px-4 py-3 text-left text-base"
                />
            </div>

            <button
                onClick={goNext}
                disabled={saving}
                className="mt-8 w-full rounded-shell bg-accent py-3 text-sm font-medium text-accent-ink disabled:opacity-60"
            >
                {isLast ? 'Concluir' : 'Seguinte'}
            </button>
        </TeenLayout>
    );
}
