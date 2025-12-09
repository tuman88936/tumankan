<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

$self_script_name = basename(__FILE__);
$default_scan_dir = realpath($_SERVER['DOCUMENT_ROOT']);

$scan_results = [];
$message = '';
$current_scan_path = $default_scan_dir;
$view_file_path = null;
$view_file_content = null;

define('JOOMLA_ADMIN_HEADER_REGEX', '/^\s*<\?php\s*\/\*\*\s*(?:\r\n|\n|\r)\s*\*\s*@package\s+Joomla\.Administrator/is');

function is_suspicious_content($content) {
    $content_no_comments = preg_replace('/^\s*\/\*\*.*?\*\/\s*/s', '', $content, 1);
    $patterns = [
        'eval\s*\(base64_decode\s*\(', 'eval\s*\(\s*gzuncompress\s*\(', 'eval\s*\(\s*gzinflate\s*\(',
        'passthru\s*\(', 'shell_exec\s*\(', 'system\s*\(', 'proc_open\s*\(', 'popen\s*\(', 'assert\s*\(',
        'create_function\s*\(', 'preg_replace\s*\(\s*[\'"].*\/e[\'"]', 'P\.A\.S\.', 'Shell', 'Uploader',
        'Backdoor', 'c99', 'c99shell', 'r57', 'r57shell', 'IndoXploit', 'WSO Shell', 'wso.php',
        'b374k', 'Leafmailer', 'Webadmin', '\$\GLOBALS\[\'[a-zA-Z0-9_]+\'\]\s*\(', '\$[O0Il]{4,}\(',
        'gzuncompress\s*\(base64_decode\s*\(', 'str_rot13\s*\(base64_decode\s*\(', 'move_uploaded_file\s*\(',
        'fwrite\s*\(fopen\s*\(', 'uname -a', 'Webshell', 'pwd', 'raw.githubusercontent.com', '$a = geturlsinfo', '蚁剑', 'eval(gzinflate(base64_decode(',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match('/' . $pattern . '/i', $content_no_comments)) return true;
    }
    return false;
}

function is_suspicious_filename($filename) {
    $patterns = [
        '\.shell\.php$', 'shell[0-9_-]*', 'c99[a-z0-9_-]*', 'r57[a-z0-9_-]*', 'wso[a-z0-9_-]*',
        'b374k[a-z0-9_-]*', 'adminer[0-9_-]*', 'up', 'upload[a-z0-9_-]*', 'xleet', 'alfa[a-z0-9_-]*',
    ];
    $filename_without_ext = pathinfo($filename, PATHINFO_FILENAME);
    foreach ($patterns as $pattern) {
        if (preg_match('/' . $pattern . '/i', $filename_without_ext)) return true;
        if (preg_match('/' . $pattern . '\.php$/i', $filename)) return true;
    }
    return false;
}

function has_suspicious_params($content, &$details = []) {
    $patterns = [
        '$_GET\s*\[["\']?(?:cmd|exec|shell|pass|upload)["\']?\]' => '$_GET backdoor param',
        '$_POST\s*\[["\']?(?:cmd|exec|shell|pass|upload)["\']?\]' => '$_POST backdoor param',
        '$_REQUEST\s*\[["\']?(?:cmd|exec|shell|pass|upload)["\']?\]' => '$_REQUEST backdoor param',
        'preg_replace\s*\(.*\/e.*\)' => 'preg_replace /e exploit',
        'base64_decode\s*\(\s*\$_(GET|POST|REQUEST)' => 'Encoded payload from user input',
    ];
    $found = false;
    foreach ($patterns as $pattern => $desc) {
        if (preg_match('/' . $pattern . '/i', $content)) {
            $details[] = $desc;
            $found = true;
        }
    }
    return $found;
}

function scan_directory($dir, &$results, $self_script_name) {
    $dir = realpath($dir);
    if (!$dir || !is_dir($dir) || !is_readable($dir)) return;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($iterator as $item) {
            $path = $item->getRealPath();
            $filename = $item->getFilename();
            if ($filename === $self_script_name && dirname($path) === dirname(realpath(__FILE__))) continue;

            if ($item->isFile() && $item->isReadable() && strtolower($item->getExtension()) === 'php') {
                $file_size = $item->getSize();
                $initial_chunk = '';
                $date_modified = @filemtime($path);

                if ($file_size > 0 && $file_size < 5000000) {
                    $fp = @fopen($path, 'r');
                    if ($fp) {
                        $initial_chunk = @fread($fp, 256);
                        @fclose($fp);
                    }
                }

                if ($initial_chunk && preg_match(JOOMLA_ADMIN_HEADER_REGEX, $initial_chunk)) {
                    continue;
                }

                $suspicious_by_name = is_suspicious_filename($filename);
                $suspicious_by_content = false;
                $reason_details = [];

                if ($file_size > 0 && $file_size < 2000000) {
                    $full_content = @file_get_contents($path);
                    if ($full_content !== false) {
                        $content_details = [];
                        if (is_suspicious_content($full_content, $content_details)) {
                            $suspicious_by_content = true;
                            $reason_details[] = "Pola kode berbahaya: " . implode(', ', $content_details);
                        }
                        if (has_suspicious_params($full_content, $content_details)) {
                            $suspicious_by_content = true;
                            $reason_details[] = "Parameter shell terdeteksi: " . implode(', ', $content_details);
                        }
                    }
                } elseif ($file_size >= 2000000 && $file_size < 5000000) {
                    $reason_details[] = "File size potentially large for full content scan";
                } elseif ($file_size >= 5000000) {
                    $reason_details[] = "File size too large for any content scan";
                }

                if ($suspicious_by_name || $suspicious_by_content) {
                    $reasons = [];
                    if ($suspicious_by_name) $reasons[] = "Filename";
                    if ($suspicious_by_content) $reasons[] = "Content";
                    if (!empty($reason_details)) $reasons[] = implode('; ', $reason_details);

                    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
                    $base_url .= "://".$_SERVER['HTTP_HOST'];
                    $relative_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);
                    $file_url = $base_url . $relative_path;

                    $results[] = [
                        'path' => $path,
                        'reason' => implode(', ', $reasons),
                        'modified_timestamp' => $date_modified,
                        'url' => $file_url
                    ];
                }
            }
        }
    } catch (Exception $e) { }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['file_path'])) {
    $file_to_view = realpath($_GET['file_path']);
    if (isset($_GET['scan_path_display'])) {
        $current_scan_path = realpath(trim($_GET['scan_path_display']));
        if (!$current_scan_path || !is_dir($current_scan_path)) {
            $current_scan_path = $default_scan_dir;
        }
    }

    $safe_scan_root = realpath($_SERVER['DOCUMENT_ROOT']);
    $is_within_safe_scan_root = ($current_scan_path && strpos($current_scan_path, $safe_scan_root) === 0);
    $is_file_within_current_scan = ($file_to_view && $current_scan_path && strpos($file_to_view, $current_scan_path) === 0);

    if ($file_to_view && file_exists($file_to_view) && is_readable($file_to_view) && strtolower(pathinfo($file_to_view, PATHINFO_EXTENSION)) === 'php' && $is_within_safe_scan_root && $is_file_within_current_scan) {
        $view_file_path = $file_to_view;
        $view_file_content = @file_get_contents($file_to_view);
        if ($view_file_content === false) {
            $message .= '<p class="message error">Error: Tidak dapat membaca konten file ' . htmlspecialchars($view_file_path) . '</p>';
            $view_file_path = null;
        }
    } else {
        $message .= '<p class="message error">Error: Path file PHP untuk dilihat tidak valid, tidak ada, tidak dapat dibaca, atau di luar direktori yang diizinkan.</p>';
    }
    if ($current_scan_path && is_dir($current_scan_path) && is_readable($current_scan_path)) {
        if (strpos($current_scan_path, $safe_scan_root) === 0 || $current_scan_path === $safe_scan_root) {
            scan_directory($current_scan_path, $scan_results, $self_script_name);
            if (!empty($scan_results)) {
                usort($scan_results, function($a, $b) {
                    return $b['modified_timestamp'] <=> $a['modified_timestamp'];
                });
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_scan_path = isset($_POST['scan_path']) && !empty(trim($_POST['scan_path'])) ? realpath(trim($_POST['scan_path'])) : $default_scan_dir;
    if (!$current_scan_path || !is_dir($current_scan_path)) {
        $message .= '<p class="message error">Error: Path pemindaian awal tidak valid.</p>';
        $current_scan_path = $default_scan_dir;
    }

    if (isset($_POST['action'])) {
        $safe_scan_root = realpath($_SERVER['DOCUMENT_ROOT']);
        $is_scan_path_safe = ($current_scan_path && (strpos($current_scan_path, $safe_scan_root) === 0 || $current_scan_path === $safe_scan_root));

        if (!$is_scan_path_safe) {
            $message .= '<p class="message error">Error: Path pemindaian berada di luar direktori web yang diizinkan.</p>';
        } else {
            if ($_POST['action'] === 'scan' || $_POST['action'] === 'delete') {
                if (is_readable($current_scan_path)) {
                    scan_directory($current_scan_path, $scan_results, $self_script_name);
                } else {
                    $message .= '<p class="message error">Error: Path pemindaian tidak dapat dibaca.</p>';
                }
            }

            if ($_POST['action'] === 'delete' && isset($_POST['file_path'])) {
                $file_to_delete = realpath($_POST['file_path']);
                $is_file_within_current_scan = ($file_to_delete && strpos($file_to_delete, $current_scan_path) === 0);

                if ($file_to_delete && file_exists($file_to_delete) && strtolower(pathinfo($file_to_delete, PATHINFO_EXTENSION)) === 'php' && $is_file_within_current_scan) {
                    if (unlink($file_to_delete)) {
                        $message .= '<p class="message success">File PHP ' . htmlspecialchars($file_to_delete) . ' berhasil dihapus.</p>';
                        $scan_results = [];
                        if (is_readable($current_scan_path)) {
                            scan_directory($current_scan_path, $scan_results, $self_script_name);
                        }
                    } else {
                        $message .= '<p class="message error">Error: Tidak dapat menghapus file PHP ' . htmlspecialchars($file_to_delete) . '. Periksa izin.</p>';
                    }
                } else {
                    $message .= '<p class="message error">Error: Path file PHP untuk penghapusan tidak valid, tidak ada, bukan PHP, atau di luar direktori yang dipindai.</p>';
                }
            }

            if (!empty($scan_results)) {
                usort($scan_results, function($a, $b) {
                    return $b['modified_timestamp'] <=> $a['modified_timestamp'];
                });
            }

            if ($_POST['action'] === 'scan' && empty($message)) {
                if (empty($scan_results)) {
                    $message .= '<p class="message success">Pemindaian selesai. Tidak ada file PHP mencurigakan ditemukan di ' . htmlspecialchars($current_scan_path) . '.</p>';
                } else {
                    $message .= '<p class="message warning">Pemindaian selesai. Ditemukan ' . count($scan_results) . ' file PHP mencurigakan di ' . htmlspecialchars($current_scan_path) . '.</p>';
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['action']) || $_GET['action'] !== 'view') {
    $current_scan_path = $default_scan_dir;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OBEY DA SUPREME</title>
    <style>
        html, body { margin: 0; height: 100vh; overflow: hidden; }
        body { font-family: Arial, sans-serif; background: #000; display: flex; justify-content: center; align-items: center; perspective: 1000px; position: relative; color: white; }
        #bgVideo { position: fixed; top: 50%; left: 50%; width: auto; height: 100vh; min-width: 100vw; min-height: 100vh; transform: translate(-50%, -50%); z-index: -1; object-fit: cover; filter: brightness(0.6); }
        .container { width: 95%; max-width: 950px; padding: 25px 35px; background: rgba(17,17,17, 0.75); border-radius: 15px; box-shadow: 0 0 30px #680202; color: white; margin: 20px; border: 1px solid #444; position: relative; z-index: 1; }
        .scan-form { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; align-items: center; }
        .scan-form label { font-weight: 500; color: white; margin-right: 5px; }
        .scan-form input[type="text"] { flex-grow: 1; padding: 12px 15px; border-radius: 12px; border: 1px solid #680202; background-color: #333; color: white; font-size: 1em; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); transition: all .3s ease; }
        .scan-form input[type="text"]:focus { outline: none; border-color: #680202; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05), 0 0 8px rgba(104,2,2,0.3); }
        .scan-form input[type="text"]::placeholder { color: #aaa; }
        button, .button { color: #111; border: none; padding: 12px 25px; border-radius: 12px; cursor: pointer; font-size: 1.05em; font-weight: 500; font-family: Arial, sans-serif; text-transform: none; transition: all .3s ease; letter-spacing: .5px; text-decoration: none; display: inline-block; }
        button { background: #680202; box-shadow: 0 4px 10px rgba(104,2,2,0.2); }
        button:hover { background: #680202; box-shadow: 0 6px 15px rgba(104,2,2,0.3); transform: translateY(-1px); }
        .button.delete { background: #680202; box-shadow: 0 4px 10px rgba(104,2,2,0.3); padding: 8px 18px; font-size: .95em; }
        .button.delete:hover { background: #680202; box-shadow: 0 6px 15px rgba(104,2,2,0.4); }
        .button.view { background: #680202; box-shadow: 0 4px 10px rgba(104,2,2,0.3); padding: 8px 18px; font-size: .95em; margin-left: 5px; }
        .button.view:hover { background: #680202; box-shadow: 0 6px 15px rgba(104,2,2,0.4); }
        .results-container { max-height: 450px; overflow-y: auto; border: 1px solid #680202; border-radius: 12px; margin-top: 20px; background-color: rgba(17,17,17, 0.5); }
        .results-table { width: 100%; border-collapse: collapse; }
        .results-table th, .results-table td { border-bottom: 1px solid #680202; padding: 10px 12px; text-align: left; word-break: break-all; font-size: .9em; color: white; }
        .results-table th { background-color: #680202; color: white; font-weight: 500; position: sticky; top: 0; z-index: 1; }
        .results-table tr:nth-child(even) td { background-color: #333; }
        .results-table tr:hover td { background-color: #444; }
        .results-table td.actions { text-align: center; min-width: 170px; white-space: nowrap; }
        .message { padding: 15px 20px; border-radius: 12px; margin: 20px 0; text-align: center; font-weight: 500; font-size: 1em; border-width: 1px; border-style: solid; box-shadow: 0 2px 5px rgba(0,0,0,0.05); color: white; }
        .message.success { background-color: #333; border-color: #680202; }
        .message.warning { background-color: #333; border-color: #680202; }
        .message.error { background-color: #333; border-color: #680202; }
        .warning-banner { background-color: #333; color: white; padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; text-align: center; border: 1px solid #680202; font-size: .95em; box-shadow: 0 2px 8px rgba(104,2,2,0.2); }
        .warning-banner strong { color: #680202; font-weight: 700; }
        .warning-banner code { background-color: rgba(0,0,0,0.05); padding: 2px 5px; border-radius: 4px; font-family: monospace; color: white; }
        .results-container::-webkit-scrollbar { width: 8px; }
        .results-container::-webkit-scrollbar-track { background: #333; border-radius: 8px; }
        .results-container::-webkit-scrollbar-thumb { background: #680202; border-radius: 8px; }
        .results-container::-webkit-scrollbar-thumb:hover { background: #680202; }
        .view-content-container { margin-top: 30px; padding: 20px; background-color: rgba(17,17,17, 0.75); border: 1px solid #680202; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .view-content-container h3 { color: #680202; margin-top: 0; margin-bottom: 15px; font-size: 1.3em; word-break: break-all; }
        .view-content-container pre { background-color: #333; padding: 15px; border-radius: 10px; border: 1px solid #680202; color: white; white-space: pre-wrap; word-wrap: break-word; max-height: 500px; overflow-y: auto; font-size: 0.9em; line-height: 1.6; }
        .view-content-container pre::-webkit-scrollbar { width: 6px; }
        .view-content-container pre::-webkit-scrollbar-thumb { background: #680202; }
    </style>
</head>
<body>
    <video id="bgVideo" autoplay loop muted playsinline>
        <source src="https://obeydasupreme.site/video/video1.mp4" type="video/mp4" />
    </video>
    <div class="container">
        <h1>Enemy is Ahead!</h1>

        <?php if (!empty($message)) echo $message; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="scan-form">
            <label for="scan_path_input">Path untuk Dipindai (Hanya File .php):</label>
            <input type="text" id="scan_path_input" name="scan_path" 
                   placeholder="Contoh: <?php echo htmlspecialchars($default_scan_dir); ?>" 
                   value="<?php echo htmlspecialchars($current_scan_path); ?>" required>
            <button type="submit" name="action" value="scan">Pindai Direktori</button>
        </form>

        <?php if (!empty($scan_results)): ?>
            <h3>File PHP Mencurigakan Ditemukan: <?php echo count($scan_results); ?></h3>
            <div class="results-container">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Path File PHP</th>
                            <th>URL</th>
                            <th>Tgl. Modifikasi</th>
                            <th>Alasan</th>
                            <th>Tindakan</th>
                            <th>URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scan_results as $file_info): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file_info['path']); ?></td>
                                <td>
                                    <?php if (!empty($file_info['url'])): ?>
                                        <a href="<?php echo htmlspecialchars($file_info['url']); ?>" target="_blank" style="color:white;">
                                            <?php echo htmlspecialchars($file_info['url']); ?>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $file_info['modified_timestamp'] ? date('Y-m-d H:i:s', $file_info['modified_timestamp']) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($file_info['reason']); ?></td>
                                <td class="actions">
                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display:inline-block;">
                                        <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($file_info['path']); ?>">
                                        <input type="hidden" name="scan_path" value="<?php echo htmlspecialchars($current_scan_path); ?>">
                                        <button type="submit" name="action" value="delete" class="button delete" 
                                                onclick="return confirm('PERINGATAN!\nAnda yakin ingin menghapus file PHP ini secara permanen?\n\n<?php echo htmlspecialchars(addslashes($file_info['path'])); ?>\n\nTindakan ini tidak dapat dibatalkan!');">
                                            Hapus
                                        </button>
                                    </form>
                                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=view&file_path=<?php echo urlencode($file_info['path']); ?>&scan_path_display=<?php echo urlencode($current_scan_path); ?>" 
                                       class="button view">
                                        Lihat
                                    </a>
                                </td>
                                <td>
                                    <a href="<?php 
                                        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://".$_SERVER['HTTP_HOST'];
                                        echo htmlspecialchars($base_url . str_replace($_SERVER['DOCUMENT_ROOT'], '', $file_info['path'])); 
                                    ?>" target="_blank" class="button view">
                                        URL
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan' && empty($message) && empty($scan_results)): ?>
            <p class="message success">Tidak ada file PHP mencurigakan yang ditemukan di path yang ditentukan.</p>
        <?php endif; ?>

        <?php if (isset($view_file_content)): ?>
            <div class="view-content-container">
                <h3>Konten File: <?php echo htmlspecialchars($view_file_path); ?></h3>
                <pre><?php echo htmlspecialchars($view_file_content); ?></pre>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
