<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expired Product Batches Alert</title>
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
            .alert-danger,
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
            background-color: #dc3545 !important;
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

        .alert-danger {
            background-color: #f8d7da !important;
            color: #721c24 !important;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid #dc3545;
            font-size: 13px;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .alert-danger p {
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
            background-color: #dc3545 !important;
            color: #ffffff !important;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .desktop-table td {
            font-weight: 400;
        }

        .batch-row {
            background-color: #f8d7da !important;
        }

        .batch-row:nth-child(even) {
            background-color: #f5c2c7 !important;
        }

        /* Column specific widths */
        .col-code {
            width: 18%;
        }

        .col-name {
            width: 25%;
        }

        .col-category {
            width: 18%;
        }

        .col-batch {
            width: 22%;
        }

        .col-stock {
            width: 12%;
        }

        .col-expiry {
            width: 15%;
        }

        /* Mobile Card Layout */
        .mobile-cards {
            display: none;
        }

        .product-card {
            background-color: #f8d7da !important;
            border: 1px solid #dc3545;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .product-card-header {
            background-color: #dc3545 !important;
            color: #ffffff !important;
            padding: 10px 12px;
            font-weight: 600;
            font-size: 14px;
            font-family: 'Poppins', Arial, sans-serif !important;
            line-height: 1.3;
        }

        .product-info {
            font-size: 12px;
            margin-top: 6px;
            opacity: 0.95;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        /* Mobile Batch Table */
        .batch-table {
            width: 100%;
            border-collapse: collapse;
            background-color: transparent !important;
            margin: 0;
            font-size: 12px;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .batch-table th {
            background-color: #dc3545 !important;
            color: #ffffff !important;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #dc3545;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .batch-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #dc3545;
            font-weight: 400;
            vertical-align: top;
            word-wrap: break-word;
            background-color: #f8d7da !important;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .batch-table tr:nth-child(even) td {
            background-color: #f5c2c7 !important;
        }

        .batch-number-cell {
            font-size: 11px;
            font-weight: 500;
            font-family: 'Poppins', Arial, sans-serif !important;
            line-height: 1.4;
        }

        .stock-cell {
            text-align: center;
            font-weight: 600;
            font-family: 'Poppins', Arial, sans-serif !important;
        }

        .expiry-cell {
            text-align: center;
            font-weight: 600;
            color: #dc3545;
            font-family: 'Poppins', Arial, sans-serif !important;
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

            .alert-danger {
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

            .batch-table th {
                padding: 8px 10px;
                font-size: 9px;
            }

            .batch-table td {
                padding: 8px 10px;
                font-size: 11px;
            }

            .batch-number-cell {
                font-size: 10px;
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

            .batch-table th {
                padding: 6px 8px;
                font-size: 8px;
            }

            .batch-table td {
                padding: 6px 8px;
                font-size: 10px;
            }

            .batch-number-cell {
                font-size: 9px;
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
            <h1>🚨 EXPIRED PRODUCT BATCHES ALERT</h1>
            <p>{{ now()->format('l, d F Y - H:i') }}</p>
        </div>

        @php
            $totalProducts = $expiredProductsData->count();
            $totalBatches = $expiredProductsData->sum(function ($productData) {
                return $productData['batches']->count();
            });
            $totalExpiredStock = $expiredProductsData->sum(function ($productData) {
                return $productData['total_expired_stock'];
            });
        @endphp

        <div class="alert-danger">
            <p><strong>URGENT ACTION REQUIRED!</strong></p>
            <p>Found <strong>{{ $totalProducts }} product(s)</strong> with <strong>{{ $totalBatches }} expired
                    batch(es)</strong> totaling <strong>{{ number_format($totalExpiredStock) }} units</strong> that need
                immediate attention:</p>
        </div>

        <!-- Desktop Table View -->
        <div class="table-container">
            <table class="desktop-table">
                <thead>
                    <tr>
                        <th class="col-code">Code</th>
                        <th class="col-name">Product Name</th>
                        <th class="col-category">Category</th>
                        <th class="col-batch">Batch Number</th>
                        <th class="col-stock">Stock</th>
                        <th class="col-expiry">Expiry Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($expiredProductsData as $productData)
                        @php
                            $product = $productData['product'];
                            $batches = $productData['batches'];
                        @endphp

                        @foreach ($batches as $index => $batch)
                            @php
                                $expiryDate =
                                    $batch->expiry_date instanceof \Carbon\Carbon
                                        ? $batch->expiry_date
                                        : \Carbon\Carbon::parse($batch->expiry_date);
                            @endphp
                            <tr class="batch-row">
                                @if ($index === 0)
                                    <td rowspan="{{ $batches->count() }}"><strong>{{ $product->code }}</strong></td>
                                    <td rowspan="{{ $batches->count() }}">{{ Str::limit($product->name, 25) }}</td>
                                    <td rowspan="{{ $batches->count() }}">
                                        {{ Str::limit($product->category->name ?? 'No Category', 15) }}</td>
                                @endif
                                <td><strong>{{ $batch->batch_number }}</strong></td>
                                <td>{{ number_format($batch->quantity) }}</td>
                                <td><strong>{{ $expiryDate->format('d M Y') }}</strong></td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="mobile-cards">
            @foreach ($expiredProductsData as $productData)
                @php
                    $product = $productData['product'];
                    $batches = $productData['batches'];
                @endphp

                <div class="product-card">
                    <div class="product-card-header">
                        <div><strong>{{ $product->code }}</strong> - {{ $product->name }}</div>
                        <div class="product-info">Category: {{ $product->category->name ?? 'No Category' }}</div>
                    </div>

                    <table class="batch-table">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Batch Number</th>
                                <th style="width: 25%;">Stock</th>
                                <th style="width: 25%;">Expiry</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($batches as $batch)
                                @php
                                    $expiryDate =
                                        $batch->expiry_date instanceof \Carbon\Carbon
                                            ? $batch->expiry_date
                                            : \Carbon\Carbon::parse($batch->expiry_date);
                                @endphp
                                <tr>
                                    <td class="batch-number-cell">{{ $batch->batch_number }}</td>
                                    <td class="stock-cell">{{ number_format($batch->quantity) }}</td>
                                    <td class="expiry-cell">{{ $expiryDate->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
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
            <p>This is an automated notification from your batch-based inventory system.</p>
            <p style="margin: 0;"><em>{{ config('app.name') }} Team</em></p>
        </div>
    </div>
</body>

</html>
