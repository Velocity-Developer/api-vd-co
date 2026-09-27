<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DummyProductBrandResource;
use App\Http\Resources\DummyProductCategoryResource;
use App\Http\Resources\DummyProductResource;
use App\Http\Resources\DummySellerResource;
use App\Models\DummyProduct;
use App\Models\DummyProductBrand;
use App\Models\DummyProductCategory;
use App\Models\DummySeller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class DummyProductController extends Controller
{
    /**
     * List dummy products (paginated), newest first, with brand, seller, categories and gallery.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            ...$this->listRules(),
            'brand' => ['nullable', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:191'],
            'seller' => ['nullable', 'string', 'max:191'],
        ]);

        $search = trim($validated['search'] ?? '');

        $products = DummyProduct::query()
            ->with(['brand:id,name,slug,image', 'seller:id,name,slug,image,city,is_verified', 'categories:id,name,slug,image', 'images'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($validated['brand'] ?? null, fn (Builder $query, string $slug) => $query->whereHas(
                'brand',
                fn (Builder $query) => $query->where('slug', $slug),
            ))
            ->when($validated['seller'] ?? null, fn (Builder $query, string $slug) => $query->whereHas(
                'seller',
                fn (Builder $query) => $query->where('slug', $slug),
            ))
            ->when($validated['category'] ?? null, fn (Builder $query, string $slug) => $query->whereHas(
                'categories',
                fn (Builder $query) => $query->where('slug', $slug),
            ))
            ->latest()
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return $this->paginatedResponse($products, DummyProductResource::collection($products));
    }

    /**
     * List dummy product brands (paginated) by name, with their product count.
     */
    public function brands(Request $request): JsonResponse
    {
        $brands = $this->termQuery(DummyProductBrand::query(), $request->validate($this->listRules()));

        return $this->paginatedResponse($brands, DummyProductBrandResource::collection($brands));
    }

    /**
     * List dummy product categories (paginated) by name, with their product count.
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = $this->termQuery(DummyProductCategory::query(), $request->validate($this->listRules()));

        return $this->paginatedResponse($categories, DummyProductCategoryResource::collection($categories));
    }

    /**
     * List dummy sellers (paginated) by name, with their product count.
     */
    public function sellers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            ...$this->listRules(),
            'city' => ['nullable', 'string', 'max:100'],
            'verified' => ['nullable', 'boolean'],
        ]);

        $search = trim($validated['search'] ?? '');

        $sellers = DummySeller::query()
            ->withCount('products')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($validated['city'] ?? null, fn (Builder $query, string $city) => $query->where('city', $city))
            ->when($request->filled('verified'), fn (Builder $query) => $query->where('is_verified', $request->boolean('verified')))
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return $this->paginatedResponse($sellers, DummySellerResource::collection($sellers));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function listRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @param  Builder<DummyProductBrand>|Builder<DummyProductCategory>  $query
     * @param  array{search?: string|null, per_page?: int|string|null}  $filters
     */
    private function termQuery(Builder $query, array $filters): LengthAwarePaginator
    {
        $search = trim($filters['search'] ?? '');

        return $query
            ->withCount('products')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    private function paginatedResponse(LengthAwarePaginator $paginator, AnonymousResourceCollection $data): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
