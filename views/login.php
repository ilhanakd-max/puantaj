<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Playlist Converter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h2>Playlist Converter'a Hoş Geldiniz</h2>
                        <p>Lütfen devam etmek için giriş yapın.</p>
                    </div>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger m-3">
                            <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="/index.php?page=login&provider=spotify" class="btn btn-success">
                                Spotify ile Giriş Yap
                            </a>
                            <a href="/index.php?page=login&provider=youtube" class="btn btn-danger">
                                YouTube Music ile Giriş Yap
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
