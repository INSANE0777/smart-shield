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
        Schema::create('analysis_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_session', 100)->index(); // Session ID from user's browser
            $table->string('asin', 20)->index();
            $table->text('product_url');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->index();
            $table->integer('current_step')->default(0);
            $table->float('progress_percentage', 5, 2)->default(0.00);
            $table->string('current_message')->default('Queued for analysis...');
            $table->integer('total_steps')->default(7);
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['user_session', 'status']);
            $table->index(['asin', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_sessions');
    }
};
