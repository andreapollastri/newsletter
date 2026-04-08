<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        $usedSlugs = [];
        $campaigns = DB::table('campaigns')->orderBy('created_at')->get();

        foreach ($campaigns as $campaign) {
            $base = Str::slug((string) $campaign->name) ?: 'campaign';
            $candidate = $base;
            $i = 1;
            while (in_array($candidate, $usedSlugs, true)) {
                $candidate = $base.'-'.$i++;
            }
            $usedSlugs[] = $candidate;

            DB::table('campaigns')->where('id', $campaign->id)->update(['slug' => $candidate]);
        }

        Schema::table('campaigns', function (Blueprint $table) {
            $table->unique('slug');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE campaigns MODIFY slug VARCHAR(255) NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
