<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\GithubService;
use Illuminate\Console\Command;

class SyncGithubProjects extends Command
{
    protected $signature = 'projects:sync-github';

    protected $description = 'Sync latest releases for public GitHub projects';

    public function handle(GithubService $githubService): int
    {
        $projects = Project::query()
            ->whereNotNull('github_url')
            ->where('github_url', '!=', '')
            ->get(['id', 'name', 'github_url']);

        $synced = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($projects as $project) {
            $repository = $this->repositoryFromUrl($project->github_url);

            if ($repository === null || $githubService->isRepositoryPrivate($repository['owner'], $repository['repo'])) {
                $skipped++;
                $this->line("Skipped {$project->name}: repository is private or URL is invalid.");
                continue;
            }

            if ($githubService->syncGithubProjectRelease($project->id) === null) {
                $failed++;
                $this->error("Failed {$project->name}: ".($githubService->lastSyncError() ?? 'Unknown error.'));
                continue;
            }

            $synced++;
            $this->info("Synced {$project->name}.");
        }

        $this->newLine();
        $this->line("Completed: {$synced} synced, {$skipped} skipped, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{owner: string, repo: string}|null */
    private function repositoryFromUrl(?string $url): ?array
    {
        $path = trim((string) parse_url((string) $url, PHP_URL_PATH), '/');
        $segments = array_values(array_filter(explode('/', $path)));

        if (count($segments) < 2) {
            return null;
        }

        return [
            'owner' => $segments[0],
            'repo' => preg_replace('/\.git$/', '', $segments[1]) ?: $segments[1],
        ];
    }
}

