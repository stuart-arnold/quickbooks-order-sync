<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });

        // Bikes
        Schema::create('bikes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Fitments
        Schema::create('fitments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Supplier Part Numbers
        Schema::create('supplier_part_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->string('supplier_part_number');
            $table->integer('packs_needed');
            $table->decimal('cost', 8, 2);
            $table->integer('stock')->default(0);

            $table->timestamps();
        });

        // Product Fitments
        Schema::create('product_fitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('bike_id')->constrained('bikes')->onDelete('cascade');
            $table->foreignId('fitment_id')->constrained('fitments')->onDelete('cascade');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // Orders
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            
            $table->string('status')->default('pending'); // e.g., pending, processed, needs_attention
        
            // New customer fields
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
        
            // New address fields
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country')->nullable();
        
            // New order comment field
            $table->text('order_comments')->nullable();

            $table->string('delivery_method')->nullable();
        
            $table->timestamps();
        });

        // Order Items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('bike_id')->nullable();
            $table->unsignedBigInteger('fitment_id')->nullable();
            $table->string('bike_name')->nullable();
            $table->string('fitment_name')->nullable();

            $table->unsignedInteger('quantity');

            // Snapshot fields
            $table->string('product_name');
            $table->decimal('product_price', 10, 2);
            $table->string('product_sku')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('product_fitments');
        Schema::dropIfExists('supplier_part_numbers');
        Schema::dropIfExists('fitments');
        Schema::dropIfExists('bikes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
    }
};
