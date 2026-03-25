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
            $table->string('product_title')->nullable()->after('product_description');
            $table->text('product_image_url')->nullable()->after('product_title');
            $table->boolean('have_product_data')->default(false)->after('product_image_url');
            $table->timestamp('product_data_scraped_at')->nullable()->after('have_product_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->dropColumn([
                'product_title',
                'product_image_url',
                'have_product_data',
                'product_data_scraped_at',
            ]);
        });
    }
};
