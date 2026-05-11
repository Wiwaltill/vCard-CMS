<?php
require_once 'includes/functions.php';
require_installed();
$config = get_config();
if (empty($config['api_enabled'])) { http_response_code(403); echo json_encode(['error'=>'api disabled']); exit; }
require_api_auth($config);
header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;
$contacts = load_json('contacts.json', []);

$find = function($id) use (&$contacts) { foreach ($contacts as $i=>$c) if (($c['id']??'')===$id) return $i; return null; };
$input = function() { $raw = file_get_contents('php://input'); $d = json_decode($raw, true); return is_array($d) ? $d : $_POST; };

if ($method === 'GET' && !$id) { echo json_encode($contacts, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
if ($method === 'GET' && $id) { $i=$find($id); if ($i===null) { http_response_code(404); echo json_encode(['error'=>'not found']); } else echo json_encode($contacts[$i], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
if ($method === 'POST') { $data=$input(); $data['id']=$data['id'] ?? make_contact_id($data['vorname']??'', $data['nachname']??'', $contacts); $contacts[]=$data; save_contacts($contacts); http_response_code(201); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
if ($method === 'PUT' && $id) { $i=$find($id); if ($i===null) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; } $data=array_replace_recursive($contacts[$i], $input()); $data['id']=$id; $contacts[$i]=$data; save_contacts($contacts); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
if ($method === 'DELETE' && $id) { $i=$find($id); if ($i===null) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; } array_splice($contacts,$i,1); save_contacts($contacts); echo json_encode(['deleted'=>$id]); exit; }
http_response_code(405); echo json_encode(['error'=>'method not allowed']);
