import SpeakButton from '@/Child/SpeakButton';
import StepInput, { MediaPreview } from '@/Child/StepInput';
import { useAttempt } from '@/Child/useAttempt';
import { Step } from '@/Child/types';
import MiddleLayout from './Layout';

export default function Attempt({ attempt, steps }: { attempt: { id: number }; steps: Step[] }) {
    const { step, index, isLast, saving, error, saveValue, saveFile, goNext, goBack } = useAttempt(attempt.id, steps);
    const instructionText = [step.title, step.body].filter(Boolean).join('. ');

    return (
        <MiddleLayout>
            <div className="mx-auto max-w-xl">
                <div className="mb-2 flex items-center justify-between text-sm text-ink-muted">
                    <span>Passo {index + 1} de {steps.length}</span>
                    {index > 0 && (
                        <button onClick={goBack} className="underline">
                            Voltar
                        </button>
                    )}
                </div>
                <div className="mb-6 flex gap-1.5" role="progressbar" aria-valuenow={index + 1} aria-valuemin={1} aria-valuemax={steps.length}>
                    {steps.map((s, i) => (
                        <span key={s.id} className={`h-1.5 flex-1 rounded-full ${i <= index ? 'bg-accent' : 'bg-border'}`} />
                    ))}
                </div>

                <div className="rounded-shell border border-border bg-surface p-6 text-center sm:p-8">
                    {step.title && <h1 className="text-xl font-semibold text-ink">{step.title}</h1>}
                    {step.body && <p className="mt-2 text-ink-muted">{step.body}</p>}

                    <div className="mt-3 flex justify-center">
                        <SpeakButton text={instructionText} />
                    </div>

                    {step.instruction_media && <MediaPreview media={step.instruction_media} />}

                    {error && (
                        <p role="alert" className="mt-4 rounded-shell bg-danger-soft px-3 py-2 text-sm text-danger">
                            {error}
                        </p>
                    )}

                    <div className="mt-6">
                        <StepInput key={step.id} step={step} saving={saving} onValue={saveValue} onFile={saveFile} />
                    </div>
                </div>

                <button
                    onClick={goNext}
                    disabled={saving}
                    className="mt-6 w-full rounded-shell bg-accent py-3 text-lg font-medium text-accent-ink disabled:opacity-60"
                >
                    {isLast ? 'Concluir missão' : 'Seguinte'}
                </button>
            </div>
        </MiddleLayout>
    );
}
