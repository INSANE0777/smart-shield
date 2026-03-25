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
            $table->json('detailed_analysis')->nullable()->after('openai_result')
                ->comment('Enhanced review analysis with explanations, red flags, and provider details');

            $table->json('fake_review_examples')->nullable()->after('detailed_analysis')
                ->comment('Examples of fake reviews with detailed explanations for transparency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->dropColumn(['detailed_analysis', 'fake_review_examples']);
        });
    }
};
