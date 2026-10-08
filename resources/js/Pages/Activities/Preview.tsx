import Mascot from '@/Components/Mascot';
import SpeakButton from '@/Child/SpeakButton';
import StepInput, { MediaPreview } from '@/Child/StepInput';
import { Step, VisualExperience } from '@/Child/types';
import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const SHELL_TABS: { value: VisualExperience; label: string }[] = [
    { value: '3-6', label: '3–6 anos' },
    { value: '7-13', label: '7–13 anos' },
    { value: '14-18', label: '14–18 anos' },
];

const SHELL_KEY: Record<VisualExperience, string> = { '3-6': 'early', '7-13': 'middle', '14-18': 'teen' };

/**
 * Read-only-ish preview: interacting with a step updates local state only —
 * nothing here is ever sent to the server, so a professional can check how
 * an activity reads in each shell before publishing it. No Attempt exists.
 */
export default function Preview({ activity, steps: initialSteps }: { activity: { id: number; title: string }; steps: Step[] }) {
    const [shell, setShell] = useState<VisualExperience>('7-13');
    const [steps, setSteps] = useState(initialSteps);
    const [index, setIndex] = useState(0);

    useEffect(() => {
        document.documentElement.dataset.shell = SHELL_KEY[shell];
        return () => {
            document.documentElement.dataset.shell = 'professional';
        };
    }, [shell]);

    if (initialSteps.length === 0) {
        return (
            <ProfessionalLayout title={`Pré-visualizar — ${activity.title}`}>
                <Head title="Pré-visualizar atividade" />
                <p className="text-ink-muted">Esta atividade ainda não tem passos guardados.</p>
            </ProfessionalLayout>
        );
    }

    const step = steps[index];
    const isLast = index === steps.length - 1;

    const setLocalValue = (value: string | boolean | string[]) => {
        setSteps((prev) => prev.map((s, i) => (i === index ? { ...s, answered: true, value } : s)));
    };

    const setLocalFile = () => {
        setSteps((prev) => prev.map((s, i) => (i === index ? { ...s, answered: true, value: 'preview' } : s)));
    };

    return (
        <ProfessionalLayout
            title={`Pré-visualizar — ${activity.title}`}
            description="As respostas nesta pré-visualização não são guardadas."
            actions={<Link href={route('activities.edit', activity.id)} className="text-sm text-ink-muted underline">← Voltar ao editor</Link>}
        >
            <Head title="Pré-visualizar atividade" />

            <div className="mb-4 flex gap-2">
                {SHELL_TABS.map((tab) => (
                    <button
                        key={tab.value}
                        onClick={() => { setShell(tab.value); setIndex(0); setSteps(initialSteps); }}
                        className={`rounded-shell border px-3 py-1.5 text-sm ${shell === tab.value ? 'border-accent bg-accent-soft text-accent' : 'border-border bg-surface text-ink-muted'}`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            <div className="rounded-shell border-2 border-dashed border-border bg-bg p-6" data-shell={SHELL_KEY[shell]}>
                <div className="mx-auto max-w-md rounded-shell border border-border bg-surface p-6 text-center">
                    <Mascot state="explain" size={shell === '3-6' ? 96 : 64} className="mx-auto" />

                    {step.title && <h2 className="mt-3 text-lg font-semibold text-ink">{step.title}</h2>}
                    {step.body && <p className="mt-2 text-ink-muted">{step.body}</p>}

                    <div className="mt-2 flex justify-center">
                        <SpeakButton text={[step.title, step.body].filter(Boolean).join('. ')} />
                    </div>

                    {step.instruction_media && <MediaPreview media={step.instruction_media} />}

                    <div className="mt-4">
                        <StepInput step={step} saving={false} onValue={setLocalValue} onFile={setLocalFile} />
                    </div>
                </div>

                <div className="mx-auto mt-4 flex max-w-md items-center justify-between text-sm text-ink-muted">
                    <button disabled={index === 0} onClick={() => setIndex((i) => Math.max(0, i - 1))} className="underline disabled:opacity-40">
                        Anterior
                    </button>
                    <span>Passo {index + 1} de {steps.length}</span>
                    <button
                        disabled={isLast}
                        onClick={() => setIndex((i) => Math.min(steps.length - 1, i + 1))}
                        className="underline disabled:opacity-40"
                    >
                        Seguinte
                    </button>
                </div>
            </div>
        </ProfessionalLayout>
    );
}
