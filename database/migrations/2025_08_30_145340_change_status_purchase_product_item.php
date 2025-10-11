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
        Schema::table('purchase_product_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('purchase_product_items', function (Blueprint $table) {
            $table->enum('status', ['Available', 'Out of Stock',])
                  ->default('Available')
                  ->after('total_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_product_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('purchase_product_items', function (Blueprint $table) {
            $table->enum('status', ['Available', 'Out of Stock',])
                  ->default('Available')
                  ->after('total_price');
        });
    }
};
