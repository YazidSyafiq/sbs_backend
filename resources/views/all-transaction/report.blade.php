<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>All Transaction Report - {{ $fromDate->format('d/m/Y') }} to {{ $untilDate->format('d/m/Y') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- jsPDF library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9px;
            line-height: 1.2;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }

        .container {
            background-color: white;
            border-radius: 5px;
            padding: 15px;
            margin: 5px auto;
            max-width: 1400px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            border: 2px solid #28a745;
        }

        .header h1 {
            margin: 0;
            font-size: 16px;
            line-height: 1.2;
            font-weight: bold;
            color: white;
        }

        .header p {
            margin: 5px 0 0 0;
            font-size: 11px;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.9);
        }

        .report-info {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }

        .report-info h3 {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: #333;
            font-weight: 600;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            font-size: 9px;
        }

        .info-item {
            margin-bottom: 6px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            display: inline-block;
            width: 120px;
        }

        .info-value {
            color: #333;
            font-weight: 400;
        }

        .filter-active {
            background-color: #e8f5e8;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .filter-active h4 {
            margin: 0 0 5px 0;
            font-size: 10px;
            color: #28a745;
            font-weight: 600;
        }

        .filter-tag {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8px;
            margin: 2px;
            font-weight: 500;
        }

        .summary-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }

        .summary-section h3 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .summary-card {
            background-color: white;
            padding: 12px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            text-align: center;
        }

        .summary-card.revenue {
            border-left: 4px solid #28a745;
        }

        .summary-card.cost {
            border-left: 4px solid #dc3545;
        }

        .summary-card.profit {
            border-left: 4px solid #17a2b8;
        }

        .summary-card.transactions {
            border-left: 4px solid #6f42c1;
        }

        .summary-card.receivables {
            border-left: 4px solid #0056b3;
        }

        .summary-card.payables {
            border-left: 4px solid #dc3545;
        }

        .summary-label {
            font-size: 10px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }

        .summary-value.positive {
            color: #28a745;
        }

        .summary-value.negative {
            color: #dc3545;
        }

        .summary-value.blue {
            color: #0056b3;
        }

        .table-container {
            margin-top: 15px;
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
            background-color: white;
            border: 1px solid #dee2e6;
            min-width: 1400px;
        }

        .report-table th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 600;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            padding: 6px 4px;
            text-align: center;
            border-bottom: 2px solid #dee2e6;
            border-right: 1px solid #dee2e6;
            white-space: nowrap;
        }

        .report-table td {
            padding: 4px 3px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            border-right: 1px solid #dee2e6;
            word-wrap: break-word;
            vertical-align: middle;
            font-weight: 400;
            color: #333;
            font-size: 7px;
        }

        .transaction-type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 6px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .type-income {
            background-color: #d4edda;
            color: #155724;
        }

        .type-expense {
            background-color: #f8d7da;
            color: #721c24;
        }

        .type-si-product {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .type-si-service {
            background-color: #e2e3ff;
            color: #4c4d7d;
        }

        /* Fixed CSS for PI Product (Supplier) */
        .type-pi-product-supplier,
        .type-pi-product {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 4px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 6px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .payment-paid,
        .payment-received {
            background-color: #d4edda;
            color: #155724;
        }

        .payment-unpaid,
        .payment-pending {
            background-color: #f8d7da;
            color: #721c24;
        }

        .footer-section {
            margin-top: 15px;
            padding: 10px;
            background-color: #2c2c2c;
            color: #ffffff;
            border-radius: 5px;
            font-size: 8px;
            text-align: center;
        }

        .footer-section p {
            margin: 2px 0;
            font-weight: 400;
            color: #ffffff;
        }

        .footer-section strong {
            font-weight: bold;
        }

        /* Action Buttons */
        .action-buttons {
            margin: 20px 0;
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 0 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-download {
            background: #28a745;
            color: white;
        }

        .btn-download:hover {
            background: #218838;
            transform: translateY(-1px);
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
            font-size: 14px;
            font-weight: bold;
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #28a745;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin-right: 15px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Hide buttons when printing */
        @media print {
            .action-buttons {
                display: none !important;
            }

            .loading-overlay {
                display: none !important;
            }

            body {
                background-color: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
                max-width: none;
            }
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .action-buttons {
                padding: 0 10px;
                margin: 15px 0;
            }

            .btn {
                font-size: 14px;
                padding: 12px 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .table-container {
                font-size: 7px;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner"></div>
        Generating Report PDF...
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <button class="btn btn-download" onclick="downloadReportPDF()">
            <span>📊</span> Download Report PDF
        </button>
    </div>

    <div class="container" id="report-content">
        <!-- Header -->
        <div class="header">
            <h1>ALL TRANSACTION REPORT</h1>
            <p><strong>Period: {{ $fromDate->format('d F Y') }} - {{ $untilDate->format('d F Y') }}</strong></p>
        </div>

        <!-- Report Info -->
        <div class="report-info">
            <h3>Report Information</h3>
            <div class="info-grid">
                <div>
                    <div class="info-item">
                        <span class="info-label">Report Date</span>
                        <span class="info-value">: {{ now()->format('d F Y, H:i') }} WIB</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Period</span>
                        <span class="info-value">: {{ $fromDate->format('d F Y') }} -
                            {{ $untilDate->format('d F Y') }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Transactions</span>
                        <span class="info-value">: {{ $totalTransactions }} transactions</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Revenue</span>
                        <span class="info-value">: Rp {{ number_format($totalRevenue, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <span class="info-label">Total Cost</span>
                        <span class="info-value">: Rp {{ number_format($totalCost, 0, ',', '.') }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Net Profit/Loss</span>
                        <span class="info-value"
                            style="font-weight: bold; color: {{ $netProfit >= 0 ? '#28a745' : '#dc3545' }}">
                            : Rp {{ number_format($netProfit, 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Receivables</span>
                        <span class="info-value" style="color: #0056b3; font-weight: bold;">
                            : Rp {{ number_format($totalReceivables, 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Payables</span>
                        <span class="info-value" style="color: #dc3545; font-weight: bold;">
                            : Rp {{ number_format($totalPayables, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Active Filters -->
            @if (!empty($filterLabels))
                <div class="filter-active">
                    <h4>Active Filters:</h4>
                    @foreach ($filterLabels as $key => $value)
                        <span class="filter-tag">{{ ucfirst(str_replace('_', ' ', $key)) }}:
                            {{ $value }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Summary Section -->
        <div class="summary-section">
            <h3>Financial Summary</h3>
            <div class="summary-grid">
                <div class="summary-card revenue">
                    <div class="summary-label">Total Revenue</div>
                    <div class="summary-value positive">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</div>
                </div>
                <div class="summary-card cost">
                    <div class="summary-label">Total Cost</div>
                    <div class="summary-value negative">Rp {{ number_format($totalCost, 0, ',', '.') }}</div>
                </div>
                <div class="summary-card profit">
                    <div class="summary-label">Net Profit/Loss</div>
                    <div class="summary-value {{ $netProfit >= 0 ? 'positive' : 'negative' }}">
                        Rp {{ number_format($netProfit, 0, ',', '.') }}
                    </div>
                </div>
                <div class="summary-card transactions">
                    <div class="summary-label">Total Transactions</div>
                    <div class="summary-value">{{ $totalTransactions }}</div>
                </div>
                <div class="summary-card receivables">
                    <div class="summary-label">Total Receivables</div>
                    <div class="summary-value blue">Rp {{ number_format($totalReceivables, 0, ',', '.') }}</div>
                </div>
                <div class="summary-card payables">
                    <div class="summary-label">Total Payables</div>
                    <div class="summary-value negative">Rp {{ number_format($totalPayables, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>

        <!-- Transaction Type Summary -->
        @if ($transactionTypeSummary->count() > 0)
            <div class="summary-section">
                <h3>Transaction Type Summary</h3>
                <div class="table-container">
                    <table class="report-table" style="min-width: 700px;">
                        <thead>
                            <tr>
                                <th>Transaction Type</th>
                                <th>Count</th>
                                <th>Total Cash Flow</th>
                                <th>Total Cost</th>
                                <th>Receivables</th>
                                <th>Payables</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactionTypeSummary as $type => $summary)
                                @php
                                    // Generate CSS class name
                                    $cssClass = strtolower(
                                        str_replace([' ', '/', '(', ')'], ['-', '-', '', ''], $type),
                                    );
                                @endphp
                                <tr>
                                    <td>
                                        <span
                                            class="transaction-type-badge type-{{ $cssClass }}">{{ $type }}</span>
                                    </td>
                                    <td style="text-align: center;">{{ number_format($summary['count']) }}</td>
                                    <td style="text-align: right; font-weight: bold;">
                                        @if ($type === 'Expense' || $type === 'PI Product (Supplier)')
                                            -
                                        @else
                                            <span
                                                style="color: {{ $summary['total_amount'] >= 0 ? '#28a745' : '#dc3545' }}">
                                                {{ $summary['total_amount'] >= 0 ? '+' : '' }}Rp
                                                {{ number_format($summary['total_amount'], 0, ',', '.') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td style="text-align: right; color: #dc3545; font-weight: bold;">
                                        @if ($summary['total_cost'] > 0)
                                            Rp {{ number_format($summary['total_cost'], 0, ',', '.') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td style="text-align: right; color: #0056b3; font-weight: bold;">
                                        {{ $summary['total_receivables'] > 0 ? 'Rp ' . number_format($summary['total_receivables'], 0, ',', '.') : '-' }}
                                    </td>
                                    <td style="text-align: right; color: #dc3545; font-weight: bold;">
                                        {{ $summary['total_payables'] > 0 ? 'Rp ' . number_format($summary['total_payables'], 0, ',', '.') : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Main Transaction Table -->
        <div class="table-container">
            <h3 style="margin: 0 0 8px 0; font-size: 12px; color: #333; font-weight: 600;">All Transactions Detail</h3>
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 25px;">NO</th>
                        <th style="width: 65px;">DATE</th>
                        <th style="width: 75px;">TRANSACTION TYPE</th>
                        <th style="width: 80px;">PO NUMBER</th>
                        <th style="width: 110px;">TRANSACTION NAME</th>
                        <th style="width: 55px;">BRANCH</th>
                        <th style="width: 70px;">USER</th>
                        <th style="width: 120px;">ITEM NAME</th>
                        <th style="width: 40px;">QTY</th>
                        <th style="width: 70px;">UNIT PRICE</th>
                        <th style="width: 85px;">AMOUNT (CASH FLOW)</th>
                        <th style="width: 70px;">COST</th>
                        <th style="width: 75px;">RECEIVABLES</th>
                        <th style="width: 75px;">PAYABLES</th>
                        <th style="width: 60px;">PAYMENT STATUS</th>
                        <th style="width: 90px;">SUPPLIER/TECHNICIAN</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($transactions->count() > 0)
                        @foreach ($transactions as $index => $transaction)
                            @php
                                // Generate CSS class name for transaction type
                                $transactionCssClass = strtolower(
                                    str_replace(
                                        [' ', '/', '(', ')'],
                                        ['-', '-', '', ''],
                                        $transaction->transaction_type,
                                    ),
                                );
                            @endphp
                            <tr>
                                <td style="text-align: center; font-weight: bold;">{{ $index + 1 }}</td>
                                <td style="text-align: center;">
                                    {{ \Carbon\Carbon::parse($transaction->date)->format('d/m/Y') }}</td>
                                <td style="text-align: center;">
                                    <span class="transaction-type-badge type-{{ $transactionCssClass }}">
                                        {{ $transaction->transaction_type }}
                                    </span>
                                </td>
                                <td style="font-weight: bold;">{{ $transaction->po_number ?? '-' }}</td>
                                <td>{{ $transaction->transaction_name }}</td>
                                <td style="text-align: center;">{{ $transaction->branch ?? '-' }}</td>
                                <td>{{ $transaction->user ?? '-' }}</td>
                                <td>
                                    {{ $transaction->item_name }}
                                    @if ($transaction->item_code && $transaction->item_code !== 'N/A')
                                        <br><small>({{ $transaction->item_code }})</small>
                                    @endif
                                </td>
                                <td style="text-align: center;">
                                    {{ $transaction->quantity ? number_format($transaction->quantity) : '-' }}</td>
                                <td style="text-align: right;">
                                    @if ($transaction->unit_price)
                                        Rp {{ number_format($transaction->unit_price, 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <!-- Amount Column - Empty for Expense and PI Product (Supplier) -->
                                <td style="text-align: right; font-weight: bold;">
                                    @if ($transaction->transaction_type === 'Expense' || $transaction->transaction_type === 'PI Product (Supplier)')
                                        -
                                    @else
                                        @if ($transaction->total_amount !== null && $transaction->total_amount != 0)
                                            <span
                                                style="color: {{ $transaction->total_amount >= 0 ? '#28a745' : '#dc3545' }};">
                                                {{ $transaction->total_amount >= 0 ? '+' : '' }}Rp
                                                {{ number_format($transaction->total_amount, 0, ',', '.') }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    @endif
                                </td>
                                <!-- Cost Column - Red color -->
                                <td style="text-align: right; color: #dc3545; font-weight: bold;">
                                    @if ($transaction->cost_price && $transaction->cost_price != 0)
                                        Rp {{ number_format($transaction->cost_price, 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <!-- Kolom Receivables -->
                                <td style="text-align: right; font-weight: bold; color: #0056b3;">
                                    @if ($transaction->outstanding_amount > 0)
                                        Rp {{ number_format($transaction->outstanding_amount, 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <!-- Kolom Payables -->
                                <td style="text-align: right; font-weight: bold; color: #dc3545;">
                                    @if ($transaction->outstanding_amount < 0)
                                        Rp {{ number_format(abs($transaction->outstanding_amount), 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td style="text-align: center;">
                                    <span class="status-badge payment-{{ strtolower($transaction->payment_status) }}">
                                        {{ $transaction->payment_status }}
                                    </span>
                                </td>
                                <td>{{ $transaction->supplier_technician ?? '-' }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="16"
                                style="text-align: center; padding: 20px; color: #666; font-style: italic;">
                                No transactions found for the selected criteria.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer-section">
            @if (file_exists(public_path('default/images/img_logo.png')))
                <div style="text-align: center; margin-bottom: 5px;">
                    <img src="{{ URL::asset('default/images/img_logo.png') }}" alt="{{ $companyInfo['name'] }}"
                        style="height: 20px; width: auto;">
                </div>
            @endif
            <p><strong>{{ $companyInfo['name'] }}</strong></p>
            <p>{{ $companyInfo['address'] }} | {{ $companyInfo['city'] }} | {{ $companyInfo['phone'] }}</p>
            <p><em>Report generated automatically: {{ now()->format('d F Y, H:i') }} WIB</em></p>
        </div>
    </div>

    <script>
        async function downloadReportPDF() {
            try {
                showLoading();

                const element = document.getElementById('report-content');
                const filename =
                    `All_Transaction_Report_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`;

                // Hide action buttons temporarily
                const actionButtons = document.querySelector('.action-buttons');
                const originalDisplay = actionButtons.style.display;
                actionButtons.style.display = 'none';

                // Create canvas from HTML element with higher quality
                const canvas = await html2canvas(element, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: false,
                    backgroundColor: '#ffffff',
                    width: element.scrollWidth,
                    height: element.scrollHeight,
                    scrollX: 0,
                    scrollY: 0,
                    windowWidth: window.innerWidth,
                    windowHeight: window.innerHeight
                });

                // Restore action buttons
                actionButtons.style.display = originalDisplay;

                // Get image data from canvas
                const imgData = canvas.toDataURL('image/jpeg', 0.95);

                // Calculate dimensions for A4 landscape
                const {
                    jsPDF
                } = window.jspdf;
                const pdf = new jsPDF({
                    orientation: 'landscape',
                    unit: 'mm',
                    format: 'a4'
                });

                // A4 landscape dimensions in mm
                const pdfWidth = 297;
                const pdfHeight = 210;

                // Calculate scaling to fit content properly
                const canvasWidth = canvas.width;
                const canvasHeight = canvas.height;
                const ratio = canvasWidth / canvasHeight;

                let imgWidth = pdfWidth - 20;
                let imgHeight = imgWidth / ratio;

                // If height exceeds page height, scale down
                if (imgHeight > pdfHeight - 20) {
                    imgHeight = pdfHeight - 20;
                    imgWidth = imgHeight * ratio;
                }

                // Center the image on the page
                const x = (pdfWidth - imgWidth) / 2;
                const y = (pdfHeight - imgHeight) / 2;

                // Add image to PDF
                pdf.addImage(imgData, 'JPEG', x, y, imgWidth, imgHeight);

                // Set PDF properties
                pdf.setProperties({
                    title: filename,
                    subject: 'All Transaction Report',
                    author: '{{ $companyInfo['name'] }}',
                    creator: 'Report System'
                });

                // Save the PDF
                pdf.save(filename);

                hideLoading();
                showNotification('Report PDF berhasil diunduh!', 'success');

            } catch (error) {
                console.error('PDF generation failed:', error);
                hideLoading();
                showNotification('Error generating PDF: ' + error.message, 'error');
            }
        }

        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                padding: 15px 25px;
                border-radius: 8px;
                font-weight: 600;
                z-index: 10000;
                font-size: 14px;
                transition: all 0.3s ease;
                transform: translateX(400px);
                ${type === 'success' ?
                    'background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); color: #155724; border: 2px solid #28a745;' :
                    'background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); color: #721c24; border: 2px solid #dc3545;'}
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            `;

            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    ${type === 'success' ? '✅' : '❌'}
                    <span>${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);

            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 4000);
        }
    </script>
</body>

</html>
