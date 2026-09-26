<?php

namespace App\Http\Controllers;

use App\Http\Requests\MediaCategoryRequest;
use App\Http\Resources\MediaCategoryResource;
use App\Models\MediaCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MediaCategoryController extends Controller
{
    /**
     * Display a listing of the media categories.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = MediaCategory::query()
            ->with('parent:id,name,slug')
            ->withCount('media')
            ->orderBy('name');

        return MediaCategoryResource::collection(
            $request->boolean('all') ? $query->get() : $query->paginate(),
        );
    }

    /**
     * Store a newly created media category.
     */
    public function store(MediaCategoryRequest $request): MediaCategoryResource
    {
        $category = MediaCategory::create($request->validated());

        return MediaCategoryResource::make($category->load('parent:id,name,slug')->loadCount('media'));
    }

    /**
     * Display the specified media category.
     */
    public function show(MediaCategory $mediaCategory): MediaCategoryResource
    {
        return MediaCategoryResource::make($mediaCategory->load('parent:id,name,slug')->loadCount('media'));
    }

    /**
     * Update the specified media category.
     */
    public function update(MediaCategoryRequest $request, MediaCategory $mediaCategory): MediaCategoryResource
    {
        $mediaCategory->update($request->validated());

        return MediaCategoryResource::make($mediaCategory->load('parent:id,name,slug')->loadCount('media'));
    }

    /**
     * Remove the specified media category; its media stay and its children move to the top level.
     */
    public function destroy(MediaCategory $mediaCategory): Response
    {
        $mediaCategory->delete();

        return response()->noContent();
    }
}
