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
            $table->string('source')->nullable()->after('status')->comment('Data source: chrome_extension, scraping, brightdata, etc.');
            $table->string('extension_version')->nullable()->after('source')->comment('Chrome extension version if applicable');
            $table->timestamp('extraction_timestamp')->nullable()->after('extension_version')->comment('When data was extracted by extension');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->dropColumn(['source', 'extension_version', 'extraction_timestamp']);
        });
    }
};
