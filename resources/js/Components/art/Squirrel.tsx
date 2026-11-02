/**
 * "Nogueira", the Academia AET squirrel — an original character drawn for
 * this project (see docs/assets.md). Colours are fixed on purpose: the
 * character must read identically in every shell, whatever the theme tokens.
 * The SVG carries no text; any words are HTML next to it.
 */
export type SquirrelState = 'welcome' | 'explain' | 'waiting' | 'celebrate';

const BODY = '#5f9c8c';
const BODY_DARK = '#477f71';
const TAIL_LIGHT = '#8cc0b0';
const BELLY = '#f6ecd6';
const INK = '#2b3a36';
const BLUSH = '#f2b49c';

export default function Squirrel({
    state = 'welcome',
    size = 160,
    className = '',
}: {
    state?: SquirrelState;
    size?: number;
    className?: string;
}) {
    const happy = state === 'celebrate';
    const sleepy = state === 'waiting';
    const armsUp = happy || state === 'explain';

    return (
        <svg viewBox="0 0 200 200" width={size} height={size} aria-hidden="true" focusable="false" className={className}>
            <ellipse cx="100" cy="188" rx="46" ry="7" fill={INK} opacity="0.1" />

            {/* tail */}
            <path d="M118 160 C176 164 196 110 170 66 C152 36 118 46 128 78 C136 102 122 118 104 126 Z" fill={BODY_DARK} />
            <path d="M126 150 C168 150 182 112 164 82 C154 66 138 70 142 88 C146 106 136 124 118 134 Z" fill={TAIL_LIGHT} />

            {/* body, belly, feet */}
            <ellipse cx="98" cy="142" rx="38" ry="44" fill={BODY} />
            <ellipse cx="98" cy="150" rx="22" ry="30" fill={BELLY} />
            <ellipse cx="78" cy="184" rx="15" ry="7" fill={BODY_DARK} />
            <ellipse cx="118" cy="184" rx="15" ry="7" fill={BODY_DARK} />

            {/* arms */}
            {armsUp ? (
                <>
                    <path d="M64 130 C46 118 42 98 52 88 C58 94 60 104 70 116 Z" fill={BODY_DARK} />
                    <path d="M132 130 C150 118 154 98 144 88 C138 94 136 104 126 116 Z" fill={BODY_DARK} />
                </>
            ) : (
                <>
                    <ellipse cx="64" cy="146" rx="9" ry="20" fill={BODY_DARK} transform="rotate(14 64 146)" />
                    <ellipse cx="132" cy="146" rx="9" ry="20" fill={BODY_DARK} transform="rotate(-14 132 146)" />
                </>
            )}

            {/* head + ears */}
            <path d="M70 70 L62 36 C74 36 86 46 90 58 Z" fill={BODY} />
            <path d="M126 70 L134 36 C122 36 110 46 106 58 Z" fill={BODY} />
            <path d="M71 62 L67 44 C74 45 80 51 83 58 Z" fill={BLUSH} />
            <path d="M125 62 L129 44 C122 45 116 51 113 58 Z" fill={BLUSH} />
            <ellipse cx="98" cy="86" rx="36" ry="32" fill={BODY} />
            <ellipse cx="98" cy="96" rx="24" ry="19" fill={BELLY} />

            {/* eyes */}
            {happy ? (
                <>
                    <path d="M76 82 Q83 74 90 82" stroke={INK} strokeWidth="4" strokeLinecap="round" fill="none" />
                    <path d="M106 82 Q113 74 120 82" stroke={INK} strokeWidth="4" strokeLinecap="round" fill="none" />
                </>
            ) : sleepy ? (
                <>
                    <path d="M76 82 Q83 87 90 82" stroke={INK} strokeWidth="4" strokeLinecap="round" fill="none" />
                    <path d="M106 82 Q113 87 120 82" stroke={INK} strokeWidth="4" strokeLinecap="round" fill="none" />
                </>
            ) : (
                <>
                    <ellipse cx="83" cy="82" rx="5" ry="6" fill={INK} />
                    <ellipse cx="113" cy="82" rx="5" ry="6" fill={INK} />
                    <circle cx="85" cy="80" r="1.8" fill="#fff" />
                    <circle cx="115" cy="80" r="1.8" fill="#fff" />
                </>
            )}

            <ellipse cx="72" cy="96" rx="7" ry="5" fill={BLUSH} opacity="0.75" />
            <ellipse cx="124" cy="96" rx="7" ry="5" fill={BLUSH} opacity="0.75" />
            <ellipse cx="98" cy="93" rx="5" ry="3.6" fill={INK} />
            {happy || state === 'explain' ? (
                <path d="M90 100 Q98 110 106 100 Q98 104 90 100 Z" fill={INK} stroke={INK} strokeWidth="2" strokeLinejoin="round" />
            ) : (
                <path d="M91 100 Q98 106 105 100" stroke={INK} strokeWidth="3" strokeLinecap="round" fill="none" />
            )}
        </svg>
    );
}
