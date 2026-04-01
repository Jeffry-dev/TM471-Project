<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('menu_items', 'category_id')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('category')
                    ->constrained('categories')
                    ->nullOnDelete();
            });
        }

        $categoryNames = DB::table('menu_items')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        foreach ($categoryNames as $name) {
            $exists = DB::table('categories')->where('name', $name)->exists();
            if (! $exists) {
                DB::table('categories')->insert([
                    'name' => $name,
                    'description' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $categoryIdByName = DB::table('categories')->pluck('id', 'name');
        foreach ($categoryIdByName as $name => $id) {
            DB::table('menu_items')
                ->where('category', $name)
                ->whereNull('category_id')
                ->update(['category_id' => $id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('menu_items', 'category_id')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });
        }
    }
};
