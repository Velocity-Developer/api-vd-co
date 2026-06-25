<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GithubService
{

    protected ?string $token;
    protected string $baseUrl;

    public function __construct()
    {
        $this->token = config('services.github.token');
        $this->baseUrl = config('services.github.url');
    }

    public function getLatestRelease(string $owner, string $repo)
    {
        // Memanggil HTTP Client bawaan Laravel
        $response = Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
        ])
            ->withToken($this->token) // Otomatis menambahkan 'Authorization: Bearer ...'
            ->get("{$this->baseUrl}/repos/{$owner}/{$repo}/releases/latest");

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        // Format data agar hanya mengambil informasi yang Anda butuhkan
        return [
            'repository'   => "{$owner}/{$repo}",
            'version_tag'  => $data['tag_name'] ?? 'N/A',
            'release_name' => $data['name'] ?? 'N/A',
            'published_at' => $data['published_at'] ?? 'N/A',
            'files'        => collect($data['assets'] ?? [])->map(function ($asset) {
                return [
                    'file_name'    => $asset['name'],
                    'file_size'    => $asset['size'], // dalam bytes
                    'download_url' => $asset['browser_download_url'],
                ];
            })->all(),
        ];
    }
}
