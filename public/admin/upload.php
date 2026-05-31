<?php
require __DIR__ . '/layout_top.php';

$error = '';

/** Helper untuk memproses Paste Text menjadi soal */
function processPasteTextAndRedirect($text, $pasteParser, $fileManager, $adminUser)
{
    $metadata = [
        'judul' => $_POST['paste_title'] ?? 'Latihan Soal (Paste)',
        'mata_pelajaran' => $_POST['paste_mapel'] ?? 'Umum',
        'kelas_target' => $_POST['paste_kelas'] ?? '-',
    ];

    $parsedData = $pasteParser->parse($text, $metadata);

    // Validasi hasil parsing
    $issues = $pasteParser->validateResult($parsedData);
    if (!empty($issues)) {
        return "<strong>❌ Format tidak valid:</strong><ul style='margin: 0.5rem 0; padding-left: 1.5rem;'>"
            . implode('', array_map(fn($i) => "<li>$i</li>", $issues))
            . "</ul>";
    }

    if (!empty($parsedData['soal'])) {
        $parsedData['metadata']['status'] = 'draft';
        $parsedData['metadata']['created_at'] = gmdate('Y-m-d\TH:i:s\Z');

        $filename = 'batch_' . time() . '_' . rand(100, 999) . '.json';
        $saved = $fileManager->writeJsonAndSync("accounts/{$adminUser}/soal/{$filename}", $parsedData);

        if ($saved) {
            echo "<script>window.location.href = 'edit.php?id=" . urlencode($filename) . "';</script>";
            exit;
        } else {
            return "Terjadi kesalahan internal saat menulis file JSON di storage.";
        }
    } else {
        return "Gagal memparse soal dari text. Periksa format teks Anda.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) die('CSRF token tidak valid.');
    $method = $_POST['submit_type'] ?? '';

    // Metode: Paste Text Plain
    if ($method === 'paste' && !empty($_POST['paste_text'])) {
        $text = $_POST['paste_text'];

        // Load PasteTextParser jika belum ada
        if (!class_exists('\Core\PasteTextParser')) {
            require __DIR__ . '/../../src/Core/PasteTextParser.php';
        }
        $pasteParser = new \Core\PasteTextParser();

        $error = processPasteTextAndRedirect($text, $pasteParser, $fileManager, $adminUser);
    }
}
?>
<div class="page-title">
    Tambah Latihan Soal
</div>

<?php if ($error): ?>
    <div
        style="background-color: #FEE2E2; color: var(--danger); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.875rem;">
        <?= $error ?>
    </div>
<?php endif; ?>

<div style="max-width: 800px; margin: 0 auto; padding-top: 1rem;">
    <!-- Bagian Paste Text -->
    <div class="card" style="display:flex; flex-direction:column; border-top: 4px solid #10B981;">
        <h3 style="margin-bottom: 1rem; color:var(--text-main);">✏️ Paste Text</h3>
        <p style="font-size:0.875rem; color:var(--text-muted); margin-bottom:1.5rem;">Salin & paste seluruh teks soal beserta jawabannya ke kotak di bawah. Sistem akan secara otomatis membacanya.</p>

        <form method="POST" style="margin-top:auto;">
            <?= csrfField() ?>
            <input type="hidden" name="submit_type" value="paste">

            <label class="form-label" style="font-weight:600; margin-bottom:0.5rem;">📚 Judul Soal:</label>
            <input type="text" name="paste_title" class="form-input" placeholder="Contoh: Latihan Matematika Kelas 2"
                style="margin-bottom:1rem; padding: 0.5rem;" required>

            <label class="form-label" style="font-weight:600; margin-bottom:0.5rem;">📖 Mata Pelajaran:</label>
            <select name="paste_mapel" class="form-input" style="margin-bottom:1rem; padding: 0.5rem;">
                <option value="Umum">Umum</option>
                <option value="Matematika">Matematika</option>
                <option value="Bahasa Indonesia">Bahasa Indonesia</option>
                <option value="IPA">IPA</option>
                <option value="IPS">IPS</option>
                <option value="Bahasa Inggris">Bahasa Inggris</option>
                <option value="PKn">PKn</option>
            </select>

            <label class="form-label" style="font-weight:600; margin-bottom:0.5rem;">🎓 Kelas Target:</label>
            <input type="text" name="paste_kelas" class="form-input" placeholder="Contoh: Kelas 2 SD"
                style="margin-bottom:1rem; padding: 0.5rem;">

            <label class="form-label" style="font-weight:600; margin-bottom:0.5rem;">📝 Paste Soal & Jawaban:</label>
            <textarea name="paste_text" class="form-input"
                placeholder="Contoh:&#10;1. Pertanyaan soal?&#10;A. Opsi A&#10;B. Opsi B&#10;C. Opsi C&#10;D. Opsi D&#10;Jawaban: A&#10;&#10;2. Pertanyaan kedua?&#10;..."
                required
                style="margin-bottom:0.75rem; padding: 0.75rem; min-height: 250px; font-family: monospace; font-size: 0.85rem; resize: vertical;"></textarea>

            <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                <button type="button" class="btn" onclick="clearTextarea()"
                    style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background-color: #EF4444; color: white; border: none; cursor: pointer; border-radius: 4px;">
                    🗑️ Clear
                </button>
            </div>

            <button type="submit" class="btn btn-success"
                style="width: 100%; justify-content:center; padding: 0.875rem; font-size: 1rem; background-color: #10B981; color: white; border: none; cursor: pointer;">Parse
                & Buat 📝</button>
        </form>
    </div>
</div>

<script>
    function clearTextarea() {
        if (confirm('Yakin ingin menghapus semua teks?')) {
            document.querySelector('textarea[name="paste_text"]').value = '';
        }
    }
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>