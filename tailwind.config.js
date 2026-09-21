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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
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
                warning: 'var(--color-warning)',
                danger: 'var(--color-danger)',
            },
            borderRadius: {
                shell: 'var(--shell-radius)',
            },
        },
    },

    plugins: [forms],
};
