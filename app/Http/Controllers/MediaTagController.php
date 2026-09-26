<?php

namespace App\Http\Controllers;

use App\Http\Requests\MediaTagRequest;
use App\Http\Resources\MediaTagResource;
use App\Models\MediaTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MediaTagController extends Controller
{
    /**
     * Display a listing of the media tags.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim($validated['search'] ?? '');

        return MediaTagResource::collection(
            MediaTag::query()
                ->withCount('media')
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->paginate()
                ->withQueryString(),
        );
    }

    /**
     * Store a newly created media tag.
     */
    public function store(MediaTagRequest $request): MediaTagResource
    {
        return MediaTagResource::make(MediaTag::create($request->validated())->loadCount('media'));
    }

    /**
     * Display the specified media tag.
     */
    public function show(MediaTag $mediaTag): MediaTagResource
    {
        return MediaTagResource::make($mediaTag->loadCount('media'));
    }

    /**
     * Update the specified media tag.
     */
    public function update(MediaTagRequest $request, MediaTag $mediaTag): MediaTagResource
    {
        $mediaTag->update($request->validated());

        return MediaTagResource::make($mediaTag->loadCount('media'));
    }

    /**
     * Remove the specified media tag; its media stay untouched.
     */
    public function destroy(MediaTag $mediaTag): Response
    {
        $mediaTag->delete();

        return response()->noContent();
    }
}
