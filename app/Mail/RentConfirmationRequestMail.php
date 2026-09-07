<?php

namespace App\Mail;

use App\Models\RentalPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class RentConfirmationRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $receivedUrl;

    public string $notReceivedUrl;

    public function __construct(public RentalPayment $payment)
    {
        $expires = now()->addDays(60);

        $this->receivedUrl = URL::temporarySignedRoute('rent.confirm', $expires, [
            'payment' => $payment->id, 'answer' => 'received',
        ]);
        $this->notReceivedUrl = URL::temporarySignedRoute('rent.confirm', $expires, [
            'payment' => $payment->id, 'answer' => 'not-received',
        ]);
    }

    public function build()
    {
        $asset = $this->payment->asset?->name ?? 'Property';
        $subject = sprintf(
            'Rent check: %s %s for %s — %s',
            $this->payment->currency,
            number_format((float) $this->payment->amount, 2),
            $asset,
            $this->payment->periodLabel()
        );

        return $this->subject($subject)->view('emails.rent_confirmation');
    }
}
