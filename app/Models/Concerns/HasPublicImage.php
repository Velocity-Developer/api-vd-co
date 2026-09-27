<?php

namespace App\Models\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An `image` column holding either a path on the public disk or an external URL.
 */
trait HasPublicImage
{
    /**
     * Folder on the public disk that uploaded pictures of this model go into.
     */
    abstract protected static function imageDirectory(): string;

    /**
     * Whether the image points at a file on the public disk rather than an external URL.
     */
    public static function isStoredImage(?string $image): bool
    {
        return filled($image) && ! Str::startsWith($image, ['http://', 'https://', '//']);
    }

    /**
     * Full URL for a stored path; external URLs are returned as is.
     */
    public static function publicImageUrl(?string $image): ?string
    {
        if (blank($image)) {
            return null;
        }

        return static::isStoredImage($image) ? Storage::disk('public')->url($image) : $image;
    }

    /**
     * Columns holding pictures; override when a model has more than `image`.
     *
     * @return list<string>
     */
    protected static function imageColumns(): array
    {
        return ['image'];
    }

    /**
     * Store an uploaded picture and return its path on the public disk.
     */
    public static function storeImage(UploadedFile $file, ?string $subfolder = null): string
    {
        $directory = collect([static::imageDirectory(), $subfolder, now()->format('Y/m')])->filter()->implode('/');

        return $file->store($directory, 'public');
    }

    /**
     * Delete a stored picture file, leaving external URLs alone.
     */
    public static function deleteStoredImage(?string $image): void
    {
        if (static::isStoredImage($image)) {
            Storage::disk('public')->delete($image);
        }
    }

    /**
     * Swap a picture column for an upload, or clear it, and delete the file it replaced.
     * Pictures in columns other than `image` go into a subfolder named after the column.
     */
    public function replaceImage(?UploadedFile $file, bool $remove = false, string $column = 'image'): void
    {
        if (! $file && ! $remove) {
            return;
        }

        $previousImage = $this->{$column};
        $subfolder = $column === 'image' ? null : Str::plural($column);

        $this->update([$column => $file ? static::storeImage($file, $subfolder) : null]);

        static::deleteStoredImage($previousImage);
    }

    /**
     * Delete the model together with all of its stored pictures.
     */
    public function deleteWithImage(): void
    {
        $this->delete();

        foreach (static::imageColumns() as $column) {
            static::deleteStoredImage($this->{$column});
        }
    }
}
