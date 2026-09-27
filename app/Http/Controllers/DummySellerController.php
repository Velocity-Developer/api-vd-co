<?php

namespace App\Http\Controllers;

use App\Http\Requests\DummySellerRequest;
use App\Http\Resources\DummySellerResource;
use App\Models\DummySeller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class DummySellerController extends Controller
{
    /**
     * Display a listing of the resource; `all=1` returns every row for pickers.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'verified' => ['nullable', 'boolean'],
        ]);

        $search = trim($validated['search'] ?? '');
        $query = DummySeller::query()
            ->withCount('products')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('verified'), fn (Builder $query) => $query->where('is_verified', $request->boolean('verified')))
            ->orderBy('name');

        return DummySellerResource::collection(
            $request->boolean('all') ? $query->get() : $query->paginate()->withQueryString(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DummySellerRequest $request): DummySellerResource
    {
        $seller = DummySeller::create($this->sellerAttributes($request));
        $seller->replaceImage($request->file('image_file'));
        $seller->replaceImage($request->file('banner_file'), column: 'banner');

        return DummySellerResource::make($seller->loadCount('products'));
    }

    /**
     * Display the specified resource.
     */
    public function show(DummySeller $dummySeller): DummySellerResource
    {
        return DummySellerResource::make($dummySeller->loadCount('products'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DummySellerRequest $request, DummySeller $dummySeller): DummySellerResource
    {
        $dummySeller->update($this->sellerAttributes($request));
        $dummySeller->replaceImage($request->file('image_file'), $request->boolean('remove_image'));
        $dummySeller->replaceImage($request->file('banner_file'), $request->boolean('remove_banner'), 'banner');

        return DummySellerResource::make($dummySeller->loadCount('products'));
    }

    /**
     * Remove the seller with its logo and banner; its products are kept without a seller.
     */
    public function destroy(DummySeller $dummySeller): Response
    {
        $dummySeller->deleteWithImage();

        return response()->noContent();
    }

    /**
     * Validated columns without the upload-only fields.
     *
     * @return array<string, mixed>
     */
    private function sellerAttributes(DummySellerRequest $request): array
    {
        return Arr::except($request->validated(), ['image_file', 'remove_image', 'banner_file', 'remove_banner']);
    }
}
