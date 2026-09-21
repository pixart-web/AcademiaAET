import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface MediaItem {
    id: number;
    title: string | null;
    kind: string;
    alt_text: string | null;
}

interface Paginated<T> {
    data: T[];
}

export default function Index({ media }: { media: Paginated<MediaItem> }) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        kind: string;
        title: string;
        alt_text: string;
        transcript: string;
        file: File | null;
    }>({ kind: 'image', title: '', alt_text: '', transcript: '', file: null });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('media.store'), { forceFormData: true, onSuccess: () => reset() });
    };

    return (
        <ProfessionalLayout title="Conteúdos">
            <Head title="Conteúdos" />

            <div className="grid gap-6 lg:grid-cols-3">
                <form onSubmit={submit} className="space-y-3 rounded-shell border border-border bg-surface p-5 lg:col-span-1">
                    <h2 className="font-semibold text-ink">Carregar novo conteúdo</h2>

                    <select
                        value={data.kind}
                        onChange={(e) => setData('kind', e.target.value)}
                        className="block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    >
                        <option value="image">Imagem</option>
                        <option value="audio">Áudio</option>
                        <option value="video">Vídeo</option>
                        <option value="document">Documento</option>
                    </select>

                    <input
                        placeholder="Título"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        className="block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    />

                    <textarea
                        placeholder="Texto alternativo (obrigatório para imagem/áudio/vídeo)"
                        value={data.alt_text}
                        onChange={(e) => setData('alt_text', e.target.value)}
                        rows={2}
                        className="block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    />
                    {errors.alt_text && <p className="text-sm text-danger">{errors.alt_text}</p>}

                    <input
                        type="file"
                        onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                        className="block w-full text-sm"
                    />

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-shell bg-accent px-4 py-2 text-sm font-medium text-accent-ink disabled:opacity-60"
                    >
                        Carregar
                    </button>
                </form>

                <div className="grid gap-3 sm:grid-cols-2 lg:col-span-2 lg:grid-cols-3">
                    {media.data.map((item) => (
                        <div key={item.id} className="rounded-shell border border-border bg-surface p-3">
                            <p className="truncate font-medium text-ink">{item.title ?? 'Sem título'}</p>
                            <p className="text-xs capitalize text-ink-muted">{item.kind}</p>
                            <button
                                onClick={() => router.delete(route('media.destroy', item.id))}
                                className="mt-2 text-xs text-danger"
                            >
                                Arquivar
                            </button>
                        </div>
                    ))}
                    {media.data.length === 0 && <p className="text-ink-muted">Ainda sem conteúdos.</p>}
                </div>
            </div>
        </ProfessionalLayout>
    );
}
