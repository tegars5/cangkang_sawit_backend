<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pembayaran Berhasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6f8;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        .card {
            background: #ffffff;
            padding: 40px;
            border-radius: 16px;
            text-align: center;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #22c55e;
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            margin: 0 auto 20px;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #111827;
        }

        p {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 25px;
        }

        .detail {
            background: #f9fafb;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: left;
            font-size: 14px;
        }

        .detail div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .detail div:last-child {
            margin-bottom: 0;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>

<div class="card">
    <div class="success-icon">✓</div>

    <h1>Pembayaran Berhasil</h1>
    <p>Terima kasih, pembayaran Anda telah kami terima.</p>

    <div class="detail">
        <div>
            <span>Kode Order</span>
            <strong>#ORDER12345</strong>
        </div>
        <div>
            <span>Total Pembayaran</span>
            <strong>Rp 150.000</strong>
        </div>
        <div>
            <span>Status</span>
            <strong style="color: #22c55e;">BERHASIL</strong>
        </div>
    </div>

    <a href="/" class="btn">Kembali ke Beranda</a>
</div>

</body>
</html>
