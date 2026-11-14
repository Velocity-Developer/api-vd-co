<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GithubService
{
    protected ?string $token;

    /** @var array<string, string|null> */
    protected array $ownerTokens = [];

    protected string $baseUrl;

    protected ?string $lastSyncError = null;

    protected bool $lastSyncUnchanged = false;

    protected bool $lastSyncInaccessible = false;

    protected ?int $lastReleaseStatus = null;

    public function __construct()
    {
        $this->token = config('services.github.token');
        $this->ownerTokens = array_change_key_case((array) config('services.github.owner_tokens', []));
        $this->baseUrl = rtrim((string) config('services.github.url'), '/');
    }

    /**
     * @return array{
     *     repository: string,
     *     version_tag: string|null,
     *     release_name: string|null,
     *     published_at: string|null,
     *     package_download_url: string|null,
     *     files: array<int, array{
     *         file_name: string|null,
     *         file_size: int|null,
     *         download_url: string|null
     *     }>
     * }|null
     */
    public function getLatestRelease(string $owner, string $repo): ?array
    {
        $response = $this->githubRequest("repos/{$owner}/{$repo}/releases/latest");
        $this->lastReleaseStatus = $response->status();

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        $assets = collect(Arr::get($data, 'assets', []))
            ->map(function (array $asset): array {
                return [
                    'asset_id' => $asset['id'] ?? null,
                    'file_name' => $asset['name'] ?? null,
                    'file_size' => $asset['size'] ?? null,
                    'download_url' => $asset['browser_download_url'] ?? null,
                ];
            })
            ->values()
            ->all();

        return [
            'repository' => "{$owner}/{$repo}",
            'version_tag' => $data['tag_name'] ?? null,
            'release_name' => $data['name'] ?? 'N/A',
            'published_at' => $data['published_at'] ?? 'N/A',
            'package_download_url' => $assets[0]['download_url'] ?? ($data['zipball_url'] ?? null),
            'files' => $assets,
        ];
    }

    /**
     * @param  bool  $skipIfCurrent  true = jangan ubah apa pun bila versi rilis terbaru sudah sama (dipakai cron)
     */
    public function syncGithubProjectRelease(int $projectId, bool $skipIfCurrent = false): ?Project
    {
        $this->lastSyncError = null;
        $this->lastSyncUnchanged = false;
        $this->lastSyncInaccessible = false;
        $project = Project::query()->find($projectId);

        if (! $project instanceof Project) {
            $this->lastSyncError = 'Project not found.';

            return null;
        }

        if (blank($project->github_url)) {
            $this->lastSyncError = 'Project has no GitHub URL.';

            return null;
        }

        $repository = $this->parseRepositoryFromUrl($project->github_url);

        if ($repository === null) {
            $this->lastSyncError = 'GitHub URL format is invalid.';

            return null;
        }

        $release = $this->getLatestRelease($repository['owner'], $repository['repo']);

        if ($release === null) {
            $this->lastSyncError = 'Latest release not found or GitHub API failed.';
            // 404 = repo privat tanpa akses token atau belum ada rilis: bukan galat cron
            $this->lastSyncInaccessible = $this->lastReleaseStatus === 404;

            return null;
        }

        if (blank($release['version_tag'])) {
            $this->lastSyncError = 'Latest release has no version tag.';

            return null;
        }

        $normalizedVersion = $this->normalizeVersionTag($release['version_tag']);

        if ($skipIfCurrent
            && $project->version === $normalizedVersion
            && (filled($project->package_file) || filled($project->package_external_url))) {
            $this->lastSyncUnchanged = true;

            return $project;
        }

        $isPrivateRepository = $this->repositoryVisibility($repository['owner'], $repository['repo']);

        if ($isPrivateRepository === null) {
            $this->lastSyncError = 'GitHub repository visibility check failed.';

            return null;
        }

        if ($isPrivateRepository || $project->paket_privat) {
            $packageFile = $this->downloadReleasePackage($project, $release, $repository);

            if ($packageFile === null) {
                $this->lastSyncError ??= 'Release package download failed.';

                return null;
            }

            $oldPackageFile = $project->package_file;

            $project->forceFill([
                'version' => $normalizedVersion,
                'package_external_url' => null,
                'package_file' => $packageFile,
            ])->save();

            // berkas lama baru dihapus setelah berkas baru tersimpan dan DB menunjuk ke sana;
            // paket privat yang masih dipakai kanal stabil tetap disimpan
            if ($oldPackageFile !== $packageFile && ! ($project->paket_privat && $oldPackageFile === $project->paket_stabil)) {
                $this->deleteStoredPackageFile($oldPackageFile, $project->paket_privat);
            }

            return $project->fresh();
        }

        $oldPackageFile = $project->package_file;

        // installer mendahulukan package_file: kosongkan agar zip rilis baru yang dipakai
        $project->forceFill([
            'version' => $normalizedVersion,
            'package_external_url' => $release['package_download_url'],
            'package_file' => null,
        ])->save();

        $this->deleteStoredPackageFile($oldPackageFile);

        return $project->fresh();
    }

    public function isRepositoryPrivate(string $owner, string $repo): bool
    {
        return $this->repositoryVisibility($owner, $repo) === true;
    }

    /**
     * Memakai token (bila ada) agar tidak kena batas 60 request/jam GitHub tanpa autentikasi.
     * 404 = privat / tidak bisa diakses; 200 = baca kolom "private"; galat lain (rate limit dll.) = null.
     */
    public function repositoryVisibility(string $owner, string $repo): ?bool
    {
        $response = $this->githubRequest("repos/{$owner}/{$repo}");

        if ($response->status() === 404) {
            return true;
        }

        if ($response->successful()) {
            return (bool) $response->json('private', false);
        }

        return null;
    }

    public function lastSyncError(): ?string
    {
        return $this->lastSyncError;
    }

    /** true bila GitHub menjawab 404 (repo privat tanpa akses token / belum ada rilis) */
    public function lastSyncInaccessible(): bool
    {
        return $this->lastSyncInaccessible;
    }

    public function lastSyncUnchanged(): bool
    {
        return $this->lastSyncUnchanged;
    }

    /**
     * @return array{owner: string, repo: string}|null
     */
    private function parseRepositoryFromUrl(string $githubUrl): ?array
    {
        $path = trim((string) parse_url($githubUrl, PHP_URL_PATH), '/');

        if ($path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path)));

        if (count($segments) < 2) {
            return null;
        }

        return [
            'owner' => $segments[0],
            'repo' => preg_replace('/\.git$/', '', $segments[1]) ?: $segments[1],
        ];
    }

    private function githubRequest(string $path): Response
    {
        return Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
        ])
            ->when(
                filled($token = $this->tokenForPath($path)),
                fn ($request) => $request->withToken($token),
            )
            ->connectTimeout(15)
            ->timeout(60)
            ->get("{$this->baseUrl}/{$path}");
    }

    private function tokenForOwner(string $owner): ?string
    {
        $ownerToken = $this->ownerTokens[strtolower($owner)] ?? null;

        return filled($ownerToken) ? $ownerToken : $this->token;
    }

    /** path API berbentuk repos/{owner}/{repo}/... */
    private function tokenForPath(string $path): ?string
    {
        $segments = explode('/', ltrim($path, '/'));

        return ($segments[0] ?? null) === 'repos' && filled($segments[1] ?? null)
            ? $this->tokenForOwner($segments[1])
            : $this->token;
    }

    private function githubAssetDownloadRequest(int $assetId, array $repository): Response
    {
        return Http::withHeaders([
            'Accept' => 'application/octet-stream',
            'Content-Type' => null,
            'Authorization' => 'Bearer '.$this->tokenForOwner($repository['owner']),
        ])
            ->connectTimeout(15)
            ->timeout(600)
            ->get("{$this->baseUrl}/repos/{$repository['owner']}/{$repository['repo']}/releases/assets/{$assetId}");
    }

    /**
     * @param  array{
     *     version_tag: string|null,
     *     package_download_url: string|null,
     *     files: array<int, array{
     *         asset_id: int|null,
     *         file_name: string|null,
     *         file_size: int|null,
     *         download_url: string|null
     *     }>
     * }  $release
     */
    private function downloadReleasePackage(Project $project, array $release, array $repository): ?string
    {
        $asset = $release['files'][0] ?? null;
        $assetId = $asset['asset_id'] ?? null;

        if (! is_array($asset) || ! is_int($assetId)) {
            $this->lastSyncError = 'Release asset ID not found.';

            return null;
        }

        $response = $this->githubAssetDownloadRequest($assetId, $repository);

        if ($response->failed()) {
            $this->lastSyncError = "GitHub asset download asset id {$assetId} failed with status ".$response->status().'.';

            return null;
        }

        $body = $response->body();

        // jangan simpan halaman galat/HTML sebagai paket
        if (strlen($body) < 22 || ! str_starts_with($body, "PK\x03\x04")) {
            $this->lastSyncError = "GitHub asset id {$assetId} is not a valid zip file.";

            return null;
        }

        $fileName = $asset['file_name']
            ?? Str::slug($project->name).'-'.Str::slug((string) $release['version_tag']).'.zip';
        // paket privat (fork BB dkk.) tidak boleh punya URL publik: simpan di disk lokal, dikirim lewat endpoint berizin
        $disk = $project->paket_privat ? 'local' : 'public';
        $folder = ($project->paket_privat ? 'paket-privat/' : 'project-packages/').Str::slug($project->name);
        $path = $folder.'/'.$fileName;

        if (! Storage::disk($disk)->put($path, $body)) {
            $this->lastSyncError = 'Release package could not be stored.';

            return null;
        }

        return $path;
    }

    private function deleteStoredPackageFile(?string $packageFile, bool $privat = false): void
    {
        if (blank($packageFile) || str_starts_with($packageFile, 'http')) {
            return;
        }

        Storage::disk($privat ? 'local' : 'public')->delete($packageFile);
    }

    private function normalizeVersionTag(string $versionTag): string
    {
        return preg_replace('/^[vV](?=\d)/', '', trim($versionTag)) ?: trim($versionTag);
    }
}
