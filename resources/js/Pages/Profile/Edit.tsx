import ProfessionalLayout from '@/Layouts/ProfessionalLayout';
import { Head } from '@inertiajs/react';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    return (
        <ProfessionalLayout title="Perfil">
            <Head title="Perfil" />

            <div className="max-w-2xl space-y-6">
                <div className="rounded-shell border border-border bg-surface p-6">
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} className="max-w-xl" />
                </div>

                <div className="rounded-shell border border-border bg-surface p-6">
                    <UpdatePasswordForm className="max-w-xl" />
                </div>
            </div>
        </ProfessionalLayout>
    );
}
