<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time data migration: treat every user already present in the database as a legacy
 * full admin (Amministratore), so existing installs keep full access after introducing roles.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')->update(['role' => UserRole::Administrator->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: previous per-user roles are not stored.
    }
};
