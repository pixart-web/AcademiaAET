export type ResponseType =
    | 'single_choice'
    | 'multiple_choice'
    | 'short_text'
    | 'drawing'
    | 'voice_recording'
    | 'video_recording'
    | 'completion_confirmation';

export type VisualExperience = '3-6' | '7-13' | '14-18';

export type MediaKind = 'image' | 'audio' | 'video' | 'document';

/**
 * AET-RC01 finding 5: carries the media's type explicitly — a signed URL
 * has no file extension, so MediaPreview can no longer guess kind from
 * the URL the way it used to.
 */
export interface MediaPayload {
    url: string;
    kind: MediaKind;
    mime_type: string;
    alt_text: string | null;
    transcript: string | null;
}

export interface Step {
    id: number;
    position: number;
    title: string | null;
    body: string | null;
    response_type: ResponseType;
    // Never carries `correct` — the server strips it before it reaches a
    // child (see ActivityStep::childSafeResponseConfig()).
    response_config: { options?: string[]; required?: boolean; max_length?: number } | null;
    required: boolean;
    instruction_media: MediaPayload | null;
    answered: boolean;
    value: unknown;
    response_media_url: string | null;
}

export interface AssignmentSummary {
    id: number;
    status: string;
    title: string;
    category: string | null;
    in_progress_attempt_id: number | null;
}

export interface CompletedAssignmentSummary {
    id: number;
    status: string;
    title: string;
    has_feedback: boolean;
    evaluated: boolean;
}

export interface AchievementSummary {
    type: 'participation' | 'effort' | 'milestone';
    count: number;
    points: number;
}
