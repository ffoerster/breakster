<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tidalProviderId = DB::table('providers')
            ->where('key', 'tidal')
            ->value('id');

        if (! $tidalProviderId) {
            $tidalProviderId = DB::table('providers')->insertGetId([
                'key' => 'tidal',
                'name' => 'TIDAL',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $providers = DB::table('providers')->pluck('id', 'key');

        foreach ($providers as $key => $id) {
            DB::table('tracks')
                ->where('provider', $key)
                ->update(['provider_id' => $id]);
        }

        DB::table('tracks')
            ->whereNull('provider_id')
            ->update(['provider_id' => $tidalProviderId]);

        Schema::table('tracks', function (Blueprint $table) {
            $table->dropUnique(['pack_id', 'provider', 'provider_track_id']);
            $table->dropColumn('provider');

            $table->unique(['pack_id', 'provider_id', 'provider_track_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropUnique(['pack_id', 'provider_id', 'provider_track_id']);

            $table->string('provider')->default('tidal')->after('pack_id');
            $table->unique(['pack_id', 'provider', 'provider_track_id']);
        });

        $tidalProviderKey = DB::table('providers')
            ->where('key', 'tidal')
            ->value('key');

        if (! $tidalProviderKey) {
            DB::table('tracks')->update(['provider' => 'tidal']);
        } else {
            DB::table('tracks')->update(['provider' => 'tidal']);
        }
    }
};
