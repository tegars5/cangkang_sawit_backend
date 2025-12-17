<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan - {{ $waybill->waybill_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            margin-bottom: 5px;
        }
        .info-label {
            width: 150px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            margin-top: 80px;
            border-top: 1px solid #000;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>SURAT JALAN</h1>
        <p>Cangkang Sawit Logistics</p>
    </div>

    <!-- Waybill Info -->
    <div class="info-section">
        <div class="info-row">
            <div class="info-label">No. Surat Jalan:</div>
            <div>{{ $waybill->waybill_number }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">No. Order:</div>
            <div>{{ $order->order_code }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Tanggal:</div>
            <div>{{ $waybill->created_at->format('d F Y') }}</div>
        </div>
    </div>

    <!-- Mitra Info -->
    <div class="info-section">
        <h3>Informasi Mitra</h3>
        <div class="info-row">
            <div class="info-label">Nama:</div>
            <div>{{ $mitra->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Email:</div>
            <div>{{ $mitra->email }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Alamat Tujuan:</div>
            <div>{{ $order->destination_address }}</div>
        </div>
    </div>

    <!-- Driver Info -->
    <div class="info-section">
        <h3>Informasi Driver</h3>
        <div class="info-row">
            <div class="info-label">Nama:</div>
            <div>{{ $driver->name ?? '-' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Email:</div>
            <div>{{ $driver->email ?? '-' }}</div>
        </div>
    </div>

    <!-- Items Table -->
    <h3>Daftar Barang</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Produk</th>
                <th>Jumlah</th>
                <th>Harga Satuan</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->product->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" style="text-align: right;">Total:</th>
                <th>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</th>
            </tr>
        </tfoot>
    </table>

    <!-- Notes -->
    @if($waybill->notes)
    <div class="info-section">
        <h3>Catatan</h3>
        <p>{{ $waybill->notes }}</p>
    </div>
    @endif

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <p>Pengirim,</p>
            <div class="signature-line">
                <p>{{ $driver->name ?? '_______________' }}</p>
            </div>
        </div>
        <div class="signature-box">
            <p>Penerima,</p>
            <div class="signature-line">
                <p>_______________</p>
            </div>
        </div>
    </div>
</body>
</html>
