/**
 * Fictional illustrated avatars. Deterministic from a numeric seed so the same
 * profile always gets the same face; they depict no real child and carry no
 * information about the person (no gender/ethnicity inference from data).
 */
const SKIN = ['#f0c7a4', '#d9a37c', '#b9805a', '#8d5a3b', '#f5d6bd'];
const HAIR = ['#2f2118', '#5a3a22', '#1f1a17', '#8a5a2b', '#3d2b20'];
const SHIRT = ['#8fa994', '#f4c6a3', '#cfe0d4', '#ecaf76', '#a9c1ad'];

export default function Avatar({ seed, size = 40, className = '' }: { seed: number; size?: number; className?: string }) {
    const skin = SKIN[seed % SKIN.length];
    const hair = HAIR[(seed * 3 + 1) % HAIR.length];
    const shirt = SHIRT[(seed * 2 + 3) % SHIRT.length];
    const style = seed % 3;

    return (
        <svg viewBox="0 0 48 48" width={size} height={size} aria-hidden="true" focusable="false" className={`rounded-full ${className}`}>
            <rect width="48" height="48" fill="#e6efe6" />
            <path d="M6 48 C8 36 16 33 24 33 C32 33 40 36 42 48 Z" fill={shirt} />
            <rect x="21" y="28" width="6" height="7" rx="3" fill={skin} />
            {style === 2 && <path d="M12 26 C8 12 18 6 24 6 C32 6 40 12 36 26 C34 20 30 16 24 16 C18 16 14 20 12 26 Z" fill={hair} />}
            <ellipse cx="24" cy="22" rx="10" ry="11" fill={skin} />
            {style === 0 && <path d="M13 22 C12 11 20 8 25 8 C33 8 37 14 35 22 C32 17 28 14 24 14 C19 14 15 17 13 22 Z" fill={hair} />}
            {style === 1 && <path d="M14 20 C16 10 32 8 35 20 C30 16 20 16 14 20 Z" fill={hair} />}
            <circle cx="20.5" cy="23" r="1.3" fill="#2b3a36" />
            <circle cx="27.5" cy="23" r="1.3" fill="#2b3a36" />
            <path d="M21 27.5 Q24 30 27 27.5" stroke="#2b3a36" strokeWidth="1.3" strokeLinecap="round" fill="none" />
        </svg>
    );
}
