<?php

namespace App\Services\Screening;

use App\Mail\ScreeningReportMail;
use App\Models\ScreeningSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ScreeningReportService
{
    public function pdf(ScreeningSession $session): string
    {
        $session->loadMissing('organization', 'conductedBy');

        return Pdf::loadView('screening.report', [
            'session' => $session,
            'result' => $session->result,
            'clinic' => $session->organization->name,
            'ageLabel' => $session->ageLabel(),
            'norms' => config('screening.phonemes'),
        ])->setPaper('a4')->output();
    }

    /**
     * Builds the PDF, stores it privately and mails it to the guardian and to
     * the clinic. Each step records its own outcome; a failure never rolls
     * back or hides the completed screening.
     */
    public function generateAndSend(ScreeningSession $session): void
    {
        $session->loadMissing('organization');

        try {
            $bytes = $this->pdf($session);
            $path = "screening-reports/{$session->organization_id}/{$session->id}.pdf";
            Storage::disk('local')->put($path, $bytes);
            $session->update(['report_path' => $path, 'report_error' => null]);
        } catch (Throwable $e) {
            report($e);
            $session->update(['report_error' => 'Falha a gerar o PDF.']);

            return;
        }

        $this->send($session, $bytes);
    }

    public function resend(ScreeningSession $session): void
    {
        $bytes = Storage::disk('local')->exists((string) $session->report_path)
            ? Storage::disk('local')->get($session->report_path)
            : $this->pdf($session);

        $this->send($session, $bytes);
    }

    private function send(ScreeningSession $session, string $bytes): void
    {
        $clinic = $session->organization->name;
        $errors = [];

        try {
            Mail::to($session->guardian_email)->send(new ScreeningReportMail($session, $bytes, $clinic));
            $session->update(['report_guardian_sent_at' => now()]);
        } catch (Throwable $e) {
            report($e);
            $errors[] = 'Falha a enviar o email ao encarregado de educação.';
        }

        $clinicEmail = $session->organization->settings['screening_clinic_email'] ?? config('screening.clinic_email');

        if ($clinicEmail) {
            try {
                Mail::to($clinicEmail)->send(new ScreeningReportMail($session, $bytes, $clinic, forClinic: true));
                $session->update(['report_clinic_sent_at' => now()]);
            } catch (Throwable $e) {
                report($e);
                $errors[] = 'Falha a enviar o email à clínica.';
            }
        } else {
            $errors[] = 'Sem email da clínica configurado — o relatório ficou só guardado na base de dados.';
        }

        $session->update(['report_error' => $errors === [] ? null : implode(' ', $errors)]);
    }
}
