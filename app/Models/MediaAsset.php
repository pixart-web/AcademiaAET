<?php

namespace App\Models;

use App\Enums\MediaKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id', 'uploaded_by_user_id', 'disk', 'path', 'mime_type', 'kind',
    'title', 'description', 'alt_text', 'transcript', 'duration_seconds', 'size_bytes',
    'status', 'license', 'attribution',
])]
class MediaAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function hasTextAlternative(): bool
    {
        return filled($this->alt_text) || filled($this->transcript);
    }
}
