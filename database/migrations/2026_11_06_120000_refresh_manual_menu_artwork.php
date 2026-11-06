<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_categories')) {
            DB::table('menu_categories')
                ->where('code', 'sharing')
                ->update(['is_active' => false]);
        }

        if (! Schema::hasTable('menu_items')) {
            return;
        }

        collect([
            'apple-pie' => 'apple-pie',
            'coke' => 'cola',
            'coke-zero-sugar' => 'cola',
            'sprite' => 'lemon-lime-soda',
            'royal' => 'orange-soda',
            'iced-tea' => 'iced-tea',
            'orange-juice' => 'orange-juice',
            'apple-juice' => 'apple-juice',
            'coke-mcfloat' => 'cola-float',
            'cotton-candy-mcfloat' => 'cotton-candy-float',
            'raspberry-mcfloat' => 'raspberry-float',
            'grape-mcfloat' => 'grape-float',
            'royal-mcfloat' => 'orange-float',
        ])->each(fn (string $artwork, string $code) => DB::table('menu_items')
            ->where('code', $code)
            ->update(['artwork' => $artwork]));
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_categories')) {
            DB::table('menu_categories')
                ->where('code', 'sharing')
                ->update(['is_active' => true]);
        }

        if (! Schema::hasTable('menu_items')) {
            return;
        }

        DB::table('menu_items')->where('code', 'apple-pie')->update(['artwork' => 'dessert']);
        DB::table('menu_items')->whereIn('code', [
            'coke',
            'coke-zero-sugar',
            'sprite',
            'royal',
            'iced-tea',
            'orange-juice',
            'apple-juice',
            'coke-mcfloat',
            'cotton-candy-mcfloat',
            'raspberry-mcfloat',
            'grape-mcfloat',
            'royal-mcfloat',
        ])->update(['artwork' => 'drink']);
    }
};
