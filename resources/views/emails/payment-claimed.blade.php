<!DOCTYPE html>
<html>
<head><title>Payment Claim</title></head>
<body style="font-family: system-ui, sans-serif;">
    <h2>💰 Payment Claim Received</h2>
    <p><strong>Customer:</strong> {{ $order->customer->name }} ({{ $order->payment_phone }})</p>
    <p><strong>Order #:</strong> {{ $order->order_number }}</p>
    <p><strong>Amount:</strong> ${{ number_format($order->total_amount, 2) }}</p>
    <p><strong>Reference:</strong> {{ $order->payment_reference }}</p>
    <p><strong>Time:</strong> {{ now()->toDateTimeString() }}</p>
    <p>
        <a href="{{ config('app.frontend_url') }}/admin/payments" 
           style="display: inline-block; padding: 10px 20px; background: #4F46E5; color: white; text-decoration: none; border-radius: 5px;">
            View in Admin Dashboard
        </a>
    </p>
</body>
</html>