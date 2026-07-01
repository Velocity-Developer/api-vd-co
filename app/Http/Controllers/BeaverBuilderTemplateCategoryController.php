<?php

namespace App\Http\Controllers;

use App\Http\Resources\BeaverBuilderTemplateCategoryResource;
use App\Models\BeaverBuilderTemplateCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class BeaverBuilderTemplateCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BeaverBuilderTemplateCategoryResource::collection(
            BeaverBuilderTemplateCategory::query()
                ->withCount('layouts')
                ->latest()
                ->paginate(),
        );
    }

    public function store(Request $request): BeaverBuilderTemplateCategoryResource
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:beaver_builder_template_categories,id'],
            'layout_ids' => ['nullable', 'array'],
            'layout_ids.*' => ['integer', 'exists:beaver_builder_layouts,id'],
        ]);

        $category = BeaverBuilderTemplateCategory::create(Arr::except($validated, ['layout_ids']));
        $category->layouts()->sync($validated['layout_ids'] ?? []);

        return BeaverBuilderTemplateCategoryResource::make($category->load('layouts'));
    }

    public function show(BeaverBuilderTemplateCategory $beaverBuilderTemplateCategory): BeaverBuilderTemplateCategoryResource
    {
        return BeaverBuilderTemplateCategoryResource::make(
            $beaverBuilderTemplateCategory->load('parent', 'children', 'layouts'),
        );
    }

    public function update(Request $request, BeaverBuilderTemplateCategory $beaverBuilderTemplateCategory): BeaverBuilderTemplateCategoryResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:beaver_builder_template_categories,id'],
            'layout_ids' => ['nullable', 'array'],
            'layout_ids.*' => ['integer', 'exists:beaver_builder_layouts,id'],
        ]);

        $beaverBuilderTemplateCategory->update(Arr::except($validated, ['layout_ids']));

        if ($request->has('layout_ids')) {
            $beaverBuilderTemplateCategory->layouts()->sync($validated['layout_ids'] ?? []);
        }

        return BeaverBuilderTemplateCategoryResource::make($beaverBuilderTemplateCategory->load('layouts'));
    }

    public function destroy(BeaverBuilderTemplateCategory $beaverBuilderTemplateCategory): Response
    {
        $beaverBuilderTemplateCategory->delete();

        return response()->noContent();
    }
}
