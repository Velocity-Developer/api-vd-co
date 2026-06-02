<?php

namespace App\Http\Controllers;

use App\Http\Resources\LicenseResource;
use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class LicenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return LicenseResource::collection(
            License::query()
                ->with('user:id,name,email')
                ->latest()
                ->paginate(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): LicenseResource
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'code' => ['required', 'string', 'max:255', 'unique:licenses,code'],
            'is_active' => ['sometimes', 'boolean'],
            'used_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $license = License::create($validated);

        return LicenseResource::make($license->load('user:id,name,email'));
    }

    /**
     * Display the specified resource.
     */
    public function show(License $license): LicenseResource
    {
        return LicenseResource::make($license->load('user:id,name,email'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, License $license): LicenseResource
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'code' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('licenses', 'code')->ignore($license)],
            'is_active' => ['sometimes', 'boolean'],
            'used_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $license->update($validated);

        return LicenseResource::make($license->load('user:id,name,email'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(License $license): Response
    {
        $license->delete();

        return response()->noContent();
    }
}
