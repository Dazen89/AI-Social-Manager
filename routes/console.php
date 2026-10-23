<?php

use App\Services\EditorialScheduleRunner;
use App\Services\XPostMetricsCollector;
use App\Services\XPostPublisher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('editorial:run-due', function (EditorialScheduleRunner $runner): void {
    $this->info('Piani editoriali elaborati: '.$runner->runDue());
})->purpose('Importa le nuove uscite e prepara le proposte editoriali dovute');

Schedule::command('editorial:run-due')->everyMinute()->withoutOverlapping(10);

Artisan::command('editorial:publish-due', function (XPostPublisher $publisher): void {
    $this->info('Post X pubblicati: '.$publisher->runDue());
})->purpose('Pubblica su X i post idonei e approvati dal piano editoriale');

Schedule::command('editorial:publish-due')->everyMinute()->withoutOverlapping(10);

Artisan::command('editorial:collect-x-metrics', function (XPostMetricsCollector $collector): void {
    $this->info('Post X misurati: '.$collector->runDue());
})->purpose('Raccoglie le metriche dei post X pubblicati da Monitor');

Schedule::command('editorial:collect-x-metrics')->everySixHours()->withoutOverlapping(30);
