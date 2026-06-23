<?php

use App\Models\Category;
use App\Models\License;
use App\Models\Post;
use App\Models\RequestLog;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    Carbon::setTestNow('2026-06-20 10:00:00');

    $user = User::factory()->create();
    $websites = Website::factory()->count(2)->create();
    $license = License::factory()->create();
    Post::factory()->count(3)->for($user)->create();

    RequestLog::factory()->create([
        'route' => '/api/check-update',
        'website_id' => $websites[0]->id,
        'license_id' => $license->id,
        'created_at' => now(),
    ]);
    RequestLog::factory()->count(2)->create([
        'route' => '/api/download',
        'website_id' => $websites[0]->id,
        'license_id' => $license->id,
        'created_at' => now()->subDays(1),
    ]);
    RequestLog::factory()->count(3)->create([
        'route' => '/api/validate-license',
        'website_id' => $websites[0]->id,
        'license_id' => $license->id,
        'created_at' => now()->subDays(10),
    ]);
    RequestLog::factory()->count(4)->create([
        'route' => '/api/check-update',
        'website_id' => $websites[0]->id,
        'license_id' => $license->id,
        'created_at' => now()->subMonths(2),
    ]);
    RequestLog::factory()->create([
        'route' => '/api/legacy-route',
        'website_id' => $websites[0]->id,
        'license_id' => $license->id,
        'created_at' => now()->subYear()->subDay(),
    ]);

    collect(range(1, 55))->each(function (int $index) use ($user): void {
        $category = Category::factory()->create([
            'name' => sprintf('Category %02d', $index),
        ]);

        $posts = Post::factory()
            ->count(56 - $index)
            ->for($user)
            ->create();

        $category->posts()->sync($posts->modelKeys());
    });

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboardData.totals.websites', 2)
            ->where('dashboardData.totals.request_logs_today', 1)
            ->where('dashboardData.totals.posts', 1543)
            ->where('dashboardData.totals.request_logs_this_month', 6)
            ->has('dashboardData.request_logs_daily', 30)
            ->where('dashboardData.request_logs_daily.19.date', '2026-06-10')
            ->where('dashboardData.request_logs_daily.19.total', 3)
            ->where('dashboardData.request_logs_daily.28.date', '2026-06-19')
            ->where('dashboardData.request_logs_daily.28.total', 2)
            ->where('dashboardData.request_logs_daily.29.date', '2026-06-20')
            ->where('dashboardData.request_logs_daily.29.total', 1)
            ->has('dashboardData.request_logs_top_routes', 3)
            ->where('dashboardData.request_logs_top_routes.0.route', '/api/check-update')
            ->where('dashboardData.request_logs_top_routes.0.total', 5)
            ->where('dashboardData.request_logs_top_routes.1.route', '/api/validate-license')
            ->where('dashboardData.request_logs_top_routes.1.total', 3)
            ->where('dashboardData.request_logs_top_routes.2.route', '/api/download')
            ->where('dashboardData.request_logs_top_routes.2.total', 2)
            ->has('dashboardData.top_categories_by_posts', 50)
            ->where('dashboardData.top_categories_by_posts.0.name', 'Category 01')
            ->where('dashboardData.top_categories_by_posts.0.total', 55)
            ->where('dashboardData.top_categories_by_posts.49.name', 'Category 50')
            ->where('dashboardData.top_categories_by_posts.49.total', 6));

    Carbon::setTestNow();
});
