<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('properties')->where('slug', 'casa-bloom')->update([
            'name' => 'santhinest',
            'address' => 'Hafeezpet',
            'latitude' => 17.4926324,
            'longitude' => 78.3698192,
            'contact_phone' => '+919742370900, +919742370700',
        ]);

        DB::table('properties')
            ->where('slug', 'casa-bloom')
            ->whereNotNull('description')
            ->eachById(function (object $property): void {
                DB::table('properties')->where('id', $property->id)->update([
                    'description' => str_ireplace('Casa Bloom', 'santhinest', $property->description),
                ]);
            });
    }

    public function down(): void
    {
        // Property details may have been edited since this update ran.
    }
};
