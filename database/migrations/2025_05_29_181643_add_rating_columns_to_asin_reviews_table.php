<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRatingColumnsToAsinReviewsTable extends Migration
{
    public function up()
    {
        Schema::table('asin_reviews', function (Blueprint $table) {
            $table->decimal('amazon_rating', 3, 2)->nullable()->after('explanation');
            $table->decimal('adjusted_rating', 3, 2)->nullable()->after('amazon_rating');
        });
    }

    public function down()
    {
        Schema::table('asin_reviews', function (Blueprint $table) {
            $table->dropColumn(['amazon_rating', 'adjusted_rating']);
        });
    }
}
