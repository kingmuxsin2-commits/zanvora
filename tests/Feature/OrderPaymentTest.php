<?php

use App\Models\User;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Order;
use App\Models\Fulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer can place an order and stock is deducted', function () {
    // Setup
    $customer = User::factory()->create(['role' => 'customer']);
    $supplierUser = User::factory()->supplier()->create();
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id, 'is_approved' => true]);
    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'wholesale_price' => 10.00,
        'retail_price' => 15.00,
        'stock_qty' => 5,
        'status' => 'active',
    ]);

    $this->actingAs($customer, 'sanctum');

    $orderData = [
        'shipping_address' => [
            'name' => 'Test User',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'Test City',
        ],
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ];

    $response = $this->postJson('/api/orders', $orderData);

    $response->assertStatus(201);
    $this->assertDatabaseHas('orders', [
        'customer_id' => $customer->id,
        'total_amount' => 30.00,
        'payment_status' => 'pending_manual',
    ]);
    $this->assertDatabaseHas('order_items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
    $this->assertDatabaseHas('fulfillments', [
        'supplier_id' => $supplier->id,
        'status' => 'awaiting_payment',
    ]);
    $this->assertEquals(3, $product->fresh()->stock_qty);
});

test('admin can confirm payment and fulfillments become pending', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();
    $supplierUser = User::factory()->supplier()->create();
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id, 'is_approved' => true]);
    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'wholesale_price' => 10.00,
        'retail_price' => 15.00,
        'stock_qty' => 5,
        'status' => 'active',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-TEST-001',
        'customer_id' => $customer->id,
        'total_amount' => 15.00,
        'shipping_address' => ['name' => 'Test'],
        'status' => 'pending_payment',
        'payment_method' => 'mobile_money',
        'payment_reference' => 'ORD-TEST-001',
        'payment_status' => 'pending_manual',
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 15.00,
        'wholesale_cost' => 10.00,
        'supplier_id' => $supplier->id,
        'commission_earned' => 5.00,
    ]);

    Fulfillment::create([
        'order_id' => $order->id,
        'supplier_id' => $supplier->id,
        'status' => 'awaiting_payment',
    ]);

    $this->actingAs($admin, 'sanctum');

    $response = $this->postJson("/api/admin/orders/{$order->id}/confirm-payment");

    $response->assertStatus(200);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'payment_status' => 'paid',
        'status' => 'processing',
    ]);
    $this->assertDatabaseHas('fulfillments', [
        'order_id' => $order->id,
        'status' => 'pending',
    ]);
});