<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $businessName }} — Analytics</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.4; }
        .header { background: #0f766e; color: #fff; padding: 18px 20px; margin-bottom: 16px; }
        .header h1 { font-size: 20px; margin-bottom: 4px; }
        .header p { font-size: 11px; opacity: 0.95; margin-top: 2px; }
        .content { padding: 0 20px 20px; }
        .section { margin-bottom: 16px; page-break-inside: avoid; }
        .section-title { font-size: 13px; font-weight: bold; color: #0f766e; border-bottom: 2px solid #0f766e; padding-bottom: 4px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th { background: #0f766e; color: #fff; font-weight: bold; text-align: left; padding: 7px 8px; font-size: 10px; }
        td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #f9fafb; }
        .meta-table td { border: none; padding: 4px 8px; background: transparent; }
        .meta-label { color: #6b7280; width: 28%; font-weight: bold; }
        .empty { color: #6b7280; font-style: italic; padding: 8px 0; }
        .footer { margin-top: 12px; font-size: 9px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $businessName }}</h1>
        <p>Vendor Performance Analytics Report</p>
        <p>Period: {{ $analytics['period_label'] }} &nbsp;|&nbsp; Generated: {{ $generatedAt }}</p>
    </div>

    <div class="content">
        <div class="section">
            <div class="section-title">Overview</div>
            <table>
                <thead>
                    <tr>
                        <th style="width:28%">Metric</th>
                        <th style="width:18%">Value</th>
                        <th style="width:36%">Description</th>
                        <th style="width:18%">Growth</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Total Products</td>
                        <td>{{ $analytics['overview']['total_products']['value'] }}</td>
                        <td>{{ $analytics['overview']['total_products']['subtitle'] }}</td>
                        <td>{{ $analytics['overview']['total_products']['growth_display'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td>Total Orders</td>
                        <td>{{ $analytics['overview']['total_orders']['value'] }}</td>
                        <td>{{ $analytics['overview']['total_orders']['subtitle'] }}</td>
                        <td>—</td>
                    </tr>
                    <tr>
                        <td>Total Revenue</td>
                        <td>{{ $analytics['overview']['total_revenue']['display'] }}</td>
                        <td>{{ $analytics['overview']['total_revenue']['subtitle'] }}</td>
                        <td>{{ $analytics['overview']['total_revenue']['growth_display'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td>Total Views</td>
                        <td>{{ $analytics['overview']['total_views']['value'] }}</td>
                        <td>{{ $analytics['overview']['total_views']['subtitle'] }}</td>
                        <td>—</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Performance Metrics</div>
            <table>
                <thead>
                    <tr>
                        <th style="width:30%">Metric</th>
                        <th style="width:20%">Value</th>
                        <th style="width:50%">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Conversion Rate</td>
                        <td>{{ $analytics['performance_metrics']['conversion_rate']['display'] }}</td>
                        <td>{{ $analytics['performance_metrics']['conversion_rate']['subtitle'] }}</td>
                    </tr>
                    <tr>
                        <td>Average Order Value</td>
                        <td>{{ $analytics['performance_metrics']['avg_order_value']['display'] }}</td>
                        <td>{{ $analytics['performance_metrics']['avg_order_value']['subtitle'] }}</td>
                    </tr>
                    <tr>
                        <td>Customer Satisfaction</td>
                        <td>{{ $analytics['performance_metrics']['satisfaction']['display'] }}</td>
                        <td>{{ $analytics['performance_metrics']['satisfaction']['subtitle'] }}</td>
                    </tr>
                    <tr>
                        <td>Return Rate</td>
                        <td>{{ $analytics['performance_metrics']['return_rate']['display'] }}</td>
                        <td>{{ $analytics['performance_metrics']['return_rate']['subtitle'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Top Products</div>
            @if(empty($analytics['top_products']))
                <p class="empty">No product sales in this period.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th style="width:8%">#</th>
                            <th style="width:42%">Product</th>
                            <th style="width:15%">Orders</th>
                            <th style="width:20%">Revenue</th>
                            <th style="width:15%">Growth</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($analytics['top_products'] as $product)
                            <tr>
                                <td>{{ $product['rank'] }}</td>
                                <td>{{ $product['name'] }}</td>
                                <td>{{ $product['orders'] }}</td>
                                <td>{{ $product['revenue_display'] }}</td>
                                <td>{{ $product['growth_display'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="section">
            <div class="section-title">Recent Activity</div>
            @if(empty($analytics['recent_activity']))
                <p class="empty">No recent activity in this period.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th style="width:14%">Type</th>
                            <th style="width:46%">Title</th>
                            <th style="width:18%">Value</th>
                            <th style="width:22%">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($analytics['recent_activity'] as $activity)
                            <tr>
                                <td>{{ ucfirst($activity['type']) }}</td>
                                <td>{{ $activity['title'] }}</td>
                                <td>{{ $activity['value'] }}</td>
                                <td>{{ $activity['time_ago'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="section">
            <div class="section-title">Daily Performance (Last 7 Days)</div>
            <table>
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Orders</th>
                        <th>Revenue (AED)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analytics['trends']['daily_performance']['data_points'] as $point)
                        <tr>
                            <td>{{ $point['label'] }}</td>
                            <td>{{ $point['orders'] }}</td>
                            <td>{{ number_format($point['revenue'], 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Weekly Revenue (Last 7 Weeks)</div>
            <table>
                <thead>
                    <tr>
                        <th>Week</th>
                        <th>Revenue (AED)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analytics['trends']['weekly_revenue']['data_points'] as $point)
                        <tr>
                            <td>{{ $point['label'] }}</td>
                            <td>{{ number_format($point['revenue'], 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="footer">Generated by Tandil Vendor Analytics</div>
    </div>
</body>
</html>
