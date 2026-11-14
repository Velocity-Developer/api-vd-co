<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\GithubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectController extends Controller
{
    /**
     * Display the projects admin page.
     */
    public function index(Request $request): InertiaResponse
    {
        return $this->renderIndex('Projects', null, $request);
    }

    public function themes(Request $request): InertiaResponse
    {
        return $this->renderIndex('Projects', ['wp_theme', 'wp_theme_child'], $request);
    }

    public function plugins(Request $request): InertiaResponse
    {
        return $this->renderIndex('Projects', ['wp_plugin'], $request);
    }

    /**
     * @param  array<int, string>|null  $types
     */
    private function renderIndex(string $page, ?array $types = null, ?Request $request = null): InertiaResponse
    {
        $projects = ProjectResource::collection(
            Project::query()
                ->when($types, fn ($query) => $query->whereIn('type', $types))
                ->when($request?->filled('paket'), fn ($query) => $query->where('paket', $request->string('paket')))
                ->when($request?->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
                ->with('parent:id,name')
                ->latest('id')
                ->paginate(),
        );

        return Inertia::render($page, [
            'pageTitle' => match ($types) {
                ['wp_theme', 'wp_theme_child'] => 'Themes',
                ['wp_plugin'] => 'Plugins',
                default => 'Projects',
            },
            'projects' => $projects,
            'parentProjects' => Project::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): ProjectResource
    {
        $validated = $request->validate($this->rules());
        $validated['slug'] = Str::slug($validated['slug']);
        $validated = $this->normalizeRequiredVersions($validated);
        $validated = $this->normalizePaketPrivat($validated);
        $validated['package_file'] = ($validated['paket_privat'] ?? false) ? null : $this->storePackageFile($request);
        $validated['icon'] = $this->storeImageFile($request, 'icon');
        $validated['screenshot'] = $this->storeImageFile($request, 'screenshot');
        unset($validated['remove_package_file'], $validated['remove_icon'], $validated['remove_screenshot']);

        $project = Project::create($validated);

        return ProjectResource::make($project->load('parent:id,name'));
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project): ProjectResource
    {
        return ProjectResource::make($project->load('parent:id,name'));
    }

    public function syncGithubRelease(Project $project, GithubService $githubService): JsonResponse
    {
        if (blank($project->github_url)) {
            return response()->json([
                'message' => 'Project must have a GitHub URL before syncing releases.',
            ], 422);
        }

        $syncedProject = $githubService->syncGithubProjectRelease($project->id);

        if (! $syncedProject) {
            return response()->json([
                'message' => $githubService->lastSyncError() ?? 'Unable to sync GitHub release for project ID '.$project->id.'.',
            ], 422);
        }

        return response()->json([
            'message' => 'GitHub release synced successfully.',
            'data' => ProjectResource::make($syncedProject->load('parent:id,name'))->resolve(),
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project): ProjectResource
    {
        $validated = $request->validate($this->rules($project, true));
        $shouldRemovePackageFile = $request->boolean('remove_package_file');
        $shouldRemoveIcon = $request->boolean('remove_icon');
        $shouldRemoveScreenshot = $request->boolean('remove_screenshot');

        if (array_key_exists('slug', $validated)) {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        $validated = $this->normalizeRequiredVersions($validated, $project);
        $validated = $this->normalizePaketPrivat($validated, $project);

        $privatAkhir = (bool) ($validated['paket_privat'] ?? $project->paket_privat);
        if ($privatAkhir && $request->hasFile('package_file')) {
            // dicek dari status project di server, bukan hanya dari field request
            throw ValidationException::withMessages(['package_file' => 'Paket privat tidak bisa diunggah manual; paket diambil dari rilis GitHub.']);
        }
        if ($privatAkhir && ($validated['type'] ?? $project->type) !== 'wp_plugin') {
            throw ValidationException::withMessages(['type' => 'Matikan Paket Privat dulu sebelum mengubah type project.']);
        }

        if ($privatAkhir && ! $project->paket_privat) {
            // berubah jadi paket privat: paket publik lama dibuang, paket berikutnya hanya dari sinkron rilis GitHub
            $this->deletePackageFile($project);
            $validated['package_file'] = null;
            $validated['package_external_url'] = null;
        } elseif (! $privatAkhir && $project->paket_privat) {
            // kembali publik: berkas privat (terbaru & stabil) dihapus, rilis berikutnya masuk disk publik lewat sinkron/unggah
            $this->deletePackageFile($project);
            if (filled($project->paket_stabil) && $project->paket_stabil !== $project->package_file) {
                Storage::disk('local')->delete($project->paket_stabil);
            }
            $validated['package_file'] = $request->hasFile('package_file') ? $this->storePackageFile($request) : null;
            $project->forceFill(['versi_stabil' => null, 'paket_stabil' => null]);
            $validated['domain_uji'] = null;
        } elseif ($request->hasFile('package_file')) {
            $this->deletePackageFile($project);
            $validated['package_file'] = $this->storePackageFile($request);
        } elseif ($shouldRemovePackageFile) {
            $this->deletePackageFile($project);
            $validated['package_file'] = null;
        }

        if ($request->hasFile('icon')) {
            $this->deleteImageFile($project->icon);
            $validated['icon'] = $this->storeImageFile($request, 'icon');
        } elseif ($shouldRemoveIcon) {
            $this->deleteImageFile($project->icon);
            $validated['icon'] = null;
        }

        if ($request->hasFile('screenshot')) {
            $this->deleteImageFile($project->screenshot);
            $validated['screenshot'] = $this->storeImageFile($request, 'screenshot');
        } elseif ($shouldRemoveScreenshot) {
            $this->deleteImageFile($project->screenshot);
            $validated['screenshot'] = null;
        }

        unset($validated['remove_package_file'], $validated['remove_icon'], $validated['remove_screenshot']);
        $project->update($validated);

        return ProjectResource::make($project->load('parent:id,name'));
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project): HttpResponse
    {
        $this->deletePackageFile($project);
        $project->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?Project $project = null, bool $isUpdate = false): array
    {
        $supportedTypes = [
            'project_internal',
            'project_client',
            'wp_theme',
            'wp_plugin',
            'wp_theme_child',
        ];

        return [
            'name' => array_values(array_filter([
                $isUpdate ? 'sometimes' : null,
                Rule::unique('projects', 'name')->ignore($project),
                'required',
                'string',
                'max:255',
            ])),
            'paket' => ['nullable', 'string', 'max:255'],
            'slug' => array_values(array_filter([
                $isUpdate ? 'sometimes' : null,
                'required',
                'string',
                'max:255',
                Rule::unique('projects', 'slug')->ignore($project),
            ])),
            'version' => ['nullable', 'string', 'max:255'],
            'requires_wp' => ['nullable', 'string', 'max:255'],
            'requires_php' => ['nullable', 'string', 'max:255'],
            'plugin_wp_required' => ['nullable', 'boolean'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'package_external_url' => ['nullable', 'url', 'max:255'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_package_file' => ['nullable', 'boolean'],
            'remove_icon' => ['nullable', 'boolean'],
            'remove_screenshot' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'paket_privat' => ['nullable', 'boolean'],
            // satu domain per baris / dipisah koma
            'domain_uji' => ['nullable', 'string', 'max:5000'],
            'type' => array_values(array_filter([
                $isUpdate ? 'sometimes' : null,
                'required',
                Rule::in($supportedTypes),
            ])),
            'package_file' => array_values(array_filter([
                $isUpdate ? 'nullable' : 'required_without:package_external_url',
                'file',
                'mimes:zip',
                'max:51200',
                'prohibited_if_accepted:paket_privat',
            ])),
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id'),
                Rule::notIn($project ? [$project->id] : []),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeRequiredVersions(array $validated, ?Project $project = null): array
    {
        $type = $validated['type'] ?? $project?->type;

        if (! $this->isWordPressType($type)) {
            $validated['requires_wp'] = null;
            $validated['requires_php'] = null;
            $validated['plugin_wp_required'] = null;

            return $validated;
        }

        $validated['requires_wp'] = $validated['requires_wp'] ?? null;
        $validated['requires_php'] = $validated['requires_php'] ?? null;
        $validated['plugin_wp_required'] = $this->isPluginType($type)
            ? ($validated['plugin_wp_required'] ?? false)
            : null;

        return $validated;
    }

    private function isPluginType(mixed $type): bool
    {
        return $type === 'wp_plugin';
    }

    private function isWordPressType(mixed $type): bool
    {
        return in_array($type, ['wp_theme', 'wp_plugin', 'wp_theme_child'], true);
    }

    private function storePackageFile(Request $request): ?string
    {
        if (! $request->hasFile('package_file')) {
            return null;
        }

        $name = $request->input('name');
        $version = $request->input('version');

        // 1. Ambil file dari request
        $file = $request->file('package_file');

        // 2. Tentukan nama file baru (contoh: nama-package-v1.0.0.zip)
        $fileName = Str::slug($name).'-'.Str::slug($version).'.'.$file->getClientOriginalExtension();

        // 3. Tentukan folder tujuan
        $folder = 'project-packages/'.Str::slug($name);

        // 4. Simpan dengan nama baru menggunakan storeAs
        $path = $file->storeAs($folder, $fileName, 'public');

        return $path;
    }

    private function deletePackageFile(Project $project): void
    {
        if ($project->package_file === null || str_starts_with($project->package_file, 'http')) {
            return;
        }

        Storage::disk($project->paket_privat ? 'local' : 'public')->delete($project->package_file);
    }

    /**
     * Paket privat: tanpa unggahan manual/URL luar (hanya rilis GitHub), wajib URL GitHub; domain uji jadi daftar.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePaketPrivat(array $validated, ?Project $project = null): array
    {
        if (array_key_exists('domain_uji', $validated)) {
            $domain = preg_split('/[\s,]+/', (string) $validated['domain_uji'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $validated['domain_uji'] = array_values(array_unique(array_filter(array_map([Project::class, 'rapikanDomain'], $domain))));
        }
        if (! ($validated['paket_privat'] ?? $project?->paket_privat ?? false)) {
            return $validated;
        }
        $github = array_key_exists('github_url', $validated) ? $validated['github_url'] : $project?->github_url;
        if (blank($github)) {
            throw ValidationException::withMessages(['github_url' => 'Paket privat wajib punya URL GitHub (paket diambil dari rilis GitHub).']);
        }
        $validated['package_external_url'] = null;

        return $validated;
    }

    /**
     * Naikkan rilis terbaru paket privat ke kanal stabil (semua situs ditawari update ini).
     */
    public function stabilkan(Project $project): JsonResponse
    {
        if (! $project->paket_privat || blank($project->package_file) || blank($project->version)) {
            return response()->json(['message' => 'Hanya paket privat yang sudah punya rilis tersinkron yang bisa dinaikkan ke stabil.'], 422);
        }
        $lama = $project->paket_stabil;
        $project->forceFill(['versi_stabil' => $project->version, 'paket_stabil' => $project->package_file])->save();
        // paket stabil lama dihapus bila sudah tidak dipakai kanal mana pun
        if (filled($lama) && $lama !== $project->package_file) {
            Storage::disk('local')->delete($lama);
        }

        return response()->json([
            'message' => "Versi {$project->version} dinaikkan ke kanal stabil.",
            'data' => ProjectResource::make($project->load('parent:id,name'))->resolve(),
        ]);
    }

    private function storeImageFile(Request $request, string $field): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $slug = Str::slug((string) $request->input('slug', 'project'));
        $folder = 'project-images/'.$slug;
        $file = $request->file($field);
        $fileName = $field.'.'.$file->getClientOriginalExtension();

        return $file->storeAs($folder, $fileName, 'public');
    }

    private function deleteImageFile(?string $path): void
    {
        if ($path === null || str_starts_with($path, 'http')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
