<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Supplier;
use App\Models\SupplierPartNumber;
use Illuminate\Support\Facades\DB;

class OrderService
{

    /**
     * Attempts to assign the best supplier(s) for an order.
     * - Tries to send whole order from 1 supplier (lowest total cost with stock).
     * - Falls back to per-product cheapest supplier if needed.
     */
    public function selectSupplierForOrder(Order $order): ?array
    {
        $productPlans = [];

        foreach ($order->items as $item) {
            $product = $item->product;
            $orderQuantity = $item->quantity;

            $lineKey = $item->id ?? ($product->id . '|' . $item->bike_id . '|' . $item->fitment_id);

            $bikeName = $item?->bike_name ?? 'Unknown Bike';
            $fitmentName = $item?->fitment_name ?? 'Unknown Fitment';

            $parts = SupplierPartNumber::where('product_id', $product->id)->get();

            $grouped = $parts->groupBy(fn($part) => $part->supplier_id);

            $fulfilledBy = [];

            foreach ($grouped as $supplierId => $partsForSupplier) {
                $supplier = Supplier::find($supplierId);
                $can_supply = true;

                foreach ($partsForSupplier as $part) {
                    $required = $part->packs_needed * $orderQuantity;

                    if ($part->stock < $required) {
                        $can_supply = false;
                        break;
                    }
                }

                if (!$can_supply) continue;

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

                $fulfilledBy[$supplierId] = [
                    'total_cost' => $totalCost,
                    'parts' => $partsUsed,
                ];
            }

            if (empty($fulfilledBy)) {
                return null;
            }

            $productPlans[$lineKey] = $fulfilledBy;
        }

        $preferredSupplierId = 1;

        $eligibleSuppliers = [];
        foreach ($productPlans as $lineKey => $suppliers) {
            foreach ($suppliers as $supplierId => $data) {
                $eligibleSuppliers[$supplierId][$lineKey] = $data;
            }
        }

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

            $best = ($preferred && $preferred['total_cost'] <= $cheapest['total_cost'] + 1.00) ? $preferred : $cheapest;
            $reason = ($best === $preferred)
                ? 'Preferred supplier selected (within £1.00 of cheapest)'
                : 'Cheapest supplier selected';

            $supplier = Supplier::find($best['supplier_id']);
            $orderParts = collect($best['products'])->pluck('parts')->flatten(1)->all();

            return [
                'mode' => 'full',
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

        $splitOrder = [];
        $splitCost = [];

        foreach ($productPlans as $lineKey => $options) {
            uasort($options, fn($a, $b) => $a['total_cost'] <=> $b['total_cost']);
            $bestSupplierId = array_key_first($options);
            $splitOrder[$bestSupplierId] = array_merge(
                $splitOrder[$bestSupplierId] ?? [],
                $options[$bestSupplierId]['parts']
            );
            $splitCost[$bestSupplierId] = ($splitCost[$bestSupplierId] ?? 0) + $options[$bestSupplierId]['total_cost'];
        }

        $result = ['suppliers' => []];
        foreach ($splitOrder as $supplierId => $parts) {
            $supplier = Supplier::find($supplierId);
            $result['suppliers'][$supplier->name] = [
                'total_cost' => $splitCost[$supplierId],
                'order_parts' => $parts,
            ];
        }

        if (empty($result['suppliers'])) {
            return ['error' => 'No supplier(s) can fulfill any part of this order.'];
        }

        $result['mode'] = 'split';
        $result['customer'] = [
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
        ];

        return $result;
    }
}
