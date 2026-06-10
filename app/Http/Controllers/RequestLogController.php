<?php

namespace App\Http\Controllers;

use App\Http\Resources\RequestLogResource;
use App\Models\RequestLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Inertia\Inertia;

class RequestLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $requestLogs = RequestLogResource::collection(
            RequestLog::query()
                ->with(['website:id,domain,status', 'license:id,code,is_active'])
                ->latest()
                ->paginate(),
        );
        return Inertia::render('RequestLogs', [
            'requestLogs' => $requestLogs
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RequestLogResource
    {
        $validated = $request->validate([
            'route' => ['required', 'string', 'max:255'],
            'method' => ['required', 'string', 'max:10'],
            'request' => ['nullable', 'array'],
            'status' => ['required', 'integer', 'between:100,599'],
            'website_id' => ['required', 'integer', 'exists:websites,id'],
            'license_id' => ['required', 'integer', 'exists:licenses,id'],
        ]);

        $requestLog = RequestLog::create($validated);

        return RequestLogResource::make($requestLog->load(['website:id,domain,status', 'license:id,code,is_active']));
    }

    /**
     * Display the specified resource.
     */
    public function show(RequestLog $requestLog): RequestLogResource
    {
        return RequestLogResource::make($requestLog->load(['website:id,domain,status', 'license:id,code,is_active']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RequestLog $requestLog): RequestLogResource
    {
        $validated = $request->validate([
            'route' => ['sometimes', 'required', 'string', 'max:255'],
            'method' => ['sometimes', 'required', 'string', 'max:10'],
            'request' => ['nullable', 'array'],
            'status' => ['sometimes', 'required', 'integer', 'between:100,599'],
            'website_id' => ['sometimes', 'required', 'integer', 'exists:websites,id'],
            'license_id' => ['sometimes', 'required', 'integer', 'exists:licenses,id'],
        ]);

        $requestLog->update($validated);

        return RequestLogResource::make($requestLog->load(['website:id,domain,status', 'license:id,code,is_active']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RequestLog $requestLog): Response
    {
        $requestLog->delete();

        return response()->noContent();
    }
}
