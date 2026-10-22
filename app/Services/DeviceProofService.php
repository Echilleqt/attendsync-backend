<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class DeviceProofService
{
    public function deviceUuidForPublicKey(string $publicKey): ?string
    {
        $key = openssl_pkey_get_public($publicKey);
        $details = $key ? openssl_pkey_get_details($key) : false;

        if (
            !$details
            || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA
            || ($details['bits'] ?? 0) < 2048
        ) {
            return null;
        }

        $encodedKey = preg_replace('/-----[^-]+-----|\s+/', '', $details['key']);
        $der = base64_decode($encodedKey, true);

        return $der === false ? null : 'AS-' . hash('sha256', $der);
    }

    public function assertValid(Request $request, string $publicKey, string $field = 'device'): void
    {
        $deviceUuid = (string) $request->header('X-Device-Id', '');
        $timestamp = (string) $request->header('X-Device-Timestamp', '');
        $nonce = (string) $request->header('X-Device-Nonce', '');
        $signature = base64_decode((string) $request->header('X-Device-Signature', ''), true);
        $expectedDeviceUuid = $this->deviceUuidForPublicKey($publicKey);

        if (
            !$expectedDeviceUuid
            || !hash_equals($expectedDeviceUuid, $deviceUuid)
            || !ctype_digit($timestamp)
            || abs(now()->timestamp - (int) $timestamp) > 120
            || !preg_match('/^[a-f0-9]{64}$/', $nonce)
            || $signature === false
        ) {
            $this->reject($field);
        }

        $message = implode("\n", [
            'ATTENDSYNC-DEVICE-V1',
            strtoupper($request->method()),
            $request->getRequestUri(),
            $timestamp,
            $nonce,
        ]);

        if (openssl_verify($message, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            $this->reject($field);
        }

        $cacheKey = 'attendsync-device-proof:' . $deviceUuid . ':' . $nonce;
        if (!Cache::add($cacheKey, true, now()->addMinutes(3))) {
            $this->reject($field);
        }
    }

    private function reject(string $field): never
    {
        throw ValidationException::withMessages([
            $field => 'Device identity could not be verified. Please re-register this device.',
        ]);
    }
}
