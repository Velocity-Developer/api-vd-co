<?php

namespace Database\Seeders;

use App\Models\Website;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WebsiteSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            [
                'domain' => 'apico-demo.test',
                'ip_address' => '192.168.10.10',
                'license_key' => 'APICO-2026-0001',
                'status' => 'active',
                'theme_version' => '1.0.0',
                'plugin_version' => '1.0.0',
                'wp_version' => '6.5.4',
                'php_version' => '8.3',
            ],
            [
                'domain' => 'client-one.test',
                'ip_address' => '192.168.10.11',
                'license_key' => 'APICO-2026-0002',
                'status' => 'active',
                'theme_version' => '1.1.0',
                'plugin_version' => '1.2.0',
                'wp_version' => '6.6.1',
                'php_version' => '8.3',
            ],
            [
                'domain' => 'expired-client.test',
                'ip_address' => '192.168.10.12',
                'license_key' => 'APICO-2026-0003',
                'status' => 'invalid',
                'theme_version' => '0.9.5',
                'plugin_version' => '0.9.8',
                'wp_version' => '6.4.5',
                'php_version' => '8.2',
            ],
        ])->each(function (array $website): void {
            Website::firstOrCreate(
                ['domain' => $website['domain']],
                $website,
            );
        });
    }
}
