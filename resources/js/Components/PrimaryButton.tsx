import { ButtonHTMLAttributes } from 'react';

export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={
                `inline-flex h-11 items-center rounded-shell bg-accent px-5 text-sm font-bold text-accent-ink shadow-soft transition hover:brightness-110 disabled:opacity-60 ${disabled ? 'opacity-60' : ''} ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
