<?php

namespace App\Console\Commands;

use App\Support\RentSchedule;
use Illuminate\Console\Command;

class SendRentReminders extends Command
{
    protected $signature = 'rent:send-reminders {--force : Send even if reminders are disabled or one went out recently}';

    protected $description = 'Email "did the rent arrive?" for every due payment that is still unconfirmed';

    public function handle(): int
    {
        $sent = RentSchedule::sendReminders(null, (bool) $this->option('force'));

        $this->info($sent ? sprintf('Digest with %d payment(s) sent to: %s', $sent, implode(', ', RentSchedule::recipients())) : 'Nothing to send.');

        return self::SUCCESS;
    }
}
