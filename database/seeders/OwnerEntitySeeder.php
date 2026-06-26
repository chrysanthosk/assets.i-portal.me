<?php

namespace Database\Seeders;

use App\Models\OwnerEntity;
use Illuminate\Database\Seeder;

class OwnerEntitySeeder extends Seeder
{
    public function run(): void
    {
        // Put *your* defaults here (examples)
        $defaults = [
            'Personal',
            'Company',
        ];

        foreach ($defaults as $i => $name) {
            OwnerEntity::firstOrCreate(
                ['name' => $name],
                ['is_active' => true, 'sort_order' => $i + 1]
            );
        }
    }
}
