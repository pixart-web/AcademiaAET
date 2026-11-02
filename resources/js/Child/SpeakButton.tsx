import Icon from '@/Components/Icon';
import { useEffect, useState } from 'react';

/**
 * Reads instruction text aloud via the browser's built-in speech synthesis —
 * an accessible fallback when an activity has no professionally-recorded
 * instruction audio. Never used for anything clinical, only the instruction
 * text a professional wrote. Hidden entirely where the browser has no
 * speech support, and repeatable without limit or penalty.
 */
export default function SpeakButton({
    text,
    label = 'Ouvir instrução',
    className = '',
    variant = 'pill',
}: {
    text: string;
    label?: string;
    className?: string;
    variant?: 'pill' | 'big';
}) {
    const [supported, setSupported] = useState(false);
    const [speaking, setSpeaking] = useState(false);

    useEffect(() => {
        setSupported(typeof window !== 'undefined' && 'speechSynthesis' in window);
    }, []);

    if (!supported || !text) {
        return null;
    }

    const speak = () => {
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'pt-PT';
        utterance.onstart = () => setSpeaking(true);
        utterance.onend = () => setSpeaking(false);
        utterance.onerror = () => setSpeaking(false);
        window.speechSynthesis.speak(utterance);
    };

    if (variant === 'big') {
        return (
            <div className={`flex flex-col items-center gap-2 ${className}`}>
                <button
                    type="button"
                    onClick={speak}
                    aria-label={label}
                    className={`flex h-24 w-24 items-center justify-center rounded-full bg-highlight text-highlight-ink shadow-lift transition active:scale-95 motion-reduce:transition-none ${speaking ? 'ring-4 ring-accent/40' : ''}`}
                >
                    <Icon name="speaker" size={44} strokeWidth={2} />
                </button>
                <span className="text-lg font-bold text-highlight-ink" aria-hidden="true">
                    {label}
                </span>
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={speak}
            aria-label={label}
            className={`inline-flex items-center gap-2 rounded-full border border-border bg-surface px-4 py-2 text-sm font-semibold text-ink hover:bg-bg-alt ${className}`}
        >
            <Icon name="speaker" size={18} className={speaking ? 'text-accent' : ''} />
            {label}
        </button>
    );
}
