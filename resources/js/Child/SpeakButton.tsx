import { useEffect, useState } from 'react';

/**
 * Reads instruction text aloud via the browser's built-in speech synthesis —
 * an accessible fallback when an activity has no professionally-recorded
 * instruction audio. Never used for anything clinical, only the instruction
 * text a professional wrote. Hidden entirely where the browser has no
 * speech support, and repeatable without limit or penalty.
 */
export default function SpeakButton({ text, label = 'Ouvir instrução', className = '' }: { text: string; label?: string; className?: string }) {
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

    return (
        <button
            type="button"
            onClick={speak}
            aria-label={label}
            className={`inline-flex items-center gap-2 rounded-shell border border-border bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-bg ${className}`}
        >
            <span aria-hidden="true">{speaking ? '🔊' : '🔈'}</span>
            {label}
        </button>
    );
}
