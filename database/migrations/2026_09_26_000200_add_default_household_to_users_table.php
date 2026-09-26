<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A super admin works across families, so their chosen "working family" is stored on the
     * account. Null means "use the first family", which keeps the default sensible.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'default_household_id')) {
                $table->foreignId('default_household_id')->nullable()->after('household_id')
                    ->constrained('households')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'default_household_id')) {
                $table->dropConstrainedForeignId('default_household_id');
            }
        });
    }
};