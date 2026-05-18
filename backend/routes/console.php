<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('app:heartbeat', function () {
    $this->info('Meeting AI is alive.');
});
