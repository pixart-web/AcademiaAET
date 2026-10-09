import { SVGProps } from 'react';

/**
 * One consistent functional icon set: 24px grid, 1.7 stroke, round caps —
 * original paths (no third-party icon licence to track). Decorative by
 * default; pass `label` when an icon is the only content of a control.
 */
const PATHS = {
    home: 'M4 11 12 4l8 7M6 10v9h12v-9M10 19v-5h4v5',
    users: 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.5M17.5 14a5 5 0 0 1 3 5',
    grid: 'M5 5h5v5H5zM14 5h5v5h-5zM5 14h5v5H5zM14 14h5v5h-5z',
    check: 'M5 12.5 10 17l9-10',
    checkCircle: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM8 12.5l3 3 5-6',
    folder: 'M3.5 7.5a2 2 0 0 1 2-2h4l2 2h7a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z',
    search: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14ZM20 20l-4-4',
    bell: 'M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15zM10 20a2 2 0 0 0 4 0',
    plus: 'M12 5v14M5 12h14',
    arrowRight: 'M5 12h14M13 6l6 6-6 6',
    chevronRight: 'm9 6 6 6-6 6',
    shield: 'M12 3 5 6v5c0 4.5 3 8 7 10 4-2 7-5.5 7-10V6z',
    users2: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.500 20a7.500 7.500 0 0 1 15 0',
    sun: 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M5.6 18.4 7 17M17 7l1.4-1.4',
    speaker: 'M4 9.500v5h4l5 4v-13l-5 4zM16.500 9a4 4 0 0 1 0 6M18.8 6.500a7.500 7.500 0 0 1 0 11',
    logout: 'M10 5H6a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h4M15 8l4 4-4 4M19 12H9',
    menu: 'M4 7h16M4 12h16M4 17h16',
    close: 'M6 6l12 12M18 6 6 18',
    list: 'M8 7h11M8 12h11M8 17h11M4.500 7h.01M4.500 12h.01M4.500 17h.01',
    user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 20a7 7 0 0 1 14 0',
    chat: 'M5 5h14a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1h-8l-4.500 3.500V16H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z',
    leaf: 'M5 19C4 9 10 4 20 4c0 10-5 16-15 15ZM5 19c3-5 6-8 11-11',
    star: 'm12 4 2.4 5 5.4.7-4 3.8 1 5.4-4.8-2.7-4.8 2.7 1-5.4-4-3.8 5.4-.7z',
    clock: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM12 7v5l3 2',
    flag: 'M6 21V4M6 5h11l-2 4 2 4H6',
    shieldCheck: 'M12 3 5 6v5c0 4.500 3 8 7 10 4-2 7-5.5 7-10V6zM9 12l2.2 2.2L15.5 10',
    settings: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19 12a7 7 0 0 0-.1-1.2l2-1.500-2-3.4-2.300.9a7 7 0 0 0-2-1.2L14.2 3h-4l-.4 2.6a7 7 0 0 0-2 1.2l-2.300-.9-2 3.4 2 1.500A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.500 2 3.4 2.300-.9a7 7 0 0 0 2 1.2l.4 2.6h4l.4-2.6a7 7 0 0 0 2-1.2l2.300.9 2-3.4-2-1.500c.1-.4.1-.8.1-1.2Z',
} as const;

export type IconName = keyof typeof PATHS;

export default function Icon({
    name,
    size = 20,
    label,
    ...rest
}: { name: IconName; size?: number; label?: string } & Omit<SVGProps<SVGSVGElement>, 'name'>) {
    return (
        <svg
            viewBox="0 0 24 24"
            width={size}
            height={size}
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
            role={label ? 'img' : undefined}
            aria-label={label}
            aria-hidden={label ? undefined : true}
            focusable="false"
            {...rest}
        >
            <path d={PATHS[name]} />
        </svg>
    );
}
