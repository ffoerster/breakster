<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name', 30);
            $table->string('session_id')->nullable();
            $table->integer('score')->default(0);
            $table->boolean('is_host')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
            $table->index(['room_id', 'session_id']);
            $table->index(['room_id', 'is_host']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_players');
    }
};
