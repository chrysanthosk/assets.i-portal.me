<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\FxRate;
use App\Models\PortalSetting;
use App\Support\Audit;
use App\Support\Fx;
use Illuminate\Http\Request;

class CurrenciesController extends Controller
{
    public function edit()
    {
        $baseCurrency = Fx::base();
        $rates = FxRate::orderBy('currency')->get();

        return view('settings.currencies', compact('baseCurrency', 'rates'));
    }

    public function updateBase(Request $request)
    {
        $data = $request->validate([
            'base_currency' => ['required', 'string', 'size:3', 'alpha'],
        ]);

        PortalSetting::updateOrCreate(
            ['key' => 'base_currency'],
            ['value' => strtoupper($data['base_currency'])]
        );

        Audit::log('settings.base_currency_updated', null, null, ['base_currency' => strtoupper($data['base_currency'])]);

        return back()->with('success', 'Base currency updated.');
    }

    public function storeRate(Request $request)
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'rate_to_base' => ['required', 'numeric', 'min:0'],
        ]);

        $currency = strtoupper($data['currency']);

        $rate = FxRate::updateOrCreate(
            ['currency' => $currency],
            ['rate_to_base' => $data['rate_to_base']]
        );

        Audit::log('fx_rate.saved', $rate, null, $rate->toArray());

        return back()->with('success', "Rate for {$currency} saved.");
    }

    public function destroyRate(FxRate $rate)
    {
        $old = $rate->toArray();
        $rate->delete();

        Audit::log('fx_rate.deleted', $rate, $old, null);

        return back()->with('success', 'Rate deleted.');
    }
}
