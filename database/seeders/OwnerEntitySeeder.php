<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OwnerEntity;

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
