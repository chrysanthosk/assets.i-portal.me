<?php

namespace App\Console\Commands;

use App\Support\DocumentReminders;
use Illuminate\Console\Command;

class SendDocumentReminders extends Command
{
    protected $signature = 'documents:send-expiry-reminders {--force : Send even if reminders are disabled}';

    protected $description = 'Email a digest of expired / soon-expiring documents';

    public function handle(): int
    {
        $sent = DocumentReminders::send((bool) $this->option('force'));
        $this->info($sent ? 'Digest sent ('.DocumentReminders::due()->count().' document(s)).' : 'Nothing to send.');

        return self::SUCCESS;
    }
}
