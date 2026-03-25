<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove redundant category columns.
 *
 * The `category` and `category_path` columns are 100% derivable from `category_tags`:
 * - category = last element of category_tags array
 * - category_path = implode(' > ', category_tags)
 *
 * Keeping only:
 * - category_tags (JSON) - the authoritative source
 * - category_source (VARCHAR) - tracking where data came from
 *
 * The model uses getCategoryAttribute() accessor to derive the leaf category on-the-fly.
 */
return new class() extends Migration {
    public function up(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            // Drop index first
            $table->dropIndex(['category']);
        });

        Schema::table('asin_data', function (Blueprint $table) {
            // Drop redundant columns
            $table->dropColumn(['category', 'category_path']);
        });
    }

    public function down(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            // Re-add columns for rollback
            $table->string('category', 100)->nullable()->after('product_image_url');
            $table->string('category_path', 500)->nullable()->after('category');
            $table->index('category');
        });
    }
};
