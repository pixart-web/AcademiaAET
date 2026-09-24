import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';

export function useChildIdentity() {
    const { auth } = usePage<PageProps>().props;
    const child = auth.child!;

    const logout = () => router.post('/crianca/sair');

    return { child, displayName: child.preferred_name ?? child.first_name, logout };
}
