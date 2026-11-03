<?php

namespace App\Mail;

use App\Models\ScreeningSession;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The body deliberately carries no result and no clinical detail — only the
 * attached PDF does — so a preview in a mail client or a forwarded notification
 * never exposes a child's screening outcome.
 */
class ScreeningReportMail extends Mailable
{
    public function __construct(
        public readonly ScreeningSession $session,
        public readonly string $pdfBytes,
        public readonly string $clinicName,
        public readonly bool $forClinic = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->forClinic
                ? "Rastreio concluído — {$this->session->child_name}"
                : "Relatório do rastreio de linguagem e fala — {$this->session->child_name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.screening-report', with: ['forClinic' => $this->forClinic]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBytes, 'relatorio-rastreio.pdf')->withMime('application/pdf'),
        ];
    }
}
