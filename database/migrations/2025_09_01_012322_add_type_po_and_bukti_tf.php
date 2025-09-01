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
        Schema::table('purchase_product_suppliers', function (Blueprint $table) {
            $table->enum('type_po', ['credit', 'cash'])->default('cash');
            $table->enum('status_paid', ['unpaid', 'paid'])->default('unpaid');
            $table->string('bukti_tf')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_product_suppliers', function (Blueprint $table) {
            $table->dropColumn('type_po');
        });
    }
};
