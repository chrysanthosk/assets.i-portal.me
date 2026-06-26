<?php

namespace Database\Seeders;

use App\Models\AssetType;
use Illuminate\Database\Seeder;

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
