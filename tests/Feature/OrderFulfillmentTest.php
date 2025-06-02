<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed'); // Seed with CoreSeeder
    }

    /** @test */
    public function it_can_select_supplier_for_a_fulfillable_order()
    {
        $order = Order::first(); // Grab one seeded order

        $service = new OrderService();
        $result = $service->selectSupplierForOrder($order);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('mode', $result);
        $this->assertContains($result['mode'], ['full', 'split']);
        $this->assertArrayHasKey('suppliers', $result);

        foreach ($result['suppliers'] as $supplierName => $data) {
            $this->assertArrayHasKey('total_cost', $data);
            $this->assertArrayHasKey('order_parts', $data);
        }
    }

    public function test_it_can_fulfill_order_completely_from_one_supplier(): void
    {
        $order = Order::with('items')->has('items')->get()->first(); // Pick a seeded order

        $result = app(OrderService::class)->selectSupplierForOrder($order);

        $this->assertIsArray($result);
        $this->assertEquals('full', $result['mode']);
        $this->assertArrayHasKey('suppliers', $result);
        $this->assertNotEmpty($result['suppliers']);
    }

    public function test_it_splits_order_when_no_single_supplier_can_fulfill(): void
    {
        $order = Order::with('items')->has('items')->get()->firstWhere('id', 2); // Choose known-split order

        $result = app(OrderService::class)->selectSupplierForOrder($order);

        $this->assertIsArray($result);
        $this->assertEquals('split', $result['mode']);
        $this->assertArrayHasKey('suppliers', $result);
        $this->assertGreaterThan(1, count($result['suppliers']));
    }

    public function test_it_returns_null_for_unfulfillable_order(): void
    {
        $order = Order::with('items')->has('items')->get()->firstWhere('id', 5); // Choose known-unfulfillable order

        $result = app(OrderService::class)->selectSupplierForOrder($order);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('No supplier(s) can fulfill any part of this order.', $result['error']);
    }

    public function test_it_returns_customer_data_in_result(): void
    {
        $order = Order::with('items')->first();
    
        $result = app(OrderService::class)->selectSupplierForOrder($order);
    
        $this->assertArrayHasKey('customer', $result);
        $this->assertArrayHasKey('name', $result['customer']);
        $this->assertArrayHasKey('address', $result['customer']);
        $this->assertArrayHasKey('comments', $result['customer']);
    }
    
}
