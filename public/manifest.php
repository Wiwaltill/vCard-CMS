<?php
require_once 'includes/functions.php';
$config = get_config();
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
 'name'=>$config['company_name'].' vCards', 'short_name'=>'vCards', 'start_url'=>'/', 'display'=>'standalone',
 'background_color'=>'#ffffff', 'theme_color'=>$config['company_color'] ?? '#0d6efd',
 'icons'=>[['src'=>'/assets/icons/icon.svg','sizes'=>'512x512','type'=>'image/svg+xml','purpose'=>'any maskable']]
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
