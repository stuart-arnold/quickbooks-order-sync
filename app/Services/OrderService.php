<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Supplier;
use App\Models\SupplierPartNumber;
use Illuminate\Support\Facades\DB;

class OrderService
{

    protected int $preferredSupplierId;

    public function __construct(int $preferredSupplierId = 1)
    {
        $this->preferredSupplierId = $preferredSupplierId;
    }

    /*
    - Reject invalid orders early
    - Build per-product fulfillment options
    - Group by supplier
    - Try to fulfill full order from one supplier (preferred if close)
    - If not, fall back to split
    - If neither works, return null or error
    */
    public function selectSupplierForOrder(Order $order): ?array
    {
        // Reject immediately if the order has customer comments. These require manual handling.
        if (!empty($order->order_comments)) {
            return [
                'status' => 'unfulfillable',
                'reason' => 'Order has comments and requires manual review',
            ];
        }       

        // This will collect possible fulfillment plans per product line.
        $productPlans = [];

        // Loop through each product in the order.
        foreach ($order->items as $item) {
            // Load the product.
            $product = $item->product;
            $orderQuantity = $item->quantity;

            // Construct a unique $lineKey for tracking this line, based on IDs.
            $lineKey = $item->id ?? ($product->id . '|' . $item->bike_id . '|' . $item->fitment_id);

            // Set human-readable bike/fitment names.
            $bikeName = $item?->bike_name ?? 'Unknown Bike';
            $fitmentName = $item?->fitment_name ?? 'Unknown Fitment';

            // Fetch all supplier parts for the product.
            $parts = SupplierPartNumber::where('product_id', $product->id)->get();

            // If none exist, we cannot fulfill this product -> whole order is unfulfillable.
            if ($parts->isEmpty()) {
                return [
                    'status' => 'unfulfillable',
                    'reason' => "Product '{$product->name}' has no supplier parts",
                ];
            }

            // Group all parts by their supplier.
            $grouped = $parts->groupBy(fn($part) => $part->supplier_id);

            // Initialize a collection to track which suppliers can fulfill this product.
            $fulfilledBy = [];

            foreach ($grouped as $supplierId => $partsForSupplier) {
                
                // Check if they have sufficient stock for every required part for this product.
                $supplier = Supplier::find($supplierId);
                $can_supply = true;

                foreach ($partsForSupplier as $part) {
                    $required = $part->packs_needed * $orderQuantity;

                    if ($part->stock < $required) {
                        $can_supply = false;
                        break;
                    }
                }

                // If any part is understocked, skip this supplier.
                if (!$can_supply) continue;

                // If a supplier can supply all required parts, calculate total cost and part breakdown.
                $partsUsed = [];
                $totalCost = 0;

                foreach ($partsForSupplier as $part) {
                    $packsTotal = $part->packs_needed * $orderQuantity;
                    $costTotal = $part->cost * $packsTotal;

                    $partsUsed[] = [
                        'product_id'         => $product->id,
                        'product_name'       => $product->name,
                        'part_number'        => $part->supplier_part_number,
                        'packs_per_unit'     => $part->packs_needed,
                        'packs_needed_total' => $packsTotal,
                        'unit_cost'          => $part->cost,
                        'total_cost'         => $costTotal,
                        'bike_id'            => $item?->bike_id,
                        'bike_name'          => $bikeName,
                        'fitment_id'         => $item?->fitment_id,
                        'fitment_name'       => $fitmentName,
                    ];

                    $totalCost += $costTotal;
                }

                // Store this in the $fulfilledBy array.
                $fulfilledBy[$supplierId] = [
                    'total_cost' => $totalCost,
                    'parts' => $partsUsed,
                ];
            }

            // If no supplier can fulfill this product -> reject the order.
            if (empty($fulfilledBy)) {
                return [
                    'status' => 'unfulfillable',
                    'reason' => "No supplier can fully supply all required parts for product '{$product->name}' (ID: {$product->id})",
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                ];
            }

            // Otherwise, add all valid supplier options for this product to the master list.
            $productPlans[$lineKey] = $fulfilledBy;
        }

        $preferredSupplierId = 1;
        $hiLevelId = Supplier::where('name', 'Hi-Level')->value('id');

        // Transform the $productPlans into a list of suppliers -> products they can fulfill.
        $eligibleSuppliers = [];
        foreach ($productPlans as $lineKey => $suppliers) {
            foreach ($suppliers as $supplierId => $data) {
                $eligibleSuppliers[$supplierId][$lineKey] = $data;
            }
        }

        // For each supplier, if they can fulfill every product line, they are a full candidate.
        $fullOrderCandidates = [];
        foreach ($eligibleSuppliers as $supplierId => $linesFulfilled) {
            if (count($linesFulfilled) === count($productPlans)) {
                $total = array_sum(array_column($linesFulfilled, 'total_cost'));

                $fullOrderCandidates[$supplierId] = [
                    'supplier_id' => $supplierId,
                    'total_cost' => $total,
                    'products' => $linesFulfilled,
                ];
            }
        }

        // Pick best full-order supplier
        if (!empty($fullOrderCandidates)) {
            $cheapest = null;
            $preferred = null;

            foreach ($fullOrderCandidates as $supplierId => $data) {
                if (!$cheapest || $data['total_cost'] < $cheapest['total_cost']) {
                    $cheapest = $data;
                }

                if ($supplierId === $preferredSupplierId) {
                    $preferred = $data;
                }
            }

            // Choose the cheapest supplier, or the preferred one if it’s within £1 of the cheapest.
            $best = ($preferred && $preferred['total_cost'] <= $cheapest['total_cost'] + 1.00) ? $preferred : $cheapest;
            $reason = ($best === $preferred)
                ? 'Preferred supplier selected (within £1.00 of cheapest)'
                : 'Cheapest supplier selected';


            // Build and return the full fulfillment structure with all part and customer info.
            $supplier = Supplier::find($best['supplier_id']);
            
            // Check if Hi-Level would be selected but the address is invalid
            if ($supplier->id === $hiLevelId && $this->failsHiLevelAddressCheck($order)) {
                return [
                    'status' => 'unfulfillable',
                    'reason' => 'Hi-Level cannot fulfill due to address line length limits',
                ];
            }

            $orderParts = collect($best['products'])->pluck('parts')->flatten(1)->all();

            return [
                'order_id' => $order->id,
                'status' => 'fulfilled',
                'delivery_method' => $order->delivery_method,
                'suppliers' => [
                    $supplier->name => [
                        'total_cost' => $best['total_cost'],
                        'order_parts' => $orderParts,
                        'reason' => $reason,
                    ]
                ],
                'customer' => [
                    'name' => $order->customer_name,
                    'email' => $order->customer_email,
                    'phone' => $order->customer_phone,
                    'address' => [
                        'line_1' => $order->address_line_1,
                        'line_2' => $order->address_line_2,
                        'city'   => $order->city,
                        'postcode' => $order->postcode,
                        'country' => $order->country,
                    ],
                    'comments' => $order->order_comments,
                ]
            ];
        }

        // Handle partial (split) orders
        $splitOrder = [];
        $splitCost = [];

        // For each product line, pick the cheapest supplier and allocate parts.
        foreach ($productPlans as $lineKey => $options) {
            uasort($options, fn($a, $b) => $a['total_cost'] <=> $b['total_cost']);
            $bestSupplierId = array_key_first($options);
            $splitOrder[$bestSupplierId] = array_merge(
                $splitOrder[$bestSupplierId] ?? [],
                $options[$bestSupplierId]['parts']
            );
            $splitCost[$bestSupplierId] = ($splitCost[$bestSupplierId] ?? 0) + $options[$bestSupplierId]['total_cost'];
        }

        // Build the return structure per supplier for the split fulfillment.
        $result = ['suppliers' => []];
        foreach ($splitOrder as $supplierId => $parts) {
            $supplier = Supplier::find($supplierId);

            // Check if Hi-Level would be selected but the address is invalid
            if ($supplier->id === $hiLevelId && $this->failsHiLevelAddressCheck($order)) {
                return [
                    'status' => 'unfulfillable',
                    'reason' => 'Hi-Level cannot fulfill due to address line length limits',
                ];
            }

            $result['suppliers'][$supplier->name] = [
                'total_cost' => $splitCost[$supplierId],
                'order_parts' => $parts,
            ];
        }

        // If somehow no parts could be allocated, return an error (should rarely hit).
        if (empty($result['suppliers'])) {
            return [
                'status' => 'unfulfillable',
                'reason' => 'No supplier(s) can fulfill any part of this order.',
            ];
        }
        
        return [
            'order_id' => $order->id,
            'status' => 'split',
            'delivery_method' => $order->delivery_method,
            'suppliers' => $result['suppliers'],
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
                'address' => [
                    'line_1' => $order->address_line_1,
                    'line_2' => $order->address_line_2,
                    'city'   => $order->city,
                    'postcode' => $order->postcode,
                    'country' => $order->country,
                ],
                'comments' => $order->order_comments,
            ],
        ];
    }

    private function failsHiLevelAddressCheck(Order $order): bool
    {
        return strlen($order->address_line_1) > 30 || strlen($order->address_line_2) > 30 || strlen($order->city) > 30;
    }
}
