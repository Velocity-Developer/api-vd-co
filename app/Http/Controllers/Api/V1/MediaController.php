<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\MediaCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    /**
     * List uploaded media (paginated), newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            ...$this->filterRules(),
            'type' => ['nullable', Rule::in(['image', 'video', 'document'])],
        ]);

        return $this->mediaResponse($validated, $validated['type'] ?? null);
    }

    /**
     * List uploaded images only (paginated), newest first.
     */
    public function gallery(Request $request): JsonResponse
    {
        return $this->mediaResponse($request->validate($this->filterRules()), 'image');
    }

    /**
     * Query filters shared by the media and gallery endpoints.
     *
     * @return array<string, array<int, mixed>>
     */
    private function filterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:191'],
            'tag' => ['nullable', 'string', 'max:191'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @param  array{search?: string|null, category?: string|null, tag?: string|null, per_page?: int|string|null}  $filters
     */
    private function mediaResponse(array $filters, ?string $type): JsonResponse
    {
        $search = trim($filters['search'] ?? '');

        $media = Media::query()
            ->with(['categories:id,name,slug', 'tags:id,name,slug'])
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->when($type, fn (Builder $query, string $type) => $query->ofType($type))
            ->when($filters['category'] ?? null, fn (Builder $query, string $slug) => $query->inCategory(
                MediaCategory::firstWhere('slug', $slug),
            ))
            ->when($filters['tag'] ?? null, fn (Builder $query, string $slug) => $query->withTag($slug))
            ->latest()
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => MediaResource::collection($media),
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
            ],
        ]);
    }
}
