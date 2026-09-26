<?php

namespace App\Models;

use Database\Factories\MediaCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaCategory extends Model
{
    /** @use HasFactory<MediaCategoryFactory> */
    use HasFactory;

    protected $guarded = [];

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_media_category')->withTimestamps();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get the ids of every category below this one.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $descendantIds = [];
        $parentIds = [$this->id];

        while ($parentIds !== []) {
            $parentIds = static::query()
                ->whereIn('parent_id', $parentIds)
                ->whereNotIn('id', $descendantIds)
                ->pluck('id')
                ->all();

            array_push($descendantIds, ...$parentIds);
        }

        return $descendantIds;
    }
}
