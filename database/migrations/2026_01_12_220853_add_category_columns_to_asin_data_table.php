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
            // Leaf category (e.g., "Routers", "Blenders")
            $table->string('category', 100)->nullable()->after('product_image_url');

            // Full category path (e.g., "Electronics > Computers > Networking > Routers")
            $table->string('category_path', 500)->nullable()->after('category');

            // Source of category data: 'amazon_breadcrumb' or 'llm_inference'
            $table->string('category_source', 50)->nullable()->after('category_path');

            // Index for efficient related products queries
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'category_path', 'category_source']);
        });
    }
};
