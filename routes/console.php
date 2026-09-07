<?php

use Illuminate\Support\Facades\Schedule;

// Rent loop: create this month's expected payments, then chase confirmations.
Schedule::command('rent:generate-due')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('rent:send-reminders')->dailyAt('08:00')->withoutOverlapping();
