<?php

namespace App\Models;

use Database\Factories\MediaTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MediaTag extends Model
{
    /** @use HasFactory<MediaTagFactory> */
    use HasFactory;

    protected $guarded = [];

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_media_tag')->withTimestamps();
    }
}
