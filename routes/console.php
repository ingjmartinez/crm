<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('auto-proceso:run-due')->everyMinute();
Schedule::command('chatbot:close-inactive-sessions')->everyMinute();
Schedule::command('bi:capturar-ventas-hora')->everyFiveMinutes()->between('06:00', '22:00')->withoutOverlapping(10)->appendOutputTo(storage_path('logs/bi-captura.log'));
Schedule::command('bi:resumir-ventas-dia')->dailyAt('05:00')->withoutOverlapping(180);
Schedule::command('bi:resumir-ventas-dia')->dailyAt('06:30')->withoutOverlapping(180);
Schedule::command('bi:resumir-ventas-dia')->dailyAt('12:30')->withoutOverlapping(180);
Schedule::command('bi:resumir-ventas-dia --solo-faltantes')->dailyAt('16:30')->withoutOverlapping(180);
