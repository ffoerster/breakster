<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_number')->default(1);
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('revealed_at')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'status']);
            $table->unique(['room_id', 'track_id']);
        });

        // Add foreign key from rooms to rounds now that rounds table exists
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreign('current_round_id')
                ->references('id')
                ->on('rounds')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign(['current_round_id']);
        });

        Schema::dropIfExists('rounds');
    }
};
