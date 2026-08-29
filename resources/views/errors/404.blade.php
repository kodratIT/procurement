<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Halaman Tidak Ditemukan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #0f172a; color: #e2e8f0; padding: 2rem; text-align: center;
        }
        .card { max-width: 480px; }
        .code { font-size: 6rem; font-weight: 800; color: #f59e0b; line-height: 1; }
        h1 { font-size: 1.5rem; margin: 1rem 0 .5rem; }
        p { color: #94a3b8; margin-bottom: 1.5rem; }
        a {
            display: inline-block; background: #f59e0b; color: #0f172a; font-weight: 600;
            padding: .65rem 1.5rem; border-radius: .5rem; text-decoration: none;
        }
        a:hover { background: #fbbf24; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">404</div>
        <h1>Halaman Tidak Ditemukan</h1>
        <p>Halaman yang Anda cari tidak ada atau telah dipindahkan.</p>
        <a href="{{ url('/') }}">Kembali ke Beranda</a>
    </div>
</body>
</html>
