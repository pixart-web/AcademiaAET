/**
 * Proposed wordmark + botanical symbol for the prototype (approved by the
 * project owner as a visual proposal — NOT a registered trademark and NOT the
 * clinic's official logo). To swap in an official logo, replace this one file:
 * every shell imports <Logo/> from here.
 */
export function LogoMark({ size = 32, className = '' }: { size?: number; className?: string }) {
    return (
        <svg viewBox="0 0 40 40" width={size} height={size} aria-hidden="true" focusable="false" className={className}>
            <path d="M20 37 C20 28 20 22 20 16" stroke="#174e45" strokeWidth="2.4" strokeLinecap="round" fill="none" />
            <path d="M20 26 C10 26 5 19 6 11 C15 11 21 17 20 26 Z" fill="#8fa994" />
            <path d="M20 22 C29 22 34 16 33 8 C25 8 19 14 20 22 Z" fill="#2c7352" />
            <path d="M20 16 C15 13 14 7 17 2 C22 5 23 11 20 16 Z" fill="#ecaf76" />
        </svg>
    );
}

export default function Logo({ size = 'md', className = '' }: { size?: 'sm' | 'md' | 'lg'; className?: string }) {
    const scale = { sm: ['text-lg', 28], md: ['text-xl', 34], lg: ['text-3xl', 48] }[size] as [string, number];

    return (
        <span className={`inline-flex items-center gap-2 ${className}`}>
            <LogoMark size={scale[1]} />
            <span className={`font-display ${scale[0]} leading-none text-accent`}>
                academia <span className="font-bold tracking-wide">AET</span>
            </span>
        </span>
    );
}
