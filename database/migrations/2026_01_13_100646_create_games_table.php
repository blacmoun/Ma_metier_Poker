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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('waiting');
            $table->timestamp('timer_at')->nullable();
            $table->integer('current_turn')->default(0);
            $table->integer('dealer_index')->default(0);
            $table->json('deck')->nullable();
            $table->json('community_cards')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
