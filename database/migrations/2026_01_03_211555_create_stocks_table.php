<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique(); // DEVI AGGIUNGERE QUESTA
            $table->string('name')->nullable(); // E QUESTA
            $table->decimal('current_price', 15, 2)->nullable(); // E QUESTA
            $table->decimal('change', 15, 2)->nullable(); // E QUESTA
            $table->decimal('change_percent', 8, 4)->nullable(); // E QUESTA
            $table->json('data')->nullable(); // E QUESTA
            $table->timestamp('last_updated')->nullable(); // E QUESTA
            $table->timestamps(); // QUESTA C'È GIÀ
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};