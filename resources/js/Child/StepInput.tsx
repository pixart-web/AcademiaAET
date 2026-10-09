import { useEffect, useRef, useState } from 'react';
import { MediaPayload, Step } from './types';

/**
 * AET-RC01 finding 5: renders strictly by the explicit `kind` the server
 * sends — never by sniffing the URL, which for a signed media.show URL
 * has no file extension to sniff in the first place. Also the only place
 * that previously had no video case at all.
 */
export function MediaPreview({ media }: { media: MediaPayload }) {
    const altText = media.alt_text ?? undefined;

    return (
        <div className="mt-4">
            {media.kind === 'audio' && <audio controls src={media.url} className="mx-auto w-full max-w-xs" />}
            {media.kind === 'video' && <video controls src={media.url} className="mx-auto max-h-64 max-w-full rounded-shell" />}
            {media.kind === 'image' && <img src={media.url} alt={altText ?? ''} className="mx-auto max-h-64 rounded-shell" />}
            {(media.alt_text || media.transcript) && (
                <p className="mt-2 text-sm text-ink-muted">{media.transcript ?? media.alt_text}</p>
            )}
        </div>
    );
}

/**
 * Recording formats a browser actually supports vary (Chrome/Firefox
 * favour webm, Safari only ever offered mp4) — tried in preference order,
 * first match wins. The chosen mime type also picks the file extension, so
 * the two can never disagree the way a hardcoded ".webm" could.
 */
const RECORDING_MIME_CANDIDATES: Record<'audio' | 'video', { mime: string; extension: string }[]> = {
    audio: [
        { mime: 'audio/webm', extension: 'webm' },
        { mime: 'audio/mp4', extension: 'm4a' },
        { mime: 'audio/ogg', extension: 'ogg' },
    ],
    video: [
        { mime: 'video/webm', extension: 'webm' },
        { mime: 'video/mp4', extension: 'mp4' },
    ],
};

function pickRecorderFormat(kind: 'audio' | 'video'): { mime: string; extension: string } | null {
    if (typeof MediaRecorder === 'undefined') return null;
    return RECORDING_MIME_CANDIDATES[kind].find((c) => MediaRecorder.isTypeSupported(c.mime)) ?? null;
}

/**
 * The seven response types from the spec, shared verbatim by all three
 * child shells — only surrounding chrome (StepInput's callers) differs.
 *
 * AET-RC01 finding 7: callers must render this with `key={step.id}` (see
 * the ChildPortal/*\/Attempt.tsx pages) — without it, React reuses this
 * component instance across steps instead of remounting it, which is what
 * let text and recording-widget state leak from one step into the next.
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
                        disabled={saving}
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
                    saving={saving}
                    recorded={step.answered}
                    savedMediaUrl={step.response_media_url}
                />
            );

        case 'video_recording':
            return (
                <RecordingInput
                    kind="video"
                    onFile={onFile}
                    saving={saving}
                    recorded={step.answered}
                    savedMediaUrl={step.response_media_url}
                />
            );

        case 'drawing':
            return <DrawingInput onFile={onFile} saving={saving} savedMediaUrl={step.response_media_url} />;

        default:
            return null;
    }
}

function RecordingInput({
    kind,
    onFile,
    saving,
    recorded,
    savedMediaUrl,
}: {
    kind: 'audio' | 'video';
    onFile: (blob: Blob, filename: string) => void;
    saving: boolean;
    recorded: boolean;
    savedMediaUrl: string | null;
}) {
    const [status, setStatus] = useState<'idle' | 'requesting' | 'recording' | 'preview' | 'error'>('idle');
    const [error, setError] = useState<string | null>(null);
    const [url, setUrl] = useState<string | null>(null);
    const [sent, setSent] = useState(false);
    const mediaRef = useRef<MediaRecorder | null>(null);
    const chunksRef = useRef<Blob[]>([]);
    const streamRef = useRef<MediaStream | null>(null);
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const blobRef = useRef<Blob | null>(null);
    const extensionRef = useRef<string>(kind === 'audio' ? 'webm' : 'webm');
    // Guards against a getUserMedia() promise resolving after the child has
    // already moved on to the next step (or gone back) — this component
    // has unmounted by then, and setState on it would both warn and leak
    // the still-open stream, since the unmount cleanup effect below only
    // sees whatever streamRef held *before* the async call finished.
    const mountedRef = useRef(true);

    useEffect(() => {
        mountedRef.current = true;

        return () => {
            mountedRef.current = false;
            streamRef.current?.getTracks().forEach((t) => t.stop());
            if (mediaRef.current && mediaRef.current.state !== 'inactive') {
                mediaRef.current.stop();
            }
            if (url) {
                URL.revokeObjectURL(url);
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const start = async () => {
        setStatus('requesting');
        setError(null);
        try {
            const constraints = kind === 'audio' ? { audio: true } : { audio: true, video: true };
            const stream = await navigator.mediaDevices.getUserMedia(constraints);

            if (!mountedRef.current) {
                // The child already left this step while the permission
                // prompt was open — don't leave the mic/camera running for
                // a component that no longer exists.
                stream.getTracks().forEach((t) => t.stop());
                return;
            }

            streamRef.current = stream;
            if (kind === 'video' && videoRef.current) {
                videoRef.current.srcObject = stream;
                videoRef.current.play();
            }
            chunksRef.current = [];

            const format = pickRecorderFormat(kind);
            extensionRef.current = format?.extension ?? 'webm';
            const recorder = format ? new MediaRecorder(stream, { mimeType: format.mime }) : new MediaRecorder(stream);

            recorder.ondataavailable = (e) => chunksRef.current.push(e.data);
            recorder.onstop = () => {
                const blob = new Blob(chunksRef.current, { type: recorder.mimeType });
                blobRef.current = blob;
                stream.getTracks().forEach((t) => t.stop());
                streamRef.current = null;

                if (!mountedRef.current) return;

                setUrl((previous) => {
                    if (previous) URL.revokeObjectURL(previous);
                    return URL.createObjectURL(blob);
                });
                setStatus('preview');
            };
            recorder.start();
            mediaRef.current = recorder;
            setStatus('recording');
        } catch {
            if (!mountedRef.current) return;
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
        if (url) URL.revokeObjectURL(url);
        setUrl(null);
        setStatus('idle');
        blobRef.current = null;
    };

    const send = () => {
        // Guards against a double-tap firing two uploads for one recording
        // before `saving` (a prop, one render behind) has had a chance to
        // disable this button.
        if (blobRef.current && !sent) {
            setSent(true);
            onFile(blobRef.current, `gravacao.${extensionRef.current}`);
        }
    };

    const disabled = saving || sent;

    return (
        <div className="space-y-3">
            {status === 'recording' && (
                <p className="flex items-center justify-center gap-2 text-danger" role="status">
                    <span className="h-2 w-2 animate-pulse rounded-full bg-danger motion-reduce:animate-none" aria-hidden="true" /> A gravar…
                </p>
            )}

            {status === 'error' && error && (
                <p role="alert" className="rounded-shell bg-danger-soft px-3 py-2 text-sm text-danger">
                    {error}
                </p>
            )}

            {kind === 'video' && status !== 'preview' && <video ref={videoRef} muted className="mx-auto max-h-56 rounded-shell" />}
            {status === 'preview' && url && kind === 'audio' && <audio controls src={url} className="mx-auto w-full max-w-xs" />}
            {status === 'preview' && url && kind === 'video' && <video controls src={url} className="mx-auto max-h-56 rounded-shell" />}
            {status === 'idle' && recorded && savedMediaUrl && kind === 'audio' && (
                <audio controls src={savedMediaUrl} className="mx-auto w-full max-w-xs" />
            )}
            {status === 'idle' && recorded && savedMediaUrl && kind === 'video' && (
                <video controls src={savedMediaUrl} className="mx-auto max-h-56 rounded-shell" />
            )}

            <div className="flex justify-center gap-3">
                {(status === 'idle' || status === 'error') && !recorded && (
                    <button type="button" disabled={saving} onClick={start} className="rounded-shell bg-accent px-5 py-2 text-accent-ink disabled:opacity-60">
                        Gravar
                    </button>
                )}
                {status === 'requesting' && <p className="text-ink-muted">A pedir permissão…</p>}
                {status === 'recording' && (
                    <button type="button" onClick={stop} className="rounded-shell bg-danger px-5 py-2 text-white">
                        Parar
                    </button>
                )}
                {status === 'preview' && !(sent && recorded) && (
                    <>
                        <button type="button" disabled={disabled} onClick={discard} className="rounded-shell border border-border px-4 py-2 disabled:opacity-60">
                            Repetir
                        </button>
                        <button type="button" disabled={disabled} onClick={send} className="rounded-shell bg-accent px-4 py-2 text-accent-ink disabled:opacity-60">
                            Enviar
                        </button>
                    </>
                )}
                {recorded && (status === 'idle' || sent) && (
                    <p className="inline-flex items-center gap-1.5 font-bold text-success" role="status">
                        Gravação enviada ✓
                    </p>
                )}
            </div>
        </div>
    );
}

function DrawingInput({
    onFile,
    saving,
    savedMediaUrl,
}: {
    onFile: (blob: Blob, filename: string) => void;
    saving: boolean;
    savedMediaUrl: string | null;
}) {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const drawing = useRef(false);
    const [sent, setSent] = useState(false);

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
        if (sent) return;
        canvasRef.current?.toBlob((blob) => {
            if (!blob) return;
            setSent(true);
            onFile(blob, 'desenho.png');
        }, 'image/png');
    };

    const disabled = saving || sent;

    return (
        <div className="space-y-3">
            {savedMediaUrl && (
                <div>
                    <p className="text-sm text-ink-muted">O teu desenho anterior:</p>
                    <img src={savedMediaUrl} alt="Desenho guardado anteriormente" className="mx-auto max-h-56 rounded-shell border border-border" />
                </div>
            )}
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
                <button type="button" disabled={disabled} onClick={clear} className="rounded-shell border border-border px-4 py-2 disabled:opacity-60">
                    Limpar
                </button>
                <button type="button" disabled={disabled} onClick={send} className="rounded-shell bg-accent px-4 py-2 text-accent-ink disabled:opacity-60">
                    Guardar desenho
                </button>
            </div>
        </div>
    );
}
