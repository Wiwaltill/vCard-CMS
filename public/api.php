<?php
require_once __DIR__ . '/includes/functions.php';
require_installed();
$config = get_config();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($config['api_enabled'])) {
    http_response_code(403);
    echo json_encode(['error' => 'api disabled']);
    exit;
}
require_api_auth($config);
header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;
$contacts = load_json('contacts.json', []);

$find = function ($id) use (&$contacts) {
    foreach ($contacts as $i => $c) if (($c['id'] ?? '') === $id) return $i;
    return null;
};
$input = function () {
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1024 * 1024) {
        http_response_code(413);
        exit(json_encode(['error' => 'request too large']));
    }
    $raw = file_get_contents('php://input', false, null, 0, 1024 * 1024 + 1);
    if ($raw === false || strlen($raw) > 1024 * 1024) {
        http_response_code(413);
        exit(json_encode(['error' => 'request too large']));
    }
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || substr(ltrim($raw), 0, 1) !== '{') throw new InvalidArgumentException();
    } catch (Throwable $error) {
        http_response_code(400);
        exit(json_encode(['error' => 'expected JSON object']));
    }
    if (!valid_backup_contacts([array_replace($data, ['id' => $data['id'] ?? 'xx'])])) {
        http_response_code(422);
        exit(json_encode(['error' => 'invalid contact fields or id']));
    }
    unset($data['_deleted_at']);
    return $data;
};

if ($method === 'GET' && !$id) {
    echo json_encode($contacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($method === 'GET' && $id) {
    $i = $find($id);
    if ($i === null) {
        http_response_code(404);
        echo json_encode(['error' => 'not found']);
    } else echo json_encode($contacts[$i], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($method === 'POST') {
    $data = array_replace(['vorname' => '', 'nachname' => '', 'telefon' => '',
        'email' => '', 'email_override' => false, 'position' => '', 'bild' => '', 'fields' => []], $input());
    $data['id'] = $data['id'] ?? make_contact_id($data['vorname'] ?? '', $data['nachname'] ?? '', $contacts);
    if ($find($data['id']) !== null || in_array($data['id'], array_column(reserved_contact_records(), 'id'), true)) {
        http_response_code(409);
        exit(json_encode(['error' => 'id already exists']));
    }
    $contacts[] = $data;
    save_contacts($contacts);
    http_response_code(201);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($method === 'PUT' && $id) {
    $i = $find($id);
    if ($i === null) {
        http_response_code(404);
        echo json_encode(['error' => 'not found']);
        exit;
    }
    $data = array_replace_recursive($contacts[$i], $input());
    $data['id'] = $id;
    $contacts[$i] = $data;
    save_contacts($contacts);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($method === 'DELETE' && $id) {
    $i = $find($id);
    if ($i === null) {
        http_response_code(404);
        echo json_encode(['error' => 'not found']);
        exit;
    }
    trash_contact($id);
    echo json_encode(['deleted' => $id]);
    exit;
}
header('Allow: GET, POST, PUT, DELETE');
http_response_code(405);
echo json_encode(['error' => 'method not allowed']);
