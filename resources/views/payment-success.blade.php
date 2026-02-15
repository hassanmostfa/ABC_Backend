<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Successful - {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .card { background: #fff; border-radius: 12px; padding: 2rem; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .icon { width: 64px; height: 64px; margin: 0 auto 1.25rem; background: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .icon svg { width: 36px; height: 36px; color: #fff; }
        h1 { font-size: 1.35rem; color: #111; margin-bottom: 0.5rem; }
        p { color: #666; font-size: 0.95rem; line-height: 1.5; }
        .order { margin-top: 1rem; font-weight: 600; color: #333; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1>Payment Successful</h1>
        <p>Thank you. Your payment has been completed successfully.</p>
        @if(!empty($order_number))
            <p class="order">Order: {{ $order_number }}</p>
        @endif
    </div>
</body>
</html>
