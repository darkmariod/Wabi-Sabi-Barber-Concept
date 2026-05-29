<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Services\LabelPdfService;
use App\Services\SerialGeneratorService;
use App\Services\ZebraZplService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SerialGeneratorService::class, function ($app) {
            return new SerialGeneratorService();
        });

        $this->app->singleton(ZebraZplService::class, function ($app) {
            return new ZebraZplService();
        });

        $this->app->singleton(LabelPdfService::class, function ($app) {
            return new LabelPdfService();
        });
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}