<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use PasswordValidationRules;

    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()
                ->withCount(['posts', 'licenses'])
                ->latest()
                ->paginate(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): UserResource
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),
        ]);

        $user = User::create($validated);

        return UserResource::make($user->loadCount(['posts', 'licenses']));
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): UserResource
    {
        return UserResource::make($user->loadCount(['posts', 'licenses']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): UserResource
    {
        $passwordRules = $this->passwordRules();
        array_shift($passwordRules);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', ...$passwordRules],
        ]);

        $user->update(Arr::where($validated, fn (mixed $value): bool => $value !== null));

        return UserResource::make($user->loadCount(['posts', 'licenses']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): Response
    {
        abort_if($request->user()?->is($user), Response::HTTP_UNPROCESSABLE_ENTITY, 'You cannot delete the currently authenticated user.');

        $user->delete();

        return response()->noContent();
    }
}
