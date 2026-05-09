<?php

namespace App\Services\GunAz;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GunAzApiClient
{
    public function pushPriceSnapshotsPayload(array $payload): Response
    {
        return $this->post(config('gun_az.endpoints.price_snapshots'), $payload);
    }

    public function pushBasketSnapshotsPayload(array $payload): Response
    {
        return $this->post(config('gun_az.endpoints.basket_snapshots'), $payload);
    }

    private function post(string $endpoint, array $payload): Response
    {
        $url = config('gun_az.base_url').$endpoint;
        $request = Http::timeout((int) config('gun_az.timeout', 60))
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'parser.gun.az/1.0']);

        $token = config('gun_az.token');
        if ($token !== null && $token !== '') {
            $request = $request->withToken((string) $token);
        }

        return $request->post($url, $payload);
    }
}
