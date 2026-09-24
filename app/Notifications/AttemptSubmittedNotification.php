<?php

namespace App\Notifications;

use App\Models\Attempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Internal-only (database channel, no mail): tells staff a response is ready
 * to review. Never carries the child's actual answers or any clinical text.
 */
class AttemptSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Attempt $attempt) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'attempt_submitted',
            'attempt_id' => $this->attempt->id,
            'child_profile_id' => $this->attempt->assignment->child_profile_id,
        ];
    }
}
