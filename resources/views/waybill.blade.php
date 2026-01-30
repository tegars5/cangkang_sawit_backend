<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - {{ $waybill->waybill_number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header-table { width: 100%; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .logo { width: 70px; height: auto; }
        .company-info { text-align: right; }
        .company-name { font-size: 18px; font-weight: bold; margin: 0; }
        
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 2px; vertical-align: top; }
        
        .content-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .content-table th, .content-table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .content-table th { background-color: #f5f5f5; }
        
        .footer-table { width: 100%; margin-top: 40px; }
        .signature-box { text-align: center; width: 45%; }
        .space { height: 60px; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td>
                <img src="{{ public_path('images/logo.png') }}" alt="Logo" class="logo">
            </td>
            <td class="company-info">
                <p class="company-name">PT Fujiyama Biomass Energy</p>
                <p style="margin:0;">Jl. Lintas Sumatera, Jambi, Indonesia</p>
                <p style="margin:0;">Email: support@cangkangsawit.com</p>
            </td>
        </tr>
    </table>

    <h2 style="text-align: center; text-decoration: underline;">SURAT JALAN</h2>

    <table class="info-table">
        <tr>
            <td width="15%">No. Waybill</td>
            <td width="2%">:</td>
            <td width="33%"><strong>{{ $waybill->waybill_number }}</strong></td>
            <td width="15%">Tujuan</td>
            <td width="2%">:</td>
            <td width="33%">{{ $order->destination_address }}</td>
        </tr>
        <tr>
            <td>No. Order</td>
            <td>:</td>
            <td>{{ $order->order_code }}</td>
            <td>Driver</td>
            <td>:</td>
            <td>{{ $driver->name ?? 'Belum ada driver' }}</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>:</td>
            <td>{{ $waybill->created_at->format('d M Y') }}</td>
            <td>Mitra</td>
            <td>:</td>
            <td>{{ $user->name ?? '-' }}</td>
        </tr>
    </table>

    <table class="content-table">
        <thead>
            <tr>
                <th>Nama Produk</th>
                <th width="15%">Jumlah</th>
                <th>Harga Satuan</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>{{ $item->product->name ?? 'Produk' }}</td>
                <td>{{ $item->quantity }}</td>
                <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align: right; font-weight: bold;">TOTAL</td>
                <td style="font-weight: bold;">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="footer-table">
        <tr>
            <td class="signature-box">
                <p>Hormat Kami,</p>
                <div class="space"></div>
                <p><strong>( Admin Gudang )</strong></p>
            </td>
            <td width="10%"></td>
            <td class="signature-box">
                <p>Diterima Oleh,</p>
                <div class="space"></div>
                <p><strong>( {{ $user->name ?? '....................' }} )</strong></p>
            </td>
        </tr>
    </table>

</body>
</html>