<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ApplySystemMailSettings
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Schema::hasTable('system_settings')) {
            $settings = DB::table('system_settings')
                ->whereIn('setting', [
                    'mail.from.address',
                    'mail.from.name',
                    'mail.smtp.host',
                    'mail.smtp.port',
                    'mail.smtp.encryption',
                    'mail.smtp.username',
                    'mail.smtp.password',
                ])
                ->pluck('value', 'setting');
            $encryption = $settings->get('mail.smtp.encryption');

            config([
                'mail.from.address' => $settings->get('mail.from.address', config('mail.from.address')),
                'mail.from.name' => $settings->get('mail.from.name', config('mail.from.name')),
                'mail.mailers.smtp.host' => $settings->get('mail.smtp.host', config('mail.mailers.smtp.host')),
                'mail.mailers.smtp.port' => (int) $settings->get('mail.smtp.port', config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.scheme' => match ($encryption) {
                    'ssl' => 'smtps',
                    'tls', 'none' => 'smtp',
                    default => config('mail.mailers.smtp.scheme'),
                },
                'mail.mailers.smtp.username' => $settings->get('mail.smtp.username', config('mail.mailers.smtp.username')),
                'mail.mailers.smtp.password' => $settings->has('mail.smtp.password')
                    ? Crypt::decryptString($settings->get('mail.smtp.password'))
                    : config('mail.mailers.smtp.password'),
                'mail.mailers.smtp.auto_tls' => $encryption === null
                    ? config('mail.mailers.smtp.auto_tls', true)
                    : $encryption !== 'none',
            ]);
        }

        return $next($request);
    }
}
