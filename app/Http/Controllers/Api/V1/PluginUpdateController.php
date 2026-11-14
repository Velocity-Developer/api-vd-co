<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Update plugin privat (fork Beaver Builder, Themer, UABB) untuk situs di server terdaftar.
 * Dipanggil modul updater velocity-addons; hanya menawarkan update, pemasangan tetap lewat WordPress / WP-CLI.
 */
class PluginUpdateController extends Controller
{
    /** masa berlaku tautan unduh */
    private const MENIT_TAUTAN = 60;

    /**
     * GET /api/v1/plugin-updates?slugs=bb-plugin,bb-theme-builder&source=domain.com
     * Header "source" (domain situs) juga diterima; dipakai memilih kanal uji/stabil.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slugs' => ['required', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:255'],
        ]);
        $slugs = array_slice(array_values(array_unique(array_filter(array_map('trim', explode(',', $data['slugs']))))), 0, 20);
        $domain = $data['source'] ?? $request->header('source');

        $hasil = [];
        foreach (Project::query()->where('paket_privat', true)->where('type', 'wp_plugin')->whereIn('slug', $slugs)->with('changelogs')->get() as $project) {
            $rilis = $project->rilisUntuk($domain);
            if ($rilis === null || ! Storage::disk('local')->exists($rilis['paket'])) {
                continue;
            }
            $hasil[$project->slug] = [
                'slug' => $project->slug,
                'name' => $project->name,
                'version' => $rilis['versi'],
                'kanal' => $project->adalahDomainUji($domain) ? 'uji' : 'stabil',
                'requires' => $project->requires_wp,
                'requires_php' => $project->requires_php,
                // tanda tangan relatif: tetap sah walau skema/host dilihat berbeda di balik Cloudflare
                'package' => url(URL::temporarySignedRoute('api.v1.plugin-package', now()->addMinutes(self::MENIT_TAUTAN), [
                    'slug' => $project->slug,
                    'versi' => $rilis['versi'],
                ], absolute: false)),
                'changelog' => $this->changelog($project),
            ];
        }

        return response()->json(['status' => true, 'data' => (object) $hasil]);
    }

    /**
     * GET /api/v1/plugin-package/{slug}/{versi}?expires=…&signature=… — zip dari disk lokal.
     * Versi harus sama dengan rilis uji atau stabil yang masih tersimpan.
     */
    public function download(string $slug, string $versi): StreamedResponse|JsonResponse
    {
        $project = Project::query()->where('paket_privat', true)->where('slug', $slug)->first();
        $paket = match (true) {
            ! $project instanceof Project => null,
            $versi === $project->versi_stabil => $project->paket_stabil,
            $versi === $project->version => $project->package_file,
            default => null,
        };
        if (blank($paket) || ! Storage::disk('local')->exists($paket)) {
            return response()->json(['status' => false, 'message' => 'Paket tidak ditemukan.'], 404);
        }

        return Storage::disk('local')->download($paket, "{$slug}-{$versi}.zip", ['Content-Type' => 'application/zip']);
    }

    private function changelog(Project $project): ?string
    {
        $catatan = $project->changelogs->sortByDesc('id')->take(5)
            ->map(fn ($c) => trim($c->project_version.': '.strip_tags((string) $c->changelog_content)))
            ->filter()->values();

        return $catatan->isEmpty() ? null : $catatan->implode("\n");
    }
}
