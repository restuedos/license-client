<?php

namespace Edzero\LicenseClient\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class LicenseService
{
    /**
     * When false, {@see EnsureLicenseIsValid} does not block requests (no verify URL configured
     * or enforcement explicitly disabled). Activation is still available if a URL is set.
     */
    public function shouldEnforce(): bool
    {
        if (trim((string) config('license-client.verify_url')) === '') {
            return false;
        }

        return (bool) config('license-client.enforce', true);
    }

    public function isVerified(): bool
    {
        if (! $this->shouldEnforce()) {
            return true;
        }

        if ($this->resolvedLocalHmacSecret() === '') {
            return false;
        }

        $status = $this->readStatus();

        if (! $this->verifyLocalIntegrity($status)) {
            return false;
        }

        $licenseToken = Arr::get($status, 'license_token');
        $signature = Arr::get($status, 'signature');

        if (! is_string($licenseToken) || ! is_string($signature)) {
            return false;
        }

        $claims = $this->verifyServerSignatureAndDecode($licenseToken, $signature);

        if ($claims === null) {
            return false;
        }

        return $this->claimsAreUsable($claims);
    }

    /**
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    public function activate(string $licenseKey): array
    {
        $verifyUrl = (string) config('license-client.verify_url');

        if ($verifyUrl === '') {
            throw new RuntimeException('LICENSE_VERIFY_URL belum dikonfigurasi.');
        }

        $this->assertLicenseServerHostAllowedForUrl($verifyUrl);

        $encryptedKey = $this->encryptLicenseKey($licenseKey);
        $timestamp = Carbon::now()->toIso8601String();
        $nonce = bin2hex(random_bytes(16));

        $requestPayload = [
            'payload' => $encryptedKey,
            'app_url' => (string) config('app.url'),
            'environment' => (string) config('app.env'),
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'device_fingerprint' => $this->deviceFingerprint(),
        ];

        $requestSignature = $this->signOutboundRequest($requestPayload);
        if ($requestSignature !== null) {
            $requestPayload['request_signature'] = $requestSignature;
        }

        try {
            $response = Http::timeout((int) config('license-client.request_timeout', 10))
                ->acceptJson()
                ->post($verifyUrl, $requestPayload);
        } catch (ConnectionException $e) {
            return [
                'ok' => false,
                'message' => 'Tidak dapat menghubungi license server. Periksa jaringan, DNS, dan sertifikat TLS (misalnya dari dalam container).',
                'data' => [
                    'exception' => $e->getMessage(),
                ],
            ];
        }

        $interpreted = $this->interpretLicenseVerifyResponse($response);
        if (! $interpreted['ok']) {
            return $interpreted;
        }

        /** @var array<string, mixed> $body */
        $body = $interpreted['body'];

        $licenseToken = Arr::get($body, 'license_token');
        $signature = Arr::get($body, 'signature');

        if (! is_string($licenseToken) || ! is_string($signature)) {
            return [
                'ok' => false,
                'message' => 'Response lisensi tidak lengkap (token/signature).',
                'data' => $body,
            ];
        }

        $claims = $this->verifyServerSignatureAndDecode($licenseToken, $signature);
        if ($claims === null || ! $this->claimsAreUsable($claims)) {
            return [
                'ok' => false,
                'message' => 'Signature lisensi tidak valid atau token sudah tidak berlaku.',
                'data' => $body,
            ];
        }

        $status = [
            'verified' => true,
            'verified_at' => Carbon::now()->toIso8601String(),
            'license_token' => $licenseToken,
            'signature' => $signature,
            'claims' => $claims,
        ];

        $this->writeStatus($status);

        return [
            'ok' => true,
            'message' => (string) Arr::get($body, 'message', 'License berhasil diverifikasi.'),
            'data' => $body,
        ];
    }

    /**
     * License-server may return JSON `{ valid, message }` with HTTP 4xx; Laravel treats
     * that as `$response->failed()`, so business errors must be read from the body first.
     *
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, message: string, data: array<string, mixed>}
     */
    private function interpretLicenseVerifyResponse(Response $response): array
    {
        $body = $this->decodeLicenseVerifyResponseBody($response);

        if (array_key_exists('valid', $body)) {
            if (! (bool) Arr::get($body, 'valid')) {
                return [
                    'ok' => false,
                    'message' => (string) Arr::get($body, 'message', 'License key tidak valid.'),
                    'data' => array_merge($body, ['http_status' => $response->status()]),
                ];
            }

            return ['ok' => true, 'body' => $body];
        }

        // Laravel validation errors (no `valid` key) — same HTTP 422 as business errors.
        if ($response->failed() && isset($body['errors']) && is_array($body['errors'])) {
            $flattened = Arr::flatten($body['errors']);
            $first = $flattened !== [] && is_string($flattened[0]) ? $flattened[0] : null;
            $message = is_string($first) && $first !== ''
                ? $first
                : (string) ($body['message'] ?? 'Permintaan verifikasi ditolak oleh license server.');

            return [
                'ok' => false,
                'message' => $message,
                'data' => array_merge($body, ['http_status' => $response->status()]),
            ];
        }

        if ($response->failed() || $body === []) {
            return [
                'ok' => false,
                'message' => 'License Verification API gagal merespons dengan sukses.',
                'data' => [
                    'status' => $response->status(),
                    'response' => $body !== [] ? $body : $response->body(),
                ],
            ];
        }

        return [
            'ok' => false,
            'message' => 'Response lisensi tidak dikenali.',
            'data' => [
                'http_status' => $response->status(),
                'body' => $body,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeLicenseVerifyResponseBody(Response $response): array
    {
        $decoded = $response->json();
        if (is_array($decoded)) {
            return $decoded;
        }

        $raw = $response->body();
        if ($raw === '' || ! is_string($raw)) {
            return [];
        }

        /** @var mixed $parsed */
        $parsed = json_decode($raw, true);

        return is_array($parsed) ? $parsed : [];
    }

    private function resolvedLocalHmacSecret(): string
    {
        return trim((string) config('license-client.local_hmac_secret'));
    }

    /**
     * @throws RuntimeException
     */
    private function assertLicenseServerHostAllowedForUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = Arr::get($parts, 'scheme');
        $host = Arr::get($parts, 'host');

        $requireHttps = (bool) config('license-client.require_https', true);

        if ($requireHttps && $scheme !== 'https') {
            throw new RuntimeException('License server URL wajib menggunakan HTTPS (LICENSE_REQUIRE_HTTPS=true).');
        }

        if (! is_string($host) || $host === '') {
            throw new RuntimeException('License server URL tidak valid.');
        }

        /** @var array<int, string> $allowedHosts */
        $allowedHosts = config('license-client.allowed_hosts', []);

        if ($requireHttps && $allowedHosts === []) {
            throw new RuntimeException(
                'LICENSE_ALLOWED_HOSTS wajib diisi (hostname license server) saat LICENSE_REQUIRE_HTTPS=true untuk mencegah salah endpoint / MITM.'
            );
        }

        if ($allowedHosts !== [] && ! in_array($host, $allowedHosts, true)) {
            throw new RuntimeException("Host {$host} tidak ada di LICENSE_ALLOWED_HOSTS.");
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signOutboundRequest(array $payload): ?string
    {
        $secret = (string) config('license-client.request_hmac_secret');

        if ($secret === '') {
            return null;
        }

        $data = implode('|', [
            (string) Arr::get($payload, 'payload', ''),
            (string) Arr::get($payload, 'app_url', ''),
            (string) Arr::get($payload, 'environment', ''),
            (string) Arr::get($payload, 'timestamp', ''),
            (string) Arr::get($payload, 'nonce', ''),
            (string) Arr::get($payload, 'device_fingerprint', ''),
        ]);

        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * @throws RuntimeException
     */
    private function encryptLicenseKey(string $licenseKey): string
    {
        $publicKeyPath = (string) config('license-client.encryption_public_key_path');

        if (! File::exists($publicKeyPath)) {
            throw new RuntimeException("File encryption public key tidak ditemukan di {$publicKeyPath}");
        }

        $publicKeyContent = File::get($publicKeyPath);
        $publicKey = openssl_pkey_get_public($publicKeyContent);

        if ($publicKey === false) {
            throw new RuntimeException('Encryption public key tidak valid.');
        }

        $encrypted = null;
        $success = openssl_public_encrypt($licenseKey, $encrypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);
        unset($publicKey);

        if (! $success || ! is_string($encrypted)) {
            throw new RuntimeException('Gagal mengenkripsi license key.');
        }

        return base64_encode($encrypted);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function verifyServerSignatureAndDecode(string $licenseToken, string $signature): ?array
    {
        $verificationKeyPath = (string) config('license-client.verification_public_key_path');
        if (! File::exists($verificationKeyPath)) {
            return null;
        }

        $publicKey = openssl_pkey_get_public(File::get($verificationKeyPath));
        if ($publicKey === false) {
            return null;
        }

        $binarySignature = base64_decode($signature, true);
        if ($binarySignature === false) {
            return null;
        }

        $verified = openssl_verify($licenseToken, $binarySignature, $publicKey, OPENSSL_ALGO_SHA256);
        unset($publicKey);

        if ($verified !== 1) {
            return null;
        }

        $json = base64_decode($licenseToken, true);
        if ($json === false) {
            return null;
        }

        /** @var array<string, mixed> $claims */
        $claims = json_decode($json, true) ?? [];
        if ($claims === []) {
            return null;
        }

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function claimsAreUsable(array $claims): bool
    {
        $appUrl = (string) config('app.url');
        $environment = (string) config('app.env');
        $clockSkew = (int) config('license-client.clock_skew_seconds', 120);

        $claimUrl = (string) Arr::get($claims, 'app_url', '');
        $claimEnv = (string) Arr::get($claims, 'environment', '');
        $expiresAt = (string) Arr::get($claims, 'expires_at', '');

        if ($claimUrl !== $appUrl || $claimEnv !== $environment) {
            return false;
        }

        $claimBucket = Arr::get($claims, 'environment_bucket');
        if (is_string($claimBucket) && $claimBucket !== '') {
            if ($claimBucket !== $this->currentEnvironmentBucket()) {
                return false;
            }
        }

        if ($expiresAt === '') {
            return false;
        }

        try {
            $expiry = Carbon::parse($expiresAt);
        } catch (Throwable) {
            return false;
        }

        return $expiry->greaterThanOrEqualTo(Carbon::now()->subSeconds($clockSkew));
    }

    /**
     * @return array<string, mixed>
     */
    private function readStatus(): array
    {
        $path = $this->statusFilePath();

        if (! File::exists($path)) {
            return ['verified' => false];
        }

        $json = File::get($path);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true) ?? [];

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $status
     *
     * @throws RuntimeException
     */
    private function writeStatus(array $status): void
    {
        $path = $this->statusFilePath();
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $status['local_hmac'] = $this->makeLocalHmac($status);
        File::put($path, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function verifyLocalIntegrity(array $status): bool
    {
        if (! Arr::has($status, 'local_hmac')) {
            return false;
        }

        $provided = (string) Arr::pull($status, 'local_hmac');
        $expected = $this->makeLocalHmac($status);

        return hash_equals($expected, $provided);
    }

    /**
     * @param  array<string, mixed>  $status
     *
     * @throws RuntimeException
     */
    private function makeLocalHmac(array $status): string
    {
        if ($this->resolvedLocalHmacSecret() === '') {
            throw new RuntimeException('LICENSE_LOCAL_HMAC_SECRET atau APP_KEY wajib diisi untuk menyimpan status lisensi.');
        }

        ksort($status);

        return hash_hmac('sha256', json_encode($status, JSON_UNESCAPED_SLASHES), $this->resolvedLocalHmacSecret());
    }

    private function statusFilePath(): string
    {
        return (string) config('license-client.status_file');
    }

    private function currentEnvironmentBucket(): string
    {
        $e = strtolower(trim((string) config('app.env')));
        /** @var array<int, string> $names */
        $names = config('license-client.production_environment_names', ['production', 'prod']);

        return in_array($e, $names, true) ? 'prod' : 'nonprod';
    }

    private function deviceFingerprint(): string
    {
        $configured = (string) config('license-client.device_fingerprint', '');
        if ($configured !== '') {
            return substr($configured, 0, 128);
        }

        return hash('sha256', implode('|', [
            (string) config('app.url'),
            (string) config('app.env'),
            php_uname('n'),
        ]));
    }
}
