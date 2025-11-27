<?php
require_once '../config.php';

if (isset($_GET['error'])) {
    header('Location: /index.php?error=' . urlencode('YouTube authentication failed: ' . $_GET['error']));
    exit;
}

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);

    $postData = [
        'code' => $code,
        'client_id' => YOUTUBE_CLIENT_ID,
        'client_secret' => YOUTUBE_CLIENT_SECRET,
        'redirect_uri' => YOUTUBE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

    $headers = [
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
        $_SESSION['youtube_access_token'] = $data['access_token'];
        if (isset($data['refresh_token'])) {
            $_SESSION['youtube_refresh_token'] = $data['refresh_token'];
        }
        $_SESSION['youtube_token_expires'] = time() + $data['expires_in'];

        // Fetch user profile to display username
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $headers = ['Authorization: Bearer ' . $data['access_token']];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $userResult = curl_exec($ch);
        curl_close($ch);
        $userData = json_decode($userResult, true);
        if (isset($userData['name'])) {
            $_SESSION['youtube_user'] = $userData['name'];
        }
    }

    header('Location: /index.php');
    exit;
}
