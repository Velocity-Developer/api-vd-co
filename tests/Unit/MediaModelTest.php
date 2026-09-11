<?php

use App\Models\Media;
use App\Models\MediaCategory;
use App\Models\MediaTag;

test('media models define expected relation methods', function (): void {
    expect(get_class_methods(Media::class))->toContain('createFromUpload', 'deleteFile', 'mediable', 'categories', 'tags')
        ->and(get_class_methods(MediaCategory::class))->toContain('media', 'parent', 'children')
        ->and(get_class_methods(MediaTag::class))->toContain('media');
});
