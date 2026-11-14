<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'paket', 'slug', 'version', 'requires_wp', 'requires_php', 'plugin_wp_required', 'github_url', 'package_file', 'package_external_url', 'icon', 'screenshot', 'description', 'type', 'parent_id', 'paket_privat', 'domain_uji'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plugin_wp_required' => 'boolean',
            'paket_privat' => 'boolean',
            'domain_uji' => 'array',
        ];
    }

    /**
     * Project yang boleh tampil di API publik/installer: paket privat (fork BB dkk.) disembunyikan.
     *
     * @param  Builder<Project>  $query
     */
    public function scopePublik(Builder $query): void
    {
        $query->where('paket_privat', false);
    }

    /** Domain kanal uji (huruf kecil, tanpa www.). */
    public function adalahDomainUji(?string $domain): bool
    {
        $domain = self::rapikanDomain($domain);

        return $domain !== '' && in_array($domain, array_map([self::class, 'rapikanDomain'], $this->domain_uji ?? []), true);
    }

    /**
     * Rilis yang ditawarkan ke satu domain: domain uji mendapat rilis terbaru, lainnya rilis stabil.
     *
     * @return array{versi: string, paket: string}|null
     */
    public function rilisUntuk(?string $domain): ?array
    {
        if ($this->adalahDomainUji($domain) && filled($this->version) && filled($this->package_file)) {
            return ['versi' => (string) $this->version, 'paket' => (string) $this->package_file];
        }

        return filled($this->versi_stabil) && filled($this->paket_stabil)
            ? ['versi' => (string) $this->versi_stabil, 'paket' => (string) $this->paket_stabil]
            : null;
    }

    public static function rapikanDomain(?string $domain): string
    {
        $domain = strtolower(trim((string) $domain));
        $domain = (string) preg_replace('#^https?://#', '', $domain);
        $domain = explode('/', $domain)[0];

        return (string) preg_replace('/^www\./', '', $domain);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ProjectChangelog, $this>
     */
    public function changelogs(): HasMany
    {
        return $this->hasMany(ProjectChangelog::class);
    }
}
