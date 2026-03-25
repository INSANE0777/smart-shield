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
            // Editorial content stored as JSON for flexibility
            // Contains structured sections: buyer_guide, product_context, category_insights, etc.
            $table->json('editorial_content')->nullable()->after('price_analyzed_at');

            // Status tracking for independent editorial content processing
            $table->string('editorial_status', 20)->default('pending')->after('editorial_content');

            // Timestamp for tracking when editorial content was generated
            $table->timestamp('editorial_generated_at')->nullable()->after('editorial_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->dropColumn(['editorial_content', 'editorial_status', 'editorial_generated_at']);
        });
    }
};

