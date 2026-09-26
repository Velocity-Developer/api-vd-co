<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Match the file name, title or alt text.
     */
    #[Scope]
    protected function search(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search): void {
            $query->where('original_name', 'like', "%{$search}%")
                ->orWhere('file_name', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('alt_text', 'like', "%{$search}%");
        });
    }

    /**
     * Limit to images, videos or documents (anything that is neither).
     */
    #[Scope]
    protected function ofType(Builder $query, string $type): void
    {
        if ($type === 'document') {
            $query->where('mime_type', 'not like', 'image/%')
                ->where('mime_type', 'not like', 'video/%');

            return;
        }

        $query->where('mime_type', 'like', "{$type}/%");
    }

    /**
     * Limit to media in the category or any of its subcategories; no category matches nothing.
     */
    #[Scope]
    protected function inCategory(Builder $query, ?MediaCategory $category): void
    {
        $categoryIds = $category ? [$category->id, ...$category->descendantIds()] : [];

        $query->whereHas('categories', fn (Builder $query) => $query->whereKey($categoryIds));
    }

    /**
     * Limit to media with the tag slug.
     */
    #[Scope]
    protected function withTag(Builder $query, string $slug): void
    {
        $query->whereHas('tags', fn (Builder $query) => $query->where('slug', $slug));
    }
}
