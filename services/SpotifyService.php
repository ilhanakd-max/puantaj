<?php
class SpotifyService
{
    private $accessToken;
    private $apiBaseUrl = 'https://api.spotify.com/v1';

    public function __construct()
    {
        $this->accessToken = $_SESSION['spotify_access_token'] ?? null;
        $this->checkAndRefreshToken();
    }

    private function checkAndRefreshToken()
    {
        if (time() > $_SESSION['spotify_token_expires']) {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://accounts.spotify.com/api/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);

            $postData = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $_SESSION['spotify_refresh_token'],
            ];
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

            $headers = [
                'Authorization: Basic ' . base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET),
                'Content-Type: application/x-www-form-urlencoded',
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($result, true);

            if (isset($data['access_token'])) {
                $_SESSION['spotify_access_token'] = $data['access_token'];
                $_SESSION['spotify_token_expires'] = time() + $data['expires_in'];
                $this->accessToken = $data['access_token'];
            }
        }
    }

    private function makeRequest($url, $method = 'GET', $data = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiBaseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ];

        if ($method === 'POST' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return null; // Or handle error more gracefully
        }

        return json_decode($result, true);
    }

    public function getUserPlaylists()
    {
        return $this->makeRequest('/me/playlists');
    }

    public function getPlaylistTracks($playlistId)
    {
        $tracks = [];
        $url = "/playlists/{$playlistId}/tracks";

        do {
            $response = $this->makeRequest($url);
            if (isset($response['items'])) {
                $tracks = array_merge($tracks, $response['items']);
                $url = isset($response['next']) ? str_replace($this->apiBaseUrl, '', $response['next']) : null;
            } else {
                $url = null;
            }
        } while ($url);

        return $tracks;
    }

    public function searchTrack($title, $artist)
    {
        $query = http_build_query(['q' => "track:{$title} artist:{$artist}", 'type' => 'track', 'limit' => 5]);
        $response = $this->makeRequest("/search?{$query}");
        return $response['tracks']['items'] ?? [];
    }

    public function createPlaylist($name, $description)
    {
        $user = $this->makeRequest('/me');
        if (!isset($user['id'])) {
            return null;
        }

        $data = [
            'name' => $name,
            'description' => $description,
            'public' => false,
        ];
        return $this->makeRequest("/users/{$user['id']}/playlists", 'POST', $data);
    }

    public function addTracksToPlaylist($playlistId, $trackUris)
    {
        // Spotify API allows adding up to 100 tracks per request
        $chunks = array_chunk($trackUris, 100);
        foreach ($chunks as $chunk) {
            $data = ['uris' => $chunk];
            $this->makeRequest("/playlists/{$playlistId}/tracks", 'POST', $data);
        }
        return true;
    }
}
