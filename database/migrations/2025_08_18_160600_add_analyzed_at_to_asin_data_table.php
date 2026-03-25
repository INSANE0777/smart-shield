<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->timestamp('first_analyzed_at')->nullable()->after('analysis_notes');
            $table->timestamp('last_analyzed_at')->nullable()->after('first_analyzed_at');
        });

        // Backfill existing records
        // For completed analyses, set both timestamps to preserve original dates
        DB::statement('UPDATE asin_data SET first_analyzed_at = updated_at, last_analyzed_at = updated_at WHERE status = "completed"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->dropColumn(['first_analyzed_at', 'last_analyzed_at']);
        });
    }
};
