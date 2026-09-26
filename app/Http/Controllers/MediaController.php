<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\MediaCategory;
use App\Models\MediaTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    /**
     * Display a listing of the uploaded media.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['image', 'video', 'document'])],
            'category' => ['nullable', 'string', 'regex:/^(none|\d+)$/'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim($validated['search'] ?? '');

        return MediaResource::collection(
            Media::query()
                ->with(['creator:id,name', 'categories:id,name,slug', 'tags:id,name,slug'])
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('original_name', 'like', "%{$search}%")
                            ->orWhere('file_name', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%")
                            ->orWhere('alt_text', 'like', "%{$search}%");
                    });
                })
                ->when($validated['type'] ?? null, function (Builder $query, string $type): void {
                    if ($type === 'document') {
                        $query->where('mime_type', 'not like', 'image/%')
                            ->where('mime_type', 'not like', 'video/%');

                        return;
                    }

                    $query->where('mime_type', 'like', "{$type}/%");
                })
                ->when($validated['category'] ?? null, function (Builder $query, string $category): void {
                    if ($category === 'none') {
                        $query->doesntHave('categories');

                        return;
                    }

                    $mediaCategory = MediaCategory::find((int) $category);
                    $categoryIds = $mediaCategory ? [$mediaCategory->id, ...$mediaCategory->descendantIds()] : [];

                    $query->whereHas('categories', fn (Builder $query) => $query->whereKey($categoryIds));
                })
                ->latest()
                ->latest('id')
                ->paginate($validated['per_page'] ?? 24)
                ->withQueryString(),
        );
    }

    /**
     * Store newly uploaded files in the media library.
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $media = collect($request->file('files'))->map(
            fn (UploadedFile $file): Media => Media::createFromUpload($file, [
                'created_by' => $request->user()->id,
                'metadata' => $this->imageMetadata($file),
            ]),
        );

        return MediaResource::collection($media->each->load('creator:id,name'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the editable details of the media.
     */
    public function update(UpdateMediaRequest $request, Media $media): MediaResource
    {
        $validated = $request->validated();

        $media->update(Arr::only($validated, ['title', 'caption']));

        if (array_key_exists('tags', $validated)) {
            $media->tags()->sync($this->tagIds($validated['tags'] ?? []));
        }

        if (array_key_exists('category_ids', $validated)) {
            $media->categories()->sync($validated['category_ids'] ?? []);
        }

        return MediaResource::make($media->load(['creator:id,name', 'categories:id,name,slug', 'tags:id,name,slug']));
    }

    /**
     * Remove the media file from storage.
     */
    public function destroy(Media $media): Response
    {
        $media->deleteFile();

        return response()->noContent();
    }

    /**
     * Find or create media tags by name and return their ids.
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    private function tagIds(array $names): array
    {
        return collect($names)
            ->map(fn (string $name): string => Str::squish($name))
            ->filter(fn (string $name): bool => Str::slug($name) !== '')
            ->unique(fn (string $name): string => Str::slug($name))
            ->map(fn (string $name): int => MediaTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )->id)
            ->values()
            ->all();
    }

    /**
     * Read the pixel dimensions of an uploaded image.
     *
     * @return array{width: int, height: int}|null
     */
    private function imageMetadata(UploadedFile $file): ?array
    {
        if (! str_starts_with((string) $file->getMimeType(), 'image/')) {
            return null;
        }

        $size = @getimagesize($file->getRealPath());

        if ($size === false) {
            return null;
        }

        return ['width' => $size[0], 'height' => $size[1]];
    }
}
