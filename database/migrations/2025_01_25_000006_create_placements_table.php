<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inserted_after_track_id')
                ->nullable()
                ->constrained('tracks')
                ->nullOnDelete();
            $table->boolean('is_correct')->nullable();
            $table->integer('points_earned')->default(0);
            $table->timestamps();

            $table->unique(['round_id', 'room_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placements');
    }
};
