<?php

namespace Tests\Feature;

use App\Models\FxRate;
use App\Models\PortalSetting;
use App\Support\Fx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_currency_options_include_base_rates_in_use_and_defaults(): void
    {
        PortalSetting::set('base_currency', 'EUR');
        FxRate::create(['currency' => 'AED', 'rate_to_base' => 0.23]);
        Fx::flush();

        $list = Fx::currencies();
        $this->assertSame('EUR', $list[0]);
        foreach (['AED', 'USD', 'GBP'] as $c) {
            $this->assertContains($c, $list);
        }
        $this->assertSame($list, array_values(array_unique($list)));
    }
}
