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
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['image', 'video', 'document'])],
            'category' => ['nullable', 'string', 'max:191'],
            'tag' => ['nullable', 'string', 'max:191'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim($validated['search'] ?? '');

        $media = Media::query()
            ->with(['categories:id,name,slug', 'tags:id,name,slug'])
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->when($validated['type'] ?? null, fn (Builder $query, string $type) => $query->ofType($type))
            ->when($validated['category'] ?? null, fn (Builder $query, string $slug) => $query->inCategory(
                MediaCategory::firstWhere('slug', $slug),
            ))
            ->when($validated['tag'] ?? null, fn (Builder $query, string $slug) => $query->withTag($slug))
            ->latest()
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15)
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
