<?php
require_once 'config.php';
require_once 'services/SpotifyService.php';
require_once 'services/YouTubeMusicService.php';

// Helper function to normalize track titles
function normalize_string($str) {
    // Remove content in parentheses (e.g., (Official Video), (Remastered))
    $str = preg_replace('/\s*\(.*?\)\s*/', '', $str);
    $str = preg_replace('/\s*\[.*?\]\s*/', '', $str);
    // Convert to lowercase
    $str = strtolower($str);
    // Remove non-alphanumeric characters
    $str = preg_replace('/[^a-z0-9\s]/', '', $str);
    return trim($str);
}

// Helper function to calculate string similarity
function get_similarity($str1, $str2) {
    similar_text($str1, $str2, $percent);
    return $percent;
}

// Router
$page = $_GET['page'] ?? 'home';

if ($page === 'home') {
    // Check if the user is authenticated with both services
    if (!isset($_SESSION['spotify_access_token']) || !isset($_SESSION['youtube_access_token'])) {
        include 'views/login.php';
        exit;
    }

    $spotifyService = new SpotifyService();
    $youTubeMusicService = new YouTubeMusicService();

    $spotifyPlaylists = $spotifyService->getUserPlaylists();
    $youtubePlaylists = $youTubeMusicService->getUserPlaylists();

    include 'views/playlist_select.php';
    exit;

} elseif ($page === 'convert' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $startTime = microtime(true);
    include 'views/processing.php';
    ob_implicit_flush(true);

    $source = $_POST['source'];
    $playlistId = $_POST['playlist_id'];
    $conversionType = $_POST['convert'];

    $spotifyService = new SpotifyService();
    $youTubeMusicService = new YouTubeMusicService();

    $result = [
        'total_tracks' => 0,
        'matched_tracks' => 0,
        'unmatched_tracks' => 0,
        'unmatched_list' => [],
    ];

    if ($source === 'spotify' && $conversionType === 'to_youtube') {
        $tracks = $spotifyService->getPlaylistTracks($playlistId);
        if ($tracks === null) {
            $_SESSION['conversion_result'] = ['error' => "Spotify'dan şarkılar alınamadı. Lütfen tekrar deneyin."];
            echo '<script>window.location.href="/index.php?page=result";</script>';
            exit;
        }
        $result['total_tracks'] = count($tracks);
        $playlistName = "Spotify'dan Dönüştürüldü";
        $newPlaylist = $youTubeMusicService->createPlaylist($playlistName, 'Playlist Converter ile dönüştürüldü');

        if ($newPlaylist) {
            $i = 0;
            foreach ($tracks as $trackItem) {
                $i++;
                $progress = ($i / $result['total_tracks']) * 100;
                echo "<script>
                    document.getElementById('progress-bar').style.width = '{$progress}%';
                    document.getElementById('progress-text').innerText = '{$i} / {$result['total_tracks']}';
                </script>";
                ob_flush();
                flush();

                $track = $trackItem['track'];
                $title = normalize_string($track['name']);
                $artist = normalize_string($track['artists'][0]['name']);

                $ytTracks = $youTubeMusicService->searchTrack($title, $artist);
                $bestMatch = null;
                $bestScore = 0;

                foreach ($ytTracks as $ytTrack) {
                    $ytTitle = normalize_string($ytTrack['snippet']['title']);
                    $score = get_similarity($title, $ytTitle);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestMatch = $ytTrack;
                    }
                }

                if ($bestMatch && $bestScore > 70) {
                    $youTubeMusicService->addTrackToPlaylist($newPlaylist['id'], $bestMatch['id']['videoId']);
                    $result['matched_tracks']++;
                } else {
                    $result['unmatched_tracks']++;
                    $result['unmatched_list'][] = "{$track['name']} by {$track['artists'][0]['name']}";
                }
            }
        }
    } elseif ($source === 'youtube' && $conversionType === 'to_spotify') {
        $tracks = $youTubeMusicService->getPlaylistTracks($playlistId);
        if ($tracks === null) {
            $_SESSION['conversion_result'] = ['error' => "YouTube'dan şarkılar alınamadı. Lütfen tekrar deneyin."];
            echo '<script>window.location.href="/index.php?page=result";</script>';
            exit;
        }
        $result['total_tracks'] = count($tracks);
        $playlistName = "YouTube'dan Dönüştürüldü";
        $newPlaylist = $spotifyService->createPlaylist($playlistName, 'Playlist Converter ile dönüştürüldü');

        if ($newPlaylist) {
            $spotifyTrackUris = [];
            $i = 0;
            foreach ($tracks as $trackItem) {
                $i++;
                $progress = ($i / $result['total_tracks']) * 100;
                echo "<script>
                    document.getElementById('progress-bar').style.width = '{$progress}%';
                    document.getElementById('progress-text').innerText = '{$i} / {$result['total_tracks']}';
                </script>";
                ob_flush();
                flush();

                $title = normalize_string($trackItem['snippet']['title']);
                $artist = '';
                if (isset($trackItem['snippet']['videoOwnerChannelTitle'])) {
                    $artist = normalize_string(str_replace(' - Topic', '', $trackItem['snippet']['videoOwnerChannelTitle']));
                }

                $spotifyTracks = $spotifyService->searchTrack($title, $artist);
                $bestMatch = null;
                $bestScore = 0;

                foreach ($spotifyTracks as $spotifyTrack) {
                    $spotifyTitle = normalize_string($spotifyTrack['name']);
                    $score = get_similarity($title, $spotifyTitle);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestMatch = $spotifyTrack;
                    }
                }

                if ($bestMatch && $bestScore > 70) {
                    $spotifyTrackUris[] = $bestMatch['uri'];
                    $result['matched_tracks']++;
                } else {
                    $result['unmatched_tracks']++;
                    $result['unmatched_list'][] = $trackItem['snippet']['title'];
                }
            }
            if (!empty($spotifyTrackUris)) {
                $spotifyService->addTracksToPlaylist($newPlaylist['id'], $spotifyTrackUris);
            }
        }
    }

    $_SESSION['conversion_result'] = $result;
    $_SESSION['conversion_result']['processing_time'] = microtime(true) - $startTime;
    echo '<script>window.location.href="/index.php?page=result";</script>';
    exit;

} elseif ($page === 'result') {
    $result = $_SESSION['conversion_result'] ?? null;
    unset($_SESSION['conversion_result']);
    include 'views/result.php';
    exit;

} elseif ($page === 'login' && isset($_GET['provider'])) {
    if ($_GET['provider'] === 'spotify') {
        $params = [
            'client_id' => SPOTIFY_CLIENT_ID,
            'response_type' => 'code',
            'redirect_uri' => SPOTIFY_REDIRECT_URI,
            'scope' => 'playlist-read-private playlist-modify-private playlist-modify-public',
        ];
        header('Location: https://accounts.spotify.com/authorize?' . http_build_query($params));
        exit;
    } elseif ($_GET['provider'] === 'youtube') {
        $params = [
            'client_id' => YOUTUBE_CLIENT_ID,
            'response_type' => 'code',
            'redirect_uri' => YOUTUBE_REDIRECT_URI,
            'scope' => 'https://www.googleapis.com/auth/youtube.readonly https://www.googleapis.com/auth/youtube',
            'access_type' => 'offline',
        ];
        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
        exit;
    }
}

// Fallback to home
header('Location: /index.php?page=home');
