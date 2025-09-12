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
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('stock');
            $table->dropColumn('supplier_price');
            $table->dropColumn('entry_date');
            $table->dropColumn('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock')->nullable();
            $table->decimal('supplier_price', 10, 2)->nullable();
            $table->date('entry_date')->nullable();
            $table->date('expiry_date')->nullable();
        });
    }
};
