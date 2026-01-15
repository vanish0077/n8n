<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['path']) && isset($_POST['content'])) {
        ob_start();
        header('Content-Type: application/json');
        $forbidden = ['bitrix','upload','local','admin','images','include','auth','cgi-bin','css','js','personal','search','vendor'];
        
        function send($s, $m) {
            ob_end_clean();
            echo json_encode(['status' => $s, 'message' => $m], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $path = $_POST['path'] ?? '';
        $content = $_POST['content'] ?? '';
        $imgs = $_FILES['images'] ?? null;

        if (empty($path) || empty($content)) {
            send('error', 'Отсутствуют данные');
        }

        $clean = trim($path, '/\\');
        if (strpos($clean, '..') !== false || empty($clean)) {
            send('error', 'Недопустимый путь');
        }
        if (in_array(strtolower(explode('/', $clean)[0] ?? ''), $forbidden)) {
            send('error', 'Запрещённая директория');
        }

        $data = json_decode($content, true);
        if (json_last_error() || !isset($data['page_title'], $data['content'])) {
            send('error', 'Некорректный JSON');
        }

        $title = $data['page_title'];
        $html = $data['content'];

        $php = <<<PHP
<?php
require(\$_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
\$APPLICATION->SetTitle("$title");
?>
$html
<?php require(\$_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
PHP;

        try {
            $root = $_SERVER['DOCUMENT_ROOT'];
            $dir = $root . '/' . $clean;
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                send('error', "Не удалось создать директорию $dir");
            }
            if (file_put_contents($dir . '/index.php', $php) === false) {
                send('error', 'Не удалось записать index.php');
            }

            $saved = [];
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $m);
            $needed = array_unique($m[1]);

            if ($imgs && isset($imgs['name']) && !empty($imgs['name'][0])) {
                $avail = [];
                foreach ($imgs['name'] as $i => $n) {
                    if ($imgs['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $avail[basename($n)] = $imgs['tmp_name'][$i];
                }
                foreach ($needed as $p) {
                    $f = ltrim(basename($p), '/');
                    if (empty($f) || !isset($avail[$f])) continue;
                    $target = $root . '/' . ltrim($p, '/');
                    $tdir = dirname($target);
                    if (!is_dir($tdir) && !mkdir($tdir, 0755, true)) continue;
                    if (move_uploaded_file($avail[$f], $target)) {
                        $saved[] = $p;
                    }
                }
            }

            $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                 . '://' . $_SERVER['HTTP_HOST'] . '/' . $clean . '/';
                 
            $msg = "<strong>Страница создана!</strong><br><br>"
                 . "Папка: <b>$clean/</b><br>Файл: <b>index.php</b><br>"
                 . "Ссылка: <a href='$url' target='_blank'>$url</a>";
                 
            if ($saved) {
                $msg .= "<br><br><strong>Изображения размещены (" . count($saved) . "):</strong><br><br>";
                foreach ($saved as $p) {
                    $full_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                              . '://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($p);
                    $msg .= "• <a href='$full_url' target='_blank'>" . htmlspecialchars($p) . "</a><br>";
                }
            } else {
                $msg .= "<br><br>Изображения не найдены или не загружены.";
            }
            $msg .= "<br><br>Готово!";
            send('success', $msg);
        } catch (Exception $e) {
            send('error', $e->getMessage());
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Неподдерживаемый тип запроса'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['tree'])) {
    header('Content-Type: text/html; charset=utf-8');
    $root = $_SERVER['DOCUMENT_ROOT'];
    $excluded_dirs = ['bitrix', 'modules', 'upload', 'local', '.git', 'cgi-bin', 'personal', 'admin', 'include', 'css', 'js', 'vendor', 'ajax', 'aspro_regions', 'auth'];
    $excluded_files = ['access.php', 'page_generation.php','robots.php','sitemap.php', '.bottom.menu.php', '.bottom_company.menu.php', '.bottom_help.menu.php', '.bottom_info.menu.php', '.cabinet.menu.php', '.htaccess', 'import.php','.htaccess_back', 'indexblocks_index1.php' ,'.left.menu.php', '.only_catalog.menu.php', '.section.php', '.subtop_content_multilevel.menu.php', '.top.menu.php', '.top_catalog_sections.menu.php', '.top_catalog_sections.menu_ext.php', '.top_catalog_wide.menu.php', '.top_catalog_wide.menu_ext.php', '.top_content_multilevel.menu.php', '404.php', 'urlrewrite.php'];
   
    $html = '<div id="file-tree-root">';
   

    function renderFolderContent($dirPath, $relBase, $level = 1) {
        global $excluded_dirs, $excluded_files, $html;
        $items = @scandir($dirPath);
        if ($items === false) return;
        $phpCount = 0;
       
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (in_array($item, $excluded_dirs)) continue;
            $fullPath = $dirPath . '/' . $item;
            if (!is_readable($fullPath)) continue;
            $relPath = $relBase . '/' . $item;
           
            if (is_dir($fullPath)) {
                $html .= '<div class="folder-header" style="padding-left: ' . ($level * 15) . 'px;">';
                $html .= '<strong>' . htmlspecialchars($item) . '/</strong></div>';
                $html .= '<ul class="folder-content" style="display:none; padding-left:20px; margin:5px 0;">';
                if ($level < 2) {
                    renderFolderContent($fullPath, $relPath, $level + 1);
                }
                $html .= '</ul>';
            }
            else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if ($ext !== 'php') continue;
                if (in_array($item, $excluded_files) || $item[0] === '.') continue;
                $phpCount++;
                $html .= '<li style="margin:5px 0; padding-left: ' . ($level * 15) . 'px;">';
                $html .= '<label style="display:flex; align-items:center; gap:8px; cursor:pointer;">';
                $html .= '<input type="checkbox" class="file-checkbox" value="' . htmlspecialchars(addslashes($relPath), ENT_QUOTES) . '">';
                $html .= htmlspecialchars($item) . '</label></li>';
            }
        }
        if ($phpCount === 0 && $level >= 1) {
            $html .= '<li style="color:#888; font-style:italic; padding:8px 0; padding-left: ' . ($level * 15) . 'px;">';
            $html .= '(нет нужных файлов)</li>';
        }
    }
    $level1 = @scandir($root);
    if ($level1 === false) {
        echo 'Ошибка чтения корня';
        exit;
    }
    $rootFiles = [];
    $rootDirs = [];
    foreach ($level1 as $item1) {
        if ($item1 === '.' || $item1 === '..' || in_array($item1, $excluded_dirs)) continue;
       
        $full1 = $root . '/' . $item1;
        if (!is_readable($full1)) continue;
        if (is_dir($full1)) {
            $rootDirs[] = $item1;
        } else {
            $ext = strtolower(pathinfo($item1, PATHINFO_EXTENSION));
            if ($ext === 'php' && !in_array($item1, $excluded_files) && $item1[0] !== '.') {
                $rootFiles[] = $item1;
            }
        }
    }
    if (!empty($rootFiles)) {
        $html .= '<div class="folder-header" style="color:#2563eb; margin-bottom:5px;"><strong>Корень сайта</strong></div>';
        $html .= '<ul class="folder-content" style="display:block; padding-left:20px; margin-bottom:15px;">';
        foreach ($rootFiles as $file) {
            $rel1 = '/' . $file;
            $html .= '<li style="margin:8px 0">';
            $html .= '<label style="display:flex; align-items:center; gap:8px; cursor:pointer;">';
            $html .= '<input type="checkbox" class="file-checkbox" value="' . htmlspecialchars(addslashes($rel1), ENT_QUOTES) . '">';
            $html .= htmlspecialchars($file) . '</label></li>';
        }
        $html .= '</ul>';
    }
    foreach ($rootDirs as $dirItem) {
        $full1 = $root . '/' . $dirItem;
        $rel1 = '/' . $dirItem;
       
        $html .= '<div class="folder-header"><strong>' . htmlspecialchars($dirItem) . '/</strong></div>';
        $html .= '<ul class="folder-content" style="display:none;padding-left:20px;margin:5px 0">';
        renderFolderContent($full1, $rel1, 1);
        $html .= '</ul>';
    }
    $html .= '</div>';
    echo $html;
    exit;
}
if (isset($_GET['file'])) {
    $rel = $_GET['file'];
    $full = $_SERVER['DOCUMENT_ROOT'] . $rel;
    if (strpos($full, $_SERVER['DOCUMENT_ROOT']) !== 0 || !file_exists($full) || is_dir($full)) {
        http_response_code(404); exit;
    }
    header('Content-Type: text/plain');
    readfile($full);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Инструменты Bitrix</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/vanish0077/n8n@main/styles.css">
</head>
<body>
<nav class="sidebar">
    <h2>Инструменты</h2>
    <ul>
        <li><a href="#import" class="active" onclick="switchPage('import')">Импорт информации с сайта клиента</a></li>
        <li><a href="#transfer" onclick="switchPage('transfer')">Перенос информации с сайта клиента</a></li>
        <li><a href="#code-improve" onclick="switchPage('code-improve')">Улучшение кода</a></li>
        <li><a href="#changelog" onclick="openChangelog()">История версий</a></li>
    </ul>
</nav>
<div class="main-content">
    <div id="page-import" class="page active">
        <div class="app-container">
            <header><h1>Импорт информации с сайта клиента</h1></header>
            <p style="text-align:center;color:#666;margin-bottom:2rem">Отправь URL или файл — получи готовый ZIP с JSON и изображениями для Битрикса</p>
            <form id="webhook-form" action="https://n8n.takfit.ru/webhook-test/content-to-bitrix" method="POST" enctype="multipart/form-data">
                <label for="input_type">Тип ввода:</label>
                <select name="input_type" id="input_type" required>
                    <option value="url">URL (ссылка на страницу)</option>
                    <option value="file">Файл (PDF или TXT)</option>
                </select>
                <div id="url-group">
                    <label for="content_url">URL страницы:</label>
                    <input type="text" name="content" id="content_url" placeholder="https://example.com/page">
                </div>
                <div id="file-group" style="display:none">
                    <label for="content_file">Файл (PDF или TXT):</label>
                    <input type="file" name="content" id="content_file" accept=".pdf,.txt">
                </div>
                <div style="text-align:center; margin-top:2rem">
                    <button type="submit" id="submitBtn">Отправить и получить ZIP</button>
                </div>
            </form>
            <div class="results" id="webhook-results">
                <h2>Результат обработки</h2>
                <div class="loading" id="webhook-loading">Обработка... может занять до 5 минут ⌛</div>
                <div id="webhook-response"></div>
            </div>
        </div>
    </div>

    <div id="page-transfer" class="page">
        <div class="app-container">
            <header><h1>Перенос информации с сайта клиента</h1></header>
            <div class="file-input-wrapper">
                <input type="file" id="file-input" accept=".zip" style="display:none">
                <label for="file-input" class="file-input-label">Выбрать ZIP-архив</label>
                <button type="button" id="instructions-btn" class="btn-green" style="display:flex;align-items:center;gap:8px">ℹ️ Инструкция</button>
                <span id="file-name"></span>
            </div>
            <div class="archive-content" id="archive-content">
                <p id="status-message">Содержимое архива появится здесь.</p>
                <div id="sections-list"></div>
            </div>
        </div>
    </div>

    <div id="page-code-improve" class="page">
        <div class="app-container">
            <header><h1>Улучшение кода</h1></header>
            <div id="settings-and-actions" style="margin: 20px 0; padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                <div style="margin-bottom: 16px; font-weight: 500;">Настройки обработки:</div>
                <div class="settings-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 20px;">
                    <label class="option" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="opt_round_images">
                        <span>Закруглять углы изображений</span>
                    </label>
                    <label class="option" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="opt_short_tags">
                        <span>Разрешить короткие теги (без лишних стилей)</span>
                    </label>
                    <label class="option" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="opt_bitrix_best">
                        <span>Применять лучшие практики Bitrix</span>
                    </label>
                    <label class="option" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="opt_d7_only">
                        <span>Только API D7 (без устаревшего кода)</span>
                    </label>
                    <label class="option" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="opt_minify">
                        <span>Минифицировать результирующий код</span>
                    </label>
                </div>

<div id="multi-send-panel" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; min-height: 40px;">
    <div style="font-weight: 500;">
        Выбрано файлов: <span id="selected-count" style="color: #3b82f6;">0</span>
    </div>
    <div id="buttons-container" style="display: flex; gap: 10px; flex-wrap: wrap; min-width: 300px; opacity: 0; pointer-events: none; transition: opacity 0.12s ease;">
        <button type="button" id="btn-select-all" class="btn-secondary">Выбрать все</button>
        <button type="button" id="btn-deselect-all" class="btn-secondary">Снять выделение</button>
        <button type="button" id="btn-process-selected" class="btn-green" disabled>Обработать выбранные</button>
    </div>
</div>
            </div>

            <div id="code-tree" style="margin-top:20px; background:#f8f9fa; padding:15px; border-radius:8px; max-height:60vh; overflow-y:auto"></div>
        </div>
    </div>

</div> 
<div id="instructions-overlay" class="overlay">
    <div class="modal">
        <h3 style="margin-top:0;color:#2c3e50">Как подготовить ZIP-архив для переноса</h3>
        <div style="text-align:left;line-height:1.6;color:#444">
            <p><strong>1. Структура архива:</strong><br>В корне только файлы вида <code>company.json</code>, <code>about.json</code> и т.д.</p>
            <p><strong>2. Изображения:</strong><br>Картинки должны иметь суффикс с именем раздела: <code>photo_company.jpg</code>, <code>logo_company.png</code>.</p>
            <p>В JSON используй оригинальные имена без суффикса: <code>&lt;img src="/images/logo.jpg"&gt;</code>.</p>
        </div>
        <button class="modal-close">Понятно, закрыть</button>
    </div>
</div>

<div id="result-overlay" class="overlay">
    <div class="modal">
        <h3 id="modal-title"></h3>
        <p id="modal-message"></p>
        <button class="modal-close">Закрыть</button>
    </div>
</div>

<div id="changelog-overlay" class="overlay">
    <div class="modal" style="max-width:800px;max-height:80vh;overflow-y:auto;">
        <h3 style="margin-top:0;color:#2c3e50">История версий</h3>
        <div id="changelog-content" style="background:#f8f9fa;padding:20px;border-radius:8px;text-align:left;line-height:1.6;font-size:0.95rem;"></div>
        <button class="modal-close" style="margin-top: 30px; padding: 12px 28px;">Закрыть</button>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/gh/vanish0077/n8n@main/scripts.js"></script>
</body>
</html>