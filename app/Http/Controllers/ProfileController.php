<?php

namespace App\Http\Controllers;

use App\Mail\EmailChangeOtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile', [
            'user' => $request->user(),
        ]);
    }

    public function updateName(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->name = $data['name'];
        $user->surname = $data['surname'];
        $user->save();

        return back()->with('success', 'Profile name updated.');
    }

    public function requestEmailChange(Request $request)
    {
        $data = $request->validate([
            'new_email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $user = $request->user();

        $otp = (string) random_int(100000, 999999);
        $user->pending_email = $data['new_email'];
        $user->email_otp_hash = Hash::make($otp);
        $user->email_otp_expires_at = now()->addMinutes(10);
        $user->save();

        Mail::to($user->pending_email)->send(new EmailChangeOtpMail(
            fullName: trim(($user->name ?? '').' '.($user->surname ?? '')) ?: $user->username,
            otp: $otp
        ));

        return back()->with('success', 'OTP sent to the new email address. Please confirm below.');
    }

    public function confirmEmailChange(Request $request)
    {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        if (! $user->pending_email || ! $user->email_otp_hash || ! $user->email_otp_expires_at) {
            return back()->with('error', 'No pending email change request found.');
        }

        if (now()->greaterThan($user->email_otp_expires_at)) {
            return back()->with('error', 'OTP expired. Please request email change again.');
        }

        if (! Hash::check($data['otp'], $user->email_otp_hash)) {
            return back()->with('error', 'Invalid OTP.');
        }

        $user->email = $user->pending_email;
        $user->pending_email = null;
        $user->email_otp_hash = null;
        $user->email_otp_expires_at = null;
        $user->save();

        return back()->with('success', 'Email updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->with('error', 'Current password is incorrect.');
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        // When password changes, force 2FA re-check next time (optional)
        $request->session()->forget('2fa_passed');

        return back()->with('success', 'Password updated successfully.');
    }
}
