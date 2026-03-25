<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     *
     * Category tags stores the full breadcrumb hierarchy as a JSON array.
     * Example: ["Electronics", "Computers", "Networking", "Routers"]
     *
     * Related products query requires ALL tags to match, ensuring
     * "Routers" only matches other "Routers", not random "Electronics".
     */
    public function up(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->json('category_tags')->nullable()->after('category_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->dropColumn('category_tags');
        });
    }
};

