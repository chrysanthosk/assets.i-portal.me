<?php

use Illuminate\Support\Facades\Schedule;

// Rent loop: create this month's expected payments, then chase confirmations.
Schedule::command('rent:generate-due')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('rent:send-reminders')->dailyAt('08:00')->withoutOverlapping();

// Weekly digest of expired / expiring documents (same recipients as rent reminders)
Schedule::command('documents:send-expiry-reminders')->weeklyOn(1, '08:15')->withoutOverlapping();
