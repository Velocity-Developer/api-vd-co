<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NewsController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->withCount('posts')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'count' => $category->posts_count,
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => $categories,
        ]);
    }

    public function posts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'post_per_page' => ['nullable', 'integer', 'between:1,100'],
            'skip' => ['nullable', 'integer', 'min:0'],
        ]);

        $postPerPage = $validated['post_per_page'] ?? 10;
        $skip = $validated['skip'] ?? 0;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $query = Post::query()
            ->with(['user:id,name,email', 'categories:id,name,slug', 'tags:id,name,slug'])
            ->when(
                isset($validated['category_id']),
                fn (Builder $query): Builder => $query->whereHas(
                    'categories',
                    fn (Builder $categoryQuery): Builder => $categoryQuery->whereKey($validated['category_id']),
                ),
            )
            ->latest();

        $total = (clone $query)->count();
        $posts = new LengthAwarePaginator(
            $query
                ->skip($skip + (($currentPage - 1) * $postPerPage))
                ->take($postPerPage)
                ->get(),
            $total,
            $postPerPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        $this->incrementExportCounters($posts->items(), $validated['category_id'] ?? null);

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => PostResource::collection($posts->items())->resolve($request),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * @param  array<int, Post>  $posts
     */
    private function incrementExportCounters(array $posts, ?int $categoryId): void
    {
        if ($posts !== []) {
            Post::query()
                ->whereKey(array_map(fn (Post $post): int => $post->getKey(), $posts))
                ->increment('export_counter');
        }

        if ($categoryId !== null) {
            Category::query()
                ->whereKey($categoryId)
                ->increment('export_counter');
        }
    }
}
