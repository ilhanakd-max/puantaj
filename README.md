# Spotify & YouTube Music Playlist Converter

This is a PHP-based web application to convert playlists between Spotify and YouTube Music.

## Features

- Convert Spotify playlists to YouTube Music.
- Convert YouTube Music playlists to Spotify.
- Secure OAuth 2.0 authentication for both services.
- Clean and responsive user interface.

## Requirements

- PHP 8.0 or higher
- cURL extension for PHP
- A web server (e.g., Apache, Nginx)

## Setup Instructions

### 1. Clone the Repository

```bash
git clone https://github.com/your-username/playlist-converter.git
cd playlist-converter
```

### 2. Configure API Credentials

You need to obtain API credentials from both Spotify and Google.

#### Spotify API Credentials

1. Go to the [Spotify Developer Dashboard](https://developer.spotify.com/dashboard/).
2. Click on "Create an App".
3. Fill in the app name and description.
4. Once the app is created, you will see your **Client ID** and **Client Secret**.
5. Click on "Edit Settings".
6. Add `http://localhost/oauth/spotify_callback.php` to the "Redirect URIs".
7. Copy the **Client ID** and **Client Secret**.

#### YouTube API Credentials

1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project.
3. Go to "APIs & Services" > "Credentials".
4. Click on "Create Credentials" > "OAuth client ID".
5. Select "Web application" as the application type.
6. Under "Authorized redirect URIs", add `http://localhost/oauth/youtube_callback.php`.
7. Copy the **Client ID** and **Client Secret**.
8. Go to "Library" and enable the "YouTube Data API v3".

### 3. Update `config.php`

Open the `config.php` file and replace the placeholder values with your actual API credentials.

```php
<?php
// Spotify API Credentials
define('SPOTIFY_CLIENT_ID', 'YOUR_SPOTIFY_CLIENT_ID');
define('SPOTIFY_CLIENT_SECRET', 'YOUR_SPOTIFY_CLIENT_SECRET');
define('SPOTIFY_REDIRECT_URI', 'http://localhost/oauth/spotify_callback.php');

// Google (YouTube) API Credentials
define('YOUTUBE_CLIENT_ID', 'YOUR_YOUTUBE_CLIENT_ID');
define('YOUTUBE_CLIENT_SECRET', 'YOUR_YOUTUBE_CLIENT_SECRET');
define('YOUTUBE_REDIRECT_URI', 'http://localhost/oauth/youtube_callback.php');
?>
```

## How to Run the Project

1. Start your web server.
2. Open your web browser and navigate to `http://localhost`.

## How to Test Playlist Conversion

1. Log in with both your Spotify and YouTube Music accounts.
2. Select a playlist from the dropdown menu.
3. Click on the desired conversion button.
4. The application will show a report with the conversion results.
