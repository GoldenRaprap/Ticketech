<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TurnstileVerifier
{
    public const SITE_KEY_SETTING = 'security.turnstile.site_key';

    public const SECRET_KEY_SETTING = 'security.turnstile.secret_key';

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * @var array{enabled: bool, configured: bool, site_key: ?string, secret_key: ?string}|null
     */
    private ?array $cachedCredentials = null;

    public function isEnabled(): bool
    {
        return $this->credentials()['enabled'];
    }

    public function isConfigured(): bool
    {
        return $this->credentials()['configured'];
    }

    public function siteKey(): ?string
    {
        return $this->credentials()['site_key'];
    }

    public function verify(string $token): bool
    {
        $credentials = $this->credentials();
        $secretKey = $credentials['secret_key'];

        if (! $credentials['configured'] || $secretKey === null) {
            Log::error('Cloudflare Turnstile is enabled but is not fully configured.');

            return false;
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(5)
                ->post(self::VERIFY_URL, [
                    'secret' => $secretKey,
                    'response' => $token,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Cloudflare Turnstile verification could not reach the verification service.', [
                'exception' => $exception,
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Cloudflare Turnstile verification returned an unsuccessful HTTP response.', [
                'status' => $response->status(),
            ]);

            return false;
        }

        return $response->json('success') === true;
    }

    /**
     * @return array{enabled: bool, configured: bool, site_key: ?string, secret_key: ?string}
     */
    private function credentials(): array
    {
        if ($this->cachedCredentials !== null) {
            return $this->cachedCredentials;
        }

        $settings = Schema::hasTable('system_settings')
            ? DB::table('system_settings')
                ->whereIn('setting', [self::SITE_KEY_SETTING, self::SECRET_KEY_SETTING])
                ->pluck('value', 'setting')
            : collect();
        $siteKey = $settings->get(self::SITE_KEY_SETTING, config('services.turnstile.site_key'));
        $secretKey = $settings->has(self::SECRET_KEY_SETTING)
            ? $this->decryptSecretKey((string) $settings->get(self::SECRET_KEY_SETTING))
            : config('services.turnstile.secret_key');
        $siteKey = is_string($siteKey) && $siteKey !== '' ? $siteKey : null;
        $secretKey = is_string($secretKey) && $secretKey !== '' ? $secretKey : null;

        $this->cachedCredentials = [
            'enabled' => $settings->has(self::SITE_KEY_SETTING)
                || $settings->has(self::SECRET_KEY_SETTING)
                || filled(config('services.turnstile.site_key'))
                || filled(config('services.turnstile.secret_key')),
            'configured' => $siteKey !== null && $secretKey !== null,
            'site_key' => $siteKey,
            'secret_key' => $secretKey,
        ];

        return $this->cachedCredentials;
    }

    private function decryptSecretKey(string $encryptedSecretKey): ?string
    {
        try {
            return Crypt::decryptString($encryptedSecretKey);
        } catch (DecryptException|MissingAppKeyException $exception) {
            Log::error('The saved Cloudflare Turnstile secret key could not be decrypted.', [
                'exception' => $exception,
            ]);

            return null;
        }
    }
}
