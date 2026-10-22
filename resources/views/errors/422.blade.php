<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tidak dapat diproses | P2H AKP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 560px">
    <div class="card shadow-sm"><div class="card-body p-4 text-center">
        <div class="display-6 mb-2">&#9888;</div>
        <h1 class="h5">Tidak dapat diproses</h1>
        <p class="text-muted">{{ $exception->getMessage() ?: 'Permintaan tidak dapat diproses.' }}</p>
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">Kembali</a>
        <a href="{{ url('/p2h') }}" class="btn btn-primary btn-sm">Ke dashboard</a>
    </div></div>
</div>
</body>
</html>
