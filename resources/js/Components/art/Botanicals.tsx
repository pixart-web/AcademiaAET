/** Decorative sprigs (original vector art, see docs/assets.md). Always aria-hidden. */
export function Sprig({ className = '', size = 120, flip = false }: { className?: string; size?: number; flip?: boolean }) {
    return (
        <svg viewBox="0 0 120 160" width={size} height={(size * 160) / 120} aria-hidden="true" focusable="false" className={className} style={flip ? { transform: 'scaleX(-1)' } : undefined}>
            <path d="M60 156 C58 110 62 70 76 18" stroke="#6f8f76" strokeWidth="3" strokeLinecap="round" fill="none" />
            <path d="M64 120 C34 112 24 88 30 66 C54 70 66 92 64 120 Z" fill="#8fa994" />
            <path d="M64 96 C88 94 102 76 100 52 C78 54 64 72 64 96 Z" fill="#a9c1ad" />
            <path d="M68 66 C48 58 42 40 48 24 C66 28 74 46 68 66 Z" fill="#8fa994" opacity="0.85" />
            <path d="M74 40 C88 36 96 24 94 10 C80 12 72 24 74 40 Z" fill="#f4c6a3" />
        </svg>
    );
}

export function Leaf({ className = '', size = 48, color = '#8fa994' }: { className?: string; size?: number; color?: string }) {
    return (
        <svg viewBox="0 0 48 48" width={size} height={size} aria-hidden="true" focusable="false" className={className}>
            <path d="M8 40 C6 18 22 6 42 6 C44 28 30 42 8 40 Z" fill={color} />
            <path d="M10 38 C18 28 26 20 36 12" stroke="#ffffff" strokeOpacity="0.55" strokeWidth="2" strokeLinecap="round" fill="none" />
        </svg>
    );
}
