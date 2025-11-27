<?php
require_once '../config.php';

if (isset($_GET['error'])) {
    header('Location: /index.php?error=' . urlencode('Spotify authentication failed: ' . $_GET['error']));
    exit;
}

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://accounts.spotify.com/api/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);

    $postData = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => SPOTIFY_REDIRECT_URI,
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

    $headers = [
        'Authorization: Basic ' . base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET),
        'Content-Type: application/x-www-form-urlencoded',
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        die('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);

    $data = json_decode($result, true);

    if (isset($data['access_token'])) {
        $_SESSION['spotify_access_token'] = $data['access_token'];
        $_SESSION['spotify_refresh_token'] = $data['refresh_token'];
        $_SESSION['spotify_token_expires'] = time() + $data['expires_in'];

        // Fetch user profile to display username
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.spotify.com/v1/me');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $headers = ['Authorization: Bearer ' . $data['access_token']];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $userResult = curl_exec($ch);
        curl_close($ch);
        $userData = json_decode($userResult, true);
        if (isset($userData['display_name'])) {
            $_SESSION['spotify_user'] = $userData['display_name'];
        }

    }

    header('Location: /index.php');
    exit;
}
