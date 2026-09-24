import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Step } from './types';

/**
 * The one execution engine shared by all three child shells: step
 * navigation, persistence and submission. Shells differ only in how they
 * render this state — see Pages/ChildPortal/{Early,Middle,Teen}.
 */
export function useAttempt(attemptId: number, steps: Step[]) {
    const [index, setIndex] = useState(() => Math.max(0, steps.findIndex((s) => !s.answered)));
    const [saving, setSaving] = useState(false);

    const step = steps[index] ?? steps[steps.length - 1];
    const isLast = index === steps.length - 1;
    const answeredCount = useMemo(() => steps.filter((s) => s.answered).length, [steps]);

    const saveValue = (value: string | boolean | string[]) => {
        setSaving(true);
        router.post(
            `/crianca/tentativas/${attemptId}/passos/${step.id}`,
            { value },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    const saveFile = (file: Blob, filename: string) => {
        setSaving(true);
        const form = new FormData();
        form.append('file', file, filename);
        router.post(`/crianca/tentativas/${attemptId}/passos/${step.id}`, form, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    const goNext = () => {
        if (isLast) {
            router.post(`/crianca/tentativas/${attemptId}/submeter`);
        } else {
            setIndex((i) => i + 1);
        }
    };

    const goBack = () => setIndex((i) => Math.max(0, i - 1));

    return { step, index, steps, isLast, saving, answeredCount, saveValue, saveFile, goNext, goBack };
}
