import Squirrel, { SquirrelState } from './art/Squirrel';

/**
 * The shells' single entry point to the character, so state names and the
 * celebratory motion live in one place. Motion respects reduced-motion via
 * Tailwind's motion-safe variant plus the global rule in app.css.
 */
export default function Mascot({
    state = 'welcome',
    size = 96,
    className = '',
}: {
    state?: SquirrelState;
    size?: number;
    className?: string;
}) {
    const motion = state === 'celebrate' ? 'motion-safe:animate-bounce' : '';

    return <Squirrel state={state} size={size} className={`${motion} ${className}`} />;
}
