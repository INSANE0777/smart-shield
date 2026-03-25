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
            $table->dropColumn([
                'fake_percentage',
                'grade',
                'explanation',
                'amazon_rating',
                'adjusted_rating',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->integer('fake_percentage')->default(0);
            $table->string('grade')->default('A');
            $table->text('explanation')->nullable();
            $table->decimal('amazon_rating', 3, 2)->default(0);
            $table->decimal('adjusted_rating', 3, 2)->default(0);
        });
    }
};
