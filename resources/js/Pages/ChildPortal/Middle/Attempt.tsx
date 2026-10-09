import SpeakButton from '@/Child/SpeakButton';
import StepInput, { MediaPreview } from '@/Child/StepInput';
import { useAttempt } from '@/Child/useAttempt';
import { Step } from '@/Child/types';
import Icon from '@/Components/Icon';
import MiddleLayout from './Layout';

export default function Attempt({ attempt, steps }: { attempt: { id: number }; steps: Step[] }) {
    const { step, index, isLast, saving, error, saveValue, saveFile, goNext, goBack } = useAttempt(attempt.id, steps);
    const instructionText = [step.title, step.body].filter(Boolean).join('. ');

    return (
        <MiddleLayout compact>
            <div className="mx-auto max-w-xl">
                <div className="mb-2 flex items-center justify-between text-sm text-ink-muted">
                    <span className="font-bold">Passo {index + 1} de {steps.length}</span>
                    {index > 0 && (
                        <button onClick={goBack} className="inline-flex items-center gap-1 font-bold text-accent hover:underline">
                            Voltar
                        </button>
                    )}
                </div>
                <div className="mb-6 flex gap-1.5" role="progressbar" aria-valuenow={index + 1} aria-valuemin={1} aria-valuemax={steps.length}>
                    {steps.map((s, i) => (
                        <span key={s.id} className={`h-1.5 flex-1 rounded-full ${i <= index ? 'bg-accent' : 'bg-bg-alt'}`} />
                    ))}
                </div>

                <div className="rounded-shell bg-bg/50 p-5 text-center sm:p-8">
                    {step.title && <h1 className="text-2xl font-extrabold text-ink">{step.title}</h1>}
                    {step.body && <p className="mt-2 text-ink-muted">{step.body}</p>}

                    <div className="mt-3 flex justify-center">
                        <SpeakButton text={instructionText} label="Ouvir" />
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
                    className="mt-6 flex h-14 w-full items-center justify-center gap-2 rounded-shell bg-accent text-lg font-extrabold text-accent-ink shadow-lift disabled:opacity-60"
                >
                    {isLast ? 'Concluir missão' : 'Seguinte'}
                    <Icon name={isLast ? 'check' : 'arrowRight'} size={22} />
                </button>
            </div>
        </MiddleLayout>
    );
}
