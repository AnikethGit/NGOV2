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

/* ── Fetch images from one Drive folder (handles pagination) ─────────────── */
function driveListImages(string $folderId, string $apiKey): array
{
    if (!function_exists('curl_init')) {
        return driveListImagesFallback($folderId, $apiKey);
    }

    $images    = [];
    $pageToken = null;

    do {
        $params = [
            'q'        => "'{$folderId}' in parents and mimeType contains 'image/' and trashed = false",
            'fields'   => 'nextPageToken,files(id,name)',
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

        if ($httpCode !== 200 || !$response) break;

        $data = json_decode($response, true);
        if (empty($data['files'])) break;

        foreach ($data['files'] as $file) {
            $id       = $file['id'];
            $images[] = [
                'id'    => $id,
                'name'  => pathinfo($file['name'], PATHINFO_FILENAME),
                // thumbnail for the grid (600 px wide)
                'thumb' => "https://drive.google.com/thumbnail?id={$id}&sz=w600",
                // large version for the lightbox (1600 px wide)
                'full'  => "https://drive.google.com/thumbnail?id={$id}&sz=w1600",
            ];
        }

        $pageToken = $data['nextPageToken'] ?? null;

    } while ($pageToken);

    return $images;
}

/* ── file_get_contents fallback (for hosts without cURL) ────────────────── */
function driveListImagesFallback(string $folderId, string $apiKey): array
{
    $images = [];
    $params = [
        'q'        => "'{$folderId}' in parents and mimeType contains 'image/' and trashed = false",
        'fields'   => 'files(id,name)',
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
        $id       = $file['id'];
        $images[] = [
            'id'    => $id,
            'name'  => pathinfo($file['name'], PATHINFO_FILENAME),
            'thumb' => "https://drive.google.com/thumbnail?id={$id}&sz=w600",
            'full'  => "https://drive.google.com/thumbnail?id={$id}&sz=w1600",
        ];
    }
    return $images;
}

/* ── Build result ────────────────────────────────────────────────────────── */
$result = [];

foreach ($folders as $folder) {
    $folderId = trim($folder['id'] ?? '');

    // Skip placeholder entries left in the config template
    if (empty($folderId) || $folderId === 'REPLACE_WITH_GOOGLE_DRIVE_FOLDER_ID') {
        continue;
    }

    $images = driveListImages($folderId, $apiKey);

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

$payload = json_encode(
    ['success' => true, 'folders' => $result],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

// Write cache (suppress errors — data/ dir may not be writable on some hosts)
@file_put_contents($cacheFile, $payload);

echo $payload;
