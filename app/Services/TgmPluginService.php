<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class TgmPluginService
{
    /**
     * Tautan unduh paket privat di daftar TGM berlaku 24 jam: tema Velocity menyimpan daftar ini sampai 12 jam
     * dan memakainya untuk memasang plugin maupun menawarkan update.
     */
    private const JAM_TAUTAN_PRIVAT = 24;

    /**
     * @param  bool  $serverTerdaftar  pemanggil dari IP server terdaftar: paket privat ikut, dengan tautan bertanda tangan
     * @param  string|null  $domain  domain situs pemanggil (kanal uji/stabil paket privat)
     * @return array<int, array{
     *     name: string,
     *     slug: string,
     *     source: string,
     *     required: bool,
     *     version: string,
     *     force_activation: bool,
     *     force_deactivation: bool,
     *     external_url: string,
     *     is_callable: string
     * }>
     */
    public function get(bool $serverTerdaftar = false, ?string $domain = null): array
    {
        return Project::query()
            ->when(! $serverTerdaftar, fn ($query) => $query->publik())
            ->where('type', 'wp_plugin')
            ->orderBy('name')
            ->get()
            ->map(function (Project $project) use ($domain): ?array {
                $versi = $project->version ?? '';
                $sumber = $this->resolveSource($project);

                if ($project->paket_privat) {
                    $rilis = $project->rilisUntuk($domain);
                    if ($rilis === null || ! Storage::disk('local')->exists($rilis['paket'])) {
                        if ($rilis !== null) {
                            Log::warning('TGM: berkas paket privat tidak ditemukan, entri dilewati', ['slug' => $project->slug, 'paket' => $rilis['paket']]);
                        }

                        return null;
                    }
                    $versi = $rilis['versi'];
                    $sumber = url(URL::temporarySignedRoute('api.v1.plugin-package', now()->addHours(self::JAM_TAUTAN_PRIVAT), [
                        'slug' => $project->slug,
                        'versi' => $versi,
                    ], absolute: false));
                }

                return [
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'source' => $sumber,
                    'required' => (bool) $project->plugin_wp_required,
                    'version' => $versi,
                    'force_activation' => false,
                    'force_deactivation' => false,
                    'external_url' => '',
                    'is_callable' => '',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveSource(Project $project): string
    {
        if ($project->paket_privat) {
            return '';
        }

        if (filled($project->package_external_url)) {
            return $project->package_external_url;
        }

        if (blank($project->package_file)) {
            return '';
        }

        if (str_starts_with($project->package_file, 'http')) {
            return $project->package_file;
        }

        return Storage::disk('public')->url($project->package_file);
    }
}
