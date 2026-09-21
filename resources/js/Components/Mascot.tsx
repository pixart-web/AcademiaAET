/**
 * Provisional mascot for Academia AET — an original, simple character used
 * across the child shells. Never renders copy of its own about health or
 * responses; callers always supply the words.
 */
type MascotState = 'welcome' | 'explain' | 'waiting' | 'celebrate';

const EYE_STATES: Record<MascotState, { ry: number }> = {
    welcome: { ry: 6 },
    explain: { ry: 6 },
    waiting: { ry: 3 },
    celebrate: { ry: 2 },
};

export default function Mascot({
    state = 'welcome',
    size = 96,
    className = '',
}: {
    state?: MascotState;
    size?: number;
    className?: string;
}) {
    const eye = EYE_STATES[state];
    const bounce = state === 'celebrate' ? 'motion-safe:animate-bounce' : '';
    const sway = state === 'waiting' ? 'motion-safe:animate-pulse' : '';

    return (
        <svg
            viewBox="0 0 120 120"
            width={size}
            height={size}
            role="img"
            aria-hidden="true"
            className={`${bounce} ${sway} ${className}`}
        >
            <ellipse cx="60" cy="108" rx="28" ry="6" fill="var(--color-ink)" opacity="0.08" />
            <circle cx="60" cy="62" r="42" fill="var(--color-accent-soft)" stroke="var(--color-accent)" strokeWidth="3" />
            <circle cx="44" cy="58" r={eye.ry} fill="var(--color-ink)" />
            <circle cx="76" cy="58" r={eye.ry} fill="var(--color-ink)" />
            {state === 'celebrate' ? (
                <path d="M44 76 Q60 92 76 76" stroke="var(--color-ink)" strokeWidth="4" fill="none" strokeLinecap="round" />
            ) : state === 'waiting' ? (
                <line x1="46" y1="78" x2="74" y2="78" stroke="var(--color-ink)" strokeWidth="4" strokeLinecap="round" />
            ) : (
                <path d="M46 76 Q60 86 74 76" stroke="var(--color-ink)" strokeWidth="4" fill="none" strokeLinecap="round" />
            )}
            <circle cx="30" cy="70" r="6" fill="var(--color-accent)" opacity="0.5" />
            <circle cx="90" cy="70" r="6" fill="var(--color-accent)" opacity="0.5" />
        </svg>
    );
}
