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
    response_config: { options?: string[]; correct?: string } | null;
    instruction_media_url: string | null;
    answered: boolean;
    value: unknown;
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
