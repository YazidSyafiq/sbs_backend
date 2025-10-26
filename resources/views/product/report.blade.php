<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Product Batch Report - {{ now()->format('d/m/Y') }}</title>
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
            max-width: 1500px;
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
            min-width: 1300px;
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
            width: 40px;
            text-align: center;
        }

        .report-table .col-code {
            width: 80px;
        }

        .report-table .col-name {
            width: 120px;
        }

        .report-table .col-category {
            width: 80px;
        }

        .report-table .col-sell-price {
            width: 70px;
            text-align: right;
        }

        .report-table .col-product-stock {
            width: 80px;
            text-align: right;
        }

        .report-table .col-product-status {
            width: 80px;
            text-align: center;
        }

        .report-table .col-batch {
            width: 100px;
        }

        .report-table .col-supplier {
            width: 80px;
        }

        .report-table .col-entry {
            width: 70px;
            text-align: center;
        }

        .report-table .col-expiry {
            width: 70px;
            text-align: center;
        }

        .report-table .col-batch-stock {
            width: 60px;
            text-align: right;
        }

        .report-table .col-cost {
            width: 70px;
            text-align: right;
        }

        .report-table .col-batch-stock-status {
            width: 70px;
            text-align: center;
        }

        .report-table .col-expiry-status {
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
            margin: 1px;
        }

        .product-status-in-stock {
            background-color: #d4edda;
            color: #155724;
        }

        .product-status-low-stock {
            background-color: #fff3cd;
            color: #856404;
        }

        .product-status-out-of-stock {
            background-color: #f8d7da;
            color: #721c24;
        }

        .batch-status-in-stock {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .batch-status-low-stock {
            background-color: #ffeaa7;
            color: #b29400;
        }

        .batch-status-out-of-stock {
            background-color: #f5c6cb;
            color: #721c24;
        }

        .expiry-fresh {
            background-color: #d4edda;
            color: #155724;
        }

        .expiry-expiring-soon {
            background-color: #fff3cd;
            color: #856404;
        }

        .expiry-expired {
            background-color: #f8d7da;
            color: #721c24;
        }

        .expiry-no-expiry {
            background-color: #e2e3ff;
            color: #4c4d7d;
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

        .summary-card.products {
            border-left: 4px solid #28a745;
        }

        .summary-card.batches {
            border-left: 4px solid #007bff;
        }

        .summary-card.stock {
            border-left: 4px solid #17a2b8;
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

        .batch-breakdown {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border: 1px solid #dee2e6;
        }

        .batch-breakdown h3 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }

        .breakdown-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }

        .breakdown-item {
            background-color: white;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            text-align: center;
        }

        .breakdown-item.in-stock {
            border-left: 4px solid #28a745;
        }

        .breakdown-item.low-stock {
            border-left: 4px solid #ffc107;
        }

        .breakdown-item.out-of-stock {
            border-left: 4px solid #dc3545;
        }

        .breakdown-item.expiring-soon {
            border-left: 4px solid #fd7e14;
        }

        .breakdown-item.expired {
            border-left: 4px solid #e74c3c;
        }

        .breakdown-item.no-expiry {
            border-left: 4px solid #6c757d;
        }

        .breakdown-label {
            font-size: 9px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .breakdown-value {
            font-size: 12px;
            font-weight: bold;
            color: #333;
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
            background: #20c997;
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

            .breakdown-grid {
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
            <h1>PRODUCT BATCH INVENTORY REPORT</h1>
            <p><strong>Generated on: {{ now()->format('d F Y, H:i') }} WIB</strong></p>
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
                        <span class="info-label">Total Products</span>
                        <span class="info-value">: {{ $totalProducts }} products</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Batches</span>
                        <span class="info-value">: {{ $totalBatches }} batches</span>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <span class="info-label">Total Stock Units</span>
                        <span class="info-value">: {{ number_format($totalStockUnits) }} units</span>
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

        <!-- Batch Status Breakdown -->
        <div class="batch-breakdown">
            <h3>Batch Status Breakdown</h3>
            <div class="breakdown-grid">
                <div class="breakdown-item in-stock">
                    <div class="breakdown-label">Batch In Stock</div>
                    <div class="breakdown-value">{{ $batchStatusBreakdown['in_stock'] }}</div>
                </div>
                <div class="breakdown-item low-stock">
                    <div class="breakdown-label">Batch Low Stock</div>
                    <div class="breakdown-value">{{ $batchStatusBreakdown['low_stock'] }}</div>
                </div>
                <div class="breakdown-item out-of-stock">
                    <div class="breakdown-label">Batch Out of Stock</div>
                    <div class="breakdown-value">{{ $batchStatusBreakdown['out_of_stock'] }}</div>
                </div>
                <div class="breakdown-item expiring-soon">
                    <div class="breakdown-label">Expiring Soon</div>
                    <div class="breakdown-value">{{ $batchStatusBreakdown['expiring_soon'] }}</div>
                </div>
                <div class="breakdown-item expired">
                    <div class="breakdown-label">Expired</div>
                    <div class="breakdown-value">{{ $batchStatusBreakdown['expired'] }}</div>
                </div>
                <div class="breakdown-item no-expiry">
                    <div class="breakdown-label">No Expiry</div>
                    <div class="breakdown-value">{{ $batchStatusBreakdown['no_expiry'] }}</div>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="table-container">
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="col-no">NO</th>
                        <th class="col-code">CODE</th>
                        <th class="col-name">PRODUCT</th>
                        <th class="col-category">CATEGORY</th>
                        <th class="col-sell-price">SELL PRICE</th>
                        <th class="col-product-stock">PRODUCT STOCK</th>
                        <th class="col-product-status">PRODUCT STATUS</th>
                        <th class="col-batch">BATCH NUMBER</th>
                        <th class="col-supplier">SUPPLIER</th>
                        <th class="col-entry">ENTRY DATE</th>
                        <th class="col-expiry">EXPIRY DATE</th>
                        <th class="col-batch-stock">BATCH STOCK</th>
                        <th class="col-cost">COST PRICE</th>
                        <th class="col-batch-stock-status">BATCH STATUS</th>
                        <th class="col-expiry-status">EXPIRY STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    @php $rowNumber = 1; @endphp
                    @if (count($reportData) > 0)
                        @foreach ($reportData as $data)
                            @php
                                $product = $data['product'];
                                $totalProductStock = $data['total_product_stock'];
                                $productStockStatus = $data['product_stock_status'];
                                $filteredBatches = $data['filtered_batches'];
                                $batchesCount = $data['batches_count'];

                                // Product status class
                                $productStatusClass = match ($productStockStatus) {
                                    'out_of_stock' => 'product-status-out-of-stock',
                                    'low_stock' => 'product-status-low-stock',
                                    default => 'product-status-in-stock',
                                };

                                // Product status label
                                $productStatusLabel = match ($productStockStatus) {
                                    'out_of_stock' => 'Out of Stock',
                                    'low_stock' => 'Low Stock',
                                    default => 'In Stock',
                                };
                            @endphp

                            @for ($i = 0; $i < $batchesCount; $i++)
                                @php
                                    $batch = $filteredBatches[$i];

                                    $batchStockStatus = $batch->stock_status;
                                    $expiryStatus = $batch->expiry_status;

                                    $batchStockStatusClass = match ($batchStockStatus) {
                                        'Out of Stock' => 'batch-status-out-of-stock',
                                        'Low Stock' => 'batch-status-low-stock',
                                        default => 'batch-status-in-stock',
                                    };

                                    $expiryStatusClass = match ($expiryStatus) {
                                        'Expired' => 'expiry-expired',
                                        'Expiring Soon' => 'expiry-expiring-soon',
                                        'Fresh' => 'expiry-fresh',
                                        default => 'expiry-no-expiry',
                                    };

                                    // Get supplier info menggunakan accessor
                                    $supplierName = $batch->supplier ? $batch->supplier->name : '-';
                                @endphp
                                <tr>
                                    @if ($i == 0)
                                        <!-- Product Level Information dengan rowspan -->
                                        <td rowspan="{{ $batchesCount }}"
                                            style="text-align: center; font-weight: bold; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            {{ $rowNumber++ }}</td>
                                        <td rowspan="{{ $batchesCount }}"
                                            style="font-weight: bold; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            {{ $product->code }}</td>
                                        <td rowspan="{{ $batchesCount }}"
                                            style="font-weight: bold; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            {{ $product->name }}</td>
                                        <td rowspan="{{ $batchesCount }}"
                                            style="vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            {{ $product->category->name ?? '-' }}</td>
                                        <td rowspan="{{ $batchesCount }}"
                                            style="text-align: right; font-weight: bold; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                                        <td rowspan="{{ $batchesCount }}"
                                            style="text-align: right; font-weight: bold; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            {{ $totalProductStock == floor($totalProductStock)
                                                ? number_format($totalProductStock, 0, ',', '.')
                                                : rtrim(rtrim(number_format($totalProductStock, 2, ',', '.'), '0'), ',') }}
                                            ({{ $product->unit ?? 'pcs' }})</td>
                                        <td rowspan="{{ $batchesCount }}"
                                            style="text-align: center; vertical-align: middle; border-right: 1px solid #dee2e6;">
                                            <span
                                                class="status-badge {{ $productStatusClass }}">{{ $productStatusLabel }}</span>
                                        </td>
                                    @endif

                                    <!-- Batch Level Information -->
                                    <td style="border-right: 1px solid #dee2e6; font-size: 6px;">
                                        {{ $batch->batch_number }}</td>
                                    <td style="border-right: 1px solid #dee2e6;">{{ $supplierName }}</td>
                                    <td style="text-align: center; border-right: 1px solid #dee2e6;">
                                        {{ $batch->entry_date->format('d/m/Y') }}</td>
                                    <td style="text-align: center; border-right: 1px solid #dee2e6;">
                                        @if ($batch->expiry_date)
                                            {{ $batch->expiry_date->format('d/m/Y') }}
                                        @else
                                            <span style="color: #666; font-style: italic;">No Expiry</span>
                                        @endif
                                    </td>
                                    <td style="text-align: right; font-weight: bold; border-right: 1px solid #dee2e6;">
                                        {{ $batch->quantity == floor($batch->quantity)
                                            ? number_format($batch->quantity, 0, ',', '.')
                                            : rtrim(rtrim(number_format($batch->quantity, 2, ',', '.'), '0'), ',') }}
                                        ({{ $product->unit ?? 'pcs' }})
                                    </td>
                                    <td style="text-align: right; border-right: 1px solid #dee2e6;">Rp
                                        {{ number_format($batch->cost_price, 0, ',', '.') }}</td>
                                    <td style="text-align: center; border-right: 1px solid #dee2e6;">
                                        <span
                                            class="status-badge {{ $batchStockStatusClass }}">{{ $batchStockStatus }}</span>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="status-badge {{ $expiryStatusClass }}">{{ $expiryStatus }}</span>
                                    </td>
                                </tr>
                            @endfor
                        @endforeach
                    @else
                        <tr>
                            <td colspan="15"
                                style="text-align: center; padding: 20px; color: #666; font-style: italic;">
                                No product batches found for the selected criteria.
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
                <div class="summary-card products">
                    <div class="summary-label">Total Products</div>
                    <div class="summary-value">{{ $totalProducts }}</div>
                </div>
                <div class="summary-card batches">
                    <div class="summary-label">Total Batches</div>
                    <div class="summary-value">{{ $totalBatches }}</div>
                </div>
                <div class="summary-card stock">
                    <div class="summary-label">Total Stock Units</div>
                    <div class="summary-value">{{ number_format($totalStockUnits) }}</div>
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
                    `Product_Batch_Report_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`;

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
                    subject: 'Product Batch Inventory Report',
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
