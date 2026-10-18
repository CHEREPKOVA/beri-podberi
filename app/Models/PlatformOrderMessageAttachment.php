<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PlatformOrderMessageAttachment extends Model
{
    protected $fillable = [
        'platform_order_message_id',
        'uploaded_by_user_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(PlatformOrderMessage::class, 'platform_order_message_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function fileExtension(): string
    {
        return strtoupper(pathinfo((string) $this->original_name, PATHINFO_EXTENSION));
    }

    public function isPreviewable(): bool
    {
        $mime = strtolower((string) $this->mime_type);
        $ext = strtolower($this->fileExtension());

        if (str_starts_with($mime, 'image/') || $mime === 'application/pdf') {
            return true;
        }

        return in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk('public')->exists($this->file_path);
    }
}
