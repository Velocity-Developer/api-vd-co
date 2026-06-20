<?php

use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('authenticated users can visit the admin users page', function () {
    $this->get(route('users'))
        ->assertOk();
});

test('user controller lists stores updates and deletes users', function () {
    User::factory()->count(2)->create();

    $this->getJson('/ajax/users')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $response = $this->postJson('/ajax/users', [
        'name' => 'Admin Staff',
        'email' => 'admin.staff@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Admin Staff')
        ->assertJsonPath('data.email', 'admin.staff@example.com');

    $user = User::where('email', 'admin.staff@example.com')->firstOrFail();

    $this->patchJson("/ajax/users/{$user->id}", [
        'name' => 'Admin Team',
        'email' => 'admin.team@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Admin Team')
        ->assertJsonPath('data.email', 'admin.team@example.com');

    $this->deleteJson("/ajax/users/{$user->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('currently authenticated user cannot delete themselves', function () {
    /** @var User $user */
    $user = auth()->user();

    $this->deleteJson("/ajax/users/{$user->id}")
        ->assertUnprocessable()
        ->assertSeeText('currently authenticated user');
});
