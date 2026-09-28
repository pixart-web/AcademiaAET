import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface StepForm {
    title: string;
    body: string;
    response_type: string;
    response_config: { options?: string[]; correct?: string };
}

interface ActivityData {
    id: number;
    title: string;
    description: string | null;
    category: string | null;
    area: string | null;
    difficulty: string | null;
    status: string;
    current_version: {
        instructions: string | null;
        evaluation_criteria: string | null;
        steps: {
            title: string | null;
            body: string | null;
            response_type: string;
            response_config: { options?: string[]; correct?: string } | null;
        }[];
    };
}

const RESPONSE_TYPE_LABEL: Record<string, string> = {
    single_choice: 'Escolha única',
    multiple_choice: 'Escolha múltipla',
    short_text: 'Texto curto',
    drawing: 'Desenho',
    voice_recording: 'Gravação de voz',
    video_recording: 'Gravação de vídeo',
    completion_confirmation: 'Confirmação de realização',
};

const emptyStep = (): StepForm => ({ title: '', body: '', response_type: 'single_choice', response_config: { options: ['', ''] } });

export default function Edit({ activity, responseTypes }: { activity: ActivityData | null; responseTypes: { value: string; label: string }[] }) {
    const version = activity?.current_version;

    const { data, setData, post, put, processing, errors } = useForm({
        title: activity?.title ?? '',
        description: activity?.description ?? '',
        category: activity?.category ?? '',
        area: activity?.area ?? '',
        difficulty: activity?.difficulty ?? '',
        instructions: version?.instructions ?? '',
        evaluation_criteria: version?.evaluation_criteria ?? '',
        steps: (version?.steps.map((s) => ({
            title: s.title ?? '',
            body: s.body ?? '',
            response_type: s.response_type,
            response_config: s.response_config ?? {},
        })) ?? [emptyStep()]) as StepForm[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (activity) {
            put(route('activities.update', activity.id));
        } else {
            post(route('activities.store'));
        }
    };

    const updateStep = (index: number, patch: Partial<StepForm>) => {
        const steps = [...data.steps];
        steps[index] = { ...steps[index], ...patch };
        setData('steps', steps);
    };

    const addStep = () => setData('steps', [...data.steps, emptyStep()]);
    const removeStep = (index: number) => setData('steps', data.steps.filter((_, i) => i !== index));

    return (
        <ProfessionalLayout title={activity ? 'Editar atividade' : 'Nova atividade'}>
            <Head title={activity ? 'Editar atividade' : 'Nova atividade'} />

            {activity && (
                <div className="mb-4 flex gap-3">
                    {activity.status !== 'published' && (
                        <button
                            onClick={() => router.post(route('activities.publish', activity.id))}
                            className="rounded-shell bg-accent px-3 py-1.5 text-sm text-accent-ink"
                        >
                            Publicar
                        </button>
                    )}
                    {activity.status !== 'archived' && (
                        <button
                            onClick={() => router.post(route('activities.archive', activity.id))}
                            className="rounded-shell border border-border px-3 py-1.5 text-sm text-ink"
                        >
                            Arquivar
                        </button>
                    )}
                    <Link
                        href={route('activities.preview', activity.id)}
                        className="rounded-shell border border-border px-3 py-1.5 text-sm text-ink"
                    >
                        Pré-visualizar
                    </Link>
                    <button
                        onClick={() => router.post(route('activities.duplicate', activity.id))}
                        className="rounded-shell border border-border px-3 py-1.5 text-sm text-ink"
                    >
                        Duplicar
                    </button>
                </div>
            )}

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <div className="grid gap-4 rounded-shell border border-border bg-surface p-5 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <label className="block text-sm font-medium text-ink">Título</label>
                        <input
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                        />
                        {errors.title && <p className="mt-1 text-sm text-danger">{errors.title}</p>}
                    </div>

                    <div className="sm:col-span-2">
                        <label className="block text-sm font-medium text-ink">Descrição</label>
                        <textarea
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={2}
                            className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-ink">Categoria</label>
                        <input
                            value={data.category}
                            onChange={(e) => setData('category', e.target.value)}
                            className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-ink">Área</label>
                        <input
                            value={data.area}
                            onChange={(e) => setData('area', e.target.value)}
                            className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-ink">Dificuldade</label>
                        <select
                            value={data.difficulty}
                            onChange={(e) => setData('difficulty', e.target.value)}
                            className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                        >
                            <option value="">—</option>
                            <option value="facil">Fácil</option>
                            <option value="medio">Médio</option>
                            <option value="dificil">Difícil</option>
                        </select>
                    </div>

                    <div className="sm:col-span-2">
                        <label className="block text-sm font-medium text-ink">Instruções gerais</label>
                        <textarea
                            value={data.instructions}
                            onChange={(e) => setData('instructions', e.target.value)}
                            rows={2}
                            className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                        />
                    </div>

                    <div className="sm:col-span-2">
                        <label className="block text-sm font-medium text-ink">Critérios de avaliação</label>
                        <textarea
                            value={data.evaluation_criteria}
                            onChange={(e) => setData('evaluation_criteria', e.target.value)}
                            rows={2}
                            className="mt-1 block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                        />
                    </div>
                </div>

                <div className="space-y-4">
                    <h2 className="font-semibold text-ink">Passos</h2>

                    {data.steps.map((step, index) => (
                        <div key={index} className="rounded-shell border border-border bg-surface p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <span className="text-sm font-medium text-ink-muted">Passo {index + 1}</span>
                                {data.steps.length > 1 && (
                                    <button type="button" onClick={() => removeStep(index)} className="text-sm text-danger">
                                        Remover
                                    </button>
                                )}
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <input
                                    placeholder="Título do passo"
                                    value={step.title}
                                    onChange={(e) => updateStep(index, { title: e.target.value })}
                                    className="rounded-shell border-border focus:border-accent focus:ring-accent"
                                />
                                <select
                                    value={step.response_type}
                                    onChange={(e) => updateStep(index, { response_type: e.target.value, response_config: {} })}
                                    className="rounded-shell border-border focus:border-accent focus:ring-accent"
                                >
                                    {responseTypes.map((rt) => (
                                        <option key={rt.value} value={rt.value}>{RESPONSE_TYPE_LABEL[rt.value] ?? rt.label}</option>
                                    ))}
                                </select>
                                <textarea
                                    placeholder="Instrução para a criança/jovem"
                                    value={step.body}
                                    onChange={(e) => updateStep(index, { body: e.target.value })}
                                    className="rounded-shell border-border sm:col-span-2 focus:border-accent focus:ring-accent"
                                    rows={2}
                                />
                            </div>

                            {(step.response_type === 'single_choice' || step.response_type === 'multiple_choice') && (
                                <div className="mt-3 space-y-2">
                                    <p className="text-sm text-ink-muted">Opções (defina a correta apenas para escolha única)</p>
                                    {(step.response_config.options ?? []).map((option, optIndex) => (
                                        <div key={optIndex} className="flex items-center gap-2">
                                            <input
                                                value={option}
                                                onChange={(e) => {
                                                    const options = [...(step.response_config.options ?? [])];
                                                    options[optIndex] = e.target.value;
                                                    updateStep(index, { response_config: { ...step.response_config, options } });
                                                }}
                                                className="flex-1 rounded-shell border-border focus:border-accent focus:ring-accent"
                                            />
                                            {step.response_type === 'single_choice' && (
                                                <label className="flex items-center gap-1 text-sm text-ink-muted">
                                                    <input
                                                        type="radio"
                                                        name={`correct-${index}`}
                                                        checked={step.response_config.correct === option}
                                                        onChange={() => updateStep(index, { response_config: { ...step.response_config, correct: option } })}
                                                    />
                                                    correta
                                                </label>
                                            )}
                                        </div>
                                    ))}
                                    <button
                                        type="button"
                                        onClick={() => updateStep(index, {
                                            response_config: { ...step.response_config, options: [...(step.response_config.options ?? []), ''] },
                                        })}
                                        className="text-sm text-accent"
                                    >
                                        + adicionar opção
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}

                    <button type="button" onClick={addStep} className="w-full rounded-shell border border-dashed border-border py-3 text-sm text-ink-muted hover:text-ink">
                        + Adicionar passo
                    </button>
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-shell bg-accent px-5 py-2.5 text-sm font-medium text-accent-ink disabled:opacity-60"
                >
                    Guardar
                </button>
            </form>
        </ProfessionalLayout>
    );
}
