<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Faktur - {{ $purchaseProduct->po_number }}</title>
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 8px;
            border-radius: 5px;
            margin-bottom: 8px;
            text-align: center;
            border: 2px solid #28a745;
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

        .parties-info {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 5px;
            margin-bottom: 8px;
        }

        .parties-info h3 {
            margin: 0 0 6px 0;
            font-size: 9px;
            color: #333;
            font-weight: 600;
        }

        .parties-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .party-section {
            padding: 8px;
            border-radius: 5px;
        }

        .company-section {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            border: 1px solid #28a745;
        }

        .customer-section {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
        }

        .party-title {
            font-size: 8px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .customer-section .party-title {
            color: #856404;
        }

        .party-details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
        }

        .party-details-table td {
            padding: 2px 4px;
            border-bottom: 1px solid #e9ecef;
        }

        .party-details-table .party-info-label {
            font-weight: 500;
            color: #666;
            width: 80px;
            white-space: nowrap;
        }

        .party-details-table .party-info-value {
            font-weight: 400;
            color: #333;
        }

        .faktur-details {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 5px;
            margin-bottom: 8px;
        }

        .faktur-details h3 {
            margin: 0 0 6px 0;
            font-size: 9px;
            color: #333;
            font-weight: 600;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .detail-item {
            font-size: 7px;
            margin-bottom: 4px;
        }

        .detail-label {
            font-weight: 500;
            color: #666;
            display: inline-block;
            width: 90px;
        }

        .detail-value {
            color: #333;
            font-weight: 400;
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

        .payment-status-paid {
            background-color: #d4edda;
            color: #155724;
            padding: 3px 8px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 6px;
            text-transform: uppercase;
            display: inline-block;
        }

        .payment-status-unpaid {
            background-color: #f8d7da;
            color: #721c24;
            padding: 3px 8px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 6px;
            text-transform: uppercase;
            display: inline-block;
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

        .items-table .col-no {
            width: 5%;
            text-align: center;
        }

        .items-table .col-product {
            width: 35%;
        }

        .items-table .col-code {
            width: 15%;
            text-align: center;
        }

        .items-table .col-qty {
            width: 10%;
            text-align: center;
        }

        .items-table .col-price {
            width: 17.5%;
            text-align: right;
        }

        .items-table .col-total {
            width: 17.5%;
            text-align: right;
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
            color: #28a745;
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

        .terbilang-section {
            background: linear-gradient(135deg, #d1f2eb 0%, #a7f3d0 100%);
            padding: 8px;
            border-radius: 5px;
            margin-top: 4px;
            border: 2px solid #10b981;
        }

        .terbilang-title {
            font-weight: bold;
            margin-bottom: 3px;
            font-size: 8px;
            color: #10b981;
        }

        .terbilang-text {
            font-size: 7px;
            color: #059669;
            font-style: italic;
            line-height: 1.2;
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
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 8px;
        }

        .signature-box {
            border-bottom: 1px solid #333;
            height: 40px;
            margin-bottom: 5px;
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

        /* Action Buttons - Updated to match invoice style */
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

    <!-- Action Buttons - Updated to match invoice style -->
    <div class="action-buttons">
        <button class="btn btn-download" onclick="downloadFakturPDF()">
            <span>📄</span> Download PDF
        </button>
    </div>

    <div class="container" id="faktur-content">
        <!-- Header -->
        <div class="header">
            <h1>FAKTUR PEMBELIAN</h1>
            <p><strong>{{ $purchaseProduct->po_number }}</strong></p>
        </div>

        <!-- Company and Customer Info -->
        <div class="parties-info">
            <h3>Informasi Pihak Terkait</h3>
            <div class="parties-grid">
                <div class="party-section company-section">
                    <div class="party-title">Dari (Penjual)</div>
                    <table class="party-details-table">
                        <tr>
                            <td class="party-info-label">Perusahaan</td>
                            <td class="party-info-value">: {{ $companyInfo['name'] }}</td>
                        </tr>
                        <tr>
                            <td class="party-info-label">Alamat</td>
                            <td class="party-info-value">: {{ $companyInfo['address'] }}</td>
                        </tr>
                        <tr>
                            <td class="party-info-label">Kota</td>
                            <td class="party-info-value">: {{ $companyInfo['city'] }}</td>
                        </tr>
                    </table>
                </div>

                <div class="party-section customer-section">
                    <div class="party-title">Kepada (Pembeli)</div>
                    <table class="party-details-table">
                        <tr>
                            <td class="party-info-label">Nama</td>
                            <td class="party-info-value">: {{ $purchaseProduct->user->name }}</td>
                        </tr>
                        @if ($purchaseProduct->user->branch)
                            <tr>
                                <td class="party-info-label">Cabang</td>
                                <td class="party-info-value">: {{ $purchaseProduct->user->branch->name }}</td>
                            </tr>
                            <tr>
                                <td class="party-info-label">Kode Cabang</td>
                                <td class="party-info-value">: {{ $purchaseProduct->user->branch->code }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <!-- Faktur Details -->
        <div class="faktur-details">
            <h3>Detail Faktur</h3>
            <div class="details-grid">
                <div>
                    <div class="detail-item">
                        <span class="detail-label">No. Faktur</span>
                        <span class="detail-value">: {{ $purchaseProduct->po_number }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Tanggal Faktur</span>
                        <span class="detail-value">: {{ $purchaseProduct->order_date->format('d F Y') }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Jenis PO</span>
                        <span class="detail-value">: {{ ucfirst($purchaseProduct->type_po) }} Purchase</span>
                    </div>
                </div>
                <div>
                    <div class="detail-item">
                        <span class="detail-label">Status Bayar</span>
                        <span class="detail-value">: <span class="payment-status-{{ $purchaseProduct->status_paid }}">
                                {{ $purchaseProduct->status_paid === 'paid' ? 'LUNAS' : 'BELUM LUNAS' }}
                            </span>
                        </span>
                    </div>
                    @if ($purchaseProduct->expected_delivery_date)
                        <div class="detail-item">
                            <span class="detail-label">Target Kirim</span>
                            <span class="detail-value">:
                                {{ $purchaseProduct->expected_delivery_date->format('d F Y') }}</span>
                        </div>
                    @endif
                    <div class="detail-item">
                        <span class="detail-label">Jumlah Item</span>
                        <span class="detail-value">: {{ $purchaseProduct->items->count() }} items</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-container">
            <h3 style="margin: 0 0 4px 0; font-size: 9px; color: #333; font-weight: 600;">Daftar Produk</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-product">Nama Produk</th>
                        <th class="col-code">Kode Produk</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-price">Harga Satuan</th>
                        <th class="col-total">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseProduct->items as $index => $item)
                        <tr>
                            <td style="text-align: center; font-weight: 600; color: #28a745;">{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $item->product->name }}</strong>
                                @if ($item->product->description)
                                    <div style="font-size: 6px; color: #666; margin-top: 2px;">
                                        {{ $item->product->description }}
                                    </div>
                                @endif
                            </td>
                            <td style="text-align: center;">{{ $item->product->code }}</td>
                            <td style="text-align: center; font-weight: 600;">{{ $item->quantity }} pcs</td>
                            <td style="text-align: right;">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                            <td style="text-align: right; font-weight: 600; color: #28a745;">Rp
                                {{ number_format($item->total_price, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="5" style="text-align: right;"><strong>TOTAL PEMBAYARAN :</strong></td>
                        <td class="grand-total" style="text-align: right;"><strong>Rp
                                {{ number_format($purchaseProduct->total_amount, 0, ',', '.') }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Layout 3 Kolom untuk Optimasi Space -->
        <table class="compact-row" style="width: 100%; margin-top: 8px;">
            <tr>
                <!-- Kolom 1: Summary -->
                <td class="compact-cell" style="width: 40%; padding-right: 6px;">
                    <div class="summary-section">
                        <div class="summary-title">Ringkasan Pembayaran</div>
                        <table style="width: 100%; font-size: 7px;">
                            <tr>
                                <td style="padding: 1px 0; color: #666;">Subtotal:</td>
                                <td style="text-align: right; font-weight: bold;">Rp
                                    {{ number_format($purchaseProduct->total_amount, 0, ',', '.') }}</td>
                            </tr>
                            <tr style="border-top: 1px solid #ddd;">
                                <td style="padding: 3px 0 1px 0; font-weight: bold; font-size: 8px;">TOTAL:</td>
                                <td style="text-align: right; font-weight: bold; font-size: 9px; color: #28a745;">Rp
                                    {{ number_format($purchaseProduct->total_amount, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Notes -->
                    @if ($purchaseProduct->notes)
                        <div class="notes-section" style="margin-top: 6px;">
                            <h4>Catatan:</h4>
                            <p>{{ $purchaseProduct->notes }}</p>
                        </div>
                    @endif
                </td>

                <!-- Kolom 2: Terbilang -->
                <td class="compact-cell" style="width: 35%; padding: 0 3px;">
                    <div class="terbilang-section">
                        <div class="terbilang-title">Terbilang</div>
                        <div class="terbilang-text">
                            {{ App\Helpers\NumberHelper::toCurrencyWords($purchaseProduct->total_amount) }}
                        </div>
                    </div>
                </td>

                <!-- Kolom 3: Terms -->
                <td class="compact-cell" style="width: 25%; padding-left: 6px;">
                    <div class="terms-section">
                        <div class="terms-title">Syarat & Ketentuan</div>
                        <div class="terms-text">
                            • Semua harga dalam IDR<br>
                            • Pembayaran sesuai kesepakatan<br>
                            • Barang milik penjual hingga lunas<br>
                            • Komplain maks 7 hari<br>
                            • Faktur sah sebagai bukti transaksi
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Enhanced Signature Section - 3 Kolom -->
        <div class="signature-section">
            <table class="signature-table">
                <tr>
                    <td class="signature-cell">
                        <div class="signature-label">HORMAT KAMI</div>
                        <div class="signature-box"></div>
                    </td>
                    <td class="signature-cell">
                        <div class="signature-label">DIKIRIM OLEH</div>
                        <div class="signature-box"></div>
        </div>
        </td>
        <td class="signature-cell">
            <div class="signature-label">DITERIMA OLEH</div>
            <div class="signature-box"></div>
        </td>
        </tr>
        </table>
    </div>

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
        function printFaktur() {
            window.print();
        }

        async function downloadFakturPDF() {
            try {
                showLoading();

                const element = document.getElementById('faktur-content');
                const filename =
                    `Faktur_{{ $purchaseProduct->po_number }}_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`;

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
                    subject: 'Purchase Order Faktur',
                    author: 'PT. Example Company',
                    creator: 'Faktur System'
                });

                // Save the PDF
                pdf.save(filename);

                hideLoading();
                showNotification('Faktur PDF berhasil diunduh!', 'success');

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
