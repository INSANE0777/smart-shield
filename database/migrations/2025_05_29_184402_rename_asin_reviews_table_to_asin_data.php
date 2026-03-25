<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        Schema::rename('asin_reviews', 'asin_data');
    }

    public function down()
    {
        Schema::rename('asin_data', 'asin_reviews');
    }
};
