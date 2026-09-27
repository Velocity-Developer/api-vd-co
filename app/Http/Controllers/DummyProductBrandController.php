<?php

namespace App\Http\Controllers;

use App\Http\Requests\DummyProductBrandRequest;
use App\Http\Resources\DummyProductBrandResource;
use App\Models\DummyProductBrand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class DummyProductBrandController extends Controller
{
    /**
     * Display a listing of the resource; `all=1` returns every row for pickers.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim($validated['search'] ?? '');
        $query = DummyProductBrand::query()
            ->withCount('products')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        return DummyProductBrandResource::collection(
            $request->boolean('all') ? $query->get() : $query->paginate()->withQueryString(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DummyProductBrandRequest $request): DummyProductBrandResource
    {
        $term = DummyProductBrand::create($this->termAttributes($request));
        $term->replaceImage($request->file('image_file'));

        return DummyProductBrandResource::make($term->loadCount('products'));
    }

    /**
     * Display the specified resource.
     */
    public function show(DummyProductBrand $dummyProductBrand): DummyProductBrandResource
    {
        return DummyProductBrandResource::make($dummyProductBrand->loadCount('products'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DummyProductBrandRequest $request, DummyProductBrand $dummyProductBrand): DummyProductBrandResource
    {
        $dummyProductBrand->update($this->termAttributes($request));
        $dummyProductBrand->replaceImage($request->file('image_file'), $request->boolean('remove_image'));

        return DummyProductBrandResource::make($dummyProductBrand->loadCount('products'));
    }

    /**
     * Remove the specified resource and its picture; its products are kept.
     */
    public function destroy(DummyProductBrand $dummyProductBrand): Response
    {
        $dummyProductBrand->deleteWithImage();

        return response()->noContent();
    }

    /**
     * Validated columns without the upload-only fields.
     *
     * @return array<string, mixed>
     */
    private function termAttributes(DummyProductBrandRequest $request): array
    {
        return Arr::except($request->validated(), ['image_file', 'remove_image']);
    }
}
