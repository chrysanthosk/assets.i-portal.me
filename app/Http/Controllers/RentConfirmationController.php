<?php

namespace App\Http\Controllers;

use App\Models\RentalPayment;
use App\Support\Audit;

/**
 * Landing page for the signed links in the rent reminder email.
 */
class RentConfirmationController extends Controller
{
    public function show(RentalPayment $payment, string $answer)
    {
        $payment->load(['asset', 'rental.tenant']);

        return view('rent.confirm', [
            'payment' => $payment,
            'answer' => $answer,
            'alreadyAnswered' => $payment->status !== RentalPayment::STATUS_PENDING,
        ]);
    }

    public function store(RentalPayment $payment, string $answer)
    {
        $payment->load(['asset', 'rental.tenant']);

        $applied = false;
        if ($payment->status === RentalPayment::STATUS_PENDING) {
            $applied = true;
            $old = $payment->toArray();

            if ($answer === 'received') {
                $payment->markReceived();
                Audit::log('rental_payment.confirmed_received', $payment, $old, $payment->fresh()->toArray());
            } else {
                $payment->markNotReceived();
                Audit::log('rental_payment.confirmed_not_received', $payment, $old, $payment->fresh()->toArray());
            }
        }

        return view('rent.confirm', [
            'payment' => $payment->fresh(['asset', 'rental.tenant']),
            'answer' => $answer,
            'alreadyAnswered' => true,
            'done' => $applied,
        ]);
    }
}
