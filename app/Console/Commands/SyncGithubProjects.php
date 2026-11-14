<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\GithubService;
use Illuminate\Console\Command;

class SyncGithubProjects extends Command
{
    protected $signature = 'projects:sync-github';

    protected $description = 'Sync latest GitHub releases (public and private) for projects whose version changed';

    public function handle(GithubService $githubService): int
    {
        $projects = Project::query()
            ->whereNotNull('github_url')
            ->where('github_url', '!=', '')
            ->get(['id', 'name', 'github_url']);

        $synced = 0;
        $unchanged = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($projects as $project) {
            if ($this->repositoryFromUrl($project->github_url) === null) {
                $skipped++;
                $this->line("Skipped {$project->name}: GitHub URL is invalid.");

                continue;
            }

            $result = $githubService->syncGithubProjectRelease($project->id, skipIfCurrent: true);

            if ($result === null && $githubService->lastSyncInaccessible()) {
                $skipped++;
                $this->warn("Skipped {$project->name}: repository or release not accessible with the GitHub token (404).");

                continue;
            }

            if ($result === null) {
                $failed++;
                $this->error("Failed {$project->name}: ".($githubService->lastSyncError() ?? 'Unknown error.'));

                continue;
            }

            if ($githubService->lastSyncUnchanged()) {
                $unchanged++;

                continue;
            }

            $synced++;
            $this->info("Synced {$project->name} to {$result->version}.");
        }

        $this->line('['.now()->toDateTimeString()."] Completed: {$synced} synced, {$unchanged} unchanged, {$skipped} skipped, {$failed} failed.");

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
