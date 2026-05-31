<?php require __DIR__ . '/layout_top.php';

$groqEnabled = $config['groq']['enabled'] ?? false;
$groqModel   = $config['groq']['model'] ?? 'mixtral-8x7b-32768';

// ── Handle save final result ──
$saveResult = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $judul   = trim($_POST['judul'] ?? '');
    $mapel   = trim($_POST['mapel'] ?? '');
    $kelas   = trim($_POST['kelas'] ?? '');
    $soalRaw = trim($_POST['soal_json'] ?? '');

    if (empty($judul) || empty($soalRaw)) {
        $saveResult = 'Data tidak lengkap.';
    } else {
        $soalArr = json_decode($soalRaw, true);
        if (!is_array($soalArr) || empty($soalArr)) {
            $saveResult = 'Format soal tidak valid.';
        } else {
            $filename = uniqid('soal_') . '.json';
            $path = "accounts/{$adminUser}/soal/{$filename}";

            $timerOptions = $config['timer_options'] ?? [];
            $timerMenit   = (int)($timerOptions[array_key_last($timerOptions)] ?? 20);

            $data = [
                'metadata' => [
                    'slug'           => $security->generateSlug($mapel),
                    'judul'          => $judul,
                    'mata_pelajaran' => $mapel,
                    'kelas_target'   => $kelas,
                    'status'         => 'active',
                    'pin'            => $security->generatePin(),
                    'timer_menit'    => $timerMenit,
                    'created_at'     => date('Y-m-d H:i:s'),
                ],
                'soal' => $soalArr,
            ];

            $ok = $fileManager->writeJsonAndSync($path, $data);
            $saveResult = $ok
                ? 'SUCCESS|' . $filename
                : 'Gagal menyimpan file.';
        }
    }
}
?>

<div class="page-title">
    🤖 AI Generator Soal
    <a href="index.php" class="btn btn-outline">← Dashboard</a>
</div>

<?php if ($saveResult): ?>
<?php if (str_starts_with($saveResult, 'SUCCESS|')): ?>
<div class="card" style="border-left:4px solid var(--success); background:#F0FDF4; text-align:center; padding:2rem;">
    <h3 style="color:var(--success); margin-bottom:1rem;">✅ Soal Berhasil Dibuat!</h3>
    <p style="margin-bottom:1rem; color:var(--text-muted);">
        File: <code><?= htmlspecialchars(substr($saveResult, 8)) ?></code>
    </p>
    <div style="display:flex; gap:.75rem; justify-content:center;">
        <a href="edit.php?id=<?= urlencode(substr($saveResult, 8)) ?>" class="btn btn-primary">Edit Soal</a>
        <a href="index.php" class="btn btn-outline">Kembali ke Dashboard</a>
    </div>
</div>
<?php else: ?>
<div class="card" style="border-left:4px solid var(--danger); background:#FEF2F2;">
    ❌ <?= htmlspecialchars($saveResult) ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (!$groqEnabled): ?>
<div class="card" style="border-left:4px solid var(--warning); background:#FFFBEB; text-align:center; padding:2rem;">
    <h3 style="color:var(--warning); margin-bottom:.75rem;">⚠️ Groq API Belum Dikonfigurasi</h3>
    <p style="color:var(--text-muted); margin-bottom:1rem;">
        Tambahkan <code>GROQ_API_KEY=your_key</code> di file <code>.env</code> untuk mengaktifkan fitur ini.
    </p>
    <a href="settings.php" class="btn btn-outline">Buka Settings</a>
</div>
<?php else: ?>

<div class="card">
    <form id="gen-form" method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="soal_json" id="soal_json" value="">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label class="form-label">Judul Soal</label>
                <input type="text" name="judul" id="judul" class="form-input"
                    placeholder="Contoh: Matematika - Bilangan Bulat" required>
            </div>
            <div class="form-group">
                <label class="form-label">Mata Pelajaran</label>
                <input type="text" name="mapel" id="mapel" class="form-input"
                    placeholder="Contoh: Matematika" required>
            </div>
            <div class="form-group">
                <label class="form-label">Kelas Target</label>
                <input type="text" name="kelas" id="kelas" class="form-input"
                    placeholder="Contoh: 5 SD / 7 SMP" required>
            </div>
            <div class="form-group">
                <label class="form-label">Jumlah Soal</label>
                <input type="number" name="jumlah" id="jumlah" class="form-input"
                    value="10" min="1" max="100" required>
            </div>
            <div class="form-group">
                <label class="form-label">Tingkat Kesulitan</label>
                <select name="difficulty" id="difficulty" class="form-select">
                    <option value="mudah">Mudah</option>
                    <option value="sedang" selected>Sedang</option>
                    <option value="sulit">Sulit</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Model AI</label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($groqModel) ?>" disabled>
                <small>Model dari konfigurasi .env</small>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Instruksi Tambahan (opsional)</label>
            <textarea name="instructions" id="instructions" class="form-textarea" rows="3"
                placeholder="Contoh: Fokus pada bilangan bulat, sertakan soal cerita"></textarea>
        </div>

        <div style="display:flex; gap:.75rem; align-items:center; margin-top:1rem;">
            <button type="submit" id="generate-btn" class="btn btn-primary" style="padding:.75rem 1.5rem;">
                🚀 Generate Soal
            </button>
            <span id="progress-text" style="color:var(--text-muted); font-size:.875rem;"></span>
        </div>
    </form>
</div>

<!-- Progress -->
<div id="progress-card" class="card" style="display:none;">
    <h4 style="margin-bottom:.75rem;">Progress Generate</h4>
    <div style="background:var(--border); border-radius:99px; height:24px; overflow:hidden; position:relative;">
        <div id="progress-bar" style="width:0%; height:100%; background:var(--primary); border-radius:99px; transition:width .3s ease;"></div>
        <span id="progress-label" style="position:absolute; top:0; left:0; right:0; text-align:center; line-height:24px; font-size:.78rem; font-weight:600; color:#fff;"></span>
    </div>
    <div id="progress-detail" style="margin-top:.5rem; font-size:.85rem; color:var(--text-muted);"></div>
</div>

<!-- Preview -->
<div id="preview-card" class="card" style="display:none;">
    <h4 style="margin-bottom:.75rem;">Preview Soal</h4>
    <div id="preview-list"></div>
    <div style="margin-top:1rem; display:flex; gap:.75rem;">
        <button type="button" class="btn btn-primary" onclick="saveSoal()">💾 Simpan Soal</button>
        <button type="button" class="btn btn-outline" onclick="location.reload()">Batal</button>
    </div>
</div>

<script>
var CSRF_TOKEN = '<?= csrfToken() ?>';
const BATCH_SIZE = 10;
let allQuestions = [];
let totalBatches = 0;
let completedBatches = 0;

document.getElementById('gen-form').addEventListener('submit', function(e) {
    e.preventDefault();

    allQuestions = [];
    completedBatches = 0;
    var jumlah = parseInt(document.getElementById('jumlah').value) || 10;
    totalBatches = Math.ceil(jumlah / BATCH_SIZE);

    document.getElementById('generate-btn').disabled = true;
    document.getElementById('generate-btn').textContent = '⏳ Menggenerate...';
    document.getElementById('progress-card').style.display = 'block';
    document.getElementById('preview-card').style.display = 'none';
    document.getElementById('progress-text').textContent = 'Memproses batch 1 dari ' + totalBatches + '...';

    processBatch(0, jumlah);
});

function processBatch(batchIndex, totalDesired) {
    var remaining = totalDesired - (batchIndex * BATCH_SIZE);
    var batchSize = Math.min(BATCH_SIZE, remaining);

    if (batchSize <= 0) {
        finishGeneration();
        return;
    }

    updateProgress(batchIndex);

    fetch('ai_generate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify({
            batch_size: batchSize,
            mapel: document.getElementById('mapel').value,
            kelas: document.getElementById('kelas').value,
            difficulty: document.getElementById('difficulty').value,
            instructions: document.getElementById('instructions').value
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.ok) {
            alert('Gagal batch ' + (batchIndex + 1) + ': ' + d.error);
            resetForm();
            return;
        }
        allQuestions = allQuestions.concat(d.questions);
        completedBatches++;
        processBatch(batchIndex + 1, totalDesired);
    })
    .catch(function(e) {
        alert('Network error: ' + e.message);
        resetForm();
    });
}

function finishGeneration() {
    updateProgress(totalBatches);
    document.getElementById('progress-text').textContent = 'Selesai! ' + allQuestions.length + ' soal tergenerate.';

    // Tampilkan preview
    var preview = document.getElementById('preview-list');
    preview.innerHTML = '';
    allQuestions.slice(0, 5).forEach(function(q, i) {
        var div = document.createElement('div');
        div.style.cssText = 'padding:.75rem 0; border-bottom:1px solid var(--border);';
        div.innerHTML = '<strong>' + (i + 1) + '. ' + escapeHtml(q.pertanyaan) + '</strong><br>' +
            '<div style="margin-left:1rem; font-size:.85rem;">' +
            (q.pilihan || []).map(function(p) { return escapeHtml(p); }).join('<br>') +
            '</div>' +
            '<span style="color:var(--success); font-size:.85rem;">✓ Jawaban: ' + (q.jawaban ? q.jawaban.charAt(0).toUpperCase() : '?') + '</span>';
        preview.appendChild(div);
    });

    if (allQuestions.length > 5) {
        var more = document.createElement('p');
        more.style.cssText = 'color:var(--text-muted); font-size:.85rem; margin-top:.5rem;';
        more.textContent = '... dan ' + (allQuestions.length - 5) + ' soal lainnya.';
        preview.appendChild(more);
    }

    // Isi hidden field
    document.getElementById('soal_json').value = JSON.stringify(allQuestions);
    document.getElementById('preview-card').style.display = 'block';
}

function saveSoal() {
    document.getElementById('gen-form').submit();
}

function updateProgress(batchIndex) {
    var pct = totalBatches > 0 ? Math.round((batchIndex / totalBatches) * 100) : 0;
    document.getElementById('progress-bar').style.width = pct + '%';
    document.getElementById('progress-label').textContent = pct + '%';
    document.getElementById('progress-detail').textContent =
        'Batch ' + (batchIndex + 1) + ' dari ' + totalBatches +
        ' (' + allQuestions.length + ' soal terkumpul)';
}

function resetForm() {
    document.getElementById('generate-btn').disabled = false;
    document.getElementById('generate-btn').textContent = '🚀 Generate Soal';
    document.getElementById('progress-text').textContent = '';
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str || ''));
    return div.innerHTML;
}
</script>

<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>
