<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public static function createFromUpload(UploadedFile $file, array $attributes = []): self
    {
        $directory = 'media/'.now()->format('Y/m');
        $disk = $attributes['disk'] ?? 'public';
        $path = $file->store($directory, $disk);

        return static::create(array_merge($attributes, [
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'extension' => $file->extension(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]));
    }

    public function deleteFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
        $this->delete();
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(MediaCategory::class, 'media_media_category')->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MediaTag::class, 'media_media_tag')->withTimestamps();
    }
}
