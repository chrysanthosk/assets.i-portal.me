<?php

namespace App\Console\Commands;

use App\Support\RentSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDueRent extends Command
{
    protected $signature = 'rent:generate-due {--as-of= : Generate for the month containing this date (default: today)}';

    protected $description = 'Create the expected monthly rent payment for every active agreement (idempotent)';

    public function handle(): int
    {
        $asOf = $this->option('as-of') ? Carbon::parse($this->option('as-of')) : now();
        $created = RentSchedule::generateDue($asOf);

        $this->info(sprintf('%d payment(s) generated for %s.', $created, $asOf->format('F Y')));

        return self::SUCCESS;
    }
}
