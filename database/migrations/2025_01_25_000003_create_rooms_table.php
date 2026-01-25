<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->foreignId('host_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pack_id')->constrained()->cascadeOnDelete();
            $table->string('state')->default('lobby');
            $table->unsignedBigInteger('current_round_id')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
