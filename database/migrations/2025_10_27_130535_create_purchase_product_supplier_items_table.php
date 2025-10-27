<?php
// database/migrations/xxxx_create_purchase_product_supplier_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_product_supplier_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_product_supplier_id')
                ->constrained('purchase_product_suppliers')
                ->cascadeOnDelete()
                ->name('pps_items_pps_id_foreign'); // Custom nama yang lebih pendek
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete()
                ->name('pps_items_product_id_foreign'); // Custom nama yang lebih pendek
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_product_supplier_items');
    }
};
