/**
 * Original scene illustrations (docs/assets.md). Decorative only — aria-hidden,
 * no text inside the artwork; every title/instruction is real HTML on a clean
 * surface laid over or below these bands.
 */

/** 3–6: soft forest clearing. Wide band, slices to fit any width. */
export function ForestScene({ className = '' }: { className?: string }) {
    return (
        <svg viewBox="0 0 400 220" preserveAspectRatio="xMidYMax slice" aria-hidden="true" focusable="false" className={className}>
            <rect width="400" height="220" fill="#f7f0dc" />
            <circle cx="320" cy="48" r="30" fill="#f8d9a8" opacity="0.7" />
            {/* far trees */}
            <g fill="#cfe0cb">
                <ellipse cx="40" cy="96" rx="52" ry="64" />
                <ellipse cx="150" cy="84" rx="46" ry="62" />
                <ellipse cx="270" cy="92" rx="54" ry="66" />
                <ellipse cx="372" cy="100" rx="46" ry="60" />
            </g>
            {/* mid trunks + canopy */}
            <g>
                <rect x="58" y="90" width="14" height="110" rx="6" fill="#9a7b5b" />
                <ellipse cx="65" cy="78" rx="48" ry="52" fill="#8fb39a" />
                <ellipse cx="46" cy="96" rx="30" ry="30" fill="#7da58b" />
                <rect x="318" y="84" width="16" height="116" rx="6" fill="#9a7b5b" />
                <ellipse cx="326" cy="70" rx="56" ry="56" fill="#8fb39a" />
                <ellipse cx="348" cy="94" rx="34" ry="32" fill="#7da58b" />
                <ellipse cx="300" cy="90" rx="26" ry="26" fill="#a6c6ab" />
            </g>
            {/* ground */}
            <path d="M0 180 C70 160 130 172 200 168 C270 164 330 156 400 176 L400 220 L0 220 Z" fill="#a9c8a0" />
            <path d="M0 200 C80 186 150 196 220 192 C290 188 350 190 400 200 L400 220 L0 220 Z" fill="#8fb88a" />
            {/* flowers + butterflies */}
            <g>
                <circle cx="92" cy="196" r="4" fill="#f4c6a3" />
                <circle cx="104" cy="204" r="3" fill="#ecaf76" />
                <circle cx="290" cy="200" r="4" fill="#f4c6a3" />
                <circle cx="304" cy="208" r="3" fill="#fff6e2" />
                <path d="M120 60 C112 52 108 62 120 66 C112 72 124 76 124 66 C128 74 136 68 126 62 C134 56 126 50 120 60 Z" fill="#ecaf76" />
                <path d="M236 40 C230 34 226 42 236 45 C230 50 238 53 238 46 C241 52 247 48 240 43 C246 39 240 35 236 40 Z" fill="#f4c6a3" />
            </g>
        </svg>
    );
}

/** 7–13: landscape with a stream, stepping stones, mountains and a treehouse. */
export function TrailScene({ className = '' }: { className?: string }) {
    return (
        <svg viewBox="0 0 400 260" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false" className={className}>
            <defs>
                <linearGradient id="trail-sky" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#9fd3cf" />
                    <stop offset="1" stopColor="#e9f1dc" />
                </linearGradient>
            </defs>
            <rect width="400" height="260" fill="url(#trail-sky)" />
            <path d="M0 150 L70 84 L120 130 L190 60 L270 140 L330 96 L400 150 L400 260 L0 260 Z" fill="#b6d3c0" />
            <path d="M0 170 L60 124 L130 160 L210 110 L300 168 L360 134 L400 160 L400 260 L0 260 Z" fill="#8fb39a" />
            {/* hill with tree + treehouse */}
            <path d="M200 200 C230 150 300 140 400 170 L400 260 L200 260 Z" fill="#7fae85" />
            <rect x="330" y="104" width="16" height="74" rx="6" fill="#8a6a4a" />
            <ellipse cx="338" cy="92" rx="52" ry="40" fill="#5f9a74" />
            <ellipse cx="312" cy="106" rx="28" ry="24" fill="#6fa883" />
            <g>
                <rect x="318" y="94" width="40" height="30" rx="3" fill="#c9965f" />
                <path d="M312 96 L338 74 L364 96 Z" fill="#a8602f" />
                <rect x="332" y="104" width="12" height="14" rx="2" fill="#7a4a24" />
                <path d="M338 74 L338 58" stroke="#7a4a24" strokeWidth="2" />
                <path d="M338 58 L352 63 L338 68 Z" fill="#ecaf76" />
                <path d="M322 124 L316 160 M356 124 L362 160" stroke="#8a6a4a" strokeWidth="3" />
            </g>
            {/* stream + stones */}
            <path d="M0 214 C80 196 150 220 230 206 C300 194 350 214 400 206 L400 260 L0 260 Z" fill="#a8d6d8" />
            <path d="M0 232 C90 216 160 238 240 226 C310 216 360 232 400 226 L400 260 L0 260 Z" fill="#8ec7cb" />
            <g fill="#b9b2a2">
                <ellipse cx="96" cy="228" rx="22" ry="8" />
                <ellipse cx="170" cy="222" rx="20" ry="7" />
                <ellipse cx="236" cy="230" rx="22" ry="8" />
            </g>
            {/* signposts */}
            <g transform="translate(212 -4)">
                <rect x="40" y="150" width="6" height="64" rx="2" fill="#8a6a4a" />
                <rect x="20" y="152" width="46" height="12" rx="3" fill="#d9b080" />
                <rect x="28" y="168" width="44" height="12" rx="3" fill="#e2c093" />
                <rect x="22" y="184" width="40" height="12" rx="3" fill="#d9b080" />
            </g>
            {/* reeds and flowers */}
            <g fill="#f4c6a3">
                <circle cx="14" cy="236" r="3.5" />
                <circle cx="388" cy="238" r="3.5" />
            </g>
        </svg>
    );
}
