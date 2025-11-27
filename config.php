<?php
session_start();

// Application URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptName = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
define('BASE_URL', "{$protocol}://{$host}{$scriptName}");

// Spotify API Credentials
define('SPOTIFY_CLIENT_ID', 'YOUR_SPOTIFY_CLIENT_ID');
define('SPOTIFY_CLIENT_SECRET', 'YOUR_SPOTIFY_CLIENT_SECRET');
define('SPOTIFY_REDIRECT_URI', BASE_URL . '/oauth/spotify_callback.php');

// Google (YouTube) API Credentials
define('YOUTUBE_CLIENT_ID', 'YOUR_YOUTUBE_CLIENT_ID');
define('YOUTUBE_CLIENT_SECRET', 'YOUR_YOUTUBE_CLIENT_SECRET');
define('YOUTUBE_REDIRECT_URI', BASE_URL . '/oauth/youtube_callback.php');
?>