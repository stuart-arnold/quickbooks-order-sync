<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected $orderService;

    // Inject the OrderService into the controller
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function processOrder(Request $request, $orderId)
    {
        // Retrieve the order from the database
        $order = Order::findOrFail($orderId);

        // Call the service function to select the supplier for this order
        $supplierSelection = $this->orderService->selectSupplierForOrder($order);

        // You can then use the result to create sales receipts, update order status, etc.
        // Example: Create sales receipts in QuickBooks

        return response()->json($supplierSelection);
    }
}
