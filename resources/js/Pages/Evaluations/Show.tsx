import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface StepView {
    id: number;
    title: string | null;
    response_type: string;
    value: unknown;
    is_correct: boolean | null;
    media_url: string | null;
}

interface Attempt {
    id: number;
    assignment: {
        child_profile: { first_name: string; preferred_name: string | null };
        activity_version: { activity: { title: string } };
    };
}

export default function Show({ attempt, steps }: { attempt: Attempt; steps: StepView[] }) {
    const { data, setData, post, processing } = useForm({
        score: '',
        shared_feedback: '',
        internal_note: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('evaluations.store', attempt.id));
    };

    const child = attempt.assignment.child_profile;

    return (
        <ProfessionalLayout title={`Avaliar — ${attempt.assignment.activity_version.activity.title}`}>
            <Head title="Avaliar" />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    <h2 className="font-semibold text-ink">
                        {child.preferred_name ?? child.first_name} — respostas
                    </h2>

                    {steps.map((step) => (
                        <div key={step.id} className="rounded-shell border border-border bg-surface p-4">
                            <p className="font-medium text-ink">{step.title}</p>

                            {step.media_url ? (
                                /\.(mp3|wav|ogg|webm|m4a)/.test(step.media_url) || step.response_type.includes('voice') ? (
                                    <audio controls src={step.media_url} className="mt-2 w-full" />
                                ) : (
                                    <>
                                        <img src={step.media_url} alt="Resposta" className="mt-2 max-h-56 rounded-shell" />
                                        {step.response_type === 'video_recording' && (
                                            <video controls src={step.media_url} className="mt-2 max-h-56 rounded-shell" />
                                        )}
                                    </>
                                )
                            ) : (
                                <p className="mt-1 text-ink-muted">
                                    {Array.isArray(step.value) ? step.value.join(', ') : String(step.value ?? '—')}
                                </p>
                            )}

                            {step.is_correct !== null && (
                                <p className={`mt-2 text-sm ${step.is_correct ? 'text-accent' : 'text-danger'}`}>
                                    {step.is_correct ? 'Correto (automático)' : 'Incorreto (automático)'}
                                </p>
                            )}
                        </div>
                    ))}
                </div>

                <form onSubmit={submit} className="h-fit space-y-4 rounded-shell border border-border bg-surface p-5">
                    <div>
                        <label className="block text-sm font-medium text-ink">Pontuação (0–100, opcional)</label>
                        <input
                            type="number"
                            min={0}
                            max={100}
                            value={data.score}
                            onChange={(e) => setData('score', e.target.value)}
                            className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-ink">Feedback partilhado</label>
                        <textarea
                            value={data.shared_feedback}
                            onChange={(e) => setData('shared_feedback', e.target.value)}
                            rows={4}
                            className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                            placeholder="Visível para a criança/encarregado de educação."
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-ink">Nota interna (opcional)</label>
                        <textarea
                            value={data.internal_note}
                            onChange={(e) => setData('internal_note', e.target.value)}
                            rows={4}
                            className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                            placeholder="Nunca visível fora do portal profissional."
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink disabled:opacity-60"
                    >
                        Guardar avaliação
                    </button>
                </form>
            </div>
        </ProfessionalLayout>
    );
}
