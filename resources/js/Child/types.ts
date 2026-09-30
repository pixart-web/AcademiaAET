export type ResponseType =
    | 'single_choice'
    | 'multiple_choice'
    | 'short_text'
    | 'drawing'
    | 'voice_recording'
    | 'video_recording'
    | 'completion_confirmation';

export type VisualExperience = '3-6' | '7-13' | '14-18';

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
    instruction_media_url: string | null;
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
