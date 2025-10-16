<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice Service - {{ $purchaseService->po_number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- jsPDF library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>


    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            line-height: 1.1;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }

        .container {
            background-color: white;
            border-radius: 5px;
            padding: 10px;
            margin: 5px auto;
            max-width: 800px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px;
            border-radius: 5px;
            margin-bottom: 8px;
            text-align: center;
            border: 2px solid #667eea;
        }

        .header h1 {
            margin: 0;
            font-size: 12px;
            line-height: 1.2;
            font-weight: bold;
            color: white;
        }

        .header p {
            margin: 3px 0 0 0;
            font-size: 8px;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.9);
        }

        .logo {
            height: 25px;
            width: auto;
            margin-bottom: 5px;
        }

        .alert-info {
            background-color: white;
            color: #333;
            padding: 6px;
            border-radius: 3px;
            margin-bottom: 8px;
            border-left: 3px solid #f0f0f0;
            font-size: 8px;
        }

        .alert-info p {
            margin: 2px 0;
        }

        .alert-info strong {
            font-weight: bold;
        }

        .po-info {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 5px;
            margin-bottom: 8px;
        }

        .po-info h3 {
            margin: 0 0 6px 0;
            font-size: 9px;
            color: #333;
            font-weight: 600;
        }

        .po-details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
        }

        .po-details-table td {
            padding: 2px 4px;
            border-bottom: 1px solid #e9ecef;
        }

        .po-details-table .po-info-label {
            font-weight: 500;
            color: #666;
            width: 30px;
            white-space: nowrap;
        }

        .po-details-table .po-info-value {
            font-weight: 400;
            color: #333;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 5px 3px 5px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 4px;
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
            border: 1px solid #ffeaa7;
        }

        .status-processing {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .status-shipped {
            background-color: #e2e3ff;
            color: #4c4d7d;
            border: 1px solid #c5c6f0;
        }

        .status-received {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-done {
            background-color: #d1f2eb;
            color: #00695c;
            border: 1px solid #b8e6d3;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .table-container {
            margin-top: 8px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
            background-color: white;
            border: 1px solid #e9ecef;
        }

        .items-table th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 600;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            padding: 4px 3px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .items-table td {
            padding: 4px 3px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
            word-wrap: break-word;
            vertical-align: top;
            font-weight: 400;
            color: #333;
        }

        .items-table .col-product {
            width: 30%;
        }

        .items-table .col-code {
            width: 15%;
        }

        .items-table .col-qty {
            width: 30%;
        }

        .items-table .col-price {
            width: 25%;
        }

        .total-row {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .total-row td {
            font-weight: bold;
            font-size: 8px;
        }

        .grand-total {
            font-size: 9px;
            color: #4ECB25;
            font-weight: bold;
        }

        .compact-section {
            margin-top: 8px;
        }

        .compact-row {
            width: 100%;
            border-collapse: collapse;
        }

        .compact-cell {
            vertical-align: top;
            padding: 4px;
        }

        .payment-info {
            background-color: #f8f9fa;
            padding: 6px;
            border-radius: 3px;
        }

        .payment-title {
            font-weight: bold;
            margin-bottom: 4px;
            color: #333;
            font-size: 8px;
        }

        .payment-status-paid {
            background-color: #d4edda;
            color: #155724;
            padding: 3px 3px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 6px;
            text-transform: uppercase;
            display: inline-block;
        }

        .payment-status-unpaid {
            background-color: #f8d7da;
            color: #721c24;
            padding: 4px 5px 4px 5px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 6px;
            text-transform: uppercase;
            display: inline-block;
        }

        .notes-section {
            background-color: #f8f9fa;
            padding: 6px;
            border-radius: 3px;
        }

        .notes-section h4 {
            margin: 0 0 3px 0;
            font-size: 8px;
            color: #333;
            font-weight: bold;
        }

        .notes-section p {
            margin: 0;
            font-size: 7px;
            color: #666;
        }

        .summary-section {
            background-color: #f8f9fa;
            padding: 6px;
            border-radius: 3px;
        }

        .summary-title {
            font-weight: bold;
            margin-bottom: 4px;
            color: #333;
            font-size: 8px;
        }

        .terms-section {
            padding: 6px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            border-radius: 3px;
        }

        .terms-title {
            font-weight: bold;
            margin-bottom: 3px;
            font-size: 7px;
            color: #333;
        }

        .terms-text {
            font-size: 5px;
            color: #666;
            line-height: 1.2;
        }

        .signature-section {
            margin-top: 10px;
            page-break-inside: avoid;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-cell {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }

        .signature-box {
            border-bottom: 1px solid #333;
            height: 25px;
            margin-bottom: 3px;
        }

        .signature-label {
            font-size: 6px;
            font-weight: bold;
            margin-bottom: 1px;
        }

        .signature-name {
            font-size: 5px;
            color: #666;
        }

        .footer-section {
            margin-top: 8px;
            padding: 6px;
            background-color: #2c2c2c;
            color: #ffffff;
            border-radius: 3px;
            font-size: 6px;
            text-align: center;
        }

        .footer-section p {
            margin: 1px 0;
            font-weight: 400;
            color: #ffffff;
        }

        .footer-section strong {
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
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
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-print {
            background: #6c757d;
            color: white;
        }

        .btn-print:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        .btn-download {
            background: #667eea;
            color: white;
        }

        .btn-download:hover {
            background: #5a67d8;
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
            border-top: 3px solid #667eea;
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

            /* Mobile responsiveness */
            @media (max-width: 768px) {
                .action-buttons {
                    padding: 0 10px;
                    margin: 15px 0;
                }

                .btn {
                    font-size: 14px;
                    padding: 10px 18px;
                }
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner"></div>
        Generating PDF...
    </div>

    @if (!$isFromMobile)
        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-download" onclick="downloadInvoicePDF()">
                <span>📄</span> Download PDF
            </button>
        </div>
    @endif

    <div class="container" id="invoice-content">
        <!-- Header dengan warna baru -->
        <div class="header">
            <h1>PURCHASE ORDER SERVICE INVOICE</h1>
            <p><strong>{{ $purchaseService->po_number }}</strong></p>
        </div>

        <!-- PO Info Kompak - 2 Kolom -->
        <div class="po-info">
            <h3>Detail Purchase Order</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <!-- Kolom Kiri -->
                    <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                        <table class="po-details-table">
                            <tr>
                                <td class="po-info-label">Nomor PO</td>
                                <td class="po-info-value">: {{ $purchaseService->po_number }}</td>
                            </tr>
                            <tr>
                                <td class="po-info-label">Nama PO</td>
                                <td class="po-info-value">: {{ $purchaseService->name }}</td>
                            </tr>
                            <tr>
                                <td class="po-info-label">Diminta Oleh</td>
                                <td class="po-info-value">: {{ $purchaseService->user->name }}</td>
                            </tr>
                        </table>
                    </td>
                    <!-- Kolom Kanan -->
                    <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                        <table class="po-details-table">
                            @if ($purchaseService->user->branch)
                                <tr>
                                    <td class="po-info-label">Cabang</td>
                                    <td class="po-info-value">: {{ $purchaseService->user->branch->name }}
                                        ({{ $purchaseService->user->branch->code }})</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="po-info-label">Tipe PO</td>
                                <td class="po-info-value">: {{ ucfirst($purchaseService->type_po) }}</td>
                            </tr>
                            <tr>
                                <td class="po-info-label">Tanggal PO</td>
                                <td class="po-info-value">: {{ $purchaseService->order_date->format('d M Y') }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Items Table Kompak -->
        <div class="table-container">
            <h3 style="margin: 0 0 4px 0; font-size: 9px; color: #333; font-weight: 600;">Item PO</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-product">Nama Produk</th>
                        <th class="col-code">Kode</th>
                        <th class="col-qty">Technician</th>
                        <th class="col-price">Harga</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseService->items as $item)
                        <tr>
                            <td><strong>{{ $item->service->name }}</strong></td>
                            <td>{{ $item->service->code }}</td>
                            <td>{{ $item->technician->name }} </td>
                            <td>Rp {{ number_format($item->selling_price, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTAL PO :</strong></td>
                        <td class="grand-total"><strong>Rp
                                {{ number_format($purchaseService->total_amount, 0, ',', '.') }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Layout 3 Kolom untuk Optimasi Space -->
        <table class="compact-row" style="width: 100%; margin-top: 8px;">
            <tr>
                <!-- Kolom 1: Payment & Notes -->
                <td class="compact-cell" style="width: 40%; padding-right: 6px;">
                    <!-- Payment Information Kompak -->
                    @if ($purchaseService->status_paid || $purchaseService->bukti_tf)
                        <div class="payment-info">
                            <div class="payment-title">Info Pembayaran</div>
                            @if ($purchaseService->status_paid)
                                <p style="margin: 2px 0; font-size: 7px;">Status:
                                    <span class="payment-status-{{ $purchaseService->status_paid }}">
                                        {{ $purchaseService->status_paid === 'paid' ? 'Lunas' : 'Belum Lunas' }}
                                    </span>
                                </p>
                            @endif
                            @if ($purchaseService->bukti_tf)
                                <p style="color: #4ECB25; font-weight: bold; margin: 2px 0; font-size: 6px;">✓ Bukti
                                    transfer diverifikasi</p>
                            @endif
                        </div>
                    @endif

                    <!-- Notes Kompak -->
                    @if ($purchaseService->notes)
                        <div class="notes-section" style="margin-top: 6px;">
                            <h4>Catatan:</h4>
                            <p>{{ $purchaseService->notes }}</p>
                        </div>
                    @endif
                </td>

                <!-- Kolom 2: Summary -->
                <td class="compact-cell" style="width: 30%; ">
                    <div class="summary-section">
                        <div class="summary-title">Ringkasan</div>
                        <table style="width: 100%; font-size: 7px;">
                            <tr>
                                <td style="padding: 1px 0; color: #666;">Subtotal:</td>
                                <td style="text-align: right; font-weight: bold;">Rp
                                    {{ number_format($purchaseService->total_amount, 0, ',', '.') }}</td>
                            </tr>
                            <tr style="border-top: 1px solid #ddd;">
                                <td style="padding: 3px 0 1px 0; font-weight: bold; font-size: 8px;">TOTAL:</td>
                                <td style="text-align: right; font-weight: bold; font-size: 9px; color: #4ECB25;">Rp
                                    {{ number_format($purchaseService->total_amount, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </div>
                </td>

                <!-- Kolom 3: Terms -->
                <td class="compact-cell" style="width: 30%; padding-left: 6px;">
                    <div class="terms-section">
                        <div class="terms-title">Syarat & Ketentuan</div>
                        <div class="terms-text">
                            • Semua harga dalam IDR<br>
                            • Pembayaran sesuai kesepakatan<br>
                            • Barang milik penjual hingga lunas<br>
                            • Komplain maks 7 hari<br>
                            • Invoice sah sebagai bukti transaksi
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Footer Kompak -->
        <div class="footer-section">
            @if (file_exists(public_path('default/images/img_logo.png')))
                <div style="text-align: center; margin-bottom: 3px;">
                    <img src="{{ URL::asset('default/images/img_logo.png') }}" alt="{{ $companyInfo['name'] }}"
                        class="logo">
                </div>
            @endif
            <p><strong>{{ $companyInfo['name'] }}</strong></p>
            <p>{{ $companyInfo['address'] }} | {{ $companyInfo['city'] }} | {{ $companyInfo['phone'] }}</p>
            <p><em>Dibuat otomatis: {{ now()->format('d F Y, H:i') }} WIB</em></p>
        </div>
    </div>

    <script>
        function printInvoice() {
            window.print();
        }

        async function downloadInvoicePDF() {
            try {
                showLoading();

                const element = document.getElementById('invoice-content');
                const filename =
                    `Invoice_{{ $purchaseService->po_number }}_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`;

                // Hide action buttons temporarily
                const actionButtons = document.querySelector('.action-buttons');
                const originalDisplay = actionButtons.style.display;
                actionButtons.style.display = 'none';

                // Create canvas from HTML element with higher quality
                const canvas = await html2canvas(element, {
                    scale: 2, // Higher resolution
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
                const pdfWidth = 297; // A4 landscape width
                const pdfHeight = 210; // A4 landscape height

                // Calculate scaling to fit content properly
                const canvasWidth = canvas.width;
                const canvasHeight = canvas.height;
                const ratio = canvasWidth / canvasHeight;

                let imgWidth = pdfWidth - 20; // Leave 10mm margin on each side
                let imgHeight = imgWidth / ratio;

                // If height exceeds page height, scale down
                if (imgHeight > pdfHeight - 20) {
                    imgHeight = pdfHeight - 20; // Leave 10mm margin top/bottom
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
                    subject: 'Purchase Order Invoice',
                    author: 'PT. Example Company',
                    creator: 'Invoice System'
                });

                // Save the PDF
                pdf.save(filename);

                hideLoading();
                showNotification('Invoice PDF berhasil diunduh!', 'success');

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
