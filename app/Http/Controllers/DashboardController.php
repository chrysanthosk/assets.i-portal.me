<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $hour = now()->hour;

        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        $fullName = trim(($request->user()->name ?? '') . ' ' . ($request->user()->surname ?? ''));

        return view('dashboard', [
            'greeting' => $greeting,
            'fullName' => $fullName ?: $request->user()->username,
        ]);
    }
}
