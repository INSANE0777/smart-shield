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
            // Price analysis data stored as JSON for flexibility
            $table->json('price_analysis')->nullable()->after('product_insights');

            // Status tracking for independent price analysis processing
            $table->string('price_analysis_status', 20)->default('pending')->after('price_analysis');

            // Timestamp for tracking when price analysis was completed
            $table->timestamp('price_analyzed_at')->nullable()->after('price_analysis_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->dropColumn(['price_analysis', 'price_analysis_status', 'price_analyzed_at']);
        });
    }
};
