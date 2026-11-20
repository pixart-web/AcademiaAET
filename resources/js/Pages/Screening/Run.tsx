import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Bucket {
    points: number;
    applicable: number;
    not_applied: number;
    percent: number | null;
}

interface Item {
    id: number;
    kind: 'speech' | 'comprehension' | 'naming' | 'repetition';
    prompt: string | null;
    image_url: string | null;
    options?: { index: number; label: string }[];
    evaluator?: { target_word: string | null; phoneme: string | null; expected_answer: string | null };
}

interface Finding {
    word: string;
    phoneme: string | null;
    outcome: string;
    produced_sound: string | null;
    expected_for_age: boolean;
}

interface Result {
    areas: Record<string, Bucket>;
    total: Bucket;
    findings: Finding[];
    attention: boolean;
    conclusion: string;
    guardian_sent: boolean;
    clinic_sent: boolean;
    report_error: string | null;
}

interface Props {
    session: { id: number; child_first_name: string; age: string; age_block: string; status: string };
    item: Item | null;
    progress: { done: number; total: number };
    result: Result | null;
}

const AREA_LABEL: Record<string, string> = {
    speech: 'Fala / Articulação',
    comprehension: 'Compreensão',
    expression: 'Expressão',
};

const KIND_LABEL: Record<Item['kind'], string> = {
    speech: 'Fala',
    comprehension: 'Compreensão',
    naming: 'Expressão · nomeação',
    repetition: 'Expressão · repetição de frase',
};

const OUTCOME_LABEL: Record<string, string> = {
    substitution: 'Substituição',
    omission: 'Omissão',
    distortion: 'Distorção',
};

const pct = (b: Bucket) => (b.percent === null ? '—' : `${Math.round(b.percent)}%`);

export default function Run({ session, item, progress, result }: Props) {
    const [busy, setBusy] = useState(false);
    const [producing, setProducing] = useState(false);
    const [sound, setSound] = useState('');

    const send = (payload: Record<string, unknown>) => {
        if (!item || busy) return;
        setBusy(true);
        router.post(
            route('screenings.answer', session.id),
            { item_id: item.id, ...payload },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    setProducing(false);
                    setSound('');
                },
            },
        );
    };

    const title = `Rastreio · ${session.child_first_name}`;
    const description = `${session.age} · bloco de ${session.age_block} anos`;

    if (result) {
        return (
            <ProfessionalLayout title={title} description={description}>
                <Head title={title} />
                <div className="max-w-3xl space-y-6">
                    <section className={`rounded-shell border p-5 shadow-soft ${result.attention ? 'border-warning bg-warning-soft' : 'border-success bg-success-soft'}`}>
                        <h2 className="font-display text-xl">Conclusão</h2>
                        <p className="mt-2 font-semibold">{result.conclusion}</p>
                        <p className="mt-2 text-xs text-ink-muted">Este rastreio não é um diagnóstico.</p>
                    </section>

                    <section className="rounded-shell border border-border bg-surface p-5 shadow-soft">
                        <h2 className="font-display text-xl">Percentagem de sucesso</h2>
                        <ul className="mt-4 space-y-3">
                            {Object.entries(result.areas).map(([key, b]) => (
                                <li key={key}>
                                    <div className="flex justify-between text-sm font-semibold">
                                        <span>{AREA_LABEL[key] ?? key}</span>
                                        <span>{pct(b)}</span>
                                    </div>
                                    <div className="mt-1 h-3 overflow-hidden rounded-full bg-bg-alt" role="img" aria-label={`${AREA_LABEL[key] ?? key}: ${pct(b)}`}>
                                        <div className="h-full rounded-full bg-accent" style={{ width: `${b.percent ?? 0}%` }} />
                                    </div>
                                    {b.not_applied > 0 && <p className="mt-1 text-xs text-ink-muted">{b.not_applied} item(ns) não aplicados / sem pontuação</p>}
                                </li>
                            ))}
                            <li className="border-t border-border pt-3 text-sm font-bold">Total: {pct(result.total)}</li>
                        </ul>
                    </section>

                    {result.findings.length > 0 && (
                        <section className="rounded-shell border border-border bg-surface p-5 shadow-soft">
                            <h2 className="font-display text-xl">Fonemas alterados ou omitidos</h2>
                            <ul className="mt-3 divide-y divide-border text-sm">
                                {result.findings.map((f, i) => (
                                    <li key={i} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                        <span>
                                            <strong>{f.word}</strong> · {f.phoneme ?? '—'} · {OUTCOME_LABEL[f.outcome] ?? f.outcome}
                                            {f.produced_sound ? ` (produziu ${f.produced_sound})` : ''}
                                        </span>
                                        {f.expected_for_age && <span className="rounded-full bg-warning-soft px-2.5 py-0.5 text-xs font-bold text-warning">já esperado para a idade</span>}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <section className="rounded-shell border border-border bg-surface p-5 text-sm shadow-soft">
                        <h2 className="font-display text-xl">Relatório</h2>
                        <p className="mt-2">
                            {result.guardian_sent ? 'Enviado ao encarregado de educação.' : 'Não enviado ao encarregado de educação.'}{' '}
                            {result.clinic_sent ? 'Enviado à clínica.' : 'Não enviado à clínica.'}
                        </p>
                        {result.report_error && <p className="mt-1 text-warning">{result.report_error}</p>}
                        <div className="mt-4 flex flex-wrap gap-3">
                            <a href={route('screenings.report', session.id)} className="inline-flex h-11 items-center rounded-shell bg-accent px-5 font-bold text-accent-ink shadow-soft hover:brightness-110">
                                Descarregar PDF
                            </a>
                            <button onClick={() => router.post(route('screenings.resend', session.id))} className="h-11 rounded-shell border border-border px-5 font-semibold hover:bg-bg-alt">
                                Reenviar por e-mail
                            </button>
                            <Link href={route('screenings.index')} className="inline-flex h-11 items-center px-3 font-semibold text-accent hover:underline">
                                Voltar à lista
                            </Link>
                        </div>
                    </section>
                </div>
            </ProfessionalLayout>
        );
    }

    if (!item) {
        return (
            <ProfessionalLayout title={title} description={description}>
                <Head title={title} />
                <p>A concluir o rastreio…</p>
            </ProfessionalLayout>
        );
    }

    const btn = 'min-h-12 rounded-shell border border-border bg-surface px-5 font-bold shadow-soft hover:bg-bg-alt disabled:opacity-60';

    return (
        <ProfessionalLayout title={title} description={description}>
            <Head title={title} />
            <div className="max-w-3xl space-y-4">
                <div>
                    <div className="flex justify-between text-xs font-semibold text-ink-muted">
                        <span>{KIND_LABEL[item.kind]}</span>
                        <span>
                            {progress.done} / {progress.total}
                        </span>
                    </div>
                    <div className="mt-1 h-2 overflow-hidden rounded-full bg-bg-alt" role="progressbar" aria-valuenow={progress.done} aria-valuemax={progress.total}>
                        <div className="h-full bg-accent" style={{ width: `${(progress.done / Math.max(progress.total, 1)) * 100}%` }} />
                    </div>
                </div>

                <section className="rounded-shell border border-border bg-surface p-6 text-center shadow-soft">
                    {item.image_url ? (
                        <img src={item.image_url} alt="" className="mx-auto max-h-64 rounded-shell" />
                    ) : (
                        <div className="mx-auto flex h-40 max-w-xs items-center justify-center rounded-shell border border-dashed border-border text-sm text-ink-muted">Imagem por carregar</div>
                    )}
                    {item.prompt && <p className="mt-4 font-display text-2xl">{item.prompt}</p>}

                    {item.options && (
                        <div className="mt-6 grid grid-cols-2 gap-3">
                            {item.options.map((o) => (
                                <button key={o.index} disabled={busy} onClick={() => send({ option_index: o.index })} className={`${btn} py-6 text-lg`}>
                                    {o.label}
                                </button>
                            ))}
                        </div>
                    )}
                </section>

                {item.evaluator && (
                    <section className="rounded-shell border border-border bg-bg-alt/50 p-5">
                        <h2 className="text-sm font-bold uppercase tracking-wide text-ink-muted">Avaliador (adulto)</h2>
                        <p className="mt-1 text-sm">
                            {item.evaluator.target_word && <>Palavra-alvo: <strong>{item.evaluator.target_word}</strong>. </>}
                            {item.evaluator.phoneme && <>Fonema: <strong>{item.evaluator.phoneme}</strong>. </>}
                            {item.evaluator.expected_answer && <>Resposta esperada: <strong>{item.evaluator.expected_answer}</strong>.</>}
                        </p>

                        {item.kind === 'speech' ? (
                            <div className="mt-4 space-y-3">
                                <div className="flex flex-wrap gap-3">
                                    <button disabled={busy} onClick={() => send({ outcome: 'correct' })} className={btn}>Correto</button>
                                    <button disabled={busy} onClick={() => setProducing(true)} className={btn}>Substituição</button>
                                    <button disabled={busy} onClick={() => send({ outcome: 'omission' })} className={btn}>Omissão</button>
                                    <button disabled={busy} onClick={() => send({ outcome: 'distortion' })} className={btn}>Distorção</button>
                                </div>
                                {producing && (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            if (sound.trim()) send({ outcome: 'substitution', produced_sound: sound.trim() });
                                        }}
                                        className="flex items-end gap-3"
                                    >
                                        <label className="block text-sm font-semibold">
                                            Som produzido
                                            <input autoFocus value={sound} onChange={(e) => setSound(e.target.value)} maxLength={32} className="mt-1 block w-40 rounded-shell border-border focus:border-accent focus:ring-accent" />
                                        </label>
                                        <button type="submit" disabled={busy || !sound.trim()} className={btn}>Registar</button>
                                    </form>
                                )}
                            </div>
                        ) : (
                            <div className="mt-4 flex gap-3">
                                <button disabled={busy} onClick={() => send({ outcome: 'correct' })} className={btn}>Correto</button>
                                <button disabled={busy} onClick={() => send({ outcome: 'incorrect' })} className={btn}>Incorreto</button>
                            </div>
                        )}
                    </section>
                )}
            </div>
        </ProfessionalLayout>
    );
}
