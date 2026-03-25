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
            $table->decimal('fake_percentage', 5, 2)->nullable()->after('openai_result');
            $table->decimal('amazon_rating', 3, 2)->nullable()->after('fake_percentage');
            $table->decimal('adjusted_rating', 3, 2)->nullable()->after('amazon_rating');
            $table->char('grade', 1)->nullable()->after('adjusted_rating');
            $table->text('explanation')->nullable()->after('grade');
            $table->string('status')->default('pending')->after('explanation');
            $table->text('analysis_notes')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asin_data', function (Blueprint $table) {
            $table->dropColumn([
                'fake_percentage',
                'amazon_rating',
                'adjusted_rating',
                'grade',
                'explanation',
                'status',
                'analysis_notes',
            ]);
        });
    }
};
