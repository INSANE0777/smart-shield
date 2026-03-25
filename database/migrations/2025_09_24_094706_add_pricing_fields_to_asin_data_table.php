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
            // Pricing information for structured data
            $table->decimal('price', 10, 2)->nullable()->after('product_data_scraped_at');
            $table->string('currency', 3)->default('USD')->after('price');
            $table->string('availability', 50)->default('in stock')->after('currency');
            $table->string('condition', 20)->default('new')->after('availability');
            $table->string('seller', 100)->nullable()->after('condition');
            $table->timestamp('price_updated_at')->nullable()->after('seller');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->dropColumn([
                'price',
                'currency',
                'availability',
                'condition',
                'seller',
                'price_updated_at',
            ]);
        });
    }
};
