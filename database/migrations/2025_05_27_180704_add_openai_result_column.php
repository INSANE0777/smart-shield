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
        Schema::table('asin_reviews', function (Blueprint $table) {
            $table->json('openai_result')->nullable()->after('reviews');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asin_reviews', function (Blueprint $table) {
            $table->dropColumn('openai_result');
        });
    }
};
