<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailChangeOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $fullName,
        public string $otp
    ) {}

    public function build()
    {
        return $this->subject('Confirm your email change (OTP)')
            ->view('emails.email_change_otp');
    }
}
