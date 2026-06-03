<?php

namespace Database\Seeders;

use App\Models\License;
use App\Models\RequestLog;
use App\Models\Website;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RequestLogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            LicenseSeeder::class,
            WebsiteSeeder::class,
        ]);

        collect([
            [
                'domain' => 'apico-demo.test',
                'license_key' => 'APICO-2026-0001',
                'route' => '/api/validate-license',
                'method' => 'POST',
                'request' => ['domain' => 'apico-demo.test', 'license_key' => 'APICO-2026-0001'],
                'status' => 200,
            ],
            [
                'domain' => 'client-one.test',
                'license_key' => 'APICO-2026-0002',
                'route' => '/api/check-update',
                'method' => 'GET',
                'request' => ['domain' => 'client-one.test', 'plugin_version' => '1.2.0'],
                'status' => 200,
            ],
            [
                'domain' => 'expired-client.test',
                'license_key' => 'APICO-2026-0003',
                'route' => '/api/validate-license',
                'method' => 'POST',
                'request' => ['domain' => 'expired-client.test', 'license_key' => 'APICO-2026-0003'],
                'status' => 403,
            ],
        ])->each(function (array $requestLog): void {
            $website = Website::where('domain', $requestLog['domain'])->firstOrFail();
            $license = License::where('code', $requestLog['license_key'])->firstOrFail();

            RequestLog::firstOrCreate(
                [
                    'route' => $requestLog['route'],
                    'method' => $requestLog['method'],
                    'status' => $requestLog['status'],
                    'website_id' => $website->id,
                    'license_id' => $license->id,
                ],
                [
                    'request' => $requestLog['request'],
                ],
            );
        });
    }
}
