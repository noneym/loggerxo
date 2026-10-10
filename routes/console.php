<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('loggerxo:prune')->dailyAt('03:30')->withoutOverlapping()->runInBackground();
Schedule::command('queue:prune-failed --hours=168')->daily();
