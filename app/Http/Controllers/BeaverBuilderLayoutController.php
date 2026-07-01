<?php

namespace App\Http\Controllers;

use App\Http\Resources\BeaverBuilderLayoutResource;
use App\Models\BeaverBuilderLayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class BeaverBuilderLayoutController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BeaverBuilderLayoutResource::collection(
            BeaverBuilderLayout::query()
                ->latest()
                ->paginate(),
        );
    }

    public function store(Request $request): BeaverBuilderLayoutResource
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['theme-layout', 'template-layout', 'row', 'module'])],
            'content' => ['required', 'string'],
            'meta' => ['nullable', 'array'],
            'screenshot' => ['nullable', 'string', 'max:255'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:beaver_builder_template_categories,id'],
        ]);

        $layout = BeaverBuilderLayout::create(Arr::except($validated, ['category_ids']));
        $layout->categories()->sync($validated['category_ids'] ?? []);

        return BeaverBuilderLayoutResource::make($layout->load('categories'));
    }

    public function show(BeaverBuilderLayout $beaverBuilderLayout): BeaverBuilderLayoutResource
    {
        return BeaverBuilderLayoutResource::make($beaverBuilderLayout->load('categories'));
    }

    public function update(Request $request, BeaverBuilderLayout $beaverBuilderLayout): BeaverBuilderLayoutResource
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(['theme-layout', 'template-layout', 'row', 'module'])],
            'content' => ['sometimes', 'required', 'string'],
            'meta' => ['nullable', 'array'],
            'screenshot' => ['nullable', 'string', 'max:255'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:beaver_builder_template_categories,id'],
        ]);

        $beaverBuilderLayout->update(Arr::except($validated, ['category_ids']));

        if ($request->has('category_ids')) {
            $beaverBuilderLayout->categories()->sync($validated['category_ids'] ?? []);
        }

        return BeaverBuilderLayoutResource::make($beaverBuilderLayout->load('categories'));
    }

    public function destroy(BeaverBuilderLayout $beaverBuilderLayout): Response
    {
        $beaverBuilderLayout->delete();

        return response()->noContent();
    }
}
