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
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedInteger('placement_timer_duration')->nullable()->after('placement_timer_enabled');
            $table->unsignedInteger('placement_delay_seconds')->nullable()->after('placement_timer_duration');
            $table->unsignedInteger('max_rounds')->nullable()->after('placement_delay_seconds');
            $table->boolean('amen_enabled')->default(true)->after('max_rounds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn([
                'placement_timer_duration',
                'placement_delay_seconds',
                'max_rounds',
                'amen_enabled',
            ]);
        });
    }
};
