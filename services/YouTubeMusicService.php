<?php
class YouTubeMusicService
{
    private $accessToken;
    private $apiBaseUrl = 'https://www.googleapis.com/youtube/v3';

    public function __construct()
    {
        $this->accessToken = $_SESSION['youtube_access_token'] ?? null;
        $this->checkAndRefreshToken();
    }

    private function checkAndRefreshToken()
    {
        if (time() > $_SESSION['youtube_token_expires']) {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);

            $postData = [
                'client_id' => YOUTUBE_CLIENT_ID,
                'client_secret' => YOUTUBE_CLIENT_SECRET,
                'refresh_token' => $_SESSION['youtube_refresh_token'],
                'grant_type' => 'refresh_token',
            ];
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

            $result = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($result, true);

            if (isset($data['access_token'])) {
                $_SESSION['youtube_access_token'] = $data['access_token'];
                $_SESSION['youtube_token_expires'] = time() + $data['expires_in'];
                $this->accessToken = $data['access_token'];
            }
        }
    }

    private function makeRequest($url, $method = 'GET', $data = [])
    {
        $ch = curl_init();

        $fullUrl = $this->apiBaseUrl . $url;

        if ($method === 'GET' && !empty($data)) {
            $fullUrl .= '?' . http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($method === 'POST' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return null;
        }

        return json_decode($result, true);
    }

    public function getUserPlaylists()
    {
        $params = ['mine' => 'true', 'part' => 'snippet,contentDetails'];
        return $this->makeRequest('/playlists', 'GET', $params);
    }

    public function getPlaylistTracks($playlistId)
    {
        $tracks = [];
        $pageToken = null;

        do {
            $params = [
                'playlistId' => $playlistId,
                'part' => 'snippet',
                'maxResults' => 50,
            ];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = $this->makeRequest('/playlistItems', 'GET', $params);

            if (isset($response['items'])) {
                $tracks = array_merge($tracks, $response['items']);
                $pageToken = $response['nextPageToken'] ?? null;
            } else {
                $pageToken = null;
            }
        } while ($pageToken);

        return $tracks;
    }

    public function searchTrack($title, $artist)
    {
        $params = [
            'q' => "{$title} {$artist}",
            'type' => 'video',
            'videoCategoryId' => '10', // Music category
            'part' => 'snippet',
            'maxResults' => 5,
        ];
        $response = $this->makeRequest('/search', 'GET', $params);
        return $response['items'] ?? [];
    }

    public function createPlaylist($name, $description)
    {
        $data = [
            'snippet' => [
                'title' => $name,
                'description' => $description,
            ],
            'status' => [
                'privacyStatus' => 'private',
            ],
        ];
        return $this->makeRequest('/playlists', 'POST', $data);
    }

    public function addTrackToPlaylist($playlistId, $videoId)
    {
        $data = [
            'snippet' => [
                'playlistId' => $playlistId,
                'resourceId' => [
                    'kind' => 'youtube#video',
                    'videoId' => $videoId,
                ],
            ],
        ];
        return $this->makeRequest('/playlistItems', 'POST', $data);
    }
}
