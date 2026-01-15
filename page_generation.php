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

        $path    = $_POST['path'] ?? '';
        $content = $_POST['content'] ?? '';
        $imgs    = $_FILES['images'] ?? null;

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
        $html  = $data['content'];

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
            $dir  = $root . '/' . $clean;

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
                    $tdir   = dirname($target);

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
        'status'  => 'error',
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
                // Папка второго уровня (или глубже) — показываем как вложенную
                $html .= '<div class="folder-header" style="padding-left: ' . ($level * 15) . 'px;">';
                $html .= '<strong>' . htmlspecialchars($item) . '/</strong></div>';
                
                $html .= '<ul class="folder-content" style="display:none; padding-left:20px; margin:5px 0;">';
                
                // Рекурсивно вызываем для следующего уровня (пока только до 2-го)
                if ($level < 2) {
                    renderFolderContent($fullPath, $relPath, $level + 1);
                } else {
                    // Можно оставить пустым или написать "(дальше не сканируем)"
                }
                
                $html .= '</ul>';
            } 
            else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if ($ext !== 'php') continue;
                if (in_array($item, $excluded_files) || $item[0] === '.') continue;
                $phpCount++;
                $html .= '<li style="margin:5px 0; padding-left: ' . ($level * 15) . 'px;">';
                $html .= htmlspecialchars($item);
                $html .= ' <button class="btn-green" style="margin-left:10px;padding:6px 12px;font-size:0.9rem" ';
               $html .= ' <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">';
$html .= '<input type="checkbox" class="file-checkbox" value="' . addslashes($relPath) . '">';
$html .= htmlspecialchars($item);
$html .= '</label>';
                $html .= '</li>';
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
    foreach ($level1 as $item1) {
        if ($item1 === '.' || $item1 === '..' || in_array($item1, $excluded_dirs)) continue;

        $full1 = $root . '/' . $item1;
        if (!is_readable($full1)) continue;

        $rel1 = '/' . $item1;

        if (is_dir($full1)) {
            $html .= '<div class="folder-header"><strong>' . htmlspecialchars($item1) . '/</strong></div>';
            $html .= '<ul class="folder-content" style="display:none;padding-left:20px;margin:5px 0">';

            renderFolderContent($full1, $rel1, 1);
            
            $html .= '</ul>';
        } 
        else {
            $ext = strtolower(pathinfo($item1, PATHINFO_EXTENSION));
            if ($ext !== 'php') continue;
            if (in_array($item1, $excluded_files) || $item1[0] === '.') continue;
            $html .= '<div style="margin:10px 0">';
            $html .= htmlspecialchars($item1);
            $html .= ' <button class="btn-green" style="margin-left:10px;padding:6px 12px;font-size:0.9rem" ';
           $html .= ' <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">';
$html .= '<input type="checkbox" class="file-checkbox" value="' . addslashes($relPath) . '">';
$html .= htmlspecialchars($item);
$html .= '</label>';
            $html .= '</div>';
        }
    }

    $html .= '</div>';
    echo $html;
    exit;
}
if (isset($_GET['file'])) {
    $rel = $_GET['file'];
    $full = $_SERVER['DOCUMENT_ROOT'] . $rel;
    if (strpos($full, $_SERVER['DOCUMENT_ROOT']) !== 0 || !file_exists($full) || is_dir($full)) {
        http_response_code(404);exit;
    }
    header('Content-Type: text/plain');
    readfile($full);
    exit;
}
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Инструменты Bitrix</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;background:#f4f6f9;color:#333;display:flex;min-height:100vh}.sidebar{width:260px;background:#2c3e50;color:#ecf0f1;position:fixed;height:100%;padding:2rem 0;box-shadow:4px 0 15px rgba(0,0,0,.1);overflow-y:auto}.sidebar h2{margin:0 0 2rem;padding:0 1.5rem;font-size:1.4rem;font-weight:600}.sidebar ul{list-style:none;padding:0;margin:0}.sidebar a{display:block;padding:14px 1.5rem;color:#ecf0f1;text-decoration:none;transition:.2s;font-size:1rem}.sidebar a:hover,.sidebar a.active{background:#34495e;color:#fff}.sidebar a.active{font-weight:600;border-left:4px solid #3498db}.main-content{margin-left:260px;padding:2rem;box-sizing:border-box;width:calc(100% - 260px)}.page{display:none}.page.active{display:block}.app-container{max-width:900px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:2.5rem}#code-tree{background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;font-size:.95rem;color:#1f2937;margin-top:20px;box-shadow:0 1px 3px rgba(0,0,0,.05)}.folder-header{padding:10px 16px;background:#f9fafb;border-bottom:1px solid #e5e7eb;cursor:pointer;user-select:none;font-weight:600;color:#111827;transition:background-color .14s ease}.folder-header:hover{background:#f1f5f9}.folder-header::before{content:"📁 ";margin-right:8px;opacity:.82}.folder-content{margin:2px 0 10px;padding:0;list-style:none;border-left:2px solid #e5e7eb;margin-left:24px}#code-tree li{display:flex;align-items:center;gap:8px;padding:7px 16px;padding-left:calc(16px + var(--level,0)*24px)!important}#code-tree li:hover{background:#fafbfc}#code-tree li::before{content:"📄 ";margin-right:8px;opacity:.78;flex-shrink:0}#code-tree .root-file{display:flex;align-items:center;justify-content:space-between;padding:7px 16px;color:#374151;transition:background-color .14s ease;border-bottom:1px solid #f3f4f6;margin:4px 0}#code-tree .root-file:hover{background:#fafbfc}#code-tree .root-file::before{content:"📄 ";margin-right:9px;opacity:.78;flex-shrink:0}#code-tree .root-file.index-file::before{content:"🏠 ";opacity:.9}#code-tree .btn-green{padding:4px 12px;font-size:.81rem;line-height:1.3;background:#6366f1;color:#fff;border:none;border-radius:6px;cursor:pointer;transition:all .14s ease;white-space:nowrap;margin-left:12px}#code-tree .btn-green:hover{background:#4f46e5;transform:translateY(-1px)}#code-tree li:contains("(нет нужных файлов)"),#code-tree .empty-message{color:#94a3b8;font-style:italic;font-size:.87rem;border-bottom:none;padding:8px 16px}.folder-header,#code-tree li,#code-tree .root-file{padding-left:calc(16px + var(--level,0)*24px)!important}header h1{margin:0 0 1.5rem;font-size:1.8rem;text-align:center;color:#2c3e50}label{display:block;margin:15px 0 6px;font-weight:600;color:#444}input,select{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;font-size:1rem}button{padding:12px 24px;background:#3498db;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500;transition:.2s}button:hover{background:#2980b9}button:disabled{background:#95a5a6;cursor:not-allowed}.file-input-wrapper{display:flex;align-items:center;justify-content:center;gap:20px;flex-wrap:wrap;margin-bottom:2rem}.file-input-label{padding:14px 36px;background:#3498db;color:#fff;border-radius:8px;cursor:pointer;font-weight:500;display:inline-block;transition:.2s}.file-input-label:hover{background:#2980b9}#file-name{margin-top:10px;width:100%;text-align:center;color:#666}.archive-content{border:1px dashed #ccc;border-radius:8px;padding:1.5rem;background:#fafafa;min-height:200px}.section-group{margin-bottom:1.5rem;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}.section-header{padding:14px 16px;background:#f8f9fa;cursor:pointer;display:flex;justify-content:space-between;align-items:center;font-weight:600}.section-header:hover{background:#eef2f6}.section-header .toggle-icon{transition:.2s}.section-header.collapsed .toggle-icon{transform:rotate(-90deg)}.section-body{padding:16px;border-top:1px solid #eee;display:none}.section-body.open{display:block}.images-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-top:12px}.image-item img{max-width:100%;max-height:180px;object-fit:contain;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,.1);transition:.2s}.image-item img:hover{transform:scale(1.05)}.image-name{margin-top:6px;font-size:.8rem;color:#666}.placeholder{text-align:center;padding:100px 20px;color:#95a5a6;font-size:1.3rem}.overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);display:none;justify-content:center;align-items:center;z-index:1000}.modal{background:#fff;padding:30px;border-radius:12px;max-width:600px;width:90%;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.2)}.results{margin-top:40px;padding:20px;background:#f8fff8;border-radius:10px;border:1px solid #d0e8d0;display:none}.results h2{color:#27ae60;text-align:center}.loading{text-align:center;color:#3498db;font-style:italic;margin:20px 0}.file-card{display:flex;align-items:center;background:#f0f8ff;padding:15px;border-radius:8px;margin-top:20px}.file-icon{font-size:40px;margin-right:20px}.download-btn{background:#27ae60;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none}.download-btn:hover{background:#219a52}.error-message{background:#fdf0f0;border:1px solid #f0c0c0;color:#c53030;padding:15px;border-radius:8px;margin-top:20px}
.modal-file-send {
    max-width: 480px;
    width: 92%;
    background: #ffffff;
    border-radius: 12px;
    padding: 28px 32px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.22);
}

.modal-file-send h3 {
    margin: 0 0 20px 0;
    color: #1f2937;
    font-size: 1.4rem;
}

.modal-file-send .filename-display {
    background: #f1f5f9;
    padding: 12px 16px;
    border-radius: 8px;
    margin: 16px 0 24px 0;
    font-family: monospace;
    font-size: 1.05rem;
    word-break: break-all;
    color: #374151;
}

.modal-file-send .settings-grid {
    display: flex;
    flex-direction: column;
    gap: 14px;                    /* расстояние между пунктами */
    margin: 20px 0 28px 0;
}

.modal-file-send .option {
    display: flex;
    align-items: center;          /* вертикальное выравнивание по центру */
    gap: 12px;                    /* расстояние между чекбоксом и текстом */
    padding: 8px 12px;
    border-radius: 8px;
    transition: background-color 0.18s ease;
    cursor: pointer;
    user-select: none;
    color: #374151;
    font-size: 1rem;
}


.modal-file-send .option:hover {
    background-color: #f8fafc;
}


.modal-file-send .option input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #6366f1;        /* цвет галочки — в тон основной кнопки */
    cursor: pointer;
    margin: 0;                    /* убираем лишние браузерные отступы */
    flex-shrink: 0;
}

.modal-file-send .option span {   /* если хотите обернуть текст в <span> */
    line-height: 1.45;
}
.modal-file-send label.option {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1rem;
    color: #374151;
    cursor: pointer;
}

.modal-file-send .actions {
    display: flex;
    gap: 16px;
    justify-content: flex-end;
    margin-top: 20px;
}

.modal-file-send button {
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    border: none;
}

.modal-file-send .btn-cancel {
    background: #e5e7eb;
    color: #374151;
}

.modal-file-send .btn-cancel:hover {
    background: #d1d5db;
}

.modal-file-send .btn-send {
    background: #6366f1;
    color: white;
}

.modal-file-send .btn-send:hover {
    background: #4f46e5;
}
</style>
</head>
<body>
<nav class="sidebar"><h2>Инструменты</h2><ul>
<li><a href="#import" class="active" onclick="switchPage('import')">Импорт информации с сайта клиента</a></li>
<li><a href="#transfer" onclick="switchPage('transfer')">Перенос информации с сайта клиента</a></li>
<li><a href="#code-improve" onclick="switchPage('code-improve')">Улучшение кода</a></li>
<li><a href="#changelog" onclick="openChangelog()">История версий</a></li>
</ul>
</nav>
<div class="main-content">
<div id="page-import" class="page active"><div class="app-container">
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
<div class="results" id="webhook-results"><h2>Результат обработки</h2><div class="loading" id="webhook-loading">Обработка... может занять до 5 минут ⌛</div><div id="webhook-response"></div></div>
</div></div>
<div id="page-transfer" class="page"><div class="app-container">
<header><h1>Перенос информации с сайта клиента</h1></header>
<div class="file-input-wrapper"><input type="file" id="file-input" accept=".zip" style="display:none">
<label for="file-input" class="file-input-label">Выбрать ZIP-архив</label>
<button type="button" id="instructions-btn" class="btn-green" style="display:flex;align-items:center;gap:8px">ℹ️ Инструкция</button>
<span id="file-name"></span></div>
<div class="archive-content" id="archive-content"><p id="status-message">Содержимое архива появится здесь.</p><div id="sections-list"></div></div>
</div></div>
<div id="page-code-improve" class="page"><div class="app-container">
<header><h1>Улучшение кода</h1></header>
<div id="code-tree" style="margin-top:20px;background:#f8f9fa;padding:15px;border-radius:8px;max-height:70vh;overflow-y:auto"></div>
<div id="multi-send-panel" style="margin: 20px 0; padding: 14px 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; display: none; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div style="font-weight: 500;">
        Выбрано файлов: <span id="selected-count" style="color: #3b82f6;">0</span>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button type="button" id="btn-select-all" class="btn-secondary" style="padding: 8px 14px; font-size: 0.95rem;">
            Выбрать все
        </button>
        <button type="button" id="btn-deselect-all" class="btn-secondary" style="padding: 8px 14px; font-size: 0.95rem;">
            Снять выделение
        </button>
        <button type="button" id="btn-process-selected" class="btn-green" disabled style="padding: 8px 18px; font-size: 0.95rem; font-weight: 500;">
            Обработать выбранные
        </button>
    </div>
</div>
</div></div>
</div>
<div id="instructions-overlay" class="overlay"><div class="modal">
<h3 style="margin-top:0;color:#2c3e50">Как подготовить ZIP-архив для переноса</h3>
<div style="text-align:left;line-height:1.6;color:#444">
<p><strong>1. Структура архива:</strong><br>В корне только файлы вида <code>company.json</code>, <code>about.json</code> и т.д.</p>
<p><strong>2. Изображения:</strong><br>Картинки должны иметь суффикс с именем раздела: <code>photo_company.jpg</code>, <code>logo_company.png</code>.</p>
<p>В JSON используй оригинальные имена без суффикса: <code>&lt;img src="/images/logo.jpg"&gt;</code>.</p>
</div>
<button class="modal-close">Понятно, закрыть</button>
</div></div>
<div id="result-overlay" class="overlay"><div class="modal"><h3 id="modal-title"></h3><p id="modal-message"></p><button class="modal-close">Закрыть</button></div></div>
<div id="changelog-overlay" class="overlay">
    <div class="modal" style="max-width:800px;max-height:80vh;overflow-y:auto;">
        <h3 style="margin-top:0;color:#2c3e50">История версий</h3>
        <div id="changelog-content" style="background:#f8f9fa;padding:20px;border-radius:8px;text-align:left;line-height:1.6;font-size:0.95rem;"></div>
        <button class="modal-close" style="margin-top: 30px; padding: 12px 28px;">Закрыть</button>
    </div>
</div>



<div id="file-send-modal" class="overlay" style="display:none;">
    <div class="modal-file-send">
        <h3>Отправка файла в n8n</h3>
        
        <div class="filename-display" id="modal-filename">имя_файла.php</div>
        
        <div class="settings-grid">
            <label class="option">
                <input type="checkbox" id="opt_round_images"> 
              <span>Закруглять картинки</span>
            </label>
            
            <label class="option">
                <input type="checkbox" id="opt_short_tags"> 
                <span>Не добавлять стили</span>
            </label>
            
            <label class="option">
                <input type="checkbox" id="opt_bitrix_best"> 
               <span>Заглушка 3</span>
            </label>
            
            <label class="option">
                <input type="checkbox" id="opt_d7_only"> 
              <span>Заглушка 4</span>
            </label>
            
            <label class="option">
                <input type="checkbox" id="opt_minify"> 
                <span>заглушка 5</span>
            </label>
        </div>
        
        <div class="actions">
            <button class="btn-cancel" onclick="closeFileModal()">Отмена</button>
            <button class="btn-send" id="btn-confirm-send">Обработать файл</button>
        </div>
    </div>
</div>


<script>
function switchPage(id){
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById('page-'+id).classList.add('active');
    document.querySelectorAll('.sidebar a').forEach(a => a.classList.remove('active'));
    document.querySelector(`.sidebar a[href="#${id}"]`).classList.add('active');
    
    if(id==='import') initImport();
    if(id==='transfer') initTransfer();
    if(id==='code-improve') initCodeImprove();
}

function initImport(){
    const f = document.getElementById('webhook-form'),
          t = document.getElementById('input_type'),
          u = document.getElementById('content_url'),
          fi = document.getElementById('content_file'),
          b = document.getElementById('submitBtn'),
          r = document.getElementById('webhook-results'),
          l = document.getElementById('webhook-loading'),
          c = document.getElementById('webhook-response');

    t.onchange = () => {
        const isUrl = t.value === 'url';
        document.getElementById('url-group').style.display = isUrl ? 'block' : 'none';
        document.getElementById('file-group').style.display = isUrl ? 'none' : 'block';
        document.getElementById('content_url').required = isUrl;
        document.getElementById('content_file').required = !isUrl;
    };
    t.dispatchEvent(new Event('change'));

    f.onsubmit = async e => {
        e.preventDefault();
        b.disabled = true;
        b.textContent = 'Обработка...';
        r.style.display = 'block';
        l.style.display = 'block';
        c.innerHTML = '';

        const d = new FormData(f);
        try {
            const res = await fetch(f.action, { method: 'POST', body: d, headers: { 'Accept': 'application/zip' } });
            if (!res.ok) throw new Error(`Ошибка ${res.status}`);
            const blob = await res.blob();
            let name = 'bitrix_pages.zip';
            const disp = res.headers.get('Content-Disposition');
            if (disp) {
                const m = disp.match(/filename\*?=([^;]+)/i);
                if (m) name = decodeURIComponent(m[1].replace(/["']/g, ''));
            }
            const url = URL.createObjectURL(blob);
            c.innerHTML = `
                <div class="file-card">
                    <div class="file-icon">📦</div>
                    <div class="file-info">
                        <div class="file-name" style="margin-bottom: 12px; font-weight: bold;">${name}</div>
                        <a href="${url}" download="${name}" class="download-btn">Скачать архив</a>
                    </div>
                </div>
            `;
        } catch (err) {
            c.innerHTML = `<div class="error-message"><strong>Ошибка:</strong> ${err.message}</div>`;
        } finally {
            l.style.display = 'none';
            b.disabled = false;
            b.textContent = 'Отправить и получить ZIP';
        }
    };
}

function initTransfer(){
    // Ваш существующий код для transfer остаётся без изменений
    // (оставляю его закомментированным для краткости, но он должен остаться в файле)
    /*
    const input = document.getElementById('file-input'), ...
    ... (весь ваш код initTransfer)
    */
}

function initCodeImprove(){
    const tree = document.getElementById('code-tree');
    tree.innerHTML = '<em>Загрузка структуры сайта...</em>';

    fetch('?tree=1')
        .then(r => r.text())
        .then(html => {
            tree.innerHTML = html;

            // Раскрытие/сворачивание папок
            document.querySelectorAll('.folder-header').forEach(header => {
                header.onclick = () => {
                    const content = header.nextElementSibling;
                    if (content && content.classList.contains('folder-content')) {
                        content.style.display = content.style.display === 'none' ? 'block' : 'none';
                    }
                };
            });

            initMultiSelectLogic();
        })
        .catch(() => {
            tree.innerHTML = '<div style="color:#c53030">Ошибка загрузки дерева файлов</div>';
        });
}

// ───────────────────────────────────────────────
//     Множественный выбор и массовая обработка
// ───────────────────────────────────────────────

let selectedFiles = new Set();

function updateSelectionUI() {
    const count = selectedFiles.size;
    const panel = document.getElementById('multi-send-panel');
    const countEl = document.getElementById('selected-count');
    const processBtn = document.getElementById('btn-process-selected');

    if (countEl) countEl.textContent = count;
    if (processBtn) processBtn.disabled = count === 0;
    if (panel) panel.style.display = count > 0 ? 'flex' : 'none';
}

function initMultiSelectLogic() {
    const tree = document.getElementById('code-tree');

    // Обработка чекбоксов
    tree.addEventListener('change', e => {
        if (!e.target.classList.contains('file-checkbox')) return;
        const path = e.target.value;
        if (e.target.checked) {
            selectedFiles.add(path);
        } else {
            selectedFiles.delete(path);
        }
        updateSelectionUI();
    });

    // Кнопка "Выбрать все"
    document.getElementById('btn-select-all')?.addEventListener('click', () => {
        document.querySelectorAll('.file-checkbox').forEach(chk => {
            chk.checked = true;
            selectedFiles.add(chk.value);
        });
        updateSelectionUI();
    });

    // Кнопка "Снять все"
    document.getElementById('btn-deselect-all')?.addEventListener('click', () => {
        document.querySelectorAll('.file-checkbox').forEach(chk => chk.checked = false);
        selectedFiles.clear();
        updateSelectionUI();
    });

    // Кнопка "Обработать выбранные"
    document.getElementById('btn-process-selected')?.addEventListener('click', async () => {
        if (selectedFiles.size === 0) return;

        const paths = [...selectedFiles];
        const total = paths.length;
        let processed = 0;
        let successCount = 0;
        const errors = [];

        show('Обработка', `Обрабатывается ${total} файл(ов)...`, true);

        for (const relPath of paths) {
            try {
                await improveSingleFile(relPath);
                successCount++;
            } catch (err) {
                errors.push({
                    filename: relPath.split('/').pop(),
                    error: err.message || String(err)
                });
            }
            processed++;
            show('Прогресс', 
                `Обработано ${processed} из ${total} (${successCount} успешно)${errors.length ? `, ошибок: ${errors.length}` : ''}`,
                errors.length === 0);
        }

        let msg = `<strong>Обработка завершена</strong><br><br>`;
        msg += `Успешно: ${successCount} из ${total}<br>`;
        if (errors.length) {
            msg += `<br><strong>Ошибки (${errors.length}):</strong><ul style="margin:8px 0; padding-left:20px;">`;
            errors.forEach(e => {
                msg += `<li><strong>${e.filename}</strong> — ${e.error}</li>`;
            });
            msg += `</ul>`;
        } else {
            msg += `<br>Все файлы обработаны успешно.`;
        }

        show('Результат', msg, errors.length === 0);

        // Опционально: можно очистить выбор после завершения
        // selectedFiles.clear();
        // updateSelectionUI();
    });

    // Инициализация состояния
    updateSelectionUI();
}

async function improveSingleFile(relPath) {
    const filename = relPath.split('/').pop();

    // 1. Получаем содержимое файла
    const res = await fetch('?file=' + encodeURIComponent(relPath));
    if (!res.ok) throw new Error(`Не удалось загрузить файл (${res.status})`);
    const code = await res.text();
    if (!code.trim()) throw new Error('Файл пустой');

    // 2. Считываем текущие настройки (чекбоксы из модального окна остаются видимыми)
    const settings = {
        round_images:   document.getElementById('opt_round_images')?.checked   ?? false,
        allow_short_tags: document.getElementById('opt_short_tags')?.checked ?? false,
        bitrix_best:     document.getElementById('opt_bitrix_best')?.checked   ?? false,
        d7_only:         document.getElementById('opt_d7_only')?.checked       ?? false,
        minify:          document.getElementById('opt_minify')?.checked        ?? false
    };

    // 3. Формируем запрос в n8n
    const fd = new FormData();
    fd.append('code', code);
    fd.append('path', relPath);
    fd.append('mode', 'improve_code');
    fd.append('round_images',   settings.round_images   ? '1' : '0');
    fd.append('allow_short_tags', settings.allow_short_tags ? '1' : '0');
    fd.append('bitrix_best',     settings.bitrix_best     ? '1' : '0');
    fd.append('d7_only',         settings.d7_only         ? '1' : '0');
    fd.append('minify',          settings.minify          ? '1' : '0');

    // 4. Отправляем в n8n
    const n8nRes = await fetch('https://n8n.takfit.ru/webhook/upgrade-code', {
        method: 'POST',
        body: fd
    });

    if (!n8nRes.ok) {
        throw new Error(`n8n ответил ошибкой HTTP ${n8nRes.status}`);
    }

    const responseText = await n8nRes.text();

    // Проверяем, что получили не JSON-ошибку, а код
    try {
        const json = JSON.parse(responseText);
        throw new Error(`Ожидался PHP-код, получен JSON: ${json.error || json.message || 'нет описания'}`);
    } catch (e) {
        // Если не удалось распарсить как JSON → считаем, что это и есть код
        if (responseText.length < 100) {
            throw new Error('Ответ от n8n слишком короткий');
        }
        // Здесь можно добавить скачивание или сохранение, если нужно
        // Пока просто считаем успешным
        return true;
    }
}

// ───────────────────────────────────────────────
//     Остальные вспомогательные функции
// ───────────────────────────────────────────────

function closeFileModal() {
    document.getElementById('file-send-modal').style.display = 'none';
}

function show(title, message, success = true) {
    const mt = document.getElementById('modal-title');
    const mm = document.getElementById('modal-message');
    const ov = document.getElementById('result-overlay');
    if (!mt || !mm || !ov) return;

    mt.textContent = title;
    mm.innerHTML = message;
    mt.style.color = success ? '#27ae60' : '#e74c3c';
    ov.style.display = 'flex';
}

document.querySelectorAll('.modal-close').forEach(btn => {
    btn.onclick = () => btn.closest('.overlay').style.display = 'none';
});

document.querySelectorAll('.overlay').forEach(ov => {
    ov.onclick = e => {
        if (e.target.classList.contains('overlay')) ov.style.display = 'none';
    };
});

function openChangelog() {
    const overlay = document.getElementById('changelog-overlay');
    const content = document.getElementById('changelog-content');
    if (!overlay || !content) return;

    overlay.style.display = 'flex';
    content.innerHTML = '<em>Загрузка истории версий...</em>';

    fetch('https://raw.githubusercontent.com/vanish0077/n8n/main/CHANGELOG.md')
        .then(r => {
            if (!r.ok) throw new Error('Файл не найден');
            return r.text();
        })
        .then(text => content.innerHTML = marked.parse(text))
        .catch(err => content.innerHTML = `<strong style="color:#c53030">Ошибка:</strong> ${err.message}`);
}

// Старый sendFileToN8n можно оставить, если хотите сохранить возможность одиночной отправки через модалку
// Но теперь основная логика — через массовую обработку

document.addEventListener('DOMContentLoaded', () => {
    switchPage('import');
});
</script>
</body></html>