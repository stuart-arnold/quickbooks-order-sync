<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'status' => 'pending',
            'customer_name' => $this->faker->name,
            'customer_email' => $this->faker->safeEmail,
            'customer_phone' => $this->faker->phoneNumber,
            'address_line_1' => $this->faker->streetAddress,
            'address_line_2' => null,
            'city' => $this->faker->city,
            'postcode' => $this->faker->postcode,
            'country' => 'UK',
            'order_comments' => null,
        ];
    }
}
