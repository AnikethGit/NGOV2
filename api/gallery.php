<?php
/**
 * Gallery API — serves Google Drive images & videos grouped by folder.
 *
 * Requires in .env:
 *   GOOGLE_DRIVE_API_KEY=AIza...
 *
 * Folder configuration: data/gallery-folders.json
 * Cache (auto-generated, gitignored): data/gallery-cache.json
 *
 * Endpoints:
 *   GET api/gallery.php           → return gallery (uses 1-hour cache)
 *   GET api/gallery.php?refresh=1 → bust cache and re-fetch from Drive
 *   GET api/gallery.php?debug=1   → show raw Drive API responses (no cache)
 */

require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

/* ── Config ──────────────────────────────────────────────────────────────── */
global $env;
$apiKey     = trim($env['GOOGLE_DRIVE_API_KEY'] ?? '');
$configFile = __DIR__ . '/../data/gallery-folders.json';
$cacheFile  = __DIR__ . '/../data/gallery-cache.json';
$cacheTTL   = 3600;
$debugMode  = (($_GET['debug']   ?? '') === '1');
$forceRefresh = (($_GET['refresh'] ?? '') === '1');

/* ── Serve cache if fresh ────────────────────────────────────────────────── */
if (!$forceRefresh && !$debugMode && file_exists($cacheFile)
        && (time() - filemtime($cacheFile)) < $cacheTTL) {
    echo file_get_contents($cacheFile);
    exit;
}

/* ── Validate setup ──────────────────────────────────────────────────────── */
if (empty($apiKey)) {
    $msg = json_encode(['success' => false,
        'error' => 'Gallery not configured. Add GOOGLE_DRIVE_API_KEY to your .env file.']);
    echo $msg;
    exit;
}

if (!file_exists($configFile)) {
    $out = json_encode(['success' => true, 'folders' => []]);
    @file_put_contents($cacheFile, $out);
    echo $out;
    exit;
}

$folders = json_decode(file_get_contents($configFile), true) ?: [];

/* ── Make a single Drive API call ────────────────────────────────────────── */
function driveApiCall(array $params): array
{
    if (function_exists('curl_init')) {
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query($params);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $url      = 'https://www.googleapis.com/drive/v3/files?' . http_build_query($params);
        $ctx      = stream_context_create(['http' => ['timeout' => 15]]);
        $response = @file_get_contents($url, false, $ctx);
        $httpCode = $response !== false ? 200 : 0;
    }

    return [
        'http_code' => $httpCode,
        'body'      => $response ? (json_decode($response, true) ?? []) : [],
        'raw'       => $response ?: '',
    ];
}

/* ── Fetch all media in a folder (images + videos, two separate queries) ─── */
function fetchFolderMedia(string $folderId, string $apiKey, bool $debug): array
{
    $results   = [];
    $debugLog  = [];

    $mimeTypes = [
        'image' => "mimeType contains 'image/'",
        'video' => "mimeType contains 'video/'",
    ];

    foreach ($mimeTypes as $type => $mimeFilter) {
        $pageToken = null;

        do {
            $params = [
                'q'        => "'{$folderId}' in parents and {$mimeFilter} and trashed = false",
                'fields'   => 'nextPageToken,files(id,name,mimeType)',
                'pageSize' => 500,
                'orderBy'  => 'name',
                'key'      => $apiKey,
            ];
            if ($pageToken) $params['pageToken'] = $pageToken;

            $call = driveApiCall($params);

            if ($debug) {
                $debugLog[] = [
                    'type'      => $type,
                    'http_code' => $call['http_code'],
                    'files_returned' => count($call['body']['files'] ?? []),
                    'error'     => $call['body']['error'] ?? null,
                ];
            }

            if ($call['http_code'] !== 200 || empty($call['body']['files'])) break;

            foreach ($call['body']['files'] as $file) {
                $id      = $file['id'];
                $isVideo = ($type === 'video');
                $results[] = [
                    'id'    => $id,
                    'name'  => pathinfo($file['name'], PATHINFO_FILENAME),
                    'type'  => $isVideo ? 'video' : 'image',
                    'thumb' => "https://drive.google.com/thumbnail?id={$id}&sz=w600",
                    'full'  => $isVideo
                        ? "https://drive.google.com/file/d/{$id}/preview"
                        : "https://drive.google.com/thumbnail?id={$id}&sz=w1600",
                ];
            }

            $pageToken = $call['body']['nextPageToken'] ?? null;

        } while ($pageToken);
    }

    // Sort: images first, then videos; each group alphabetically by name
    usort($results, function ($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'image' ? -1 : 1;
        }
        return strcmp($a['name'], $b['name']);
    });

    return $debug ? ['__media__' => $results, '__debug__' => $debugLog] : $results;
}

/* ── Build result ────────────────────────────────────────────────────────── */
$result      = [];
$debugResult = [];

foreach ($folders as $folder) {
    $folderId = trim($folder['id'] ?? '');
    if (empty($folderId) || $folderId === 'REPLACE_WITH_GOOGLE_DRIVE_FOLDER_ID') continue;

    $raw = fetchFolderMedia($folderId, $apiKey, $debugMode);

    if ($debugMode && isset($raw['__media__'])) {
        $media = $raw['__media__'];
        $debugResult[] = [
            'folder_id'   => $folderId,
            'folder_name' => $folder['name'] ?? '',
            'media_count' => count($media),
            'api_calls'   => $raw['__debug__'],
        ];
    } else {
        $media = $raw;
    }

    if (!empty($media)) {
        $result[] = [
            'id'     => $folderId,
            'name'   => $folder['name'] ?? 'Gallery',
            'icon'   => $folder['icon'] ?? 'images',
            'count'  => count($media),
            'images' => $media,
        ];
    }
}

/* ── Output ──────────────────────────────────────────────────────────────── */
if ($debugMode) {
    echo json_encode([
        'debug'           => true,
        'api_key_set'     => !empty($apiKey),
        'folders_in_config' => count($folders),
        'folders_with_media' => count($result),
        'detail'          => $debugResult,
        'result'          => $result,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$payload = json_encode(
    ['success' => true, 'folders' => $result],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

@file_put_contents($cacheFile, $payload);
echo $payload;
