<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('inv:refresh-inspections')->dailyAt('02:30')->onOneServer();
Schedule::command('inv:notify-due')->weekdays()->at('07:00')->onOneServer();
Schedule::command('inv:audit-verify')->dailyAt('03:15')->onOneServer();
Schedule::command('model:prune')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
