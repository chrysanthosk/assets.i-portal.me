<?php

namespace App\Mail;

use App\Models\RentalPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * One email listing every payment that is due and still unconfirmed, each
 * row with its own signed Yes / No links.
 */
class RentCheckDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<int, array{received:string, not_received:string}> keyed by payment id */
    public array $links = [];

    /** @param Collection<int, RentalPayment> $payments */
    public function __construct(public Collection $payments)
    {
        $expires = now()->addDays(60);
        foreach ($payments as $p) {
            $this->links[$p->id] = [
                'received' => URL::temporarySignedRoute('rent.confirm', $expires, ['payment' => $p->id, 'answer' => 'received']),
                'not_received' => URL::temporarySignedRoute('rent.confirm', $expires, ['payment' => $p->id, 'answer' => 'not-received']),
            ];
        }
    }

    public function build()
    {
        $n = $this->payments->count();

        return $this->subject(sprintf('Rent check: %d payment%s waiting for your answer', $n, $n === 1 ? '' : 's'))
            ->view('emails.rent_check_digest');
    }
}
