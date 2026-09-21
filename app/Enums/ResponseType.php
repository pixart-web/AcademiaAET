<?php

namespace App\Enums;

enum ResponseType: string
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case ShortText = 'short_text';
    case Drawing = 'drawing';
    case VoiceRecording = 'voice_recording';
    case VideoRecording = 'video_recording';
    case CompletionConfirmation = 'completion_confirmation';

    public function isRecording(): bool
    {
        return in_array($this, [self::VoiceRecording, self::VideoRecording], true);
    }

    public function isAutoScorable(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultipleChoice], true);
    }
}
