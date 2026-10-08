import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

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
                bg: 'var(--color-bg)',
                surface: 'var(--color-surface)',
                ink: 'var(--color-ink)',
                'ink-muted': 'var(--color-ink-muted)',
                border: 'var(--color-border)',
                accent: {
                    DEFAULT: 'var(--color-accent)',
                    ink: 'var(--color-accent-ink)',
                    soft: 'var(--color-accent-soft)',
                },
                'bg-alt': 'var(--color-bg-alt)',
                highlight: {
                    DEFAULT: 'var(--color-highlight)',
                    ink: 'var(--color-highlight-ink)',
                },
                success: { DEFAULT: 'var(--color-success)', soft: 'var(--color-success-soft)' },
                warning: { DEFAULT: 'var(--color-warning)', soft: 'var(--color-warning-soft)' },
                danger: { DEFAULT: 'var(--color-danger)', soft: 'var(--color-danger-soft)' },
                info: { DEFAULT: 'var(--color-info)', soft: 'var(--color-info-soft)' },
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
