import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Token colours are CSS variables (they change per [data-shell]), which
// Tailwind can't alpha-blend on its own, so `bg-surface/90` & co. would be
// silently dropped. color-mix lets every `/NN` opacity modifier keep working.
const token = (name) => `color-mix(in srgb, var(--color-${name}) calc(<alpha-value> * 100%), transparent)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['var(--font-body)', ...defaultTheme.fontFamily.sans],
                serif: ['var(--font-serif)', ...defaultTheme.fontFamily.serif],
                display: ['var(--font-display)', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                bg: token('bg'),
                surface: token('surface'),
                ink: token('ink'),
                'ink-muted': token('ink-muted'),
                border: token('border'),
                accent: {
                    DEFAULT: token('accent'),
                    ink: token('accent-ink'),
                    soft: token('accent-soft'),
                },
                'bg-alt': token('bg-alt'),
                highlight: {
                    DEFAULT: token('highlight'),
                    ink: token('highlight-ink'),
                },
                success: { DEFAULT: token('success'), soft: token('success-soft') },
                warning: { DEFAULT: token('warning'), soft: token('warning-soft') },
                danger: { DEFAULT: token('danger'), soft: token('danger-soft') },
                info: { DEFAULT: token('info'), soft: token('info-soft') },
            },
            borderRadius: {
                shell: 'var(--shell-radius)',
            },
            boxShadow: {
                soft: 'var(--shadow-soft)',
                lift: 'var(--shadow-lift)',
            },
        },
    },

    plugins: [forms],
};
