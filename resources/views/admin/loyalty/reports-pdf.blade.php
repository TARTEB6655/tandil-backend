<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loyalty Reports &amp; Export</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.4; }
        .header { background: #312e81; color: #fff; padding: 18px 20px; margin-bottom: 16px; }
        .header h1 { font-size: 20px; margin-bottom: 4px; }
        .header p { font-size: 11px; opacity: 0.95; margin-top: 2px; }
        .content { padding: 0 20px 20px; }
        .section { margin-bottom: 16px; page-break-inside: avoid; }
        .section-title { font-size: 13px; font-weight: bold; color: #312e81; border-bottom: 2px solid #312e81; padding-bottom: 4px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th { background: #312e81; color: #fff; font-weight: bold; text-align: left; padding: 7px 8px; font-size: 10px; }
        td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #f9fafb; }
        .meta-table td { border: none; padding: 4px 8px; background: transparent; }
        .meta-label { color: #6b7280; width: 28%; font-weight: bold; }
        .empty { color: #6b7280; font-style: italic; padding: 8px 0; }
        .footer { margin-top: 12px; font-size: 9px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px; }
        .num { text-align: right; }
    </style>
</head>
<body>
    @php
        $health = $report['health'];
        $filters = $report['filters'];
        $summary = $report['summary'];
        $scopeLabel = ($filters['customer_scope'] ?? 'all') === 'specific' ? 'Specific customer' : 'All customers';
        $periodLabel = match ($filters['period'] ?? 'month') {
            'week' => 'Week',
            'specific' => 'Specific date',
            default => 'Month',
        };
    @endphp

    <div class="header">
        <h1>Loyalty Reports &amp; Export</h1>
        <p>Overview of loyalty activity for offline analysis</p>
        <p>Period: {{ $periodLabel }} ({{ $filters['date_from'] }} → {{ $filters['date_to'] }}) &nbsp;|&nbsp; Generated: {{ $generatedAt }}</p>
    </div>

    <div class="content">
        <div class="section">
            <div class="section-title">Filters</div>
            <table class="meta-table">
                <tr>
                    <td class="meta-label">Customer scope</td>
                    <td>{{ $scopeLabel }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Period</td>
                    <td>{{ $periodLabel }} ({{ $filters['date_from'] }} → {{ $filters['date_to'] }})</td>
                </tr>
                @if(($filters['customer_scope'] ?? 'all') === 'specific')
                    <tr>
                        <td class="meta-label">Customers</td>
                        <td>
                            {{ count($filters['specific_customers'] ?? []) ? implode(', ', $filters['specific_customers']) : '—' }}
                        </td>
                    </tr>
                @endif
            </table>
        </div>

        <div class="section">
            <div class="section-title">Program health</div>
            <table>
                <thead>
                    <tr>
                        <th>Outstanding</th>
                        <th>Redeemed</th>
                        <th>Campaigns</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="num">{{ number_format($health['outstanding']) }}</td>
                        <td class="num">{{ number_format($health['redeemed']) }}</td>
                        <td class="num">{{ number_format($health['campaigns']) }}</td>
                        <td>{{ $health['status_label'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Summary</div>
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
            <div class="section-title">Customers</div>
            @if(count($customers) === 0)
                <p class="empty">No customers match the selected filters.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th class="num">Balance</th>
                            <th class="num">Earned</th>
                            <th class="num">Redeemed</th>
                            <th class="num">Rewards</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $row)
                            <tr>
                                <td>{{ $row['id'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['email'] }}</td>
                                <td class="num">{{ number_format($row['points_balance']) }}</td>
                                <td class="num">{{ number_format($row['points_earned']) }}</td>
                                <td class="num">{{ number_format($row['points_redeemed']) }}</td>
                                <td class="num">{{ number_format($row['rewards_redeemed']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="footer">Tandil Loyalty Report · PDF export</div>
    </div>
</body>
</html>
