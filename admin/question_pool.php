<?php
session_start();
require_once __DIR__ . '/../config.php';

// SuperAdmin kontrolu — normal admin erisemez
if (empty($_SESSION['super_admin_logged_in']) || empty($_SESSION['super_admin_id'])) {
    // Normal admin session varsa bilgilendir
    if (!empty($_SESSION['admin_id'])) {
        echo '<div style="padding:40px;text-align:center;font-family:sans-serif;color:#e2e8f0;background:#0f0f23;min-height:100vh;"><h2>Bu sayfa SuperAdmin yetkisi gerektirir.</h2><p><a href="/admin/dashboard.php" style="color:#6366f1;">Dashboard\'a Don</a></p></div>';
        exit;
    }
    header('Location: /admin/');
    exit;
}

$adminName = $_SESSION['super_admin_name'] ?? 'SuperAdmin';

// Red flag count for sidebar (superadmin tum okullari gorur)
$redFlagCount = (int)$pdo->query("SELECT COUNT(*) FROM aibuke_sentiment_alerts WHERE is_resolved = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soru Havuzu — Aibuke SuperAdmin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0f0f23; --surface: #1a1a2e; --border: #2d2d44; --text: #e2e8f0; --muted: #94a3b8; --primary: #6366f1; --success: #22c55e; --error: #ef4444; --warning: #f59e0b; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .page-header h1 { font-size: 22px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .card h3 { font-size: 15px; margin-bottom: 14px; color: var(--text); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 16px; text-align: center; }
        .stat-num { font-size: 26px; font-weight: 700; color: var(--primary); }
        .stat-label { font-size: 12px; color: var(--muted); margin-top: 4px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-red { background: rgba(239,68,68,0.15); color: var(--error); }
        .badge-green { background: rgba(34,197,94,0.15); color: var(--success); }
        .badge-blue { background: rgba(99,102,241,0.15); color: var(--primary); }
        .badge-yellow { background: rgba(245,158,11,0.15); color: var(--warning); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; color: var(--muted); font-weight: 500; padding: 10px; border-bottom: 1px solid var(--border); }
        td { padding: 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
        tr:hover { background: rgba(99,102,241,0.04); }
        input, select, textarea { padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text); font-size: 13px; font-family: inherit; }
        textarea { resize: vertical; }
        .btn { padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; font-weight: 500; transition: opacity 0.15s; }
        .btn:hover { opacity: 0.85; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-success { background: var(--success); color: #fff; }
        .btn-danger { background: var(--error); color: #fff; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .success-msg { background: rgba(34,197,94,0.12); color: var(--success); padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }
        .error-msg { background: rgba(239,68,68,0.12); color: var(--error); padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }

        /* Upload area */
        .upload-area { border: 2px dashed var(--border); border-radius: 12px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.2s; margin-bottom: 14px; }
        .upload-area:hover, .upload-area.dragover { border-color: var(--primary); background: rgba(99,102,241,0.05); }
        .upload-area i { font-size: 36px; color: var(--muted); margin-bottom: 10px; }
        .upload-area p { color: var(--muted); font-size: 13px; }
        .url-input { display: flex; gap: 8px; margin-bottom: 14px; }
        .url-input input { flex: 1; }

        /* Progress */
        .upload-progress { display: none; }
        .upload-progress.active { display: block; }
        .progress-bar { height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; margin: 12px 0; }
        .progress-fill { height: 100%; background: var(--primary); border-radius: 3px; transition: width 0.3s; width: 0; }
        .progress-status { font-size: 13px; color: var(--muted); }

        /* Filters */
        .filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; align-items: center; }
        .filters select, .filters input { min-width: 120px; }

        /* Pagination */
        .pagination { display: flex; gap: 6px; justify-content: center; margin-top: 16px; }
        .pagination button { padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--text); cursor: pointer; font-size: 12px; }
        .pagination button.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 24px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal h3 { margin-bottom: 16px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; }
        .form-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }

        .q-text-cell { max-width: 350px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 16px; }
            .filters { flex-direction: column; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="page-header">
            <h1><i class="fas fa-database" style="color:var(--primary);"></i> Soru Havuzu</h1>
        </div>

        <!-- Istatistikler -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card"><div class="stat-num" id="statTotal">-</div><div class="stat-label">Toplam Soru</div></div>
            <div class="stat-card"><div class="stat-num" id="statTyt">-</div><div class="stat-label">TYT</div></div>
            <div class="stat-card"><div class="stat-num" id="statAyt">-</div><div class="stat-label">AYT</div></div>
            <div class="stat-card"><div class="stat-num" id="statLgs">-</div><div class="stat-label">LGS</div></div>
        </div>

        <!-- PDF Yukle -->
        <div class="card">
            <h3><i class="fas fa-file-pdf" style="color:#ef4444;"></i> PDF'ten Soru Yukle</h3>

            <div class="upload-area" id="uploadArea" onclick="document.getElementById('pdfFile').click()">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>PDF dosyasini surukle veya tikla</p>
                <input type="file" id="pdfFile" accept=".pdf" style="display:none" onchange="handleFileSelect(this)">
            </div>

            <div class="url-input">
                <input type="url" id="pdfUrl" placeholder="veya PDF linkini yapistir...">
                <button class="btn btn-primary" onclick="uploadPDF()"><i class="fas fa-upload"></i> Yukle</button>
            </div>

            <div class="upload-progress" id="uploadProgress">
                <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
                <div class="progress-status" id="progressStatus">Hazirlaniyor...</div>
            </div>

            <div id="uploadResult"></div>
        </div>

        <!-- Ders Dagilimi -->
        <div class="card" id="subjectBreakdown" style="display:none;">
            <h3><i class="fas fa-chart-bar" style="color:var(--primary);"></i> Ders Dagilimi</h3>
            <div id="subjectStats"></div>
        </div>

        <!-- Soru Listesi -->
        <div class="card">
            <h3><i class="fas fa-list"></i> Sorular</h3>

            <div class="filters">
                <select id="filterSubject" onchange="loadQuestions()">
                    <option value="">Tum Dersler</option>
                    <option>Turkce</option><option>Matematik</option><option>Sosyal Bilimler</option><option>Fen Bilimleri</option>
                    <option>Fizik</option><option>Kimya</option><option>Biyoloji</option><option>Edebiyat</option><option>Tarih</option><option>Cografya</option>
                </select>
                <select id="filterExamType" onchange="loadQuestions()">
                    <option value="">Tum Sinav</option>
                    <option value="tyt">TYT</option><option value="ayt">AYT</option><option value="lgs">LGS</option>
                </select>
                <select id="filterDifficulty" onchange="loadQuestions()">
                    <option value="">Tum Zorluk</option>
                    <option value="easy">Kolay</option><option value="medium">Orta</option><option value="hard">Zor</option>
                </select>
                <input type="text" id="filterSearch" placeholder="Soru ara..." onkeyup="debounceSearch()">
                <select id="filterActive" onchange="loadQuestions()">
                    <option value="">Tumu</option>
                    <option value="1" selected>Aktif</option><option value="0">Pasif</option>
                </select>
            </div>

            <div id="questionTable">
                <p style="color:var(--muted);text-align:center;padding:20px;">Yukleniyor...</p>
            </div>

            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <!-- Duzenleme Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <h3><i class="fas fa-edit"></i> Soru Duzenle</h3>
            <input type="hidden" id="editId">
            <div class="form-group"><label>Ders</label><input type="text" id="editSubject"></div>
            <div class="form-group"><label>Konu</label><input type="text" id="editTopic"></div>
            <div class="form-group"><label>Zorluk</label>
                <select id="editDifficulty"><option value="easy">Kolay</option><option value="medium">Orta</option><option value="hard">Zor</option></select>
            </div>
            <div class="form-group"><label>Sinav Tipi</label>
                <select id="editExamType"><option value="tyt">TYT</option><option value="ayt">AYT</option><option value="lgs">LGS</option></select>
            </div>
            <div class="form-group"><label>Soru Metni</label><textarea id="editText" rows="4"></textarea></div>
            <div class="form-group"><label>A</label><input type="text" id="editA"></div>
            <div class="form-group"><label>B</label><input type="text" id="editB"></div>
            <div class="form-group"><label>C</label><input type="text" id="editC"></div>
            <div class="form-group"><label>D</label><input type="text" id="editD"></div>
            <div class="form-group"><label>E</label><input type="text" id="editE"></div>
            <div class="form-group"><label>Dogru Cevap</label><input type="text" id="editCorrect" maxlength="1" style="width:60px;"></div>
            <div class="form-group"><label>Kaynak</label><input type="text" id="editSource"></div>
            <div class="form-actions">
                <button class="btn" onclick="closeEdit()" style="background:var(--border);">Iptal</button>
                <button class="btn btn-primary" onclick="saveEdit()">Kaydet</button>
            </div>
        </div>
    </div>

    <script>
    let currentPage = 1;
    let searchTimeout;

    // Sayfa yuklendiginde
    document.addEventListener('DOMContentLoaded', () => {
        loadStats();
        loadQuestions();
        setupDragDrop();
        checkProcessStatus();
    });

    // Sayfa açıldığında devam eden işlem var mı kontrol et
    async function checkProcessStatus() {
        try {
            const res = await fetch('/admin/api/question_pool.php?action=process_status');
            const data = await res.json();
            if (data.status === 'processing') {
                const progress = document.getElementById('uploadProgress');
                const status = document.getElementById('progressStatus');
                progress.classList.add('active');
                status.textContent = data.step || 'İşleniyor...';
                document.getElementById('progressFill').style.width = '50%';
                pollProcessStatus();
            }
        } catch(e) {}
    }

    // Drag & Drop
    function setupDragDrop() {
        const area = document.getElementById('uploadArea');
        ['dragenter','dragover'].forEach(e => area.addEventListener(e, ev => { ev.preventDefault(); area.classList.add('dragover'); }));
        ['dragleave','drop'].forEach(e => area.addEventListener(e, ev => { ev.preventDefault(); area.classList.remove('dragover'); }));
        area.addEventListener('drop', ev => {
            const file = ev.dataTransfer.files[0];
            if (file && file.type === 'application/pdf') {
                document.getElementById('pdfFile').files = ev.dataTransfer.files;
                handleFileSelect(document.getElementById('pdfFile'));
            }
        });
    }

    function handleFileSelect(input) {
        if (input.files[0]) {
            document.getElementById('pdfUrl').value = '';
            document.querySelector('.upload-area p').textContent = input.files[0].name;
            uploadPDF();
        }
    }

    async function uploadPDF() {
        const fileInput = document.getElementById('pdfFile');
        const urlInput = document.getElementById('pdfUrl');
        const progress = document.getElementById('uploadProgress');
        const fill = document.getElementById('progressFill');
        const status = document.getElementById('progressStatus');
        const result = document.getElementById('uploadResult');

        const fd = new FormData();
        fd.append('action', 'upload_pdf');

        if (fileInput.files[0]) {
            fd.append('pdf', fileInput.files[0]);
        } else if (urlInput.value.trim()) {
            fd.append('pdf_url', urlInput.value.trim());
        } else {
            result.innerHTML = '<div class="error-msg">PDF dosyasi veya URL gerekli</div>';
            return;
        }

        progress.classList.add('active');
        result.innerHTML = '';
        fill.style.width = '5%';
        status.textContent = 'PDF yükleniyor...';

        try {
            const res = await fetch('/admin/api/question_pool.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                fill.style.width = '10%';
                status.textContent = 'PDF alındı, arka planda işleniyor...';
                pollProcessStatus();
            } else {
                fill.style.width = '100%';
                result.innerHTML = `<div class="error-msg">${data.error || 'Bilinmeyen hata'}</div>`;
                setTimeout(() => { progress.classList.remove('active'); fill.style.width = '0'; }, 3000);
            }
        } catch (e) {
            result.innerHTML = `<div class="error-msg">Bağlantı hatası: ${e.message}</div>`;
            setTimeout(() => { progress.classList.remove('active'); fill.style.width = '0'; }, 3000);
        }
    }

    function pollProcessStatus() {
        const progress = document.getElementById('uploadProgress');
        const fill = document.getElementById('progressFill');
        const status = document.getElementById('progressStatus');
        const result = document.getElementById('uploadResult');

        const poll = setInterval(async () => {
            try {
                const res = await fetch('/admin/api/question_pool.php?action=process_status');
                const data = await res.json();

                if (data.status === 'processing') {
                    status.textContent = data.step || 'İşleniyor...';

                    // Ders bazlı ilerleme göster
                    if (data.subjects) {
                        let subjHtml = '';
                        let doneCount = 0;
                        let totalCount = 0;
                        for (const [key, info] of Object.entries(data.subjects)) {
                            totalCount++;
                            let icon = '⏳';
                            if (info.status === 'tamam') { icon = '✅'; doneCount++; }
                            else if (info.status === 'isleniyor') icon = '🔄';
                            else if (info.status === 'hata') { icon = '❌'; doneCount++; }
                            subjHtml += `<span style="margin-right:12px;">${icon} ${info.name}: ${info.done}/${info.total}</span>`;
                        }
                        result.innerHTML = `<div style="padding:10px;color:#a1a1aa;font-size:0.85rem;">${subjHtml}</div>`;
                        fill.style.width = Math.max(10, Math.round((doneCount / totalCount) * 90) + 10) + '%';
                    }
                } else if (data.status === 'done') {
                    clearInterval(poll);
                    fill.style.width = '100%';
                    status.textContent = 'Tamamlandı!';

                    let detailHtml = '';
                    if (data.subjects) {
                        for (const [key, info] of Object.entries(data.subjects)) {
                            detailHtml += `<span class="badge badge-blue">${info.name}: ${info.done}</span> `;
                        }
                    }
                    result.innerHTML = `<div class="success-msg">
                        <strong>${data.total_inserted || 0} soru eklendi!</strong><br>
                        <small>Sınav: ${(data.exam_type||'').toUpperCase()} | Kaynak: ${data.source||''}</small><br>
                        <div style="margin-top:6px;">${detailHtml}</div>
                    </div>`;
                    loadStats();
                    loadQuestions();
                    setTimeout(() => { progress.classList.remove('active'); fill.style.width = '0'; }, 5000);
                } else if (data.status === 'error') {
                    clearInterval(poll);
                    fill.style.width = '100%';
                    result.innerHTML = `<div class="error-msg">${data.message || 'İşleme hatası'}</div>`;
                    setTimeout(() => { progress.classList.remove('active'); fill.style.width = '0'; }, 3000);
                }
            } catch (e) {}
        }, 3000);
    }

    async function loadStats() {
        try {
            const res = await fetch('/admin/api/question_pool.php?action=stats');
            const data = await res.json();

            document.getElementById('statTotal').textContent = (data.total || 0).toLocaleString('tr');
            document.getElementById('statTyt').textContent = (data.by_exam_type?.tyt || 0).toLocaleString('tr');
            document.getElementById('statAyt').textContent = (data.by_exam_type?.ayt || 0).toLocaleString('tr');
            document.getElementById('statLgs').textContent = (data.by_exam_type?.lgs || 0).toLocaleString('tr');

            // Ders dagilimi
            if (data.by_subject && Object.keys(data.by_subject).length > 0) {
                const box = document.getElementById('subjectBreakdown');
                box.style.display = 'block';
                let html = '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
                for (const [subj, cnt] of Object.entries(data.by_subject)) {
                    html += `<div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px 14px;min-width:120px;"><div style="font-weight:600;font-size:15px;">${cnt}</div><div style="font-size:12px;color:var(--muted);">${subj}</div></div>`;
                }
                html += '</div>';
                document.getElementById('subjectStats').innerHTML = html;
            }
        } catch (e) { console.error('Stats error:', e); }
    }

    function debounceSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { currentPage = 1; loadQuestions(); }, 400);
    }

    async function loadQuestions() {
        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            subject: document.getElementById('filterSubject').value,
            exam_type: document.getElementById('filterExamType').value,
            difficulty: document.getElementById('filterDifficulty').value,
            search: document.getElementById('filterSearch').value,
            is_active: document.getElementById('filterActive').value
        });

        try {
            const res = await fetch('/admin/api/question_pool.php?' + params);
            const data = await res.json();

            if (!data.questions || data.questions.length === 0) {
                document.getElementById('questionTable').innerHTML = '<p style="color:var(--muted);text-align:center;padding:30px;">Soru bulunamadi</p>';
                document.getElementById('pagination').innerHTML = '';
                return;
            }

            let html = '<table><thead><tr><th>ID</th><th>Ders</th><th>Konu</th><th>Soru</th><th>Cevap</th><th>Zorluk</th><th>Kaynak</th><th>Kullanim</th><th></th></tr></thead><tbody>';

            for (const q of data.questions) {
                const diffBadge = {easy:'badge-green',medium:'badge-yellow',hard:'badge-red'}[q.difficulty] || 'badge-blue';
                const shortText = (q.question_text || '').substring(0, 80) + ((q.question_text || '').length > 80 ? '...' : '');
                const activeIcon = q.is_active == 1 ? '<i class="fas fa-check-circle" style="color:var(--success);"></i>' : '<i class="fas fa-times-circle" style="color:var(--error);"></i>';

                html += `<tr>
                    <td>${q.id}</td>
                    <td>${q.subject}</td>
                    <td><small>${q.topic || '-'}</small></td>
                    <td class="q-text-cell" title="${escHtml(q.question_text)}">${escHtml(shortText)}</td>
                    <td><strong>${q.correct_answer}</strong></td>
                    <td><span class="badge ${diffBadge}">${q.difficulty}</span></td>
                    <td><small>${q.source || '-'}</small></td>
                    <td>${q.times_used}</td>
                    <td style="white-space:nowrap;">
                        ${activeIcon}
                        <button class="btn btn-sm" onclick="toggleActive(${q.id})" title="Aktif/Pasif" style="background:var(--border);margin:0 2px;"><i class="fas fa-power-off"></i></button>
                        <button class="btn btn-sm" onclick="openEdit(${q.id})" style="background:var(--border);"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="deleteQ(${q.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            }

            html += '</tbody></table>';
            document.getElementById('questionTable').innerHTML = html;

            // Pagination
            let pagHtml = '';
            for (let p = 1; p <= data.total_pages; p++) {
                if (data.total_pages > 10 && Math.abs(p - data.page) > 3 && p !== 1 && p !== data.total_pages) continue;
                pagHtml += `<button class="${p === data.page ? 'active' : ''}" onclick="currentPage=${p};loadQuestions()">${p}</button>`;
            }
            pagHtml = `<span style="color:var(--muted);font-size:12px;line-height:30px;">${data.total} soru</span>` + pagHtml;
            document.getElementById('pagination').innerHTML = pagHtml;

        } catch (e) {
            document.getElementById('questionTable').innerHTML = `<p style="color:var(--error);text-align:center;">Yukleme hatasi</p>`;
        }
    }

    async function toggleActive(id) {
        const fd = new FormData(); fd.append('action', 'toggle_active'); fd.append('id', id);
        await fetch('/admin/api/question_pool.php', { method: 'POST', body: fd });
        loadQuestions();
    }

    async function deleteQ(id) {
        if (!confirm('Bu soruyu silmek istediginize emin misiniz?')) return;
        const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
        await fetch('/admin/api/question_pool.php', { method: 'POST', body: fd });
        loadQuestions();
        loadStats();
    }

    async function openEdit(id) {
        // Mevcut soru verisini tablodan al
        const res = await fetch(`/admin/api/question_pool.php?action=list&search=&page=1&is_active=`);
        const data = await res.json();
        const q = data.questions?.find(x => x.id == id);
        if (!q) {
            // Tek soru icin tekrar cek
            const r2 = await fetch(`/admin/api/question_pool.php?action=list&search=${id}&page=1&is_active=`);
            const d2 = await r2.json();
            const q2 = d2.questions?.find(x => x.id == id);
            if (!q2) return alert('Soru bulunamadi');
            fillEditForm(q2);
        } else {
            fillEditForm(q);
        }
        document.getElementById('editModal').classList.add('active');
    }

    function fillEditForm(q) {
        document.getElementById('editId').value = q.id;
        document.getElementById('editSubject').value = q.subject || '';
        document.getElementById('editTopic').value = q.topic || '';
        document.getElementById('editDifficulty').value = q.difficulty || 'medium';
        document.getElementById('editExamType').value = q.exam_type || 'tyt';
        document.getElementById('editText').value = q.question_text || '';
        document.getElementById('editA').value = q.option_a || '';
        document.getElementById('editB').value = q.option_b || '';
        document.getElementById('editC').value = q.option_c || '';
        document.getElementById('editD').value = q.option_d || '';
        document.getElementById('editE').value = q.option_e || '';
        document.getElementById('editCorrect').value = q.correct_answer || '';
        document.getElementById('editSource').value = q.source || '';
    }

    function closeEdit() { document.getElementById('editModal').classList.remove('active'); }

    async function saveEdit() {
        const fd = new FormData();
        fd.append('action', 'update');
        fd.append('id', document.getElementById('editId').value);
        fd.append('subject', document.getElementById('editSubject').value);
        fd.append('topic', document.getElementById('editTopic').value);
        fd.append('difficulty', document.getElementById('editDifficulty').value);
        fd.append('exam_type', document.getElementById('editExamType').value);
        fd.append('question_text', document.getElementById('editText').value);
        fd.append('option_a', document.getElementById('editA').value);
        fd.append('option_b', document.getElementById('editB').value);
        fd.append('option_c', document.getElementById('editC').value);
        fd.append('option_d', document.getElementById('editD').value);
        fd.append('option_e', document.getElementById('editE').value);
        fd.append('correct_answer', document.getElementById('editCorrect').value);
        fd.append('source', document.getElementById('editSource').value);

        await fetch('/admin/api/question_pool.php', { method: 'POST', body: fd });
        closeEdit();
        loadQuestions();
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }
    </script>
</body>
</html>
