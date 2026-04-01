<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('menu_items', 'ingredients')) {
                $table->json('ingredients')->nullable();
            }
            if (! Schema::hasColumn('menu_items', 'allergens')) {
                $table->json('allergens')->nullable();
            }
            if (! Schema::hasColumn('menu_items', 'dietary_tags')) {
                $table->json('dietary_tags')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('menu_items', 'ingredients')) {
                $table->dropColumn('ingredients');
            }
            if (Schema::hasColumn('menu_items', 'allergens')) {
                $table->dropColumn('allergens');
            }
            if (Schema::hasColumn('menu_items', 'dietary_tags')) {
                $table->dropColumn('dietary_tags');
            }
        });
    }
};
