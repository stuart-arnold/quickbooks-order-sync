<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\OrderService;

class OrderFulfillmentController extends Controller
{
    public function index()
    {
        return view('admin.fulfillment', ['results' => session('results')]);
    }

    public function run(Request $request)
    {
        $results = [];

        $orders = Order::where('status', 'pending')->get();
        foreach ($orders as $order) {
            $result = app(OrderService::class)->selectSupplierForOrder($order);
            $results[] = [
                'order_id' => $order->id,
                'result' => $result,
            ];
        }

        return redirect()->route('fulfillment.index')->with('results', $results);
    }
}