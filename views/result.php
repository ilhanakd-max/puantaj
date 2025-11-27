<?php
if (!isset($result)) {
    // Redirect if no result data is available
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dönüşüm Sonucu - Playlist Converter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header text-center">
                <h2>Dönüşüm Sonucu</h2>
            </div>
            <div class="card-body">
                <?php if (isset($result['error'])): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($result['error']); ?>
                    </div>
                <?php else: ?>
                    <p><strong>Toplam Şarkı:</strong> <?php echo $result['total_tracks']; ?></p>
                    <p><strong>Eşleşen Şarkı:</strong> <?php echo $result['matched_tracks']; ?></p>
                    <p><strong>Eşleşmeyen Şarkı:</strong> <?php echo $result['unmatched_tracks']; ?></p>
                    <p><strong>İşlem Süresi:</strong> <?php echo round($result['processing_time'], 2); ?> saniye</p>

                    <?php if (!empty($result['unmatched_list'])) : ?>
                        <h5 class="mt-4">Eşleşmeyen Şarkılar</h5>
                        <ul class="list-group">
                            <?php foreach ($result['unmatched_list'] as $track) : ?>
                                <li class="list-group-item"><?php echo htmlspecialchars($track); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="mt-4 text-center">
                    <a href="/index.php" class="btn btn-primary">Yeni Bir Dönüşüm Yap</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
