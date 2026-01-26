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
        Schema::table('rounds', function (Blueprint $table) {
            $table->foreignId('amen_claimed_by_room_player_id')
                ->nullable()
                ->constrained('room_players')
                ->nullOnDelete();
            $table->timestamp('amen_claimed_at')->nullable();
            $table->integer('amen_awarded_points')->nullable();
            $table->timestamp('amen_resolved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropForeign(['amen_claimed_by_room_player_id']);
            $table->dropColumn([
                'amen_claimed_by_room_player_id',
                'amen_claimed_at',
                'amen_awarded_points',
                'amen_resolved_at',
            ]);
        });
    }
};
