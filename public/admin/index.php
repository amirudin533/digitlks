<?php require __DIR__ . '/layout_top.php'; ?>
<div class="page-title">
    Dashboard
    <div style="display: flex; gap: 10px;">
        <a href="upload.php" class="btn btn-primary">+ Buat Latihan</a>
        <a href="prompt_generator_quickstart.php" class="btn btn-secondary" style="background: #667eea;" target="_blank">🤖 Generate Prompt (AI)</a>
    <a href="settings.php" class="btn btn-outline" style="border-color: var(--warning); color: var(--warning);">⚙️ Settings</a>
    </div>
</div>
</div>

<?php
// Siapkan List Kontak WA global
$waContacts = $config['whatsapp']['contacts'] ?? [];
if (empty($waContacts) && !empty($config['whatsapp']['child_number'])) {
    $waContacts[] = ['name' => 'Kirim WA', 'number' => $config['whatsapp']['child_number']];
}
?>

<div class="card">
    <h3 style="margin-bottom: 1.5rem; font-size: 1.125rem;">Daftar Latihan Soal</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Judul & Mapel</th>
                <th>Status</th>
                <th>Timer</th>
                <th>Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $files = $fileManager->listSoalFiles($adminUser);
            
            // Urutkan berdasarkan tanggal terbaru
            usort($files, function($a, $b) {
                $timeA = isset($a['metadata']['created_at']) ? strtotime($a['metadata']['created_at']) : 0;
                $timeB = isset($b['metadata']['created_at']) ? strtotime($b['metadata']['created_at']) : 0;
                return $timeB <=> $timeA;
            });

            // Pagination logic
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            if ($page < 1) $page = 1;
            $itemsPerPage = 10; // Jumlah item per halaman
            
            $totalItems = count($files);
            $totalPages = ceil($totalItems / $itemsPerPage);
            if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

            $offset = ($page - 1) * $itemsPerPage;
            $paginatedFiles = array_slice($files, $offset, $itemsPerPage);

            if (empty($paginatedFiles)): ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding: 3rem; color: var(--text-muted);">Belum ada soal.
                        Silakan buat latihan baru terlebih dahulu.</td>
                </tr>
            <?php else:
                foreach ($paginatedFiles as $file):
                    $meta = $file['metadata'];
                    $slug = $meta['slug'] ?? '';
                    $status = $meta['status'] ?? 'draft';
                    $isDraft = $status === 'draft';
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <strong>
                                    <?= htmlspecialchars($meta['judul'] ?? 'Tanpa Judul') ?>
                                </strong>
                                <button type="button" class="btn btn-outline" style="padding: 2px 6px; font-size: 14px; border: none; background: transparent;" onclick="quickRename('<?= urlencode($file['filename']) ?>', '<?= htmlspecialchars(addslashes($meta['judul'] ?? 'Tanpa Judul')) ?>')" title="Quick Rename">✏️</button>
                            </div>
                            <small style="color:var(--text-muted)">
                                <?= htmlspecialchars($meta['mata_pelajaran'] ?? '') ?> - Kelas
                                <?= htmlspecialchars($meta['kelas_target'] ?? '') ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($isDraft): ?>
                                <span class="badge badge-gray">Draft</span>
                            <?php elseif ($status === 'completed'): ?>
                                <span class="badge" style="background: var(--primary); color: white;">Sudah Dikerjakan</span>
                            <?php else: ?>
                                <span class="badge badge-success">Aktif</span><br>
                                <small style="display:inline-block; margin-top:4px;">PIN: <strong
                                        style="letter-spacing:1px; color:var(--text-main);">
                                        <?= htmlspecialchars($meta['pin'] ?? '') ?>
                                    </strong></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $timerVal = $meta['timer_menit'] ?? null;
                            if ($timerVal === null || $timerVal === '') {
                                echo '-';
                            } elseif ((int)$timerVal === 0) {
                                echo '<span style="color:#059669; font-weight:600;">⏾ Tanpa Batas</span>';
                            } else {
                                echo (int)$timerVal . ' Menit';
                            }
                            ?>
                        </td>
                        <td>
                            <?= isset($meta['created_at']) ? date('d M Y, H:i', strtotime($meta['created_at'])) : '-' ?>
                        </td>
                        <td style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                            <?php if ($isDraft): ?>
                                <a href="edit.php?id=<?= urlencode($file['filename']) ?>" class="btn btn-primary">Lengkapi</a>
                            <?php elseif ($status === 'completed'): ?>
                                <a href="result.php?id=<?= urlencode($file['filename']) ?>" class="btn"
                                    style="background-color: var(--success); color: white;" title="Lihat Nilai & Evaluasi Anak">📊
                                    Lihat Hasil</a>
                            <?php else: ?>
                                <a href="../s/index.php?slug=<?= urlencode($slug) ?>" target="_blank" class="btn btn-outline"
                                    title="Buka Halaman Soal">Buka Soal</a>
                                <a href="edit.php?id=<?= urlencode($file['filename']) ?>" class="btn btn-outline"
                                    title="Edit Soal">✎</a>
<?php 
if (!empty($waContacts)): 
    $quizUrl = $config['app']['url'] . '/s/index.php?slug=' . urlencode($slug);
    $pin = $meta['pin'] ?? '';
    $judul = $meta['judul'] ?? 'Latihan Soal';
    $message = "Halo! Ini link latihan soal untuk kamu:\n\n📚 " . $judul . "\n🔗 " . $quizUrl . "\n🔑 PIN: " . $pin . "\n\nSelamat belajar! 💪";
    
    if (count($waContacts) === 1): 
        $contact = $waContacts[0];
        $waUrl = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $contact['number']) . '?text=' . urlencode($message);
?>
<a href="<?= $waUrl ?>" target="_blank" class="btn btn-outline" style="color: #25D366; border-color: #25D366;" title="Kirim ke WhatsApp">
    📱 <?= htmlspecialchars($contact['name'] ?: 'Kirim WA') ?>
</a>
<?php else: ?>
    <button type="button" class="btn btn-outline" style="color: #25D366; border-color: #25D366; padding: 0.5rem 0.75rem;" onclick="openWaModal(this.getAttribute('data-msg'))" data-msg="<?= htmlspecialchars($message) ?>">📱 Pilih Kontak WA ▾</button>
<?php endif; endif; ?>
                            <?php endif; ?>
                            <?php $csrfT = htmlspecialchars(csrfToken()); ?>
                            <a href="clone.php?id=<?= urlencode($file['filename']) ?>&_token=<?= $csrfT ?>" class="btn btn-outline" style="background:#EEF2FF; color:var(--primary);"
                                title="Clone Latihan">📋 Clone</a>
                            <a href="delete.php?id=<?= urlencode($file['filename']) ?>&_token=<?= $csrfT ?>" class="btn btn-danger"
                                onclick="return confirm('Yakin ingin menghapus soal ini secara permanen?')"
                                title="Hapus Latihan">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if (isset($totalPages) && $totalPages > 1): ?>
<div style="display: flex; justify-content: center; align-items: center; margin-top: 1rem; margin-bottom: 2rem; gap: 0.5rem;">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>" class="btn btn-outline" style="padding: 0.5rem 1rem;">← Prev</a>
    <?php else: ?>
        <span class="btn btn-outline" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">← Prev</span>
    <?php endif; ?>
    
    <span style="font-weight: 600; padding: 0 1rem; color: var(--text-main);">
        Halaman <?= $page ?> dari <?= $totalPages ?>
    </span>
    
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>" class="btn btn-outline" style="padding: 0.5rem 1rem;">Next →</a>
    <?php else: ?>
        <span class="btn btn-outline" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">Next →</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Modal Kirim WA -->
<div id="waModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; width:90%; max-width:400px; border-radius:8px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.15); display:flex; flex-direction:column; max-height:80vh;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0; font-size:1.125rem;">Pilih Kontak WA</h3>
            <button onclick="closeWaModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; line-height:1;">&times;</button>
        </div>
        <input type="text" id="waSearch" placeholder="🔍 Cari nama kontak..." style="width:100%; padding:0.5rem 0.75rem; margin-bottom:10px; border:1px solid var(--border); border-radius:var(--radius-sm); box-sizing:border-box;">
        
        <div id="waList" style="flex:1; overflow-y:auto; margin-bottom:10px;">
            <!-- Render list here -->
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
            <button id="waPrev" class="btn btn-outline" style="padding:0.25rem 0.5rem; font-size:0.75rem;">← Prev</button>
            <span id="waPageInfo" style="font-size:0.75rem; color:var(--text-muted);"></span>
            <button id="waNext" class="btn btn-outline" style="padding:0.25rem 0.5rem; font-size:0.75rem;">Next →</button>
        </div>
    </div>
</div>

<script>
const waContacts = <?= json_encode($waContacts) ?>;
let currentWaMessage = "";
let currentWaPage = 1;
const itemsPerPage = 5;
let filteredContacts = waContacts;

function openWaModal(message) {
    currentWaMessage = message;
    document.getElementById('waModal').style.display = 'flex';
    document.getElementById('waSearch').value = '';
    filteredContacts = waContacts;
    currentWaPage = 1;
    renderWaList();
}

function closeWaModal() {
    document.getElementById('waModal').style.display = 'none';
}

function renderWaList() {
    const listDiv = document.getElementById('waList');
    listDiv.innerHTML = '';
    
    const maxPage = Math.ceil(filteredContacts.length / itemsPerPage) || 1;
    if (currentWaPage > maxPage) currentWaPage = maxPage;
    
    const startIndex = (currentWaPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageContacts = filteredContacts.slice(startIndex, endIndex);
    
    if (pageContacts.length === 0) {
        listDiv.innerHTML = '<div style="text-align:center; color:var(--text-muted); padding:1rem;">Kontak tidak ditemukan</div>';
    }
    
    pageContacts.forEach(c => {
        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.justifyContent = 'space-between';
        row.style.alignItems = 'center';
        row.style.padding = '0.5rem 0';
        row.style.borderBottom = '1px solid var(--border)';
        
        const leftDiv = document.createElement('div');
        
        const nameDiv = document.createElement('div');
        nameDiv.style.fontWeight = '600';
        nameDiv.style.fontSize = '0.875rem';
        nameDiv.textContent = c.name;
        
        const telpDiv = document.createElement('div');
        telpDiv.style.fontSize = '0.75rem';
        telpDiv.style.color = 'var(--text-muted)';
        telpDiv.style.marginTop = '2px';
        telpDiv.textContent = c.number;

        leftDiv.appendChild(nameDiv);
        leftDiv.appendChild(telpDiv);
        
        const btnWrapper = document.createElement('div');
        
        const btn = document.createElement('a');
        const num = c.number.replace(/[^0-9]/g, '');
        btn.href = 'https://wa.me/' + num + '?text=' + encodeURIComponent(currentWaMessage);
        btn.target = '_blank';
        btn.className = 'btn';
        btn.style.backgroundColor = '#25D366';
        btn.style.color = '#fff';
        btn.style.padding = '0.25rem 0.75rem';
        btn.style.fontSize = '0.75rem';
        btn.style.textDecoration = 'none';
        btn.innerHTML = 'Kirim ↗';
        
        btnWrapper.appendChild(btn);
        
        row.appendChild(leftDiv);
        row.appendChild(btnWrapper);
        
        listDiv.appendChild(row);
    });
    
    document.getElementById('waPageInfo').textContent = `Hal ${currentWaPage} / ${maxPage}`;
    document.getElementById('waPrev').disabled = currentWaPage === 1;
    document.getElementById('waNext').disabled = currentWaPage === maxPage;
    document.getElementById('waPrev').style.opacity = currentWaPage === 1 ? '0.5' : '1';
    document.getElementById('waNext').style.opacity = currentWaPage === maxPage ? '0.5' : '1';
}

document.getElementById('waSearch').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    filteredContacts = waContacts.filter(c => c.name.toLowerCase().includes(term) || c.number.includes(term));
    currentWaPage = 1;
    renderWaList();
});

document.getElementById('waPrev').addEventListener('click', () => {
    if (currentWaPage > 1) {
        currentWaPage--;
        renderWaList();
    }
});

document.getElementById('waNext').addEventListener('click', () => {
    const maxPage = Math.ceil(filteredContacts.length / itemsPerPage);
    if (currentWaPage < maxPage) {
        currentWaPage++;
        renderWaList();
    }
});

function quickRename(id, currentTitle) {
    var newTitle = prompt("Masukkan nama baru untuk soal ini:", currentTitle);
    if (newTitle !== null && newTitle.trim() !== "" && newTitle !== currentTitle) {
        document.getElementById('rename_id').value = id;
        document.getElementById('rename_title').value = newTitle.trim();
        document.getElementById('formQuickRename').submit();
    }
}
</script>

<form id="formQuickRename" method="post" action="rename.php" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="rename_id">
    <input type="hidden" name="new_title" id="rename_title">
</form>

<?php require __DIR__ . '/layout_bottom.php'; ?>