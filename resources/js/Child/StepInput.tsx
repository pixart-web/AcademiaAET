import { useRef, useState } from 'react';
import { Step } from './types';

export function MediaPreview({ url }: { url: string }) {
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

/**
 * The seven response types from the spec, shared verbatim by all three
 * child shells — only surrounding chrome (StepInput's callers) differs.
 */
export default function StepInput({
    step,
    saving,
    onValue,
    onFile,
    buttonClassName = 'rounded-shell border px-4 py-3 text-lg',
    activeButtonClassName = 'border-accent bg-accent-soft',
    inactiveButtonClassName = 'border-border bg-surface',
}: {
    step: Step;
    saving: boolean;
    onValue: (v: string | boolean | string[]) => void;
    onFile: (blob: Blob, filename: string) => void;
    buttonClassName?: string;
    activeButtonClassName?: string;
    inactiveButtonClassName?: string;
}) {
    const [text, setText] = useState((step.value as string) ?? '');

    switch (step.response_type) {
        case 'single_choice':
            return (
                <div className="grid gap-3" role="radiogroup" aria-label={step.title ?? 'Escolha uma opção'}>
                    {(step.response_config?.options ?? []).map((option) => (
                        <button
                            key={option}
                            type="button"
                            role="radio"
                            aria-checked={step.value === option}
                            disabled={saving}
                            onClick={() => onValue(option)}
                            className={`${buttonClassName} ${step.value === option ? activeButtonClassName : inactiveButtonClassName}`}
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
                                type="button"
                                aria-pressed={active}
                                disabled={saving}
                                onClick={() => onValue(active ? selected.filter((o) => o !== option) : [...selected, option])}
                                className={`${buttonClassName} ${active ? activeButtonClassName : inactiveButtonClassName}`}
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
                    <label htmlFor={`step-${step.id}-text`} className="sr-only">
                        {step.title ?? 'A tua resposta'}
                    </label>
                    <textarea
                        id={`step-${step.id}-text`}
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
                    type="button"
                    disabled={saving}
                    onClick={() => onValue(true)}
                    className={`${buttonClassName} ${step.value ? activeButtonClassName : inactiveButtonClassName}`}
                >
                    {step.value ? 'Feito ✓' : 'Marcar como feito'}
                </button>
            );

        case 'voice_recording':
            return (
                <RecordingInput
                    kind="audio"
                    onFile={onFile}
                    recorded={Boolean(step.value !== undefined && step.value !== null)}
                />
            );

        case 'video_recording':
            return (
                <RecordingInput
                    kind="video"
                    onFile={onFile}
                    recorded={Boolean(step.value !== undefined && step.value !== null)}
                />
            );

        case 'drawing':
            return <DrawingInput onFile={onFile} />;

        default:
            return null;
    }
}

function RecordingInput({
    kind,
    onFile,
    recorded,
}: {
    kind: 'audio' | 'video';
    onFile: (blob: Blob, filename: string) => void;
    recorded: boolean;
}) {
    const [status, setStatus] = useState<'idle' | 'requesting' | 'recording' | 'preview' | 'error'>('idle');
    const [error, setError] = useState<string | null>(null);
    const [url, setUrl] = useState<string | null>(null);
    const mediaRef = useRef<MediaRecorder | null>(null);
    const chunksRef = useRef<Blob[]>([]);
    const streamRef = useRef<MediaStream | null>(null);
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const blobRef = useRef<Blob | null>(null);

    const start = async () => {
        setStatus('requesting');
        setError(null);
        try {
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
        } catch {
            // Permission refused or no device — a clear, actionable message,
            // never a silent dead end (spec: "permissão de microfone/câmara recusada").
            setError(
                kind === 'audio'
                    ? 'Não foi possível aceder ao microfone. Verifica as permissões do navegador e tenta novamente.'
                    : 'Não foi possível aceder à câmara. Verifica as permissões do navegador e tenta novamente.',
            );
            setStatus('error');
        }
    };

    const stop = () => mediaRef.current?.stop();

    const discard = () => {
        setUrl(null);
        setStatus('idle');
        blobRef.current = null;
    };

    const send = () => {
        if (blobRef.current) {
            onFile(blobRef.current, kind === 'audio' ? 'gravacao.webm' : 'video.webm');
        }
    };

    return (
        <div className="space-y-3">
            {status === 'recording' && (
                <p className="flex items-center justify-center gap-2 text-danger" role="status">
                    <span className="h-2 w-2 animate-pulse rounded-full bg-danger motion-reduce:animate-none" aria-hidden="true" /> A gravar…
                </p>
            )}

            {status === 'error' && error && (
                <p role="alert" className="rounded-shell bg-danger/10 px-3 py-2 text-sm text-danger">
                    {error}
                </p>
            )}

            {kind === 'video' && status !== 'preview' && <video ref={videoRef} muted className="mx-auto max-h-56 rounded-shell" />}
            {status === 'preview' && url && kind === 'audio' && <audio controls src={url} className="mx-auto w-full max-w-xs" />}
            {status === 'preview' && url && kind === 'video' && <video controls src={url} className="mx-auto max-h-56 rounded-shell" />}

            <div className="flex justify-center gap-3">
                {(status === 'idle' || status === 'error') && !recorded && (
                    <button type="button" onClick={start} className="rounded-shell bg-accent px-5 py-2 text-accent-ink">
                        Gravar
                    </button>
                )}
                {status === 'requesting' && <p className="text-ink-muted">A pedir permissão…</p>}
                {status === 'recording' && (
                    <button type="button" onClick={stop} className="rounded-shell bg-danger px-5 py-2 text-white">
                        Parar
                    </button>
                )}
                {status === 'preview' && (
                    <>
                        <button type="button" onClick={discard} className="rounded-shell border border-border px-4 py-2">
                            Repetir
                        </button>
                        <button type="button" onClick={send} className="rounded-shell bg-accent px-4 py-2 text-accent-ink">
                            Enviar
                        </button>
                    </>
                )}
                {recorded && status === 'idle' && <p className="text-ink-muted">Gravação enviada ✓</p>}
            </div>
        </div>
    );
}

function DrawingInput({ onFile }: { onFile: (blob: Blob, filename: string) => void }) {
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
        canvasRef.current?.toBlob((blob) => blob && onFile(blob, 'desenho.png'), 'image/png');
    };

    return (
        <div className="space-y-3">
            <canvas
                ref={canvasRef}
                width={320}
                height={240}
                role="img"
                aria-label="Área de desenho"
                className="mx-auto touch-none rounded-shell border border-border bg-white"
                onPointerDown={start}
                onPointerMove={move}
                onPointerUp={end}
                onPointerLeave={end}
            />
            <div className="flex justify-center gap-3">
                <button type="button" onClick={clear} className="rounded-shell border border-border px-4 py-2">
                    Limpar
                </button>
                <button type="button" onClick={send} className="rounded-shell bg-accent px-4 py-2 text-accent-ink">
                    Guardar desenho
                </button>
            </div>
        </div>
    );
}
