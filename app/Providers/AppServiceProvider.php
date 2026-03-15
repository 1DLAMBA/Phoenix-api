<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('brevo-api', function (array $config) {
            $apiKey = (string) config('services.brevo.api_key');

            if ($apiKey === '') {
                throw new InvalidArgumentException('BREVO_API_KEY is not configured.');
            }

            return new BrevoApiTransport(
                new Client(),
                $apiKey,
                config('mail.from.address'),
                config('mail.from.name')
            );
        });
    }
}
