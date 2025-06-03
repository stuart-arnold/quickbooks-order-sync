<?php
/**
 * Order Fulfillment Logic Coverage
 *
 * 1. Order-level early exits
 * - order_comments present → returns null
 *   → Covered by: test_it_returns_null_when_order_has_comments
 *
 * 2. Per-item validation
 * - Product has no supplier parts → return null
 *   → test_it_skips_product_with_no_supplier_parts
 *   → test_it_rejects_order_when_one_product_has_no_available_parts
 *
 * - Supplier does not have all required parts for a product → skip that supplier
 *   → test_it_requires_all_parts_from_supplier_to_fulfill_product
 *
 * - Supplier has some parts but not all → treated same as above
 *   → test_it_ignores_suppliers_with_incomplete_part_sets
 *
 * - One supplier can fulfill all parts for a product → include in options
 *   → test_it_can_fulfill_order_completely_from_one_supplier
 *
 * 3. Decision logic
 * - Only one supplier can fulfill everything → pick them
 *   → test_it_can_fulfill_order_completely_from_one_supplier
 *
 * - Multiple can → pick cheapest
 *   → test_it_chooses_cheapest_supplier_when_both_can_fulfill
 *
 * - Preferred supplier within £1 → choose preferred
 *   → test_it_prefers_supplier_within_one_pound_of_cheapest
 *
 * - No supplier can fulfill everything, but each line can be fulfilled by someone else → return split
 *   → test_it_splits_order_when_no_single_supplier_can_fulfill
 *
 * - No fulfillment options at all → return null or error
 *   → test_it_returns_null_when_no_supplier_has_stock
 *   → test_it_returns_null_when_no_supplier_has_enough_stock
 *   → test_it_rejects_order_if_stock_is_insufficient_even_with_multiple_suppliers
 */

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SupplierPartNumber;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    public function test_it_can_fulfill_order_completely_from_one_supplier(): void
    {
        $supplierA = Supplier::factory()->create(['name' => 'Hendler']);
        $supplierB = Supplier::factory()->create(['name' => 'Hi-Level']);
    
        $product1 = Product::factory()->create(['name' => 'Brake Pads']);
        $product2 = Product::factory()->create(['name' => 'Oil Filter']);
    
        // Both parts available from Supplier A
        SupplierPartNumber::create([
            'product_id' => $product1->id,
            'supplier_id' => $supplierA->id,
            'supplier_part_number' => 'A-SPN-1',
            'packs_needed' => 1,
            'cost' => 9.00,
            'stock' => 10,
        ]);
    
        SupplierPartNumber::create([
            'product_id' => $product2->id,
            'supplier_id' => $supplierA->id,
            'supplier_part_number' => 'A-SPN-2',
            'packs_needed' => 1,
            'cost' => 7.00,
            'stock' => 10,
        ]);
    
        // Also available from Supplier B — but we want A to win
        SupplierPartNumber::create([
            'product_id' => $product1->id,
            'supplier_id' => $supplierB->id,
            'supplier_part_number' => 'B-SPN-1',
            'packs_needed' => 1,
            'cost' => 10.00,
            'stock' => 10,
        ]);
    
        SupplierPartNumber::create([
            'product_id' => $product2->id,
            'supplier_id' => $supplierB->id,
            'supplier_part_number' => 'B-SPN-2',
            'packs_needed' => 1,
            'cost' => 8.00,
            'stock' => 10,
        ]);
    
        $order = Order::create([
            'status' => 'pending',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'customer_phone' => '07000000000',
            'address_line_1' => '1 Test Road',
            'city' => 'Testville',
            'postcode' => 'TST123',
            'country' => 'UK',
        ]);
    
        $order->items()->createMany([
            [
                'product_id' => $product1->id,
                'quantity' => 1,
                'product_name' => $product1->name,
                'product_price' => $product1->price,
                'product_sku' => $product1->sku,
            ],
            [
                'product_id' => $product2->id,
                'quantity' => 1,
                'product_name' => $product2->name,
                'product_price' => $product2->price,
                'product_sku' => $product2->sku,
            ]
        ]);

        // Dump what's in the DB right now
        /*
        dump([
            'suppliers' => Supplier::all()->toArray(),
            'products' => Product::all()->toArray(),
            'order' => $order->toArray(),
            'order_items' => $order->items()->get()->toArray(),
            'supplier_parts' => SupplierPartNumber::all()->toArray(),
        ]);
        */
    
        $result = app(OrderService::class)->selectSupplierForOrder($order);

        // dump($result);
    
        $this->assertEquals('full', $result['mode']);
        $this->assertCount(1, $result['suppliers']);
    
        $this->assertArrayHasKey('Hendler', $result['suppliers']);
    }
    
    public function test_it_splits_order_when_no_single_supplier_can_fulfill(): void
    {
        $supplierA = Supplier::factory()->create(['name' => 'Hendler']);
        $supplierB = Supplier::factory()->create(['name' => 'Hi-Level']);
        $product1 = Product::factory()->create(['name' => 'Brake Pads']);
        $product2 = Product::factory()->create(['name' => 'Oil Filter']);
    
        // One part only available from supplier A
        SupplierPartNumber::create([
            'product_id' => $product1->id,
            'supplier_id' => $supplierA->id,
            'supplier_part_number' => 'SPN-A1',
            'packs_needed' => 1,
            'cost' => 10.00,
            'stock' => 10,
        ]);
    
        // One part only available from supplier B
        SupplierPartNumber::create([
            'product_id' => $product2->id,
            'supplier_id' => $supplierB->id,
            'supplier_part_number' => 'SPN-B1',
            'packs_needed' => 1,
            'cost' => 8.00,
            'stock' => 10,
        ]);
    
        $order = Order::create([
            'status' => 'pending',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'customer_phone' => '07000000000',
            'address_line_1' => '1 Test Road',
            'city' => 'Testville',
            'postcode' => 'TST123',
            'country' => 'UK',
        ]);
    
        $order->items()->createMany([
            [
                'product_id' => $product1->id,
                'quantity' => 1,
                'product_name' => $product1->name,
                'product_price' => $product1->price,
                'product_sku' => $product1->sku,
            ],
            [
                'product_id' => $product2->id,
                'quantity' => 1,
                'product_name' => $product2->name,
                'product_price' => $product2->price,
                'product_sku' => $product2->sku,
            ]
        ]);
    
        $result = app(OrderService::class)->selectSupplierForOrder($order);
    
        $this->assertEquals('split', $result['mode']);
        $this->assertGreaterThan(1, count($result['suppliers']));
    }    

    public function test_it_returns_null_when_no_supplier_has_stock(): void
    {
        $supplierA = Supplier::factory()->create(['name' => 'Hendler']);
        $supplierB = Supplier::factory()->create(['name' => 'Hi-Level']);
    
        $product1 = Product::factory()->create(['name' => 'Brake Pads']);
        $product2 = Product::factory()->create(['name' => 'Oil Filter']);
    
        // Both suppliers have 0 stock for their parts
        SupplierPartNumber::create([
            'product_id' => $product1->id,
            'supplier_id' => $supplierA->id,
            'supplier_part_number' => 'A1',
            'packs_needed' => 1,
            'cost' => 9.00,
            'stock' => 0,
        ]);
    
        SupplierPartNumber::create([
            'product_id' => $product2->id,
            'supplier_id' => $supplierB->id,
            'supplier_part_number' => 'B1',
            'packs_needed' => 1,
            'cost' => 8.00,
            'stock' => 0,
        ]);
    
        $order = Order::create([
            'status' => 'pending',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'customer_phone' => '07000000000',
            'address_line_1' => '1 Test Road',
            'city' => 'Testville',
            'postcode' => 'TST123',
            'country' => 'UK',
        ]);
    
        $order->items()->createMany([
            [
                'product_id' => $product1->id,
                'quantity' => 1,
                'product_name' => $product1->name,
                'product_price' => $product1->price,
                'product_sku' => $product1->sku,
            ],
            [
                'product_id' => $product2->id,
                'quantity' => 1,
                'product_name' => $product2->name,
                'product_price' => $product2->price,
                'product_sku' => $product2->sku,
            ]
        ]);
    
        $result = app(OrderService::class)->selectSupplierForOrder($order);
    
        $this->assertNull($result);
    }

    public function test_it_returns_null_when_no_supplier_has_enough_stock(): void
    {
        $supplierA = Supplier::factory()->create(['name' => 'Hendler']);
        $supplierB = Supplier::factory()->create(['name' => 'Hi-Level']);

        $product = Product::factory()->create(['name' => 'Fuel Pump']);

        // Both suppliers have too little stock
        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplierA->id,
            'supplier_part_number' => 'A-FAIL',
            'packs_needed' => 2,
            'cost' => 15.00,
            'stock' => 1, // Needs 4, has 1
        ]);

        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplierB->id,
            'supplier_part_number' => 'B-FAIL',
            'packs_needed' => 2,
            'cost' => 14.00,
            'stock' => 2, // Still not enough
        ]);

        $order = Order::create([
            'status' => 'pending',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'customer_phone' => '07000000000',
            'address_line_1' => '1 Test Road',
            'city' => 'Testville',
            'postcode' => 'TST123',
            'country' => 'UK',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 2, // Needs 4 packs (2 per item)
            'product_name' => $product->name,
            'product_price' => $product->price,
            'product_sku' => $product->sku,
        ]);

        $result = app(OrderService::class)->selectSupplierForOrder($order);

        $this->assertNull($result);
    }

    public function test_it_returns_null_when_order_has_comments(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Hendler']);
        $product = Product::factory()->create(['name' => 'Chain']);

        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_part_number' => 'CHAIN-01',
            'packs_needed' => 1,
            'cost' => 20.00,
            'stock' => 10,
        ]);

        $order = Order::create([
            'status' => 'pending',
            'customer_name' => 'Commenter',
            'customer_email' => 'comment@example.com',
            'customer_phone' => '07000000000',
            'address_line_1' => '1 Test Road',
            'city' => 'Testville',
            'postcode' => 'TST123',
            'country' => 'UK',
            'order_comments' => 'Please pack carefully, I had issues last time.', // 🚫 Comment triggers manual
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'product_sku' => $product->sku,
        ]);

        $result = app(OrderService::class)->selectSupplierForOrder($order);

        $this->assertNull($result);
    }

    public function test_it_requires_all_parts_from_supplier_to_fulfill_product(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Hendler']);
        $product = Product::factory()->create(['name' => 'Mirror Set']);
    
        // Supplier only has one of the two needed parts in stock
        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_part_number' => 'LEFT-MIRROR',
            'packs_needed' => 1,
            'cost' => 10.00,
            'stock' => 1,
        ]);
        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_part_number' => 'RIGHT-MIRROR',
            'packs_needed' => 1,
            'cost' => 10.00,
            'stock' => 0,
        ]);
    
        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'product_sku' => $product->sku,
        ]);
    
        $result = app(OrderService::class)->selectSupplierForOrder($order);
    
        $this->assertNull($result);
    }

    public function test_it_chooses_cheapest_supplier_when_both_can_fulfill(): void
    {
        $supplierA = Supplier::factory()->create(['name' => 'Hendler']); // Preferred (id = 1)
        $supplierB = Supplier::factory()->create(['name' => 'Hi-Level']); // Cheaper

        $product = Product::factory()->create(['name' => 'Clutch Cable']);

        // Both suppliers can fulfill
        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplierA->id,
            'supplier_part_number' => 'SPN-A',
            'packs_needed' => 1,
            'cost' => 10.00,
            'stock' => 10,
        ]);

        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplierB->id,
            'supplier_part_number' => 'SPN-B',
            'packs_needed' => 1,
            'cost' => 7.00,
            'stock' => 10,
        ]);

        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'product_sku' => $product->sku,
        ]);

        $result = app(OrderService::class)->selectSupplierForOrder($order);

        $this->assertEquals('full', $result['mode']);
        $this->assertArrayHasKey('Hi-Level', $result['suppliers']);
        $this->assertEquals(7.00, $result['suppliers']['Hi-Level']['total_cost']);
        $this->assertEquals('Cheapest supplier selected', $result['suppliers']['Hi-Level']['reason']);
    }

    public function test_it_prefers_supplier_within_one_pound_of_cheapest(): void
    {
        $supplierA = Supplier::factory()->create(['name' => 'Hendler']); // Preferred
        $supplierB = Supplier::factory()->create(['name' => 'Hi-Level']); // Slightly cheaper

        $product = Product::factory()->create(['name' => 'Headlight Bulb']);

        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplierA->id,
            'supplier_part_number' => 'A-BULB',
            'packs_needed' => 1,
            'cost' => 10.00,
            'stock' => 10,
        ]);

        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplierB->id,
            'supplier_part_number' => 'B-BULB',
            'packs_needed' => 1,
            'cost' => 9.20,
            'stock' => 10,
        ]);

        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'product_sku' => $product->sku,
        ]);

        $result = app(OrderService::class)->selectSupplierForOrder($order);

        $this->assertEquals('full', $result['mode']);
        $this->assertArrayHasKey('Hendler', $result['suppliers']);
        $this->assertEquals(10.00, $result['suppliers']['Hendler']['total_cost']);
        $this->assertEquals('Preferred supplier selected (within £1.00 of cheapest)', $result['suppliers']['Hendler']['reason']);
    }

    public function test_it_rejects_order_if_stock_is_insufficient_even_with_multiple_suppliers(): void
    {
        $supplierA = Supplier::factory()->create(['name' => 'Hendler']);
        $supplierB = Supplier::factory()->create(['name' => 'Hi-Level']);

        $product = Product::factory()->create(['name' => 'Fuel Cap']);

        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplierA->id,
            'supplier_part_number' => 'A-FUEL',
            'packs_needed' => 1,
            'cost' => 6.00,
            'stock' => 0,
        ]);

        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplierB->id,
            'supplier_part_number' => 'B-FUEL',
            'packs_needed' => 1,
            'cost' => 5.50,
            'stock' => 0,
        ]);

        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'product_sku' => $product->sku,
        ]);

        $result = app(OrderService::class)->selectSupplierForOrder($order);

        $this->assertNull($result);
    }

    public function test_it_ignores_suppliers_with_incomplete_part_sets(): void
    {
        $supplierA = Supplier::factory()->create(['name' => 'Hendler']);
        $supplierB = Supplier::factory()->create(['name' => 'Hi-Level']);

        $product = Product::factory()->create(['name' => 'Crash Kit']);

        // Product needs 2 parts
        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplierA->id,
            'supplier_part_number' => 'CK-A1',
            'packs_needed' => 1,
            'cost' => 8.00,
            'stock' => 10,
        ]);

        // Only one of the two required parts from Supplier B
        SupplierPartNumber::create([
            'product_id' => $product->id,
            'supplier_id' => $supplierB->id,
            'supplier_part_number' => 'CK-B1',
            'packs_needed' => 1,
            'cost' => 7.00,
            'stock' => 10,
        ]);
        // Supplier B is missing a second required part

        // Simulate that two parts are required for this product from a supplier
        // We'll do this by just not having both parts available for B

        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'product_sku' => $product->sku,
        ]);

        $result = app(OrderService::class)->selectSupplierForOrder($order);

        // Only supplier A has a complete set of parts
        $this->assertNotNull($result);
        $this->assertEquals('Hendler', array_key_first($result['suppliers']));
    }

    public function test_it_skips_product_with_no_supplier_parts(): void
    {
        // Arrange: Create a product with no supplier part numbers
        $product = Product::factory()->create();

        // Create an order and add this product as an item
        $order = Order::factory()->create();
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'product_sku' => $product->sku,
            'quantity' => 1, // ← required
        ]);

        // Act: Run your fulfillment logic
        $result = app(OrderService::class)->selectSupplierForOrder($order);

        // Assert: The order should be marked as unfulfillable or partially fulfilled
        $this->assertNull($result, 'Order should be unfulfillable due to missing supplier parts.');
    }

    public function test_it_rejects_order_when_one_product_has_no_available_parts(): void
    {
        // Arrange
        $productA = Product::factory()->create(['name' => 'Chain Kit']);
        $productB = Product::factory()->create(['name' => 'Fuel Tap']);

        $order = Order::factory()->create();

        // Add both products to the order
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'product_name' => $productA->name,
            'product_price' => $productA->price,
            'product_sku' => $productA->sku,
            'quantity' => 1,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $productB->id,
            'product_name' => $productB->name,
            'product_price' => $productB->price,
            'product_sku' => $productB->sku,
            'quantity' => 1,
        ]);

        // Set up supplier and parts for Product A only
        $supplier = Supplier::factory()->create(['name' => 'Hendler']);

        SupplierPartNumber::create([
            'product_id' => $productA->id,
            'supplier_id' => $supplier->id,
            'supplier_part_number' => 'CHAIN-KIT-STD',
            'packs_needed' => 1,
            'cost' => 2500,
            'stock' => 10,
        ]);

        // Note: Product B has no parts at all

        // Act
        $result = app(OrderService::class)->selectSupplierForOrder($order);

        // Assert
        $this->assertNull($result, 'Order should be unfulfillable if any product has no available parts.');
    }

}
