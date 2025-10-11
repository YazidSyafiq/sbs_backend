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
        Schema::create('purchase_products', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->string('name'); // Nama PO
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // User pengaju
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->enum('status', ['Draft', 'Confirmed', 'Received', 'Cancelled'])->default('Draft');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_products');
    }
};
