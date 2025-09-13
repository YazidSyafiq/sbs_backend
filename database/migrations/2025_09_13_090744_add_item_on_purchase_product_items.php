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
            $table->decimal('cost_price', 15, 2)->nullable()->after('unit_price');
            $table->decimal('profit_amount', 15, 2)->nullable()->after('cost_price');
            $table->decimal('profit_margin', 8, 4)->nullable()->after('profit_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_product_items', function (Blueprint $table) {
            $table->dropColumn(['cost_price', 'profit_amount', 'profit_margin']);
        });
    }
};
