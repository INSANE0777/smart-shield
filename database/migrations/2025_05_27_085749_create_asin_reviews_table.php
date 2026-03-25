<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAsinReviewsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asin_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('asin', 20)->index();
            $table->string('country', 5)->nullable();
            $table->text('product_description')->nullable();
            $table->json('reviews')->nullable(); // Raw reviews array
            $table->json('analysis')->nullable(); // OpenAI result JSON
            $table->unsignedTinyInteger('fake_percentage')->nullable();
            $table->string('grade', 2)->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->unique(['asin', 'country']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asin_reviews');
    }
}
