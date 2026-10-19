/**
 * Aibuke - AI Evrak Doldurma & Word İndirme Modülü
 * Her evrak sayfası bu modülü kullanır
 */

window.AiEvrak = {

    // Aktif doldurulan veri
    _data: null,
    _type: null,

    /**
     * AI ile doldur butonu başlatır
     * @param {string} type - evrak türü
     * @param {object} fieldMap - {alan_adi: 'form_field_id'} haritası
     */
    init(type, fieldMap) {
        this._type = type;
        this._fieldMap = fieldMap;
    },

    /**
     * AI'ya istek at, alanları doldur
     */
    async fill(extraInfo = '') {
        const btn    = document.getElementById('ai-fill-btn');
        const status = document.getElementById('ai-fill-status');
        const panel  = document.getElementById('ai-preview-panel');

        btn.disabled = true;
        btn.innerHTML = '<span class="ai-spin">⟳</span> Aibüke düşünüyor...';
        if (status) status.textContent = '';

        const formData = new FormData();
        formData.append('type',   this._type);
        formData.append('tarih',  document.getElementById('ai-tarih')?.value  || '');
        formData.append('sinif',  document.getElementById('ai-sinif')?.value  || '');
        formData.append('ders',   document.getElementById('ai-ders')?.value   || '');
        formData.append('extra',  extraInfo || document.getElementById('ai-extra')?.value || '');
        formData.append('prompt', document.getElementById('ai-prompt')?.value || '');

        try {
            const res  = await fetch('/api/ai_fill.php', { method: 'POST', body: formData });
            const json = await res.json();

            if (!json.success) {
                this._showError(btn, json.error || 'Bir hata oluştu');
                return;
            }

            if (!json.data) {
                this._showError(btn, 'Aibüke yanıt üretemedi, tekrar deneyin.');
                return;
            }
            this._data = json.data;
            this._renderPreview(json.data, panel);
            this._fillFields(json.data);

            btn.disabled = false;
            btn.innerHTML = '🤖 Yeniden Doldur';

            const wordBtn = document.getElementById('ai-word-btn');
            if (wordBtn) wordBtn.style.display = 'inline-flex';

        } catch (e) {
            this._showError(btn, 'Bağlantı hatası: ' + e.message);
        }
    },

    /**
     * Word olarak indir
     */
    async downloadWord() {
        if (!this._data) return;

        const btn = document.getElementById('ai-word-btn');
        btn.disabled = true;
        btn.innerHTML = '⟳ Hazırlanıyor...';

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/api/word_export.php';

        const t = document.createElement('input');
        t.type = 'hidden'; t.name = 'type'; t.value = this._type;
        form.appendChild(t);

        const d = document.createElement('input');
        d.type = 'hidden'; d.name = 'data'; d.value = JSON.stringify(this._data);
        form.appendChild(d);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = '📄 Word İndir';
        }, 2000);
    },

    _fillFields(data) {
        if (!this._fieldMap) return;
        for (const [key, elId] of Object.entries(this._fieldMap)) {
            const el  = document.getElementById(elId);
            const val = data[key];
            if (!el || val === undefined) continue;
            if (Array.isArray(val)) {
                el.value = val.join('\n');
            } else if (typeof val === 'object') {
                el.value = JSON.stringify(val, null, 2);
            } else {
                el.value = val;
            }
        }
    },

    _renderValue(v) {
        if (v === null || v === undefined) return '—';
        if (Array.isArray(v)) {
            const items = v.map((x, i) => {
                const inner = this._renderValue(x);
                return `<li>${i + 1}. ${inner}</li>`;
            }).join('');
            return `<ul style="margin:4px 0 4px 16px">${items}</ul>`;
        }
        if (typeof v === 'object') {
            return Object.entries(v)
                .map(([a, b]) => `<b>${a.replace(/_/g,' ')}:</b> ${this._renderValue(b)}`)
                .join('<br>');
        }
        return String(v);
    },

    _renderPreview(data, panel) {
        if (!panel || !data) return;
        let html = '<div class="ai-preview-inner">';
        for (const [k, v] of Object.entries(data)) {
            if (k === 'imza_blogu') continue;
            const label = k.replace(/_/g, ' ');
            const val = this._renderValue(v);
            html += `<div class="ai-preview-row"><span class="ai-preview-key">${label}</span><div class="ai-preview-val">${val}</div></div>`;
        }
        if (data.imza_blogu) {
            html += `<div class="ai-preview-imza">${data.imza_blogu.replace(/\n/g, '<br>')}</div>`;
        }
        html += '</div>';
        panel.innerHTML = html;
        panel.style.display = 'block';
    },

    _showError(btn, msg) {
        btn.disabled = false;
        btn.innerHTML = '🤖 AI ile Doldur';
        const status = document.getElementById('ai-fill-status');
        if (status) { status.textContent = '⚠ ' + msg; status.style.color = '#ef4444'; }
    }
};
