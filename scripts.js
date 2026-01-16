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
    const tree = document.getElementById('code-tree');
    tree.innerHTML = '<em>Загрузка структуры сайта...</em>';
    
    fetch('?tree=1')
        .then(r => r.text())
        .then(html => {
            tree.innerHTML = html;
            document.querySelectorAll('.folder-header').forEach(header => {
                header.onclick = () => {
                    const content = header.nextElementSibling;
                    if (content?.classList.contains('folder-content')) {
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

let selectedFiles = new Set();
function updateSelectionUI() {
    const count = selectedFiles.size;
    document.getElementById('selected-count').textContent = count;
    document.getElementById('btn-process-selected').disabled = count === 0;
    
    const buttonsContainer = document.getElementById('buttons-container');
    if (buttonsContainer) {
        if (count > 0) {
            buttonsContainer.style.opacity = '1';
            buttonsContainer.style.pointerEvents = 'auto';
        } else {
            buttonsContainer.style.opacity = '0';
            buttonsContainer.style.pointerEvents = 'none';
        }
    }
}
function initMultiSelectLogic() {
    const tree = document.getElementById('code-tree');
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
    document.getElementById('btn-select-all')?.addEventListener('click', () => {
        document.querySelectorAll('.file-checkbox').forEach(chk => {
            chk.checked = true;
            selectedFiles.add(chk.value);
        });
        updateSelectionUI();
    });
    document.getElementById('btn-deselect-all')?.addEventListener('click', () => {
        document.querySelectorAll('.file-checkbox').forEach(chk => chk.checked = false);
        selectedFiles.clear();
        updateSelectionUI();
    });
    document.getElementById('btn-process-selected')?.addEventListener('click', async () => {
        if (selectedFiles.size === 0) return;
        const paths = [...selectedFiles];
        const total = paths.length;
        let processed = 0;
        let successCount = 0;
        const errors = [];
        show('Обработка', `Обрабатывается ${total} файл(ов)...`, true);
        const settings = {
            round_images: document.getElementById('opt_round_images')?.checked ?? false,
            allow_short_tags: document.getElementById('opt_short_tags')?.checked ?? false,
            bitrix_best: document.getElementById('opt_bitrix_best')?.checked ?? false,
            d7_only: document.getElementById('opt_d7_only')?.checked ?? false,
            minify: document.getElementById('opt_minify')?.checked ?? false
        };
        for (const relPath of paths) {
            try {
                await improveSingleFile(relPath, settings);
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
            errors.forEach(e => msg += `<li><strong>${e.filename}</strong> — ${e.error}</li>`);
            msg += `</ul>`;
        } else {
            msg += `<br>Все файлы обработаны успешно.`;
        }
        show('Результат', msg, errors.length === 0);
        updateSelectionUI();
    });
    updateSelectionUI();
}
async function improveSingleFile(relPath, settings) {
    const filename = relPath.split('/').pop();

    const res = await fetch('?file=' + encodeURIComponent(relPath));
    if (!res.ok) throw new Error(`Не удалось загрузить файл (${res.status})`);

    const code = await res.text();
    if (!code.trim()) throw new Error('Файл пустой');

    const fd = new FormData();
    fd.append('code', code);
    fd.append('path', relPath);
    fd.append('mode', 'improve_code');
    fd.append('round_images',   settings.round_images   ? '1' : '0');
    fd.append('allow_short_tags', settings.allow_short_tags ? '1' : '0');
    fd.append('bitrix_best',    settings.bitrix_best    ? '1' : '0');
    fd.append('d7_only',        settings.d7_only        ? '1' : '0');
    fd.append('minify',         settings.minify         ? '1' : '0');

    const n8nRes = await fetch('https://n8n.takfit.ru/webhook/upgrade-code', {
        method: 'POST',
        body: fd
    });

    if (!n8nRes.ok) {
        throw new Error(`n8n ответил ошибкой HTTP ${n8nRes.status}`);
    }

    const responseText = await n8nRes.text();

    try {
        const json = JSON.parse(responseText);
        throw new Error(`Ожидался PHP-код, получен JSON: ${json.error || json.message || 'нет описания'}`);
    } catch (e) {
        if (responseText.length < 100) {
            throw new Error('Ответ от n8n слишком короткий');
        }
        // Здесь можно добавить логику сохранения/скачивания улучшенного кода
        return true;
    }
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
    fetch('/page_generation.php?action=changelog')
        .then(r => {
            if (!r.ok) throw new Error('Не удалось загрузить файл');
            return r.text();
        })
        .then(text => {
            content.innerHTML = marked.parse(text);
        })
        .catch(err => {
            content.innerHTML = `<strong style="color:#c53030">Ошибка:</strong> ${err.message}`;
        });
}


document.addEventListener('DOMContentLoaded', () => {
    switchPage('import');
});
