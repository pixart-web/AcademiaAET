<?php

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAssignmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Assignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $child = $this->assignment->childProfile;

        // Operational only: no responses, scores, or clinical content ever go
        // in an email — see spec Module K.
        return (new MailMessage)
            ->subject('Nova atividade atribuída')
            ->line('Foi atribuída uma nova atividade a '.($child->preferred_name ?: $child->first_name).'.')
            ->action('Aceder à Academia AET', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'assignment_created',
            'assignment_id' => $this->assignment->id,
            'child_profile_id' => $this->assignment->child_profile_id,
        ];
    }
}
