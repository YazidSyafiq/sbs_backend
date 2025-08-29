<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Expiring Soon Alert</title>
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
            .alert-warning,
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
            background-color: #ffc107 !important;
            color: #212529 !important;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            line-height: 1.3;
            font-family: 'Poppins', Arial, sans-serif;
            font-weight: bold;
        }

        .header p {
            margin: 8px 0 0 0;
            font-size: 12px;
            font-family: 'Poppins', Arial, sans-serif;
            font-weight: 400;
        }

        .alert-warning {
            background-color: #fff3cd !important;
            color: #856404 !important;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid #ffc107;
            font-size: 13px;
            font-family: 'Poppins', Arial, sans-serif;
        }

        .alert-warning p {
            margin: 5px 0;
        }

        /* Responsive Table */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 400px;
            font-size: 11px;
            background-color: white !important;
            font-family: 'Poppins', Arial, sans-serif;
        }

        th,
        td {
            padding: 6px 4px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            word-wrap: break-word;
            vertical-align: top;
            font-family: 'Poppins', Arial, sans-serif;
        }

        th {
            background-color: #ffc107 !important;
            color: #212529 !important;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            font-weight: 400;
        }

        .expiring-row {
            background-color: #fff3cd !important;
        }

        .expiring-row:nth-child(even) {
            background-color: #ffeaa7 !important;
        }

        /* Column specific widths */
        .col-code {
            width: 20%;
        }

        .col-name {
            width: 20%;
        }

        .col-category {
            width: 20%;
        }

        .col-stock {
            width: 20%;
        }

        .col-expiry {
            width: 20%;
        }

        .footer {
            margin-top: 20px;
            padding: 15px;
            background-color: #2c2c2c !important;
            /* Dark grey background */
            color: #ffffff !important;
            /* White text */
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

            table {
                font-size: 6px;
                min-width: 300px;
            }

            th,
            td {
                padding: 3px 2px;
            }

            th {
                font-size: 7px;
            }

            .alert-warning {
                font-size: 11px;
                padding: 10px;
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
            table {
                font-size: 7px;
            }

            th {
                font-size: 6px;
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
            <h1>⏰ PRODUCTS EXPIRING SOON</h1>
            <p>{{ now()->format('l, d F Y - H:i') }}</p>
        </div>

        <div class="alert-warning">
            <p><strong>ATTENTION NEEDED!</strong></p>
            <p>The following <strong>{{ $expiringSoonProducts->count() }} product(s)</strong> will expire within the
                next 30 days. Please review and take appropriate action:</p>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="col-code">Code</th>
                        <th class="col-name">Name</th>
                        <th class="col-category">Category</th>
                        <th class="col-stock">Stock</th>
                        <th class="col-expiry">Expiry Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($expiringSoonProducts as $product)
                        @php
                            $expiryDate =
                                $product->expiry_date instanceof \Carbon\Carbon
                                    ? $product->expiry_date
                                    : \Carbon\Carbon::parse($product->expiry_date);
                        @endphp
                        <tr class="expiring-row">
                            <td><strong>{{ $product->code }}</strong></td>
                            <td>{{ Str::limit($product->name, 20) }}</td>
                            <td>{{ Str::limit($product->category->name, 12) }}</td>
                            <td>{{ $product->stock }} pcs</td>
                            <td>{{ $expiryDate->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
