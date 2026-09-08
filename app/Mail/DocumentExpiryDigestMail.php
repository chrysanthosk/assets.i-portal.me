<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DocumentExpiryDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param Collection<int, \App\Models\AssetDocument> $documents */
    public function __construct(public Collection $documents, public int $days) {}

    public function build()
    {
        $expired = $this->documents->filter(fn ($d) => $d->expires_at->isPast())->count();
        $soon = $this->documents->count() - $expired;

        return $this->subject(sprintf('Documents: %d expired, %d expiring within %d days', $expired, $soon, $this->days))
            ->view('emails.document_expiry_digest');
    }
}
