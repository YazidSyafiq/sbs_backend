<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Need Purchase Alert</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            box-sizing: border-box;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif !important;
            line-height: 1.4;
            color: #333 !important;
            background-color: #f8f9fa !important;
            max-width: 700px;
            margin: 0 auto;
            padding: 10px;
        }

        /* Force light mode untuk semua email clients */
        @media (prefers-color-scheme: dark) {

            body,
            .container,
            .header,
            .alert-info,
            table,
            th,
            td {
                background-color: inherit !important;
                color: inherit !important;
            }
        }

        .container {
            background-color: white !important;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
        }

        .header {
            background-color: #17a2b8 !important;
            color: #ffffff !important;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            line-height: 1.3;
            font-family: 'Poppins', Arial, sans-serif !important;
            font-weight: bold;
        }

        .header p {
            margin: 8px 0 0 0;
            font-size: 12px;
            font-family: 'Poppins', Arial, sans-serif !important;
            font-weight: 400;
        }

        .alert-info {
            background-color: #d1ecf1 !important;
            color: #0c5460 !important;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid #17a2b8;
            font-size: 13px;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .alert-info p {
            margin: 5px 0;
        }

        /* Desktop Table */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 10px;
        }

        .desktop-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            background-color: white !important;
            font-family: 'Poppins', Arial, sans-serif !important;
            display: table;
        }

        .desktop-table th,
        .desktop-table td {
            padding: 8px 6px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            word-wrap: break-word;
            vertical-align: top;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .desktop-table th {
            background-color: #17a2b8 !important;
            color: #ffffff !important;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .desktop-table td {
            font-weight: 400;
        }

        .need-purchase-row {
            background-color: #d1ecf1 !important;
        }

        .need-purchase-row:nth-child(even) {
            background-color: #bee5eb !important;
        }

        .critical-row {
            background-color: #f8d7da !important;
        }

        .critical-row:nth-child(even) {
            background-color: #f5c2c7 !important;
        }

        /* Column specific widths */
        .col-code {
            width: 12%;
        }

        .col-name {
            width: 20%;
        }

        .col-category {
            width: 15%;
        }

        .col-available {
            width: 10%;
        }

        .col-pending {
            width: 10%;
        }

        .col-projected {
            width: 10%;
        }

        .col-needed {
            width: 10%;
        }

        .col-status {
            width: 13%;
        }

        /* Mobile Card Layout */
        .mobile-cards {
            display: none;
        }

        .product-card {
            background-color: #d1ecf1 !important;
            border: 1px solid #17a2b8;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .product-card.critical {
            background-color: #f8d7da !important;
            border-color: #dc3545;
        }

        .product-card-header {
            background-color: #17a2b8 !important;
            color: #ffffff !important;
            padding: 10px 12px;
            font-weight: 600;
            font-size: 14px;
            font-family: 'Poppins', Arial, sans-serif !important;
            line-height: 1.3;
        }

        .product-card-header.critical {
            background-color: #dc3545 !important;
        }

        .product-info {
            font-size: 12px;
            margin-top: 6px;
            opacity: 0.95;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        /* Mobile Details Table */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            background-color: transparent !important;
            margin: 0;
            font-size: 12px;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .details-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #17a2b8;
            font-weight: 400;
            vertical-align: top;
            font-family: 'Poppins', Arial, sans-serif !important;
            background-color: #d1ecf1 !important;
        }

        .product-card.critical .details-table td {
            border-bottom-color: #dc3545;
            background-color: #f8d7da !important;
        }

        .details-label {
            font-weight: 600;
            color: #0c5460;
            width: 40%;
        }

        .product-card.critical .details-label {
            color: #721c24;
        }

        .details-value {
            font-weight: 500;
            color: #333;
        }

        .critical-value {
            font-weight: 600;
            color: #dc3545;
        }

        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-critical {
            background-color: #dc3545;
            color: white;
        }

        .status-low {
            background-color: #ffc107;
            color: #212529;
        }

        .footer {
            margin-top: 20px;
            padding: 15px;
            background-color: #2c2c2c !important;
            color: #ffffff !important;
            border-radius: 8px;
            font-size: 11px;
            text-align: center;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .footer p {
            margin: 5px 0;
            font-weight: 400;
            color: #ffffff !important;
        }

        .logo {
            height: 40px;
            width: auto;
        }

        /* Mobile Responsive */
        @media screen and (max-width: 600px) {
            body {
                padding: 5px;
            }

            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 16px;
            }

            .header p {
                font-size: 11px;
            }

            .alert-info {
                font-size: 12px;
                padding: 10px;
            }

            /* Hide desktop table, show mobile cards */
            .desktop-table {
                display: none !important;
            }

            .mobile-cards {
                display: block !important;
            }

            .product-card-header {
                padding: 8px 10px;
                font-size: 13px;
            }

            .product-info {
                font-size: 11px;
                margin-top: 5px;
            }

            .details-table td {
                padding: 6px 10px;
                font-size: 11px;
            }

            .footer {
                font-size: 10px;
                padding: 12px;
            }

            .logo {
                height: 35px !important;
            }
        }

        /* Extra small mobile */
        @media screen and (max-width: 360px) {
            .container {
                padding: 10px;
            }

            .header h1 {
                font-size: 14px;
            }

            .product-card-header {
                font-size: 12px;
                padding: 6px 8px;
            }

            .product-info {
                font-size: 10px;
                margin-top: 4px;
            }

            .details-table td {
                padding: 5px 8px;
                font-size: 10px;
            }

            .footer {
                font-size: 9px;
                padding: 10px;
            }

            .logo {
                height: 30px !important;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🛒 PRODUCTS NEED PURCHASE ALERT</h1>
            <p>{{ now()->format('l, d F Y - H:i') }}</p>
        </div>

        @php
            $totalProducts = $needPurchaseProducts->count();
            $totalUnitsNeeded = $needPurchaseProducts->sum('need_purchase');
            $criticalProducts = $needPurchaseProducts->where('projected_stock', '<=', 0)->count();
        @endphp

        <div class="alert-info">
            <p><strong>PURCHASE PLANNING REQUIRED!</strong></p>
            <p>Found <strong>{{ $totalProducts }} product(s)</strong> that need restocking with a total of
                <strong>{{ number_format($totalUnitsNeeded) }} units</strong> needed to fulfill pending orders.
            </p>
            @if ($criticalProducts > 0)
                <p><strong>{{ $criticalProducts }} product(s)</strong> are in critical status with insufficient stock
                    for current orders.</p>
            @endif
        </div>

        <!-- Desktop Table View -->
        <div class="table-container">
            <table class="desktop-table">
                <thead>
                    <tr>
                        <th class="col-code">Code</th>
                        <th class="col-name">Product Name</th>
                        <th class="col-category">Category</th>
                        <th class="col-available">Available</th>
                        <th class="col-pending">Pending</th>
                        <th class="col-projected">Projected</th>
                        <th class="col-needed">Need Buy</th>
                        <th class="col-status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($needPurchaseProducts as $productData)
                        @php
                            $product = $productData['product'];
                            $isCritical = $productData['projected_stock'] <= 0;
                        @endphp

                        <tr class="{{ $isCritical ? 'critical-row' : 'need-purchase-row' }}">
                            <td><strong>{{ $product->code }}</strong></td>
                            <td>{{ Str::limit($product->name, 25) }}</td>
                            <td>{{ Str::limit($product->category->name ?? 'No Category', 15) }}</td>
                            <td>{{ number_format($productData['available_stock']) }}</td>
                            <td><strong>{{ number_format($productData['pending_orders']) }}</strong></td>
                            <td class="{{ $isCritical ? 'critical-value' : '' }}">
                                {{ number_format($productData['projected_stock']) }}
                            </td>
                            <td><strong>{{ number_format($productData['need_purchase']) }}</strong></td>
                            <td>
                                <span class="status-badge {{ $isCritical ? 'status-critical' : 'status-low' }}">
                                    {{ $isCritical ? 'Critical' : 'Low Stock' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="mobile-cards">
            @foreach ($needPurchaseProducts as $productData)
                @php
                    $product = $productData['product'];
                    $isCritical = $productData['projected_stock'] <= 0;
                @endphp

                <div class="product-card {{ $isCritical ? 'critical' : '' }}">
                    <div class="product-card-header {{ $isCritical ? 'critical' : '' }}">
                        <div><strong>{{ $product->code }}</strong> - {{ $product->name }}</div>
                        <div class="product-info">Category: {{ $product->category->name ?? 'No Category' }}</div>
                    </div>

                    <table class="details-table">
                        <tr>
                            <td class="details-label">Available Stock</td>
                            <td class="details-value">: {{ number_format($productData['available_stock']) }} units</td>
                        </tr>
                        <tr>
                            <td class="details-label">Pending Orders</td>
                            <td class="details-value">: <strong>{{ number_format($productData['pending_orders']) }}
                                    units</strong></td>
                        </tr>
                        <tr>
                            <td class="details-label">Projected Stock</td>
                            <td class="details-value {{ $isCritical ? 'critical-value' : '' }}">
                                : {{ number_format($productData['projected_stock']) }} units
                            </td>
                        </tr>
                        <tr>
                            <td class="details-label">Need to Purchase</td>
                            <td class="details-value">: <strong>{{ number_format($productData['need_purchase']) }}
                                    units</strong></td>
                        </tr>
                        <tr>
                            <td class="details-label">Status:</td>
                            <td class="details-value">
                                : <span class="status-badge {{ $isCritical ? 'status-critical' : 'status-low' }}">
                                    {{ $isCritical ? 'Critical' : 'Low Stock' }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            @endforeach
        </div>

        <div class="footer">
            <div style="text-align: center; margin-bottom: 15px;">
                <img src="{{ $message->embed(public_path('default/images/img_logo.png')) }}"
                    alt="{{ config('app.name') }}" class="logo">
            </div>
            <p><strong>Inventory Management Alert</strong></p>
            <p>This is an automated notification from your inventory management system.</p>
            <p style="margin: 0;"><em>{{ config('app.name') }} Team</em></p>
        </div>
    </div>
</body>

</html>
