<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PostSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, string>
     */
    private const SeedImageIds = [1011, 1025, 1035, 1043, 1050];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereIn('email', [
            'admin@example.com',
            'usertest@example.com',
        ])->get();

        if ($users->isEmpty()) {
            $users = User::factory(2)->create();
        }

        $users->each(function (User $user): void {
            if (Post::whereBelongsTo($user)->exists()) {
                return;
            }

            $imagePaths = $this->seedImagePaths($user);

            Post::factory(5)
                ->for($user)
                ->sequence(...array_map(
                    fn (string $image): array => ['image' => $image],
                    $imagePaths,
                ))
                ->create();
        });
    }

    /**
     * @return array<int, string>
     */
    private function seedImagePaths(User $user): array
    {
        return collect(self::SeedImageIds)
            ->map(function (int $imageId, int $index) use ($user): string {
                $path = 'post/'.now()->format('y-m').'/seed-post-'.$user->id.'-'.($index + 1).'.jpg';

                Storage::disk('public')->put(
                    $path,
                    $this->downloadPicsumImage($imageId),
                );

                return $path;
            })
            ->all();
    }

    private function downloadPicsumImage(int $imageId): string
    {
        return Http::timeout(20)
            ->retry(2, 500)
            ->get("https://picsum.photos/id/{$imageId}/1200/675")
            ->throw()
            ->body();
    }
}
