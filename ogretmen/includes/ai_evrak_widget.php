<?php
/**
 * AI Evrak Widget — her evrak sayfasına include edilir
 * Kullanım: include 'includes/ai_evrak_widget.php';
 * Sayfa değişkenleri: $aiType, $aiSiniflar (opsiyonel), $aiExtraLabel (opsiyonel)
 */
$ogretmenInfo   = getOgretmenInfo();
$aiSiniflar     = $aiSiniflar ?? [];
$aiExtraLabel   = $aiExtraLabel ?? '';
$aiOgrenciSec   = in_array($aiExtraLabel, ['Öğrenci Adı Soyadı', 'Aday Öğretmen Adı Soyadı']) && $aiExtraLabel === 'Öğrenci Adı Soyadı';
?>
<style>
.ai-evrak-box{background:#1a1a2e;border:1px solid #6366f1;border-radius:12px;padding:20px;margin-bottom:24px}
.ai-evrak-box h3{color:#a5b4fc;font-size:14px;font-weight:600;margin-bottom:14px;display:flex;align-items:center;gap:6px}
.ai-evrak-fields{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-bottom:10px}
.ai-prompt-row{margin-bottom:14px}
.ai-prompt-row label{font-size:12px;color:#94a3b8;display:block;margin-bottom:4px}
.ai-prompt-row textarea{width:100%;padding:8px 10px;background:#0f0f23;border:1px solid #2d2d44;border-radius:7px;color:#e2e8f0;font-size:13px;resize:vertical;min-height:56px;font-family:inherit}
.ai-prompt-row textarea:focus{outline:none;border-color:#6366f1}
.ai-prompt-row textarea::placeholder{color:#4a5568}
.ai-evrak-fields label{font-size:12px;color:#94a3b8;display:block;margin-bottom:4px}
.ai-evrak-fields input,.ai-evrak-fields select{width:100%;padding:7px 10px;background:#0f0f23;border:1px solid #2d2d44;border-radius:7px;color:#e2e8f0;font-size:13px}
.ai-evrak-btns{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn-ai-fill{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.2s}
.btn-ai-fill:disabled{opacity:.6;cursor:not-allowed}
.btn-ai-fill:hover:not(:disabled){opacity:.9}
.btn-word{background:#1d6f42;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:none;align-items:center;gap:6px;transition:.2s}
.btn-word:hover:not(:disabled){opacity:.9}
#ai-fill-status{font-size:12px;color:#94a3b8}
.ai-preview-panel{background:#0f0f23;border:1px solid #2d2d44;border-radius:10px;padding:16px;margin-top:14px;display:none;max-height:360px;overflow-y:auto}
.ai-preview-inner{}
.ai-preview-row{padding:6px 0;border-bottom:1px solid #1e1e35;font-size:13px;display:flex;gap:10px}
.ai-preview-row:last-child{border-bottom:none}
.ai-preview-key{color:#94a3b8;min-width:140px;font-weight:600;text-transform:capitalize;font-size:12px}
.ai-preview-val{color:#e2e8f0;flex:1}
.ai-preview-imza{margin-top:12px;padding-top:10px;border-top:1px dashed #2d2d44;color:#6366f1;font-size:12px;white-space:pre-line}
@keyframes spin{to{transform:rotate(360deg)}}
.ai-spin{display:inline-block;animation:spin 1s linear infinite}
</style>

<div class="ai-evrak-box">
    <h3>🤖 MiniMax M2.7 ile Otomatik Doldur</h3>
    <div class="ai-evrak-fields">
        <div>
            <label>Tarih</label>
            <input type="date" id="ai-tarih" value="<?= date('Y-m-d') ?>">
        </div>
        <?php if ($aiOgrenciSec): ?>
        <div>
            <label>Sınıf</label>
            <select id="ai-ogrenci-sinif" onchange="aiEvrakOgrenciYukle(this.value)" style="width:100%;padding:7px 10px;background:#0f0f23;border:1px solid #2d2d44;border-radius:7px;color:#e2e8f0;font-size:13px">
                <option value="">— Sınıf seç —</option>
            </select>
            <input type="hidden" id="ai-sinif">
        </div>
        <?php elseif (!empty($aiSiniflar)): ?>
        <div>
            <label>Sınıf</label>
            <select id="ai-sinif">
                <?php foreach ($aiSiniflar as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
        <div>
            <label>Sınıf</label>
            <input type="text" id="ai-sinif" placeholder="Örn: 9-A">
        </div>
        <?php endif; ?>
        <div>
            <label>Ders/Branş</label>
            <input type="text" id="ai-ders" value="<?= htmlspecialchars($ogretmenInfo['ders'] ?? '') ?>">
        </div>
        <?php if ($aiOgrenciSec): ?>
        <div>
        <div>
            <label>Öğrenci</label>
            <select id="ai-ogrenci-sec" onchange="document.getElementById('ai-extra').value=this.options[this.selectedIndex].text" style="width:100%;padding:7px 10px;background:#0f0f23;border:1px solid #2d2d44;border-radius:7px;color:#e2e8f0;font-size:13px">
                <option value="">— Önce sınıf seçin —</option>
            </select>
            <input type="hidden" id="ai-extra">
        </div>
        <?php elseif ($aiExtraLabel): ?>
        <div>
            <label><?= htmlspecialchars($aiExtraLabel) ?></label>
            <input type="text" id="ai-extra" placeholder="<?= htmlspecialchars($aiExtraLabel) ?>">
        </div>
        <?php endif; ?>
    </div>
    <div class="ai-prompt-row">
        <label>📝 Konu / Bağlam <small style="color:#4a5568">— yazarsanız o yönde üretirim, boş bırakırsanız kendim belirlerim</small></label>
        <?php
        $placeholders = [
            'zumre'        => 'Örn: Sınav başarısının düşük olduğu konular, telafi planı, ortak sınav tarihleri...',
            'veli_toplanti'=> 'Örn: Dönem sonu notları, devamsızlık sorunları, velilerin talepleri...',
            'bep'          => 'Örn: Öğrenci okuma güçlüğü yaşıyor, sosyal becerilerde destek gerekiyor...',
            'iyep_matematik'=> 'Örn: Kesirler ve ondalık sayılarda zorlanıyor, temel işlem hataları yapıyor...',
            'iyep_turkce'  => 'Örn: Okuduğunu anlamada güçlük, yazım kuralları eksiklikleri...',
            'maarif_rapor' => 'Örn: Bu ay bilim fuarı düzenlendi, öğrenci devamsızlıkları arttı...',
            'kulup'        => 'Örn: Kitap okuma alışkanlığı kazandırmak, tartışma becerileri geliştirmek...',
            'gunluk_plan'  => 'Örn: Denklemler konusu, öğrenciler bir önceki derste zorluk yaşadı...',
            'rehberlik'    => 'Örn: Öğrenci arkadaşlık ilişkilerinde sorun yaşıyor, motivasyon düşüklüğü...',
            'performans'   => 'Örn: Su döngüsü konusunda araştırma ve sunum görevi...',
            'surec'        => 'Örn: 3 haftalık proje süreci, bilim fuarı için deney tasarımı...',
            'aday_ogretmen'=> 'Örn: Sınıf yönetiminde gelişim gösteriyor, alan bilgisi güçlü...',
        ];
        $ph = $placeholders[$aiType] ?? 'İçeriği yönlendirmek için kısa bir açıklama yazın...';
        ?>
        <textarea id="ai-prompt" placeholder="<?= htmlspecialchars($ph) ?>"></textarea>
    </div>

    <div class="ai-evrak-btns">
        <button class="btn-ai-fill" id="ai-fill-btn" onclick="AiEvrak.fill()">🤖 AI ile Doldur</button>
        <button class="btn-word" id="ai-word-btn" onclick="AiEvrak.downloadWord()">📄 Word İndir</button>
        <span id="ai-fill-status"></span>
    </div>
    <div class="ai-preview-panel" id="ai-preview-panel"></div>
</div>

<script src="/ogretmen/assets/ai_evrak.js?v=<?= time() ?>"></script>
<script>
AiEvrak.init('<?= $aiType ?>');

<?php if ($aiOgrenciSec): ?>
// Sınıf listesini yükle
(async function() {
    const res  = await fetch('/api/ogrenci_listesi.php');
    const json = await res.json();
    if (!json.success) return;
    const siniflar = [...new Set(json.ogrenciler.map(o => o.sinif))].sort();
    const sel = document.getElementById('ai-ogrenci-sinif');
    siniflar.forEach(s => { const o = document.createElement('option'); o.value = o.textContent = s; sel.appendChild(o); });
})();

async function aiEvrakOgrenciYukle(sinif) {
    const sel = document.getElementById('ai-ogrenci-sec');
    sel.innerHTML = '<option value="">Yükleniyor...</option>';
    if (!sinif) { sel.innerHTML = '<option value="">— Önce sınıf seçin —</option>'; return; }
    // Aynı zamanda ai-sinif alanını da güncelle
    const sinifInput = document.getElementById('ai-sinif');
    if (sinifInput) sinifInput.value = sinif;
    const res  = await fetch('/api/ogrenci_listesi.php?sinif=' + encodeURIComponent(sinif));
    const json = await res.json();
    sel.innerHTML = '<option value="">— Öğrenci seçin —</option>';
    (json.ogrenciler || []).forEach(o => {
        const opt = document.createElement('option');
        opt.value = o.id;
        opt.textContent = o.ad;
        sel.appendChild(opt);
    });
}
<?php endif; ?>
</script>
