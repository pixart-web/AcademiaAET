<?php

namespace App\Notifications;

use App\Models\Evaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EvaluationAvailableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Evaluation $evaluation) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $child = $this->evaluation->attempt->assignment->childProfile;

        return (new MailMessage)
            ->subject('Nova avaliação disponível')
            ->line('Está disponível feedback sobre uma atividade de '.($child->preferred_name ?: $child->first_name).'.')
            ->action('Aceder à Academia AET', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'evaluation_available',
            'evaluation_id' => $this->evaluation->id,
        ];
    }
}
