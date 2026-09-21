import Mascot from '@/Components/Mascot';
import ChildPortalLayout from '@/Layouts/ChildPortalLayout';
import { Head, router } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';

type ResponseType =
    | 'single_choice'
    | 'multiple_choice'
    | 'short_text'
    | 'drawing'
    | 'voice_recording'
    | 'video_recording'
    | 'completion_confirmation';

interface Step {
    id: number;
    position: number;
    title: string | null;
    body: string | null;
    response_type: ResponseType;
    response_config: { options?: string[] } | null;
    instruction_media_url: string | null;
    answered: boolean;
    value: unknown;
}

export default function Attempt({
    attempt,
    steps,
}: {
    attempt: { id: number; status: string; attempt_number: number };
    steps: Step[];
}) {
    const [index, setIndex] = useState(() => Math.max(0, steps.findIndex((s) => !s.answered)));
    const [saving, setSaving] = useState(false);
    const step = steps[index] ?? steps[steps.length - 1];
    const isLast = index === steps.length - 1;

    const saveValue = (value: string | boolean | string[]) => {
        setSaving(true);
        router.post(
            `/crianca/tentativas/${attempt.id}/passos/${step.id}`,
            { value },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    const saveFile = (file: Blob) => {
        setSaving(true);
        const form = new FormData();
        form.append('file', file, 'gravacao.webm');
        router.post(`/crianca/tentativas/${attempt.id}/passos/${step.id}`, form, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    const goNext = () => {
        if (isLast) {
            router.post(`/crianca/tentativas/${attempt.id}/submeter`);
        } else {
            setIndex((i) => i + 1);
        }
    };

    return (
        <ChildPortalLayout>
            <Head title={step.title ?? 'Atividade'} />

            <div className="mx-auto max-w-xl">
                <div className="mb-6 flex items-center gap-2">
                    {steps.map((s, i) => (
                        <span
                            key={s.id}
                            className={`h-2 flex-1 rounded-full ${i <= index ? 'bg-accent' : 'bg-border'}`}
                        />
                    ))}
                </div>

                <div className="rounded-shell border border-border bg-surface p-6 text-center sm:p-10">
                    <Mascot state="explain" size={72} className="mx-auto mb-4" />

                    {step.title && <h1 className="text-xl font-semibold text-ink">{step.title}</h1>}
                    {step.body && <p className="mt-2 text-ink-muted">{step.body}</p>}

                    {step.instruction_media_url && (
                        <MediaPreview url={step.instruction_media_url} />
                    )}

                    <div className="mt-6">
                        <StepInput step={step} saving={saving} onValue={saveValue} onFile={saveFile} onNext={goNext} />
                    </div>
                </div>

                <button
                    onClick={goNext}
                    disabled={saving}
                    className="mt-6 w-full rounded-shell bg-accent py-3 text-lg font-medium text-accent-ink disabled:opacity-60"
                >
                    {isLast ? 'Concluir' : 'Seguinte'}
                </button>
            </div>
        </ChildPortalLayout>
    );
}

function MediaPreview({ url }: { url: string }) {
    const isAudio = /\.(mp3|wav|ogg|webm)(\?|$)/i.test(url) || url.includes('audio');
    return (
        <div className="mt-4">
            {isAudio ? (
                <audio controls src={url} className="mx-auto w-full max-w-xs" />
            ) : (
                <img src={url} alt="" className="mx-auto max-h-64 rounded-shell" />
            )}
        </div>
    );
}

function StepInput({
    step,
    saving,
    onValue,
    onFile,
}: {
    step: Step;
    saving: boolean;
    onValue: (v: string | boolean | string[]) => void;
    onFile: (blob: Blob) => void;
    onNext: () => void;
}) {
    const [text, setText] = useState((step.value as string) ?? '');

    switch (step.response_type) {
        case 'single_choice':
            return (
                <div className="grid gap-3">
                    {(step.response_config?.options ?? []).map((option) => (
                        <button
                            key={option}
                            disabled={saving}
                            onClick={() => onValue(option)}
                            className={`rounded-shell border px-4 py-3 text-lg ${
                                step.value === option ? 'border-accent bg-accent-soft' : 'border-border bg-surface'
                            }`}
                        >
                            {option}
                        </button>
                    ))}
                </div>
            );

        case 'multiple_choice': {
            const selected = Array.isArray(step.value) ? (step.value as string[]) : [];
            return (
                <div className="grid gap-3">
                    {(step.response_config?.options ?? []).map((option) => {
                        const active = selected.includes(option);
                        return (
                            <button
                                key={option}
                                disabled={saving}
                                onClick={() => onValue(active ? selected.filter((o) => o !== option) : [...selected, option])}
                                className={`rounded-shell border px-4 py-3 text-lg ${active ? 'border-accent bg-accent-soft' : 'border-border bg-surface'}`}
                            >
                                {option}
                            </button>
                        );
                    })}
                </div>
            );
        }

        case 'short_text':
            return (
                <div className="space-y-3 text-left">
                    <textarea
                        value={text}
                        onChange={(e) => setText(e.target.value)}
                        onBlur={() => onValue(text)}
                        rows={3}
                        className="block w-full rounded-shell border-border focus:border-accent focus:ring-accent"
                    />
                </div>
            );

        case 'completion_confirmation':
            return (
                <button
                    disabled={saving}
                    onClick={() => onValue(true)}
                    className="rounded-shell border border-accent bg-accent-soft px-6 py-3 text-lg text-accent"
                >
                    {step.value ? 'Feito ✓' : 'Marcar como feito'}
                </button>
            );

        case 'voice_recording':
            return <RecordingInput kind="audio" onFile={onFile} recorded={Boolean(step.value !== undefined && step.value !== null)} />;

        case 'video_recording':
            return <RecordingInput kind="video" onFile={onFile} recorded={Boolean(step.value !== undefined && step.value !== null)} />;

        case 'drawing':
            return <DrawingInput onFile={onFile} />;

        default:
            return null;
    }
}

function RecordingInput({ kind, onFile, recorded }: { kind: 'audio' | 'video'; onFile: (blob: Blob) => void; recorded: boolean }) {
    const [status, setStatus] = useState<'idle' | 'recording' | 'preview'>('idle');
    const [url, setUrl] = useState<string | null>(null);
    const mediaRef = useRef<MediaRecorder | null>(null);
    const chunksRef = useRef<Blob[]>([]);
    const streamRef = useRef<MediaStream | null>(null);
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const blobRef = useRef<Blob | null>(null);

    const start = async () => {
        const constraints = kind === 'audio' ? { audio: true } : { audio: true, video: true };
        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        streamRef.current = stream;
        if (kind === 'video' && videoRef.current) {
            videoRef.current.srcObject = stream;
            videoRef.current.play();
        }
        chunksRef.current = [];
        const recorder = new MediaRecorder(stream);
        recorder.ondataavailable = (e) => chunksRef.current.push(e.data);
        recorder.onstop = () => {
            const blob = new Blob(chunksRef.current, { type: recorder.mimeType });
            blobRef.current = blob;
            setUrl(URL.createObjectURL(blob));
            setStatus('preview');
            stream.getTracks().forEach((t) => t.stop());
            streamRef.current = null;
        };
        recorder.start();
        mediaRef.current = recorder;
        setStatus('recording');
    };

    const stop = () => mediaRef.current?.stop();

    const discard = () => {
        setUrl(null);
        setStatus('idle');
        blobRef.current = null;
    };

    const send = () => {
        if (blobRef.current) {
            onFile(blobRef.current);
        }
    };

    return (
        <div className="space-y-3">
            {status === 'recording' && (
                <p className="flex items-center justify-center gap-2 text-danger">
                    <span className="h-2 w-2 animate-pulse rounded-full bg-danger" /> A gravar…
                </p>
            )}

            {kind === 'video' && status !== 'preview' && <video ref={videoRef} muted className="mx-auto max-h-56 rounded-shell" />}
            {status === 'preview' && url && kind === 'audio' && <audio controls src={url} className="mx-auto w-full max-w-xs" />}
            {status === 'preview' && url && kind === 'video' && <video controls src={url} className="mx-auto max-h-56 rounded-shell" />}

            <div className="flex justify-center gap-3">
                {status === 'idle' && !recorded && (
                    <button onClick={start} className="rounded-shell bg-accent px-5 py-2 text-accent-ink">Gravar</button>
                )}
                {status === 'recording' && (
                    <button onClick={stop} className="rounded-shell bg-danger px-5 py-2 text-white">Parar</button>
                )}
                {status === 'preview' && (
                    <>
                        <button onClick={discard} className="rounded-shell border border-border px-4 py-2">Repetir</button>
                        <button onClick={send} className="rounded-shell bg-accent px-4 py-2 text-accent-ink">Enviar</button>
                    </>
                )}
                {recorded && status === 'idle' && <p className="text-ink-muted">Gravação enviada ✓</p>}
            </div>
        </div>
    );
}

function DrawingInput({ onFile }: { onFile: (blob: Blob) => void }) {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const drawing = useRef(false);

    const pos = (e: React.PointerEvent<HTMLCanvasElement>) => {
        const rect = e.currentTarget.getBoundingClientRect();
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    };

    const start = (e: React.PointerEvent<HTMLCanvasElement>) => {
        drawing.current = true;
        const ctx = canvasRef.current?.getContext('2d');
        const { x, y } = pos(e);
        ctx?.beginPath();
        ctx?.moveTo(x, y);
    };

    const move = (e: React.PointerEvent<HTMLCanvasElement>) => {
        if (!drawing.current) return;
        const ctx = canvasRef.current?.getContext('2d');
        const { x, y } = pos(e);
        if (ctx) {
            ctx.lineWidth = 4;
            ctx.lineCap = 'round';
            ctx.strokeStyle = 'var(--color-ink)';
            ctx.lineTo(x, y);
            ctx.stroke();
        }
    };

    const end = () => (drawing.current = false);

    const clear = () => {
        const canvas = canvasRef.current;
        const ctx = canvas?.getContext('2d');
        if (canvas && ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
    };

    const send = () => {
        canvasRef.current?.toBlob((blob) => blob && onFile(blob), 'image/png');
    };

    return (
        <div className="space-y-3">
            <canvas
                ref={canvasRef}
                width={320}
                height={240}
                className="mx-auto touch-none rounded-shell border border-border bg-white"
                onPointerDown={start}
                onPointerMove={move}
                onPointerUp={end}
                onPointerLeave={end}
            />
            <div className="flex justify-center gap-3">
                <button onClick={clear} className="rounded-shell border border-border px-4 py-2">Limpar</button>
                <button onClick={send} className="rounded-shell bg-accent px-4 py-2 text-accent-ink">Guardar desenho</button>
            </div>
        </div>
    );
}
