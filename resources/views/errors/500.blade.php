<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Terjadi Kesalahan</title></head>
<body><main><h1>{{ $exception?->getStatusCode() ?? 'Error' }}</h1><h2>Terjadi Kesalahan</h2><p>Maaf, terjadi kesalahan pada server. Silakan coba lagi.</p><a href="{{ url('/') }}">Kembali ke Beranda</a></main></body></html>