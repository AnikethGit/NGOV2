<?php
/**
 * Gallery API — serves Google Drive images grouped by folder.
 *
 * Requires in .env:
 *   GOOGLE_DRIVE_API_KEY=AIza...
 *
 * Folder configuration lives in:
 *   data/gallery-folders.json
 *
 * Responses are cached for 1 hour in:
 *   data/gallery-cache.json  (auto-created, gitignored)
 *
 * Endpoints:
 *   GET api/gallery.php           → return gallery (uses cache if fresh)
 *   GET api/gallery.php?refresh=1 → bust cache and re-fetch from Drive
 */

require_once '../includes/config.php';

/* ── Only allow GET ──────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

/* ── Read config ─────────────────────────────────────────────────────────── */
global $env;
$apiKey     = trim($env['GOOGLE_DRIVE_API_KEY'] ?? '');
$configFile = __DIR__ . '/../data/gallery-folders.json';
$cacheFile  = __DIR__ . '/../data/gallery-cache.json';
$cacheTTL   = 3600; // seconds (1 hour)

/* ── Serve from cache if fresh ───────────────────────────────────────────── */
$forceRefresh = (($_GET['refresh'] ?? '') === '1');
if (
    !$forceRefresh
    && file_exists($cacheFile)
    && (time() - filemtime($cacheFile)) < $cacheTTL
) {
    echo file_get_contents($cacheFile);
    exit;
}

/* ── Validate setup ──────────────────────────────────────────────────────── */
if (empty($apiKey)) {
    echo json_encode([
        'success' => false,
        'error'   => 'Gallery not configured. Add GOOGLE_DRIVE_API_KEY to your .env file.',
    ]);
    exit;
}

if (!file_exists($configFile)) {
    $out = json_encode(['success' => true, 'folders' => []]);
    @file_put_contents($cacheFile, $out);
    echo $out;
    exit;
}

$folders = json_decode(file_get_contents($configFile), true) ?: [];

/* ── Debug mode: ?debug=1 shows raw Drive API responses ──────────────────── */
$debugMode = (($_GET['debug'] ?? '') === '1');

/* ── Fetch images from one Drive folder (handles pagination) ─────────────── */
function driveListImages(string $folderId, string $apiKey, bool $debug = false): array
{
    if (!function_exists('curl_init')) {
        return driveListImagesFallback($folderId, $apiKey);
    }

    $images    = [];
    $pageToken = null;
    $debugLog  = [];

    do {
        $params = [
            'q'        => "'{$folderId}' in parents and (mimeType contains 'image/' or mimeType contains 'video/') and trashed = false",
            'fields'   => 'nextPageToken,files(id,name,mimeType)',
            'pageSize' => 500,
            'orderBy'  => 'name',
            'key'      => $apiKey,
        ];
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($debug) {
            $debugLog[] = [
                'folder_id' => $folderId,
                'http_code' => $httpCode,
                'response'  => json_decode($response, true) ?? $response,
            ];
        }

        if ($httpCode !== 200 || !$response) break;

        $data = json_decode($response, true);
        if (empty($data['files'])) break;

        foreach ($data['files'] as $file) {
            $id      = $file['id'];
            $isVideo = strpos($file['mimeType'] ?? '', 'video/') === 0;
            $images[] = [
                'id'    => $id,
                'name'  => pathinfo($file['name'], PATHINFO_FILENAME),
                'type'  => $isVideo ? 'video' : 'image',
                // thumbnail works for both images and videos (first frame)
                'thumb' => "https://drive.google.com/thumbnail?id={$id}&sz=w600",
                // videos use the Drive preview embed URL; images use high-res thumbnail
                'full'  => $isVideo
                    ? "https://drive.google.com/file/d/{$id}/preview"
                    : "https://drive.google.com/thumbnail?id={$id}&sz=w1600",
            ];
        }

        $pageToken = $data['nextPageToken'] ?? null;

    } while ($pageToken);

    if ($debug) {
        return ['__debug__' => $debugLog, '__images__' => $images];
    }
    return $images;
}

/* ── file_get_contents fallback (for hosts without cURL) ────────────────── */
function driveListImagesFallback(string $folderId, string $apiKey): array
{
    $images = [];
    $params = [
        'q'        => "'{$folderId}' in parents and (mimeType contains 'image/' or mimeType contains 'video/') and trashed = false",
        'fields'   => 'files(id,name,mimeType)',
        'pageSize' => 500,
        'orderBy'  => 'name',
        'key'      => $apiKey,
    ];
    $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query($params);
    $ctx = stream_context_create(['http' => ['timeout' => 15]]);
    $response = @file_get_contents($url, false, $ctx);
    if (!$response) return $images;

    $data = json_decode($response, true);
    foreach (($data['files'] ?? []) as $file) {
        $id      = $file['id'];
        $isVideo = strpos($file['mimeType'] ?? '', 'video/') === 0;
        $images[] = [
            'id'    => $id,
            'name'  => pathinfo($file['name'], PATHINFO_FILENAME),
            'type'  => $isVideo ? 'video' : 'image',
            'thumb' => "https://drive.google.com/thumbnail?id={$id}&sz=w600",
            'full'  => $isVideo
                ? "https://drive.google.com/file/d/{$id}/preview"
                : "https://drive.google.com/thumbnail?id={$id}&sz=w1600",
        ];
    }
    return $images;
}

/* ── Build result ────────────────────────────────────────────────────────── */
$result      = [];
$debugResult = [];

foreach ($folders as $folder) {
    $folderId = trim($folder['id'] ?? '');

    // Skip placeholder entries left in the config template
    if (empty($folderId) || $folderId === 'REPLACE_WITH_GOOGLE_DRIVE_FOLDER_ID') {
        continue;
    }

    $raw = driveListImages($folderId, $apiKey, $debugMode);

    // In debug mode the function returns an array with __debug__ and __images__ keys
    if ($debugMode && isset($raw['__debug__'])) {
        $debugResult[] = [
            'folder_id'   => $folderId,
            'folder_name' => $folder['name'] ?? '',
            'api_calls'   => $raw['__debug__'],
            'image_count' => count($raw['__images__']),
        ];
        $images = $raw['__images__'];
    } else {
        $images = $raw;
    }

    if (!empty($images)) {
        $result[] = [
            'id'     => $folderId,
            'name'   => $folder['name']  ?? 'Gallery',
            'icon'   => $folder['icon']  ?? 'images',
            'count'  => count($images),
            'images' => $images,
        ];
    }
}

// In debug mode: return diagnostic info without caching
if ($debugMode) {
    echo json_encode(
        ['debug' => true, 'api_key_set' => !empty($apiKey), 'folders_checked' => $debugResult, 'result' => $result],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
    exit;
}

$payload = json_encode(
    ['success' => true, 'folders' => $result],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

// Write cache (suppress errors — data/ dir may not be writable on some hosts)
@file_put_contents($cacheFile, $payload);

echo $payload;
