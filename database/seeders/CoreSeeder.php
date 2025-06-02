<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Bike;
use App\Models\Fitment;
use App\Models\SupplierPartNumber;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductFitment;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create(); // this line creates the $faker instance

        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('order_items')->truncate();
        DB::table('orders')->truncate();
        DB::table('product_fitments')->truncate();
        DB::table('supplier_part_numbers')->truncate();
        DB::table('fitments')->truncate();
        DB::table('bikes')->truncate();
        DB::table('products')->truncate();
        DB::table('suppliers')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Suppliers
        $hendler = Supplier::create(['name' => 'Hendler']);
        $hilevel = Supplier::create(['name' => 'Hi-Level']);

        // Fitments
        $fitments = collect(['Front', 'Rear', 'Left', 'Right'])->map(fn($name) => Fitment::create(['name' => $name]));

        // Bikes
        $bikes = collect([
            'Yamaha R6 2006',
            'Honda CBR600RR 2007',
            'Kawasaki ZX-10R 2011',
        ])->map(fn($name) => Bike::create(['name' => $name]));

        // Products
        $products = collect([
            ['name' => 'EBC HH Brake Pads', 'sku' => 'EBC-FA347HH', 'price' => 32.99],
            ['name' => 'Brake Disc Wave', 'sku' => 'BRAKE-DISC-WAVE', 'price' => 109.50],
            ['name' => 'Mirror Universal', 'sku' => 'MIRROR-UNI-BLK', 'price' => 15.99],
            ['name' => 'Chain & Sprocket Kit', 'sku' => 'CHAIN-KIT-XRK', 'price' => 154.75],
            ['name' => 'Oil Filter HF204', 'sku' => 'OIL-HF204', 'price' => 6.50],
        ])->map(fn($data) => Product::create($data));

        // Product Fitments
        foreach ($products as $product) {
            foreach ($bikes->random(rand(1, 2)) as $bike) {
                foreach ($fitments->random(rand(1, 2)) as $fitment) {
                    ProductFitment::create([
                        'product_id' => $product->id,
                        'bike_id' => $bike->id,
                        'fitment_id' => $fitment->id,
                        'notes' => rand(0, 1) ? 'Fits perfectly' : null,
                    ]);
                }
            }
        }

        // Supplier Part Numbers — mixed scenarios
        foreach ($products as $i => $product) {
            if ($i % 2 === 0) {
                // One supplier
                SupplierPartNumber::create([
                    'product_id' => $product->id,
                    'supplier_id' => $hendler->id,
                    'supplier_part_number' => 'SPN-' . Str::upper(Str::random(5)),
                    'packs_needed' => rand(1, 3),
                    'cost' => rand(300, 1200) / 100,
                    'stock' => $faker->boolean(80) ? rand(1, 100) : 0,
                ]);
            } else {
                // Both suppliers
                SupplierPartNumber::create([
                    'product_id' => $product->id,
                    'supplier_id' => $hendler->id,
                    'supplier_part_number' => 'SPN-' . Str::upper(Str::random(5)),
                    'packs_needed' => rand(1, 2),
                    'cost' => rand(300, 1000) / 100,
                    'stock' => $faker->boolean(80) ? rand(1, 100) : 0,
                ]);
                for ($x = 0; $x < 2; $x++) {
                    SupplierPartNumber::create([
                        'product_id' => $product->id,
                        'supplier_id' => $hilevel->id,
                        'supplier_part_number' => 'SPN-' . Str::upper(Str::random(5)),
                        'packs_needed' => rand(1, 3),
                        'cost' => rand(250, 1200) / 100,
                        'stock' => $faker->boolean(80) ? rand(1, 100) : 0,
                    ]);
                }
            }
        }

        // Orders with full customer and address data
        $ordersData = [
            [
                'customer_name' => 'Alice Johnson',
                'customer_email' => 'alice@example.com',
                'customer_phone' => '07123456789',
                'address_line_1' => '12 Elm Street',
                'address_line_2' => 'Flat 2A',
                'city' => 'Sheffield',
                'postcode' => 'S1 2AB',
                'country' => 'UK',
                'order_comments' => null,
            ],
            [
                'customer_name' => 'Bob Smith',
                'customer_email' => 'bob@example.com',
                'customer_phone' => '07234567890',
                'address_line_1' => '349 Long Road Industrial Estate', // exactly 31 chars
                'address_line_2' => '',
                'city' => 'Manchester',
                'postcode' => 'M1 3CD',
                'country' => 'UK',
                'order_comments' => 'Urgent delivery, please.',
            ],
            [
                'customer_name' => 'Charlie Rose',
                'customer_email' => 'charlie@example.com',
                'customer_phone' => '07345678901',
                'address_line_1' => '56 Baker Street',
                'address_line_2' => 'Suite 101',
                'city' => 'Paris',
                'postcode' => '69001',
                'country' => 'France',
                'order_comments' => null,
            ],
            [
                'customer_name' => 'Diana Prince',
                'customer_email' => 'diana@example.com',
                'customer_phone' => '07456789012',
                'address_line_1' => '78 High Street',
                'address_line_2' => '',
                'city' => 'Belfast',
                'postcode' => 'BT15 4DF',
                'country' => 'Northern Ireland',
                'order_comments' => null,
            ],
            [
                'customer_name' => 'Edward King',
                'customer_email' => 'edward@example.com',
                'customer_phone' => '07567890123',
                'address_line_1' => '90 Market Square',
                'address_line_2' => 'Unit 5',
                'city' => 'Nottingham',
                'postcode' => 'NG1 5GH',
                'country' => 'UK',
                'order_comments' => 'Contact before delivery.',
            ]
        ];

        foreach ($ordersData as $data) {
            $order = Order::create(array_merge([
                'status' => 'pending',
            ], $data));

            for ($x = 0; $x < rand(1, 3); $x++) {
                $product = $products->random();
            
                // 80% chance of having a bike
                $hasBike = rand(1, 100) <= 80;
                $bike = $hasBike ? $bikes->random() : null;
            
                // If bike exists, 80% chance of having a fitment
                $hasFitment = $bike && rand(1, 100) <= 80;
                $fitment = $hasFitment ? $fitments->random() : null;
            
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'bike_id' => $bike?->id,
                    'fitment_id' => $fitment?->id,
                    'quantity' => rand(1, 2),
                    'product_name' => $product->name,
                    'product_price' => $product->price,
                    'product_sku' => $product->sku,
                    'bike_name' => $bike?->name,
                    'fitment_name' => $fitment?->name,
                ]);
            }            
        }
    }
}
