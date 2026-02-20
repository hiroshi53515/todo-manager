<?php
/**
 * タスク・進捗管理 API
 *
 * GET  api.php?key=YOUR_KEY  → データ取得
 * POST api.php?key=YOUR_KEY  → データ保存
 */

// ===== 設定 =====
// APIキー（この値を変更してください。知っている人だけがアクセスできます）
define('API_KEY', 'matsuda2002todo');

// データファイルのパス
define('DATA_FILE', __DIR__ . '/data.json');

// ===== CORS設定（異なるドメインからのアクセスを許可） =====
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONSリクエスト（プリフライト）への対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ===== APIキー認証 =====
$key = isset($_GET['key']) ? $_GET['key'] : '';
if ($key !== API_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid API key'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== データファイルの初期化 =====
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, '[]');
}

// ===== リクエスト処理 =====
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // データ取得
    $data = file_get_contents(DATA_FILE);
    if ($data === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read data'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo $data;

} elseif ($method === 'POST') {
    // データ保存
    $input = file_get_contents('php://input');

    // JSONとして有効か検証
    $decoded = json_decode($input);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ファイルロック付きで書き込み（同時書き込み防止）
    $fp = fopen(DATA_FILE, 'w');
    if ($fp === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to open data file'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (flock($fp, LOCK_EX)) {
        // 整形して保存（デバッグしやすいように）
        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        fwrite($fp, $pretty);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        echo json_encode(['success' => true, 'count' => count((array)$decoded)], JSON_UNESCAPED_UNICODE);
    } else {
        fclose($fp);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to lock data file'], JSON_UNESCAPED_UNICODE);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
}
