<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $order['order_number'] }} — {{ $documentTitle }}</title>
    <style>{!! \App\Support\PdfBrand::documentCss() !!}</style>
</head>
<body>
    @include('partials.pdf-brand-header')
    <div class="header">
        <h1>{{ $businessName }}</h1>
        <p>{{ $documentTitle }}</p>
        <p>Order: {{ $order['order_number'] }} &nbsp;|&nbsp; Status: {{ $order['status_label'] }} &nbsp;|&nbsp; Generated: {{ $generatedAt }}</p>
    </div>

    <div class="content">
        <div class="section">
            <div class="section-title">Order Information</div>
            <table class="meta-table">
                <tr>
                    <td class="meta-label">Order Date</td>
                    <td>{{ $order['order_date'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Delivery Date</td>
                    <td>{{ $order['delivery_date_label'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Payment Method</td>
                    <td>{{ $order['payment_method'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Payment Status</td>
                    <td>{{ $order['payment_status'] ?? '—' }}</td>
                </tr>
                @if (!empty($order['tracking_number']))
                <tr>
                    <td class="meta-label">Tracking Number</td>
                    <td>{{ $order['tracking_number'] }}</td>
                </tr>
                @endif
            </table>
        </div>

        <div class="section">
            <div class="section-title">Customer</div>
            <table class="meta-table">
                <tr>
                    <td class="meta-label">Name</td>
                    <td>{{ $order['customer']['name'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Phone</td>
                    <td>{{ $order['customer']['phone'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Email</td>
                    <td>{{ $order['customer']['email'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Address</td>
                    <td>{!! nl2br(e($order['customer']['address_text'] ?? '—')) !!}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Products</div>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th style="width:12%">Qty</th>
                        <th style="width:18%">Unit Price</th>
                        <th style="width:18%">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($order['products'] as $product)
                        <tr>
                            <td>{{ $product['name'] }}</td>
                            <td>{{ $product['qty'] }}</td>
                            <td>{{ $product['currency'] }} {{ number_format($product['unit_price'], 2) }}</td>
                            <td>{{ $product['currency'] }} {{ number_format($product['price'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">No products found for this vendor order.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Totals</div>
            <table class="totals">
                <tr>
                    <td class="meta-label">Subtotal</td>
                    <td class="amount">{{ $order['currency'] }} {{ number_format($order['subtotal'], 2) }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Tax</td>
                    <td class="amount">{{ $order['currency'] }} {{ number_format($order['tax_amount'], 2) }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Shipping</td>
                    <td class="amount">{{ $order['currency'] }} {{ number_format($order['shipping_amount'], 2) }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Total</td>
                    <td class="amount">{{ $order['currency'] }} {{ number_format($order['total_amount'], 2) }}</td>
                </tr>
            </table>
        </div>

        @if (!empty($order['order_notes']))
        <div class="section">
            <div class="section-title">Order Notes</div>
            <div class="notes">{{ $order['order_notes'] }}</div>
        </div>
        @endif
    </div>

    <div class="footer">
        {{ $businessName }} — {{ $documentTitle }} — {{ $order['order_number'] }}
    </div>
</body>
</html>
