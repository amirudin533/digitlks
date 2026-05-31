<?php
/**
 * IMPORT QUESTIONS FORM
 * UI untuk admin mengimport questions dari file HTML atau URL
 * 
 * Features:
 * - Toggle antara file upload atau URL input
 * - Preview metadata (judul, mata_pelajaran, kelas_target)
 * - Real-time validation
 * - Status feedback
 */

session_start();

// Check if admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$pageTitle = "Import Questions";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Cici Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .content {
            padding: 30px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
        }

        .tab-btn {
            flex: 1;
            padding: 15px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #999;
            transition: all 0.3s;
        }

        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        input[type="text"],
        input[type="url"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="url"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .file-upload {
            position: relative;
            border: 2px dashed #667eea;
            border-radius: 5px;
            padding: 30px;
            text-align: center;
            background: rgba(102, 126, 234, 0.05);
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload:hover {
            border-color: #764ba2;
            background: rgba(102, 126, 234, 0.1);
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-upload-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .file-upload-text {
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
        }

        .file-upload-hint {
            font-size: 12px;
            color: #999;
        }

        .file-name {
            margin-top: 15px;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 5px;
            font-size: 14px;
            color: #666;
            display: none;
        }

        .file-name.show {
            display: block;
        }

        .metadata-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .metadata-grid.full {
            grid-template-columns: 1fr;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        button {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-reset {
            background: #f0f0f0;
            color: #666;
            flex: 1;
        }

        .btn-reset:hover {
            background: #e0e0e0;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
            font-size: 14px;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .loader {
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            background: rgba(0, 0, 0, 0.95) !important;
            padding: 40px !important;
            border-radius: 10px !important;
            z-index: 99999 !important;
            text-align: center !important;
            color: white !important;
            min-width: 300px !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5) !important;
            display: none !important;
            font-family: Arial, sans-serif !important;
        }

        .loader.show {
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
        }

        .spinner {
            border: 5px solid rgba(255, 255, 255, 0.3) !important;
            border-top: 5px solid #4CAF50 !important;
            border-radius: 50% !important;
            width: 60px !important;
            height: 60px !important;
            animation: spin 1s linear infinite !important;
            margin: 0 auto 20px !important;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .preview {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            display: none;
        }

        .preview.show {
            display: block;
        }

        .preview-item {
            margin-bottom: 10px;
            font-size: 14px;
        }

        .preview-label {
            font-weight: 600;
            color: #667eea;
        }

        .preview-value {
            color: #666;
            margin-left: 10px;
        }

        .hint-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Import Questions</h1>
            <p>Tambahkan soal dari file HTML atau URL</p>
        </div>

        <div class="content">
            <!-- Status Messages - ALWAYS VISIBLE -->
            <div id="alert" class="alert" style="margin-bottom: 20px; min-height: 50px; display: flex; align-items: center; padding: 15px; border-radius: 5px; background: #f0f0f0;"></div>
            
            <!-- Loader - FIXED POSITIONING, ULTRA-HIGH Z-INDEX -->
            <div id="loader" class="loader">
                <div class="spinner"></div>
                <div style="margin-top: 20px;">
                    <p id="loaderText" style="font-size: 16px; color: white;">Sedang memproses...</p>
                    <p id="loaderProgress" style="font-size: 12px; opacity: 0.7; margin-top: 10px;"></p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" data-tab="file-upload">📁 Upload File</button>
                <button class="tab-btn" data-tab="url-import">🔗 Import dari URL</button>
            </div>

            <form id="importForm">
                <!-- File Upload Tab -->
                <div id="file-upload" class="tab-content active">
                    <div class="form-group">
                        <label>Pilih File HTML</label>
                        <div class="file-upload" onclick="document.getElementById('fileInput').click()">
                            <div class="file-upload-icon">📄</div>
                            <div class="file-upload-text">Klik atau drag file HTML ke sini</div>
                            <div class="file-upload-hint">Maksimal 10MB, Format: HTML</div>
                            <input type="file" id="fileInput" name="file" accept=".html,.htm" />
                        </div>
                        <div id="fileName" class="file-name"></div>
                    </div>
                    <input type="hidden" name="type" value="file" />
                </div>

                <!-- URL Import Tab -->
                <div id="url-import" class="tab-content">
                    <div class="form-group">
                        <label>URL Sumber (Cici/Dola AI)</label>
                        <input 
                            type="url" 
                            id="urlInput" 
                            name="url" 
                            placeholder="https://cici.ai/quiz/..." 
                            required
                        />
                        <div class="hint-text">Masukkan URL lengkap dari Cici atau Dola AI</div>
                    </div>
                    <input type="hidden" name="type" value="url" />
                </div>

                <!-- Metadata -->
                <div style="margin-top: 30px; padding-top: 30px; border-top: 2px solid #f0f0f0;">
                    <h3 style="margin-bottom: 20px; font-size: 16px; color: #333;">📋 Metadata (Opsional)</h3>
                    
                    <div class="metadata-grid">
                        <div class="form-group">
                            <label>Mata Pelajaran</label>
                            <input 
                                type="text" 
                                name="mata_pelajaran" 
                                placeholder="Contoh: Matematika"
                            />
                        </div>
                        <div class="form-group">
                            <label>Kelas Target</label>
                            <input 
                                type="text" 
                                name="kelas_target" 
                                placeholder="Contoh: X, XI, XII"
                            />
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Judul Soal (Opsional)</label>
                        <input 
                            type="text" 
                            name="judul" 
                            placeholder="Judul custom untuk soal ini"
                        />
                    </div>
                </div>

                <!-- Preview -->
                <div id="preview" class="preview"></div>

                <!-- Action Buttons -->
                <div class="button-group">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        📤 Import Soal
                    </button>
                    <button type="reset" class="btn-reset" id="resetBtn">
                        🔄 Bersihkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const form = document.getElementById('importForm');
        const alert = document.getElementById('alert');
        const loader = document.getElementById('loader');
        const tabs = document.querySelectorAll('.tab-btn');
        const fileInput = document.getElementById('fileInput');
        const urlInput = document.getElementById('urlInput');
        const fileUploadArea = document.querySelector('.file-upload');

        // Tab switching
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Update active tab
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                // Show/hide content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(tab.dataset.tab).classList.add('active');

                // Update form type
                const type = tab.dataset.tab === 'file-upload' ? 'file' : 'url';
                document.querySelector('input[name="type"]').value = type;

                // Update required fields
                fileInput.required = (type === 'file');
                urlInput.required = (type === 'url');
            });
        });

        // File upload handling
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                updateFileName(file.name, file.size);
            }
        });

        // Drag and drop
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.style.background = 'rgba(102, 126, 234, 0.15)';
        });

        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.style.background = 'rgba(102, 126, 234, 0.05)';
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.style.background = 'rgba(102, 126, 234, 0.05)';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileName(files[0].name, files[0].size);
            }
        });

        function updateFileName(name, size) {
            const fileNameDiv = document.getElementById('fileName');
            const sizeMB = (size / 1024 / 1024).toFixed(2);
            fileNameDiv.innerHTML = `✓ ${name} (${sizeMB}MB)`;
            fileNameDiv.classList.add('show');
        }

        // Form submission with comprehensive error handling
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(form);
            const type = formData.get('type');
            let content = null;
            let filename = null;
            let processStart = Date.now();

            try {
                hideAlert();
                showLoader('⏳ Validasi data...');
                console.log('🔵 Import started:', { type, timestamp: new Date().toISOString() });

                // ================================================================
                // 1. VALIDATE & PREPARE DATA
                // ================================================================

                if (type === 'file') {
                    const file = fileInput.files[0];
                    if (!file) throw new Error('Pilih file terlebih dahulu');

                    // Check file size
                    const maxSize = 10 * 1024 * 1024; // 10MB
                    if (file.size > maxSize) {
                        throw new Error('File terlalu besar. Maksimal 10MB.');
                    }

                    // Check file type
                    if (!['text/html', 'text/plain'].includes(file.type) && !file.name.endsWith('.html')) {
                        throw new Error('File harus berformat HTML');
                    }

                    showLoader('📖 Membaca file...');
                    console.log('📄 File selected:', file.name, formatFileSize(file.size));

                    // Read file as base64
                    const reader = new FileReader();
                    await new Promise((resolve, reject) => {
                        reader.onload = () => {
                            console.log('✓ File loaded');
                            resolve();
                        };
                        reader.onerror = () => {
                            console.error('✗ File read error');
                            reject(new Error('Gagal membaca file. File mungkin rusak.'));
                        };
                        reader.onprogress = (e) => {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            console.log('📊 Reading:', percent + '%');
                        };
                        reader.readAsArrayBuffer(file);
                    });

                    try {
                        const arrayBuffer = reader.result;
                        const bytes = new Uint8Array(arrayBuffer);
                        content = btoa(String.fromCharCode.apply(null, bytes));
                        filename = file.name;
                        showLoader(`✓ File siap (${formatFileSize(file.size)})...`);
                        console.log('✓ Base64 encoded');
                    } catch (e) {
                        console.error('✗ Encoding error:', e.message);
                        throw new Error('Gagal encode file: ' + e.message);
                    }
                } else {
                    const url = urlInput.value.trim();
                    if (!url) throw new Error('Masukkan URL');
                    
                    // Validate URL format
                    try {
                        new URL(url);
                        console.log('✓ URL valid:', url.substring(0, 50));
                    } catch (e) {
                        console.error('✗ Invalid URL:', url);
                        throw new Error('Format URL tidak valid. Contoh: https://cici.ai/quiz/abc123');
                    }
                    
                    content = url;
                    showLoader('🔗 Validasi URL...');
                }

                // ================================================================
                // 2. BUILD PAYLOAD
                // ================================================================

                const payload = {
                    type: type,
                    content: content,
                    filename: filename,
                    meta: {
                        mata_pelajaran: formData.get('mata_pelajaran') || undefined,
                        kelas_target: formData.get('kelas_target') || undefined,
                        judul: formData.get('judul') || undefined
                    }
                };

                showLoader('📤 Mengirim ke server...');
                console.log('📦 Payload size:', (JSON.stringify(payload).length / 1024).toFixed(2) + 'KB');

                // ================================================================
                // 3. SEND REQUEST WITH TIMEOUT
                // ================================================================

                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 120000); // 2 minutes

                let response;
                try {
                    console.log('🚀 Fetch request started');
                    response = await fetch('/public/api/import-questions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || '')
                        },
                        body: JSON.stringify(payload),
                        signal: controller.signal
                    });
                    clearTimeout(timeoutId);
                } catch (fetchError) {
                    clearTimeout(timeoutId);
                    console.error('❌ Fetch error:', fetchError);
                    if (fetchError.name === 'AbortError') {
                        throw new Error('Request timeout (120 detik). Server tidak merespons.');
                    }
                    throw new Error('Network error: ' + fetchError.message);
                }

                showLoader('⚙️ Memproses respons...');

                // Check HTTP status
                console.log('📍 HTTP Status:', response.status, response.statusText);
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('❌ HTTP Error Response:', {
                        status: response.status,
                        statusText: response.statusText,
                        body: errorText.substring(0, 200)
                    });

                    if (response.status === 404) {
                        throw new Error('❌ Endpoint API tidak ditemukan (404). Cek path: /public/api/import-questions.php');
                    } else if (response.status === 405) {
                        throw new Error('❌ Method tidak didukung (405). API hanya menerima POST request.');
                    } else if (response.status === 500) {
                        throw new Error('❌ Server error (500). Cek file log di /storage/logs/import-errors.log');
                    } else {
                        throw new Error(`❌ HTTP Error ${response.status}: ${response.statusText}\n\n${errorText}`);
                    }
                }

                // Parse JSON response
                let result;
                try {
                    result = await response.json();
                    console.log('✓ JSON Response:', result);
                } catch (jsonError) {
                    console.error('❌ JSON Parse Error:', jsonError);
                    const respText = await response.text();
                    throw new Error('Respons dari server bukan JSON valid:\n\n' + respText.substring(0, 300));
                }

                // ================================================================
                // 4. HANDLE RESPONSE
                // ================================================================

                if (!result) {
                    throw new Error('Respons kosong dari server');
                }

                if (result.success) {
                    const elapsed = ((Date.now() - processStart) / 1000).toFixed(1);
                    const successMsg = `✓ ${result.message} (${elapsed}s)`;
                    console.log('🎉 SUCCESS:', successMsg);
                    showAlert(successMsg, 'success');
                    showPreview(result);
                    
                    // Reset form after 3 seconds
                    setTimeout(() => {
                        form.reset();
                        document.getElementById('fileName').classList.remove('show');
                        document.getElementById('preview').classList.remove('show');
                    }, 3000);
                } else {
                    const errorMsg = result.message || 'Unknown error';
                    const errorCode = result.error_code || '';
                    console.error('❌ API Error:', errorMsg, errorCode);
                    throw new Error(`${errorMsg}${errorCode ? '\n[' + errorCode + ']' : ''}`);
                }

            } catch (error) {
                console.error('❌ IMPORT ERROR:', error);
                const elapsed = ((Date.now() - processStart) / 1000).toFixed(1);
                const errorMsg = error.message || 'Terjadi error tidak diketahui';
                const fullMsg = `${errorMsg}\n\n⏱️ Waktu: ${elapsed}s\n📱 Lihat browser console (F12) untuk detail lebih lanjut.`;
                showAlert(fullMsg, 'error');
                console.error('Stack trace:', error.stack);
            } finally {
                hideLoader();
            }
        });

        // Helper function
        function formatFileSize(bytes) {
            const units = ['B', 'KB', 'MB'];
            let size = bytes;
            let unitIdx = 0;
            while (size > 1024 && unitIdx < units.length - 1) {
                size /= 1024;
                unitIdx++;
            }
            return size.toFixed(2) + units[unitIdx];
        }

        function showAlert(message, type = 'info') {
            alert.textContent = message;
            alert.className = `alert show alert-${type}`;
            alert.style.display = 'block';
            alert.style.whiteSpace = 'pre-wrap';
            alert.style.wordWrap = 'break-word';
            console.log('📢 Alert:', type, message);
        }

        function hideAlert() {
            alert.classList.remove('show');
            alert.style.display = 'none';
        }

        function showLoader(text = '⏳ Sedang memproses...') {
            loader.style.display = 'flex';
            loader.classList.add('show');
            document.getElementById('loaderText').textContent = text;
            console.log('▶️ Loader shown:', text);
        }

        function hideLoader() {
            loader.style.display = 'none';
            loader.classList.remove('show');
            console.log('⏸️ Loader hidden');
        }

        function showPreview(result) {
            const preview = document.getElementById('preview');
            const stats = result.stats || {};
            
            let html = `
                <div class="preview-item">
                    <span class="preview-label">Soal Diterima:</span>
                    <span class="preview-value">${stats.total_questions || 0} pertanyaan</span>
                </div>
                <div class="preview-item">
                    <span class="preview-label">Judul:</span>
                    <span class="preview-value">${stats.title || '-'}</span>
                </div>
                <div class="preview-item">
                    <span class="preview-label">Mata Pelajaran:</span>
                    <span class="preview-value">${stats.subject || '-'}</span>
                </div>
                <div class="preview-item">
                    <span class="preview-label">Kelas:</span>
                    <span class="preview-value">${stats.class || '-'}</span>
                </div>
            `;

            preview.innerHTML = html;
            preview.classList.add('show');
        }

        // Initialize
        fileInput.required = true;
        urlInput.required = false;
    </script>
</body>
</html>
