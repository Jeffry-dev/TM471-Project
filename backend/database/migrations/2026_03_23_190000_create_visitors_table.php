<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->text('user_agent')->nullable();
            $table->timestamp('visited_at')->useCurrent();

            $table->index('visited_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
