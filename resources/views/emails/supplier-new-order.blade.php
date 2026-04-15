<!DOCTYPE html>
<html>
<head><title>New Order Ready</title></head>
<body style="font-family: system-ui, sans-serif;">
    <h2>📦 New Order to Fulfill</h2>
    <p>Hello {{ $supplier->business_name }},</p>
    <p>A new order <strong>#{{ $order->order_number }}</strong> is ready for you to ship.</p>
    
    <h3>Items:</h3>
    <ul>
        @foreach($items as $item)
            <li>{{ $item->product->title }} x {{ $item->quantity }}</li>
        @endforeach
    </ul>
    
    <h3>Shipping Address:</h3>
    <p>
        {{ $order->shipping_address['name'] }}<br>
        {{ $order->shipping_address['address'] }}<br>
        {{ $order->shipping_address['city'] }}<br>
        {{ $order->shipping_address['phone'] }}
    </p>
    
    <p>
        <a href="{{ $frontendUrl }}/supplier/orders" 
           style="display: inline-block; padding: 10px 20px; background: #4F46E5; color: white; text-decoration: none; border-radius: 5px;">
            View & Fulfill Order
        </a>
    </p>
    
    <p>Thank you,<br>{{ config('app.name') }}</p>
</body>
</html>