<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\License;
use App\Models\Website;

class LicenseController extends Controller
{
    public function index(Request $request, License $license)
    {
        // Mengambil nilai header 'license'
        $license = $request->header('license');

        // Cek jika license tidak valid
        $license = License::where('code', $license)->first();

        // Jika license tidak valid
        if (!$license) {
            return response()->json([
                'status' => false,
                'message' => 'License not found',
            ], 404);
        }

        // Mengambil nilai header 'source'
        $source = $request->header('source');
        $website = $source ? Website::where('domain', $source)->first() : null;

        //update License Key website
        if ($website) {
            $website->license_key = $license->code;
            $website->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => [
                'status' => $license->toArray()['is_active'],
                'is_active' => $license->toArray()['is_active'],
                'code' => $license->toArray()['code'],
                'website' => $website ? $website->toArray()['domain'] : null,
            ],
        ]);
    }
}
