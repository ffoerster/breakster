<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('provider_track_id')->index();
            $table->string('title');
            $table->string('artist');
            $table->string('label')->nullable();
            $table->date('release_date');
            $table->string('artwork_url')->nullable();
            $table->string('preview_url')->nullable();
            $table->string('external_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->timestamps();

            $table->unique(['pack_id', 'provider_id', 'provider_track_id']);
            $table->index(['pack_id', 'is_active']);
            $table->index('release_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
