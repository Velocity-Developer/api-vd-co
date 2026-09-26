<?php

namespace App\Http\Controllers;

use App\Http\Requests\DummyProductCategoryRequest;
use App\Http\Resources\DummyProductCategoryResource;
use App\Models\DummyProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DummyProductCategoryController extends Controller
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
        $query = DummyProductCategory::query()
            ->withCount('products')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        return DummyProductCategoryResource::collection(
            $request->boolean('all') ? $query->get() : $query->paginate()->withQueryString(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DummyProductCategoryRequest $request): DummyProductCategoryResource
    {
        return DummyProductCategoryResource::make(DummyProductCategory::create($request->validated())->loadCount('products'));
    }

    /**
     * Display the specified resource.
     */
    public function show(DummyProductCategory $dummyProductCategory): DummyProductCategoryResource
    {
        return DummyProductCategoryResource::make($dummyProductCategory->loadCount('products'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DummyProductCategoryRequest $request, DummyProductCategory $dummyProductCategory): DummyProductCategoryResource
    {
        $dummyProductCategory->update($request->validated());

        return DummyProductCategoryResource::make($dummyProductCategory->loadCount('products'));
    }

    /**
     * Remove the specified resource from storage; its products are kept.
     */
    public function destroy(DummyProductCategory $dummyProductCategory): Response
    {
        $dummyProductCategory->delete();

        return response()->noContent();
    }
}
