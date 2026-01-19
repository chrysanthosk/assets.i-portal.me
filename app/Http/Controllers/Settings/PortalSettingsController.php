<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PortalSetting;
use Illuminate\Http\Request;

class PortalSettingsController extends Controller
{
    public function edit()
    {
        $setting = PortalSetting::firstOrCreate(
            ['key' => 'portal_name'],
            ['value' => 'i-portal']
        );

        return view('settings.portal', [
            'portalName' => $setting->value,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'portal_name' => ['required','string','max:60'],
        ]);

        PortalSetting::updateOrCreate(
            ['key' => 'portal_name'],
            ['value' => $data['portal_name']]
        );

        return back()->with('success', 'Portal settings saved.');
    }
}
