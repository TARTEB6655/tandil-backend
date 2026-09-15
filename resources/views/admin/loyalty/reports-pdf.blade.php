<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loyalty Reports &amp; Export</title>
    <style>{!! \App\Support\PdfBrand::documentCss() !!}</style>
</head>
<body>
    @php
        $health = $report['health'];
        $summary = $report['summary'];
        $filters = $report['filters'];
        $scopeLabel = ($filters['customer_scope'] ?? 'all') === 'specific' ? 'Specific customer' : 'All customers';
        $periodLabel = match ($filters['period'] ?? 'month') {
            'week' => 'Week',
            'specific' => 'Specific date',
            default => 'Month',
        };
    @endphp

    @include('partials.pdf-brand-header')
    <div class="header">
        <h1>Loyalty Reports &amp; Export</h1>
        <p>Program health and filtered period summary</p>
        <p>Generated: {{ $generatedAt }}</p>
    </div>

    <div class="content">
        <div class="section">
            <div class="section-title">Filters applied</div>
            <table class="meta-table">
                <tr>
                    <td class="meta-label">Customer scope</td>
                    <td>{{ $scopeLabel }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Period</td>
                    <td>{{ $periodLabel }} ({{ $filters['date_from'] }} → {{ $filters['date_to'] }})</td>
                </tr>
                @if(($filters['customer_scope'] ?? 'all') === 'specific' && ! empty($filters['specific_customers']))
                    <tr>
                        <td class="meta-label">Customers</td>
                        <td>{{ implode(', ', $filters['specific_customers']) }}</td>
                    </tr>
                @endif
            </table>
        </div>

        <div class="section">
            <div class="section-title">Program health</div>
            <table>
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th class="num">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Outstanding</td><td class="num">{{ number_format($health['outstanding']) }}</td></tr>
                    <tr><td>Redeemed</td><td class="num">{{ number_format($health['redeemed']) }}</td></tr>
                    <tr><td>Campaigns</td><td class="num">{{ number_format($health['campaigns']) }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Summary (filtered period)</div>
            <table>
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th class="num">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Customers with points</td><td class="num">{{ number_format($summary['customers_with_points']) }}</td></tr>
                    <tr><td>Points outstanding</td><td class="num">{{ number_format($summary['points_outstanding']) }}</td></tr>
                    <tr><td>Points earned</td><td class="num">{{ number_format($summary['points_earned']) }}</td></tr>
                    <tr><td>Points redeemed</td><td class="num">{{ number_format($summary['points_redeemed']) }}</td></tr>
                    <tr><td>Rewards redeemed</td><td class="num">{{ number_format($summary['rewards_redeemed']) }}</td></tr>
                    <tr><td>Active campaigns</td><td class="num">{{ number_format($summary['active_campaigns']) }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Customer detail</div>
            @if(empty($rows))
                <p class="empty">No customers matched these filters.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th class="num">Balance</th>
                            <th class="num">Earned</th>
                            <th class="num">Redeemed</th>
                            <th class="num">Rewards</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>{{ $row['customer_id'] }}</td>
                                <td>{{ $row['customer_name'] }}</td>
                                <td>{{ $row['email'] }}</td>
                                <td class="num">{{ number_format($row['points_balance']) }}</td>
                                <td class="num">{{ number_format($row['points_earned_in_period']) }}</td>
                                <td class="num">{{ number_format($row['points_redeemed_in_period']) }}</td>
                                <td class="num">{{ number_format($row['rewards_redeemed_in_period']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="footer">Tandil Loyalty · Export PDF</div>
    </div>
</body>
</html>
