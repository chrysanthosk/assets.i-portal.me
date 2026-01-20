<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AssetType;

class AssetTypeSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'Apartment',
            'House',
            'Mezonete',
            'Land',
            'Commercial',
            'Office',
        ];

        foreach ($defaults as $i => $name) {
            AssetType::firstOrCreate(
                ['name' => $name],
                ['is_active' => true, 'sort_order' => $i + 1]
            );
        }
    }
}
