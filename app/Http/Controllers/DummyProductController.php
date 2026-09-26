<?php

namespace App\Http\Controllers;

use App\Http\Requests\DummyProductRequest;
use App\Http\Resources\DummyProductResource;
use App\Models\DummyProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class DummyProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'regex:/^(none|\d+)$/'],
            'category' => ['nullable', 'integer'],
        ]);

        $search = trim($validated['search'] ?? '');

        return DummyProductResource::collection(
            DummyProduct::query()
                ->with(['brand:id,name,slug', 'categories:id,name,slug'])
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('title', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
                })
                ->when($validated['brand'] ?? null, function (Builder $query, string $brand): void {
                    $brand === 'none'
                        ? $query->whereNull('dummy_product_brand_id')
                        : $query->where('dummy_product_brand_id', (int) $brand);
                })
                ->when($validated['category'] ?? null, fn (Builder $query, string $category) => $query->whereHas(
                    'categories',
                    fn (Builder $query) => $query->whereKey((int) $category),
                ))
                ->latest()
                ->latest('id')
                ->paginate()
                ->withQueryString(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DummyProductRequest $request): JsonResponse
    {
        $attributes = $this->productAttributes($request);

        if ($request->hasFile('image_file')) {
            $attributes['image'] = DummyProduct::storeImage($request->file('image_file'));
        }

        $product = DummyProduct::create($attributes);
        $product->categories()->sync($request->validated('category_ids') ?? []);

        return DummyProductResource::make($this->loadRelations($product))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(DummyProduct $dummyProduct): DummyProductResource
    {
        return DummyProductResource::make($this->loadRelations($dummyProduct));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DummyProductRequest $request, DummyProduct $dummyProduct): DummyProductResource
    {
        $attributes = $this->productAttributes($request);
        $previousImage = $dummyProduct->image;

        if ($request->hasFile('image_file')) {
            $attributes['image'] = DummyProduct::storeImage($request->file('image_file'));
        } elseif ($request->boolean('remove_image')) {
            $attributes['image'] = null;
        }

        $dummyProduct->update($attributes);

        if (array_key_exists('image', $attributes)) {
            DummyProduct::deleteStoredImage($previousImage);
        }

        if ($request->has('category_ids')) {
            $dummyProduct->categories()->sync($request->validated('category_ids') ?? []);
        }

        return DummyProductResource::make($this->loadRelations($dummyProduct));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DummyProduct $dummyProduct): Response
    {
        $dummyProduct->delete();
        DummyProduct::deleteStoredImage($dummyProduct->image);

        return response()->noContent();
    }

    /**
     * Validated product columns without the upload-only fields.
     *
     * @return array<string, mixed>
     */
    private function productAttributes(DummyProductRequest $request): array
    {
        return Arr::except($request->validated(), ['category_ids', 'image_file', 'remove_image']);
    }

    private function loadRelations(DummyProduct $product): DummyProduct
    {
        return $product->load(['brand:id,name,slug', 'categories:id,name,slug']);
    }
}
