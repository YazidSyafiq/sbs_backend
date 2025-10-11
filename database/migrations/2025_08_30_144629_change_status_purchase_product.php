<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_products', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('purchase_products', function (Blueprint $table) {
            $table->enum('status', ['Requested', 'Processing', 'Shipped', 'Received', 'Cancelled'])
                  ->default('Requested')
                  ->after('expected_delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_products', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
