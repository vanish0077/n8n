<?php
if ($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['path'])){
    ob_start();header('Content-Type: application/json');
    $forbidden=['bitrix','upload','local','admin','images','include','auth','cgi-bin','css','js','personal','search','vendor'];
    function send($s,$m){ob_end_clean();echo json_encode(['status'=>$s,'message'=>$m],JSON_UNESCAPED_UNICODE);exit;}
    $path=$_POST['path']??'';$content=$_POST['content']??'';$imgs=$_FILES['images']??null;
    if(empty($path)||empty($content))send('error','Отсутствуют данные');
    $clean=trim($path,'/\\');
    if(strpos($clean,'..')!==false||empty($clean))send('error','Недопустимый путь');
    if(in_array(strtolower(explode('/',$clean)[0]??''),$forbidden))send('error','Запрещённая директория');
    $data=json_decode($content,true);
    if(json_last_error()|| !isset($data['page_title'],$data['content']))send('error','Некорректный JSON');
    $title=$data['page_title'];$html=$data['content'];
    $php=<<<PHP
<?php
require(\$_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
\$APPLICATION->SetTitle("$title");
?>
$html
<?php require(\$_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
PHP;
    try{
        $root=$_SERVER['DOCUMENT_ROOT'];$dir=$root.'/'.$clean;
        if(!is_dir($dir)&&!mkdir($dir,0755,true))send('error',"Не удалось создать $dir");
        if(file_put_contents($dir.'/index.php',$php)===false)send('error','Не удалось записать index.php');
        $saved=[];preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',$html,$m);
        $needed=array_unique($m[1]);
        if($imgs&&isset($imgs['name'])&&!empty($imgs['name'][0])){
            $avail=[];
            foreach($imgs['name'] as $i=>$n){
                if($imgs['error'][$i]!==UPLOAD_ERR_OK)continue;
                $avail[basename($n)]=$imgs['tmp_name'][$i];
            }
            foreach($needed as $p){
                $f=ltrim(basename($p),'/');
                if(empty($f)||!isset($avail[$f]))continue;
                $target=$root.'/'.ltrim($p,'/');
                $tdir=dirname($target);
                if(!is_dir($tdir)&&!mkdir($tdir,0755,true))continue;
                if(move_uploaded_file($avail[$f],$target))$saved[]=$p;
            }
        }
        $url=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/'.$clean.'/';
        $msg="<strong>Страница создана!</strong><br><br>Папка: <b>$clean/</b><br>Файл: <b>index.php</b><br>Ссылка: <a href='$url' target='_blank'>$url</a>";
        if($saved){
            $msg.="<br><br><strong>Изображения размещены (".count($saved)."):</strong><br><br>";
            foreach($saved as $p)$msg.="• <a href='".(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].htmlspecialchars($p)."' target='_blank'>".htmlspecialchars($p)."</a><br>";
        }else $msg.="<br><br>Изображения не найдены или не загружены.";
        $msg.="<br><br>Готово!";
        send('success',$msg);
    }catch(Exception $e){send('error',$e->getMessage());}
}
if (isset($_GET['tree'])) {
    header('Content-Type: text/html; charset=utf-8');
    $root = $_SERVER['DOCUMENT_ROOT'];
    $excluded_dirs = ['bitrix', 'modules', 'upload', 'local', '.git', 'cgi-bin', 'personal', 'admin', 'include', 'css', 'js', 'vendor', 'ajax', 'aspro_regions', 'auth'];
    $excluded_files = ['access.php', 'page_generation.php','robots.php','sitemap.php', '.bottom.menu.php', '.bottom_company.menu.php', '.bottom_help.menu.php', '.bottom_info.menu.php', '.cabinet.menu.php', '.htaccess', '.htaccess_back', '.left.menu.php', '.only_catalog.menu.php', '.section.php', '.subtop_content_multilevel.menu.php', '.top.menu.php', '.top_catalog_sections.menu.php', '.top_catalog_sections.menu_ext.php', '.top_catalog_wide.menu.php', '.top_catalog_wide.menu_ext.php', '.top_content_multilevel.menu.php', '404.php', 'urlrewrite.php'];
    $html = '<div id="file-tree-root">';
    $level1 = @scandir($root);
    if ($level1 === false) { echo 'Ошибка чтения корня'; exit; }
    foreach ($level1 as $item1) {
        if ($item1 === '.' || $item1 === '..' || in_array($item1, $excluded_dirs)) continue;
        $full1 = $root . '/' . $item1;
        if (!is_readable($full1)) continue;
        $rel1 = '/' . $item1;
        if (is_dir($full1)) {
            $html .= '<div class="folder-header"><strong>' . htmlspecialchars($item1) . '/</strong></div>';
            $html .= '<ul class="folder-content" style="display:none;padding-left:20px;margin:5px 0">';
            $level2 = @scandir($full1);
            if ($level2 !== false) {
                foreach ($level2 as $item2) {
                    if ($item2 === '.' || $item2 === '..' || in_array($item2, $excluded_dirs)) continue;
                    $full2 = $full1 . '/' . $item2;
                    if (!is_readable($full2)) continue;
                    $ext = strtolower(pathinfo($item2, PATHINFO_EXTENSION));
                    if ($ext !== 'php') continue;
                    if (in_array($item2, $excluded_files) || $item2[0] === '.') continue;
                    $rel2 = $rel1 . '/' . $item2;
                    $html .= '<li style="margin:5px 0">' . htmlspecialchars($item2) . ' <button class="btn-green" style="margin-left:10px;padding:6px 12px;font-size:0.9rem" onclick="sendFileToN8n(\'' . addslashes($rel2) . '\')">Отправить в n8n</button></li>';
                }
            }
            $html .= '</ul>';
        } else {
            $ext = strtolower(pathinfo($item1, PATHINFO_EXTENSION));
            if ($ext !== 'php') continue;
            if (in_array($item1, $excluded_files) || $item1[0] === '.') continue;
            $html .= '<div style="margin:10px 0">' . htmlspecialchars($item1) . ' <button class="btn-green" style="margin-left:10px;padding:6px 12px;font-size:0.9rem" onclick="sendFileToN8n(\'' . addslashes($rel1) . '\')">Отправить в n8n</button></div>';
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
<style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;background:#f4f6f9;color:#333;display:flex;min-height:100vh}.sidebar{width:260px;background:#2c3e50;color:#ecf0f1;position:fixed;height:100%;padding:2rem 0;box-shadow:4px 0 15px rgba(0,0,0,.1);overflow-y:auto}.sidebar h2{margin:0 0 2rem;padding:0 1.5rem;font-size:1.4rem;font-weight:600}.sidebar ul{list-style:none;padding:0;margin:0}.sidebar a{display:block;padding:14px 1.5rem;color:#ecf0f1;text-decoration:none;transition:.2s;font-size:1rem}.sidebar a:hover,.sidebar a.active{background:#34495e;color:#fff}.sidebar a.active{font-weight:600;border-left:4px solid #3498db}.main-content{margin-left:260px;padding:2rem;box-sizing:border-box;width:calc(100%-260px)}.page{display:none}.page.active{display:block}.app-container{max-width:900px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:2.5rem}header h1{margin:0 0 1.5rem;font-size:1.8rem;text-align:center;color:#2c3e50}label{display:block;margin:15px 0 6px;font-weight:600;color:#444}input,select{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;font-size:1rem}button{padding:12px 24px;background:#3498db;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500;transition:.2s}button:hover{background:#2980b9}button:disabled{background:#95a5a6;cursor:not-allowed}.btn-green{background:#27ae60}.btn-green:hover{background:#219a52}.file-input-wrapper{display:flex;align-items:center;justify-content:center;gap:20px;flex-wrap:wrap;margin-bottom:2rem}.file-input-label{padding:14px 36px;background:#3498db;color:#fff;border-radius:8px;cursor:pointer;font-weight:500;display:inline-block;transition:.2s}.file-input-label:hover{background:#2980b9}#file-name{margin-top:10px;width:100%;text-align:center;color:#666}.archive-content{border:1px dashed #ccc;border-radius:8px;padding:1.5rem;background:#fafafa;min-height:200px}.section-group{margin-bottom:1.5rem;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}.section-header{padding:14px 16px;background:#f8f9fa;cursor:pointer;display:flex;justify-content:space-between;align-items:center;font-weight:600}.section-header:hover{background:#eef2f6}.section-header .toggle-icon{transition:.2s}.section-header.collapsed .toggle-icon{transform:rotate(-90deg)}.section-body{padding:16px;border-top:1px solid #eee;display:none}.section-body.open{display:block}.images-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-top:12px}.image-item img{max-width:100%;max-height:180px;object-fit:contain;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,.1);transition:.2s}.image-item img:hover{transform:scale(1.05)}.image-name{margin-top:6px;font-size:.8rem;color:#666}.placeholder{text-align:center;padding:100px 20px;color:#95a5a6;font-size:1.3rem}.overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);display:none;justify-content:center;align-items:center;z-index:1000}.modal{background:#fff;padding:30px;border-radius:12px;max-width:600px;width:90%;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.2)}.results{margin-top:40px;padding:20px;background:#f8fff8;border-radius:10px;border:1px solid #d0e8d0;display:none}.results h2{color:#27ae60;text-align:center}.loading{text-align:center;color:#3498db;font-style:italic;margin:20px 0}.file-card{display:flex;align-items:center;background:#f0f8ff;padding:15px;border-radius:8px;margin-top:20px}.file-icon{font-size:40px;margin-right:20px}.download-btn{background:#27ae60;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none}.download-btn:hover{background:#219a52}.error-message{background:#fdf0f0;border:1px solid #f0c0c0;color:#c53030;padding:15px;border-radius:8px;margin-top:20px}.main-content{width:calc(100% - 260px);}#code-tree ul{padding-left:20px}#code-tree li{margin:8px 0}#code-tree strong{color:#2c3e50}.folder-header{cursor:pointer;font-weight:600;margin:10px 0;padding:5px;background:#f0f0f0;border-radius:4px}.folder-header:hover{background:#e0e0e0}</style>
</head><body>
<nav class="sidebar"><h2>Инструменты</h2><ul>
<li><a href="#import" class="active" onclick="switchPage('import')">Импорт информации с сайта клиента</a></li>
<li><a href="#transfer" onclick="switchPage('transfer')">Перенос информации с сайта клиента</a></li>
<li><a href="#code-improve" onclick="switchPage('code-improve')">Улучшение кода</a></li>
<li><a href="#changelog" onclick="openChangelog()">История версий</a></li>
</ul></nav>
<div class="main-content">
<div id="page-import" class="page active"><div class="app-container">
<header><h1>Импорт информации с сайта клиента</h1></header>
<p style="text-align:center;color:#666;margin-bottom:2rem">Отправь URL или файл — получи готовый ZIP с JSON и изображениями для Битрикса</p>
<form id="webhook-form" action="https://n8n.takfit.ru/webhook-test/content-to-bitrix" method="POST" enctype="multipart/form-data">
<label for="input_type">Тип ввода:</label><select name="input_type" id="input_type" required><option value="url">URL (ссылка на страницу)</option><option value="file">Файл (PDF или TXT)</option></select>
<label for="content_url">URL страницы:</label><input type="text" name="content" id="content_url" placeholder="https://example.com/page">
<label for="content_file">Файл (PDF или TXT):</label><input type="file" name="content" id="content_file" accept=".pdf,.txt" style="display:none">
<label for="aspro_solution">Решение Аспро:</label><select name="aspro_solution" id="aspro_solution" required><option value="" disabled selected>Выберите решение</option><option value="Аспро: Премьер">Аспро: Премьер</option><option value="Аспро: Максимум">Аспро: Максимум</option><option value="Аспро: Лайтшоп">Аспро: Лайтшоп</option><option value="Аспро: Корпоративный сайт 3.0">Аспро: Корпоративный сайт 3.0</option><option value="Аспро: Приорити 2.0">Аспро: Приорити 2.0</option></select>
<div style="text-align:center;margin-top:2rem"><button type="submit" id="submitBtn">Отправить и получить ZIP</button></div>
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
<script>
function switchPage(id){
    document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
    document.getElementById('page-'+id).classList.add('active');
    document.querySelectorAll('.sidebar a').forEach(a=>a.classList.remove('active'));
    document.querySelector(`.sidebar a[href="#${id}"]`).classList.add('active');
    if(id==='import')initImport();
    if(id==='transfer')initTransfer();
    if(id==='code-improve')initCodeImprove();
}
function initImport(){
    const f=document.getElementById('webhook-form'),t=document.getElementById('input_type'),u=document.getElementById('content_url'),fi=document.getElementById('content_file'),b=document.getElementById('submitBtn'),r=document.getElementById('webhook-results'),l=document.getElementById('webhook-loading'),c=document.getElementById('webhook-response');
    t.onchange=()=>{u.style.display=t.value==='url'?'block':'none';u.required=t.value==='url';fi.style.display=t.value==='file'?'block':'none';fi.required=t.value==='file';};
    t.dispatchEvent(new Event('change'));
    f.onsubmit=async e=>{e.preventDefault();b.disabled=true;b.textContent='Обработка...';r.style.display='block';l.style.display='block';c.innerHTML='';
        const d=new FormData(f);
        try{
            const res=await fetch(f.action,{method:'POST',body:d,headers:{'Accept':'application/zip'}});
            if(!res.ok)throw new Error(`Ошибка ${res.status}`);
            const blob=await res.blob();let name='bitrix_pages.zip';
            const disp=res.headers.get('Content-Disposition');if(disp){const m=disp.match(/filename\*?=([^;]+)/i);if(m)name=decodeURIComponent(m[1].replace(/["']/g,''));}
            const url=URL.createObjectURL(blob);
            c.innerHTML = `
    <div class="file-card">
        <div class="file-icon">📦</div>
        <div class="file-info">
            <div class="file-name" style="margin-bottom: 12px; font-weight: bold;">${name}</div>
            <a href="${url}" download="${name}" class="download-btn">Скачать архив</a>
        </div>
    </div>
`;
        }catch(err){c.innerHTML=`<div class="error-message"><strong>Ошибка:</strong> ${err.message}</div>`;}
        finally{l.style.display='none';b.disabled=false;b.textContent='Отправить и получить ZIP';}
    };
}
function initTransfer(){
    const input=document.getElementById('file-input'),name=document.getElementById('file-name'),list=document.getElementById('sections-list'),msg=document.getElementById('status-message'),ov=document.getElementById('result-overlay'),mt=document.getElementById('modal-title'),mm=document.getElementById('modal-message');
    let lastFile=null;
    input.onchange=async e=>{
        const file=e.target.files[0];if(!file)return;
        lastFile=file;await process(file);
    };
    async function process(file){
        name.textContent=`Выбран: ${file.name}`;list.innerHTML='';msg.textContent='Распаковка архива...';msg.style.display='block';
        try{
            const zip=await JSZip.loadAsync(file);msg.style.display='none';
            const files=[];zip.forEach((p,en)=>{if(!en.dir)files.push({path:p,entry:en});});
            if(!files.length){msg.textContent='Архив пуст.';msg.style.display='block';return;}
            const jsons=files.filter(f=>f.path.toLowerCase().endsWith('.json'));
            for(const j of jsons){
                const base=j.path.split('/').pop().replace(/\.json$/i,''),suf='_'+base;
                const imgs=files.filter(f=>!f.path.toLowerCase().endsWith('.json')&&f.path.includes(suf)&&/\.(jpe?g|png|gif|webp|svg)$/i.test(f.path));
                const group=document.createElement('div');group.className='section-group';
                const head=document.createElement('div');head.className='section-header collapsed';
                head.innerHTML=`<span>📄 ${j.path} ${imgs.length?`<small>(${imgs.length} изображ.)</small>`:''}</span><span class="toggle-icon">▼</span>`;
                const body=document.createElement('div');body.className='section-body';
                const btn=document.createElement('button');btn.textContent='Создать страницу';btn.className='apply-btn';
                btn.onclick=()=>createPage(j.entry,imgs,base);body.appendChild(btn);
                if(imgs.length){
                    let grid='<div class="images-grid">';
                    for(const img of imgs){
                        const blob=await img.entry.async('blob'),url=URL.createObjectURL(blob);
                        grid+=`<div class="image-item"><img src="${url}" alt="${img.path}"><div class="image-name">${img.path.split('/').pop()}</div></div>`;
                    }
                    grid+='</div>';body.insertAdjacentHTML('beforeend',grid);
                }else body.insertAdjacentHTML('beforeend','<p style="color:#999;font-style:italic">Изображения не найдены.</p>');
                head.onclick=()=>{head.classList.toggle('collapsed');body.classList.toggle('open');head.querySelector('.toggle-icon').textContent=head.classList.contains('collapsed')?'▼':'▲';};
                group.append(head,body);list.appendChild(group);
            }
        }catch(err){msg.textContent='Ошибка чтения архива.';console.error(err);}
    }
    if(lastFile)process(lastFile);
    function show(t,m,s=true){mt.textContent=t;mm.innerHTML=m;mt.style.color=s?'#27ae60':'#e74c3c';ov.style.display='flex';}
   
    async function createPage(entry,imgs,base){
        const folder=prompt(`Папка для страницы "${entry.name}":\n(например: company)`);if(!folder?.trim())return;
        show('Обработка...','Распаковка на сервер...',true);
        const json=await entry.async('string');
        const fd=new FormData();fd.append('path',folder.trim());fd.append('content',json);
        const suf='_'+base;
        for(const img of imgs){
            const blob=await img.entry.async('blob');
            let n=img.path.split('/').pop();
            const dot=n.lastIndexOf('.');if(dot!==-1){const pre=n.substring(0,dot),ext=n.substring(dot);if(pre.endsWith(suf))n=pre.slice(0,-suf.length)+ext;}
            fd.append('images[]',blob,n);
        }
        const res=await fetch('',{method:'POST',body:fd});
        const data=await res.json();
        show(data.status==='success'?'Готово!':'Ошибка',data.message,data.status==='success');
    }
    const instrBtn = document.getElementById('instructions-btn');
    if (instrBtn) instrBtn.onclick = () => document.getElementById('instructions-overlay').style.display = 'flex';
}
function initCodeImprove(){
    const tree=document.getElementById('code-tree');
    tree.innerHTML='<em>Загрузка структуры сайта...</em>';
    fetch('?tree=1')
        .then(r=>r.text())
        .then(html=>{
            tree.innerHTML=html;
            document.querySelectorAll('.folder-header').forEach(header => {
                header.onclick = () => {
                    const content = header.nextElementSibling;
                    if (content && content.classList.contains('folder-content')) {
                        content.style.display = content.style.display === 'none' ? 'block' : 'none';
                    }
                };
            });
        })
        .catch(()=>tree.innerHTML='Ошибка загрузки дерева файлов');
}
function sendFileToN8n(relPath){
    fetch('?file='+encodeURIComponent(relPath))
        .then(r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.text();
        })
        .then(code => {
            const fd = new FormData();
            fd.append('code', code);
            fd.append('path', relPath);
            return fetch('https://n8n.takfit.ru/webhook-test/upgrade-code', {
                method: 'POST',
                body: fd
            });
        })
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            // Пытаемся распарсить JSON, но если не получится — игнорируем
            return res.text().then(text => {
                try {
                    return text ? JSON.parse(text) : { message: 'Файл отправлен успешно' };
                } catch (e) {
                    return { message: 'Файл отправлен успешно (без JSON-ответа)' };
                }
            });
        })
        .then(data => show('Успех', data.message || 'Файл отправлен', true))
        .catch(err => show('Ошибка', err.message || 'Неизвестная ошибка', false));
}
function show(t,m,s=true){
    const mt=document.getElementById('modal-title'),mm=document.getElementById('modal-message'),ov=document.getElementById('result-overlay');
    mt.textContent=t;mm.innerHTML=m;mt.style.color=s?'#27ae60':'#e74c3c';ov.style.display='flex';
}
document.querySelectorAll('.modal-close').forEach(btn => {
    btn.onclick = () => btn.closest('.overlay').style.display = 'none';
});
document.querySelectorAll('.overlay').forEach(ov => {
    ov.onclick = e => {if(e.target.classList.contains('overlay'))ov.style.display='none';};
});
function openChangelog() {
    const overlay = document.getElementById('changelog-overlay');
    const content = document.getElementById('changelog-content');
    if (!overlay || !content) return;
    overlay.style.display = 'flex';
    content.innerHTML = '<em>Загрузка истории версий...</em>';
    fetch('https://raw.githubusercontent.com/vanish0077/n8n/main/CHANGELOG.md')
        .then(r => {if (!r.ok) throw new Error('Файл не найден');return r.text();})
        .then(text => content.innerHTML = marked.parse(text))
        .catch(err => content.innerHTML = `<strong style="color:#c53030">Ошибка:</strong> ${err.message}`);
}
document.addEventListener('DOMContentLoaded',()=>switchPage('import'));
</script>
</body></html>