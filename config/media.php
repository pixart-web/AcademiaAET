<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload size limits per media kind (KB)
    |--------------------------------------------------------------------------
    |
    | Enforced in MediaAssetController::store() against the Laravel "max"
    | validation rule, which operates in kilobytes. Override per environment
    | via the MEDIA_MAX_*_KB variables (e.g. a clinic wanting smaller video
    | uploads on a constrained disk) without touching application code.
    |
    */
    'max_size_kb' => [
        'image' => (int) env('MEDIA_MAX_IMAGE_KB', 8 * 1024),
        'audio' => (int) env('MEDIA_MAX_AUDIO_KB', 25 * 1024),
        'video' => (int) env('MEDIA_MAX_VIDEO_KB', 100 * 1024),
        'document' => (int) env('MEDIA_MAX_DOCUMENT_KB', 15 * 1024),
    ],

];
