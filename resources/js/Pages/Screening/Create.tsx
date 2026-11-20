import InputError from '@/Components/InputError';
import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, ReactNode } from 'react';

const BLOCKS: [number, number, string][] = [
    [36, 47, '3 anos'],
    [48, 59, '4 anos'],
    [60, 71, '5 anos'],
    [72, 95, '6–7 anos'],
];

/** Client-side PREVIEW only — the server recalculates age and block and is the source of truth. */
function preview(value: string): string | null {
    const m = value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!m) return null;
    const [d, mo, y] = [Number(m[1]), Number(m[2]), Number(m[3])];
    const now = new Date();
    let months = (now.getFullYear() - y) * 12 + (now.getMonth() + 1 - mo);
    if (now.getDate() < d) months--;
    if (months < 0) return null;
    const label = `${Math.floor(months / 12)} anos e ${months % 12} meses`;
    const block = BLOCKS.find(([min, max]) => months >= min && months <= max);
    return block ? `Idade: ${label} → Bloco de ${block[2]}` : `Idade: ${label} — fora da faixa dos 3 aos 7 anos`;
}

function Field({ label, error, children, hint }: { label: string; error?: string; children: ReactNode; hint?: string }) {
    return (
        <label className="block">
            <span className="block text-sm font-semibold text-ink">{label}</span>
            {children}
            {hint && <span className="mt-1 block text-xs text-ink-muted">{hint}</span>}
            <InputError message={error} className="mt-1" />
        </label>
    );
}

const input = 'mt-1 block w-full rounded-shell border-border bg-surface focus:border-accent focus:ring-accent';

export default function Create({ institutions }: { institutions: string[] }) {
    const { data, setData, post, processing, errors } = useForm({
        institution_name: '',
        child_name: '',
        birth_date: '',
        gender: '',
        guardian_name: '',
        guardian_email: '',
        guardian_phone: '',
        consent: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('screenings.store'));
    };

    const age = preview(data.birth_date);

    return (
        <ProfessionalLayout title="Novo rastreio" description="Registo inicial da criança e do encarregado de educação.">
            <Head title="Novo rastreio" />

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <section className="space-y-4 rounded-shell border border-border bg-surface p-5 shadow-soft">
                    <h2 className="font-display text-xl">Escola / instituição / localidade</h2>
                    <Field label="Nome da escola, colégio ou localidade" error={errors.institution_name}>
                        <input list="instituicoes" value={data.institution_name} onChange={(e) => setData('institution_name', e.target.value)} className={input} required />
                        <datalist id="instituicoes">
                            {institutions.map((i) => (
                                <option key={i} value={i} />
                            ))}
                        </datalist>
                    </Field>
                </section>

                <section className="space-y-4 rounded-shell border border-border bg-surface p-5 shadow-soft">
                    <h2 className="font-display text-xl">Criança</h2>
                    <Field label="Nome completo" error={errors.child_name}>
                        <input value={data.child_name} onChange={(e) => setData('child_name', e.target.value)} className={input} required />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Data de nascimento" error={errors.birth_date} hint="Formato DD/MM/AAAA">
                            <input inputMode="numeric" placeholder="DD/MM/AAAA" maxLength={10} value={data.birth_date} onChange={(e) => setData('birth_date', e.target.value)} className={input} required />
                        </Field>
                        <Field label="Género" error={errors.gender}>
                            <select value={data.gender} onChange={(e) => setData('gender', e.target.value)} className={input} required>
                                <option value="">—</option>
                                <option value="feminino">Feminino</option>
                                <option value="masculino">Masculino</option>
                                <option value="outro">Outro / prefere não indicar</option>
                            </select>
                        </Field>
                    </div>
                    {age && (
                        <p className="rounded-shell bg-accent-soft px-3 py-2 text-sm font-semibold text-accent" role="status">
                            {age}
                        </p>
                    )}
                </section>

                <section className="space-y-4 rounded-shell border border-border bg-surface p-5 shadow-soft">
                    <h2 className="font-display text-xl">Encarregado de educação</h2>
                    <Field label="Nome" error={errors.guardian_name}>
                        <input value={data.guardian_name} onChange={(e) => setData('guardian_name', e.target.value)} className={input} required />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="E-mail" error={errors.guardian_email} hint="Para onde será enviado o relatório.">
                            <input type="email" value={data.guardian_email} onChange={(e) => setData('guardian_email', e.target.value)} className={input} required />
                        </Field>
                        <Field label="Telefone" error={errors.guardian_phone} hint="Para posterior contacto da clínica.">
                            <input type="tel" value={data.guardian_phone} onChange={(e) => setData('guardian_phone', e.target.value)} className={input} required />
                        </Field>
                    </div>
                    <label className="flex items-start gap-3 text-sm">
                        <input type="checkbox" checked={data.consent} onChange={(e) => setData('consent', e.target.checked)} className="mt-0.5 rounded border-border text-accent focus:ring-accent" />
                        <span>Confirmo que o encarregado de educação autorizou o rastreio e o envio do relatório para o e-mail indicado.</span>
                    </label>
                    <InputError message={errors.consent} />
                </section>

                <button type="submit" disabled={processing} className="h-12 rounded-shell bg-accent px-8 font-bold text-accent-ink shadow-soft hover:brightness-110 disabled:opacity-60">
                    Iniciar rastreio
                </button>
            </form>
        </ProfessionalLayout>
    );
}
