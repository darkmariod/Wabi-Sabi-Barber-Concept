<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:full-flow {--fresh : Eliminar datos demo existentes antes de crear}', function () {
    $exitCode = $this->call(\App\Console\Commands\DemoFullFlow::class, [
        '--fresh' => $this->option('fresh'),
    ]);
    return $exitCode;
})->purpose('Flujo demo completo para clientes');
