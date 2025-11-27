<?php
if (!isset($spotifyPlaylists) || !isset($youtubePlaylists)) {
    // Redirect if playlist data is not available
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playlist Seç - Playlist Converter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header text-center">
                <h2>Playlist Dönüştürücü</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Spotify Olarak Giriş Yapıldı: <?php echo htmlspecialchars($_SESSION['spotify_user']); ?></h5>
                        <form action="/index.php?page=convert" method="post">
                            <div class="mb-3">
                                <label for="spotify_playlist" class="form-label">Spotify Playlist'i</label>
                                <select name="playlist_id" id="spotify_playlist" class="form-select">
                                    <?php if (isset($spotifyPlaylists['items']) && is_array($spotifyPlaylists['items'])): ?>
                                        <?php foreach ($spotifyPlaylists['items'] as $playlist) : ?>
                                            <option value="<?php echo $playlist['id']; ?>"><?php echo htmlspecialchars($playlist['name']); ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option disabled>Spotify playlist'leri yüklenemedi.</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <input type="hidden" name="source" value="spotify">
                            <button type="submit" name="convert" value="to_youtube" class="btn btn-primary">YouTube Music'e Dönüştür</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <h5>YouTube Music Olarak Giriş Yapıldı: <?php echo htmlspecialchars($_SESSION['youtube_user']); ?></h5>
                        <form action="/index.php?page=convert" method="post">
                            <div class="mb-3">
                                <label for="youtube_playlist" class="form-label">YouTube Music Playlist'i</label>
                                <select name="playlist_id" id="youtube_playlist" class="form-select">
                                    <?php if (isset($youtubePlaylists['items']) && is_array($youtubePlaylists['items'])): ?>
                                        <?php foreach ($youtubePlaylists['items'] as $playlist) : ?>
                                            <option value="<?php echo $playlist['id']; ?>"><?php echo htmlspecialchars($playlist['snippet']['title']); ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option disabled>YouTube Music playlist'leri yüklenemedi.</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <input type="hidden" name="source" value="youtube">
                            <button type="submit" name="convert" value="to_spotify" class="btn btn-primary">Spotify'a Dönüştür</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
