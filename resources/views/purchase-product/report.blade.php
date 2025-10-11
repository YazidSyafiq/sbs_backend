<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Purchase Product Report - {{ $fromDate->format('d/m/Y') }} to {{ $untilDate->format('d/m/Y') }}</title>
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
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            border: 2px solid #2c3e50;
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
            width: 100px;
        }

        .info-value {
            color: #333;
            font-weight: 400;
        }

        .filter-active {
            background-color: #e8f4f8;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .filter-active h4 {
            margin: 0 0 5px 0;
            font-size: 10px;
            color: #2c3e50;
            font-weight: 600;
        }

        .filter-tag {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8px;
            margin: 2px;
            font-weight: 500;
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

        .report-table .col-no {
            width: 30px;
            text-align: center;
        }

        .report-table .col-po {
            width: 90px;
        }

        .report-table .col-date {
            width: 70px;
            text-align: center;
        }

        .report-table .col-name {
            width: 120px;
        }

        .report-table .col-branch {
            width: 60px;
            text-align: center;
        }

        .report-table .col-product {
            width: 140px;
        }

        .report-table .col-qty {
            width: 60px;
            text-align: center;
        }

        .report-table .col-unit-price {
            width: 80px;
            text-align: right;
        }

        .report-table .col-total-price {
            width: 80px;
            text-align: right;
        }

        .report-table .col-cost {
            width: 80px;
            text-align: right;
        }

        .report-table .col-profit {
            width: 80px;
            text-align: right;
        }

        .report-table .col-type {
            width: 60px;
            text-align: center;
        }

        .report-table .col-status-po {
            width: 80px;
            text-align: center;
        }

        .report-table .col-status-paid {
            width: 70px;
            text-align: center;
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

        .status-draft {
            background-color: #f8f9fa;
            color: #6c757d;
        }

        .status-requested {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-shipped {
            background-color: #e2e3ff;
            color: #4c4d7d;
        }

        .status-received {
            background-color: #d4f6d4;
            color: #1b5e20;
        }

        .status-done {
            background-color: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .payment-paid {
            background-color: #d4edda;
            color: #155724;
        }

        .payment-unpaid {
            background-color: #f8d7da;
            color: #721c24;
        }

        .type-credit {
            background-color: #fff3cd;
            color: #856404;
        }

        .type-cash {
            background-color: #d4edda;
            color: #155724;
        }

        .summary-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            border-left: 4px solid #3498db;
        }

        .summary-card.cost {
            border-left: 4px solid #e74c3c;
        }

        .summary-card.profit {
            border-left: 4px solid #27ae60;
        }

        .summary-card.orders {
            border-left: 4px solid #9b59b6;
        }

        .summary-card.items {
            border-left: 4px solid #f39c12;
        }

        .summary-card.average {
            border-left: 4px solid #34495e;
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
            color: #27ae60;
        }

        .summary-value.negative {
            color: #e74c3c;
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
            background: #2c3e50;
            color: white;
        }

        .btn-download:hover {
            background: #34495e;
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
            border-top: 3px solid #2c3e50;
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
            <h1>PURCHASE PRODUCT REPORT</h1>
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
                        <span class="info-label">Total Orders</span>
                        <span class="info-value">: {{ $totalOrders }} orders</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Items</span>
                        <span class="info-value">: {{ $totalItems }} items</span>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <span class="info-label">Total Revenue</span>
                        <span class="info-value">: Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Cost</span>
                        <span class="info-value">: Rp {{ number_format($totalCost, 0, ',', '.') }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Profit</span>
                        <span class="info-value">: Rp {{ number_format($totalProfit, 0, ',', '.') }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Profit Margin</span>
                        <span class="info-value">:
                            {{ $totalAmount > 0 ? number_format(($totalProfit / $totalAmount) * 100, 2) : 0 }}%</span>
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

        <!-- Report Table -->
        <div class="table-container">
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="col-no">NO</th>
                        <th class="col-po">PO NUMBER</th>
                        <th class="col-date">DATE</th>
                        <th class="col-name">PO NAME</th>
                        <th class="col-branch">BRANCH</th>
                        <th class="col-product">PRODUCT</th>
                        <th class="col-qty">QTY</th>
                        <th class="col-unit-price">UNIT PRICE</th>
                        <th class="col-total-price">TOTAL PRICE</th>
                        <th class="col-cost">COST</th>
                        <th class="col-profit">PROFIT</th>
                        <th class="col-type">TYPE</th>
                        <th class="col-status-po">PO STATUS</th>
                        <th class="col-status-paid">PAYMENT</th>
                    </tr>
                </thead>
                <tbody>
                    @php $rowNumber = 1; @endphp
                    @if (count($filteredData) > 0)
                        @foreach ($filteredData as $data)
                            @php
                                $purchase = $data->purchase;
                                $filteredItems = $data->filtered_items;
                                $itemsCount = $data->items_count;
                            @endphp

                            @for ($i = 0; $i < $itemsCount; $i++)
                                @php $item = $filteredItems[$i]; @endphp
                                <tr>
                                    @if ($i == 0)
                                        <!-- PO Level Information dengan rowspan -->
                                        <td rowspan="{{ $itemsCount }}"
                                            style="text-align: center; font-weight: bold; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            {{ $rowNumber++ }}</td>
                                        <td rowspan="{{ $itemsCount }}"
                                            style="font-weight: bold; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            {{ $purchase->po_number }}</td>
                                        <td rowspan="{{ $itemsCount }}"
                                            style="text-align: center; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            {{ $purchase->order_date->format('d/m/Y') }}</td>
                                        <td rowspan="{{ $itemsCount }}"
                                            style="vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            {{ $purchase->name }}</td>
                                        <td rowspan="{{ $itemsCount }}"
                                            style="text-align: center; font-weight: bold; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            {{ $purchase->user->branch->code ?? '-' }}</td>
                                    @endif

                                    <!-- Item Level Information -->
                                    <td style="border-right: 1px solid #dee2e6;">{{ $item->product->name ?? '-' }}
                                        @if ($item->product)
                                            <br><small>({{ $item->product->code }})</small>
                                        @endif
                                    </td>
                                    <td style="text-align: center; border-right: 1px solid #dee2e6;">
                                        {{ number_format($item->quantity) }}</td>
                                    <td style="text-align: right; border-right: 1px solid #dee2e6;">Rp
                                        {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                    <td style="text-align: right; font-weight: bold; border-right: 1px solid #dee2e6;">
                                        Rp {{ number_format($item->total_price, 0, ',', '.') }}</td>
                                    <td style="text-align: right; border-right: 1px solid #dee2e6;">Rp
                                        {{ number_format($item->cost_price * $item->quantity, 0, ',', '.') }}</td>
                                    <td
                                        style="text-align: right; font-weight: bold; color: {{ $item->profit_amount >= 0 ? '#27ae60' : '#e74c3c' }};">
                                        Rp {{ number_format($item->profit_amount, 0, ',', '.') }}
                                    </td>

                                    @if ($i == 0)
                                        <!-- PO Level Status dengan rowspan -->
                                        <td rowspan="{{ $itemsCount }}"
                                            style="text-align: center; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            <span
                                                class="status-badge type-{{ $purchase->type_po }}">{{ ucfirst($purchase->type_po) }}</span>
                                        </td>
                                        <td rowspan="{{ $itemsCount }}"
                                            style="text-align: center; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            <span
                                                class="status-badge status-{{ strtolower(str_replace(' ', '-', $purchase->status)) }}">{{ $purchase->status }}</span>
                                        </td>
                                        <td rowspan="{{ $itemsCount }}"
                                            style="text-align: center; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            <span
                                                class="status-badge payment-{{ $purchase->status_paid ?? 'unpaid' }}">{{ ucfirst($purchase->status_paid ?? 'unpaid') }}</span>
                                        </td>
                                    @endif
                                </tr>
                            @endfor
                        @endforeach
                    @else
                        <tr>
                            <td colspan="14"
                                style="text-align: center; padding: 20px; color: #666; font-style: italic;">
                                No data found for the selected criteria.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Summary Section -->
        <div class="summary-section">
            <h3>Report Summary</h3>
            <div class="summary-grid">
                <div class="summary-card revenue">
                    <div class="summary-label">Total Revenue</div>
                    <div class="summary-value positive">Rp {{ number_format($totalAmount, 0, ',', '.') }}</div>
                </div>
                <div class="summary-card cost">
                    <div class="summary-label">Total Cost</div>
                    <div class="summary-value negative">Rp {{ number_format($totalCost, 0, ',', '.') }}</div>
                </div>
                <div class="summary-card profit">
                    <div class="summary-label">Total Profit</div>
                    <div class="summary-value {{ $totalProfit >= 0 ? 'positive' : 'negative' }}">Rp
                        {{ number_format($totalProfit, 0, ',', '.') }}</div>
                </div>
                <div class="summary-card orders">
                    <div class="summary-label">Total Orders</div>
                    <div class="summary-value">{{ $totalOrders }} Orders</div>
                </div>
                <div class="summary-card items">
                    <div class="summary-label">Total Items</div>
                    <div class="summary-value">{{ $totalItems }} Items</div>
                </div>
                <div class="summary-card average">
                    <div class="summary-label">Average Order Value</div>
                    <div class="summary-value">Rp {{ number_format($averageOrderValue, 0, ',', '.') }}</div>
                </div>
            </div>
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
                    `Purchase_Product_Report_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`;

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
                    subject: 'Purchase Product Report',
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
