<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Storage;

class TgmPluginService
{
    /**
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
    public function get(): array
    {
        return Project::query()
            ->where('type', 'wp_plugin')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => [
                'name' => $project->name,
                'slug' => $project->slug,
                'source' => $this->resolveSource($project),
                'required' => (bool) $project->plugin_wp_required,
                'version' => $project->version ?? '',
                'force_activation' => false,
                'force_deactivation' => false,
                'external_url' => '',
                'is_callable' => '',
            ])
            ->all();
    }

    private function resolveSource(Project $project): string
    {
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
