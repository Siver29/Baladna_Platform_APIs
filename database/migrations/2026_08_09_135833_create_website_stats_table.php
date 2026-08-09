<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('website_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('total_reports')->default(0);
            $table->unsignedBigInteger('resolved_reports')->default(0);
            $table->unsignedBigInteger('pending_reports')->default(0);
            $table->unsignedBigInteger('anonymous_reports')->default(0);
            $table->unsignedBigInteger('active_categories')->default(0);
            $table->unsignedBigInteger('active_areas')->default(0);
            $table->unsignedBigInteger('active_agencies')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_stats');
    }
};
