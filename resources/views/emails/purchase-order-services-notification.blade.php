<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Service Status Update</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            line-height: 1.4;
            color: #333 !important;
            background-color: #f8f9fa !important;
            max-width: 600px;
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
                background-color: white !important;
                color: #333 !important;
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
            background-color: white !important;
            color: #333 !important;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            border: 2px solid #f0f0f0;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            line-height: 1.3;
            font-family: 'Poppins', Arial, sans-serif;
            font-weight: bold;
            color: #333 !important;
        }

        .header p {
            margin: 8px 0 0 0;
            font-size: 12px;
            font-family: 'Poppins', Arial, sans-serif;
            font-weight: 400;
            color: #666 !important;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 8px 0;
        }

        .status-requested {
            background-color: #fff3cd !important;
            color: #856404 !important;
            border: 1px solid #ffeaa7;
        }

        .status-processing {
            background-color: #d1ecf1 !important;
            color: #0c5460 !important;
            border: 1px solid #bee5eb;
        }

        .status-shipped {
            background-color: #e2e3ff !important;
            color: #4c4d7d !important;
            border: 1px solid #c5c6f0;
        }

        .status-received {
            background-color: #d4edda !important;
            color: #155724 !important;
            border: 1px solid #c3e6cb;
        }

        .status-done {
            background-color: #d1f2eb !important;
            color: #00695c !important;
            border: 1px solid #b8e6d3;
        }

        .alert-info {
            background-color: white !important;
            color: #333 !important;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid #f0f0f0;
            font-size: 13px;
            font-family: 'Poppins', Arial, sans-serif;
        }

        .alert-info p {
            margin: 5px 0;
        }

        .po-info {
            background-color: #f8f9fa !important;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .po-info h3 {
            margin: 0 0 15px 0;
            font-size: 14px;
            color: #333 !important;
            font-weight: 600;
        }

        /* Invoice Download Button */
        .invoice-section {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa !important;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .invoice-button {
            display: inline-block;
            background-color: #007bff !important;
            color: #ffffff !important;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            font-family: 'Poppins', Arial, sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .invoice-button:hover {
            background-color: #0056b3 !important;
            color: #ffffff !important;
        }

        .invoice-button:visited {
            color: #ffffff !important;
        }

        .invoice-text {
            margin: 10px 0 5px 0;
            font-size: 12px;
            color: #666 !important;
            font-family: 'Poppins', Arial, sans-serif;
        }

        /* Table untuk PO Details */
        .po-details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            background-color: transparent !important;
            font-family: 'Poppins', Arial, sans-serif;
        }

        .po-details-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e9ecef;
            font-family: 'Poppins', Arial, sans-serif;
            background-color: transparent !important;
        }

        .po-details-table .po-info-label {
            font-weight: 500;
            color: #666 !important;
            width: 140px;
            white-space: nowrap;
        }

        .po-details-table .po-info-value {
            font-weight: 400;
            color: #333 !important;
        }

        /* Responsive Table */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 400px;
            font-size: 11px;
            background-color: white !important;
            font-family: 'Poppins', Arial, sans-serif;
            border: 1px solid #e9ecef;
        }

        th,
        td {
            padding: 8px 6px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
            word-wrap: break-word;
            vertical-align: top;
            font-family: 'Poppins', Arial, sans-serif;
        }

        th {
            background-color: #f8f9fa !important;
            color: #333 !important;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            font-weight: 400;
            color: #333 !important;
        }

        /* Column specific widths */
        .col-product {
            width: 40%;
        }

        .col-code {
            width: 30%;
        }

        .col-price {
            width: 30%;
        }

        .total-row {
            background-color: #f8f9fa !important;
            font-weight: 600;
        }

        .footer {
            margin-top: 20px;
            padding: 15px;
            background-color: #2c2c2c !important;
            color: #ffffff !important;
            border-radius: 8px;
            font-size: 11px;
            text-align: center;
            font-family: 'Poppins', Arial, sans-serif;
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

        /* Mobile Styles */
        @media screen and (max-width: 480px) {
            body {
                padding: 10px;
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

            .po-details-table .po-info-label {
                width: 120px;
                font-size: 11px;
            }

            .po-details-table {
                font-size: 11px;
            }

            table {
                font-size: 9px;
                min-width: 300px;
            }

            th,
            td {
                padding: 4px 3px;
            }

            th {
                font-size: 8px;
            }

            .alert-info {
                font-size: 11px;
                padding: 10px;
            }

            .invoice-button {
                font-size: 11px;
                padding: 10px 20px;
            }

            .invoice-text {
                font-size: 11px;
            }

            .footer {
                font-size: 9px;
                padding: 10px;
            }

            .logo {
                height: 30px !important;
            }
        }

        @media screen and (max-width: 360px) {
            .po-details-table .po-info-label {
                width: 100px;
                font-size: 10px;
            }

            .po-details-table {
                font-size: 10px;
            }

            table {
                font-size: 8px;
            }

            th {
                font-size: 7px;
            }

            .invoice-button {
                font-size: 10px;
                padding: 8px 16px;
            }

            .footer {
                font-size: 8px;
            }

            .logo {
                height: 25px !important;
            }
        }

        /* Gmail and Outlook compatibility */
        .mso-hide {
            display: none;
        }

        /* Prevent auto-scaling */
        @media screen and (max-device-width: 480px) {

            table,
            td,
            th {
                -webkit-text-size-adjust: none;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>PURCHASE ORDER SERVICE STATUS UPDATE</h1>
            <p><strong>{{ $purchaseService->po_number }}</strong></p>
            <p>{{ now()->format('l, d F Y - H:i') }}</p>
        </div>

        <div class="alert-info">
            @php
                $statusMessages = [
                    'Requested' => 'A new purchase request has been submitted and is awaiting approval.',
                    'Approved' => 'The purchase order has been approved and is awaiting to proccess.',
                    'In Progress' => 'The purchase order is currently in progress and the service is being delivered.',
                    'Done' => 'The purchase order has been completed successfully.',
                    'Cancelled' => 'The purchase order has been cancelled.',
                ];
            @endphp
            <p><strong>STATUS UPDATE:</strong></p>
            <p>{{ $statusMessages[$purchaseService->status] ?? 'Purchase order status has been updated.' }}</p>
        </div>

        <div class="po-info">
            <h3>Purchase Order Service Details</h3>
            <table class="po-details-table">
                <tr>
                    <td class="po-info-label">PO Number</td>
                    <td class="po-info-value">: {{ $purchaseService->po_number }}</td>
                </tr>
                <tr>
                    <td class="po-info-label">PO Name</td>
                    <td class="po-info-value">: {{ $purchaseService->name }}</td>
                </tr>
                <tr>
                    <td class="po-info-label">Requested By</td>
                    <td class="po-info-value">: {{ $purchaseService->user->name }}</td>
                </tr>
                @if ($purchaseService->user->branch)
                    <tr>
                        <td class="po-info-label">Branch</td>
                        <td class="po-info-value">: {{ $purchaseService->user->branch->name }}
                            ({{ $purchaseService->user->branch->code }})</td>
                    </tr>
                @endif
                <tr>
                    <td class="po-info-label">Type</td>
                    <td class="po-info-value">: {{ ucfirst($purchaseService->type_po) }} Purchase</td>
                </tr>
                <tr>
                    <td class="po-info-label">Order Date</td>
                    <td class="po-info-value">: {{ $purchaseService->order_date->format('d M Y') }}</td>
                </tr>
                @if ($purchaseService->expected_proccess_date)
                    <tr>
                        <td class="po-info-label">Scheduled Date</td>
                        <td class="po-info-value">: {{ $purchaseService->expected_proccess_date->format('d M Y') }}
                        </td>
                    </tr>
                @endif
            </table>
        </div>

        <div class="table-container">
            <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #333; font-weight: 600;">Order Items</h3>
            <table>
                <thead>
                    <tr>
                        <th class="col-product">Service Name</th>
                        <th class="col-code">Code</th>
                        <th class="col-price">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseService->items as $item)
                        <tr>
                            <td><strong>{{ $item->service->name }}</strong></td>
                            <td>{{ $item->service->code }}</td>
                            <td>Rp {{ number_format($item->selling_price, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="2" style="text-align: right;"><strong>TOTAL AMOUNT:</strong></td>
                        <td><strong>Rp {{ number_format($purchaseService->total_amount, 0, ',', '.') }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($purchaseService->notes)
            <div style="margin-top: 20px; padding: 12px; background-color: #f8f9fa; border-radius: 5px;">
                <h4 style="margin: 0 0 8px 0; font-size: 12px; color: #333;">Notes:</h4>
                <p style="margin: 0; font-size: 11px; color: #666;">{{ $purchaseService->notes }}</p>
            </div>
        @endif

        {{-- Invoice Download Button - Show only for Requested and Done status --}}
        @if (in_array($purchaseService->status, ['Approved', 'Done']))
            <div class="invoice-section">
                <p class="invoice-text">
                    <strong>Purchase Order Invoice</strong><br><br>
                </p>
                <a href="{{ route('purchase-service.invoice', ['purchaseService' => $purchaseService->id]) }}"
                    class="invoice-button">
                    Download Invoice
                </a>
                <p style="font-size: 10px; color: #888; margin-top: 10px; font-style: italic;">
                    Click the button above to download the PDF document
                </p>
            </div>
        @endif

        <div class="footer">
            <div style="text-align: center; margin-bottom: 15px;">
                <img src="{{ $message->embed(public_path('default/images/img_logo.png')) }}"
                    alt="{{ config('app.name') }}" class="logo">
            </div>
            <p><strong>Purchase Order Management System</strong></p>
            <p>This is an automated notification from your purchase management system.</p>
            <p style="margin: 0;"><em>{{ config('app.name') }} Team</em></p>
        </div>
    </div>
</body>

</html>
