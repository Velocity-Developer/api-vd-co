<?php

namespace Database\Seeders;

use App\Models\License;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LicenseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            'APICO-2026-0001',
            'APICO-2026-0002',
            'APICO-2026-0003',
            'APICO-2026-0004',
            'APICO-2026-0005',
            'APICO-2026-0006',
            'APICO-2026-0007',
            'APICO-2026-0008',
            'APICO-2026-0009',
            'APICO-2026-0010',
        ])->each(function (string $code): void {
            License::firstOrCreate(
                ['code' => $code],
                [
                    'is_active' => true,
                    'expires_at' => now()->addYear(),
                ],
            );
        });
    }
}
