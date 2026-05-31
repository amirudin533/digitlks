<?php
/**
 * IMPORT QUESTIONS API - ENHANCED WITH ERROR HANDLING
 * Handle question imports from HTML files or URLs
 * 
 * Features:
 * - Comprehensive error handling & logging
 * - Request/response logging
 * - Debug mode with detailed error messages
 * - Proper HTTP status codes
 * - Live progress feedback
 */

// ============================================================================
// 1. INITIALIZATION & ERROR HANDLING
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Setup logging
$logsDir = __DIR__ . '/../../storage/logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}

$errorLogFile = $logsDir . '/import-errors.log';
$debugLogFile = $logsDir . '/import-debug.log';

// Helper function to log errors
function logError($message, $context = []) {
    global $errorLogFile;
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logMsg = "[$timestamp] ERROR: $message$contextStr\n";
    @file_put_contents($errorLogFile, $logMsg, FILE_APPEND);
}

// Helper function to log debug info
function logDebug($message, $context = []) {
    global $debugLogFile;
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logMsg = "[$timestamp] DEBUG: $message$contextStr\n";
    @file_put_contents($debugLogFile, $logMsg, FILE_APPEND);
}

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logError("PHP Error [$errno]", [
        'message' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    return true;
});

// Custom exception handler
set_exception_handler(function($exception) {
    logError("Uncaught Exception", [
        'message' => $exception->getMessage(),
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi error pada server. Lihat log untuk detail.',
        'error_code' => 'SERVER_ERROR'
    ], JSON_PRETTY_PRINT);
    exit();
});

// Setup headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

logDebug("API Request Started", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => $_SERVER['REQUEST_URI']
]);

// ============================================================================
// 2. REQUEST VALIDATION
// ============================================================================

// Handle OPTIONS request (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Hanya POST yang didukung.',
        'error_code' => 'METHOD_NOT_ALLOWED'
    ], JSON_PRETTY_PRINT);
    logError("Invalid HTTP method: " . $_SERVER['REQUEST_METHOD']);
    exit();
}

// ============================================================================
// 3. LOAD DEPENDENCIES
// ============================================================================

$requiredFiles = [
    __DIR__ . '/../../config/config.php',
    __DIR__ . '/../../src/Core/Parser.php',
    __DIR__ . '/../../src/Core/Scraper.php',
    __DIR__ . '/../../src/Core/FileManager.php'
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        logError("Required file not found: $file");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'File requirement tidak ditemukan: ' . basename($file),
            'error_code' => 'FILE_NOT_FOUND'
        ], JSON_PRETTY_PRINT);
        exit();
    }
    require_once $file;
}

use Core\Parser;
use Core\Scraper;
use Core\FileManager;

// ============================================================================
// 4. MAIN PROCESSING
// ============================================================================

try {
    logDebug("Starting import process");

    // Parse JSON input
    $rawInput = file_get_contents('php://input');
    logDebug("Received payload", ['size' => strlen($rawInput) . ' bytes']);

    $input = json_decode($rawInput, true);

    if ($input === null && $rawInput !== '') {
        logError("JSON parsing failed", ['error' => json_last_error_msg()]);
        throw new Exception("Request body bukan JSON valid. Error: " . json_last_error_msg());
    }

    if (empty($input)) {
        throw new Exception("Request body kosong atau JSON tidak valid");
    }

    // Validate input structure
    $type = $input['type'] ?? null;
    $content = $input['content'] ?? null;
    $meta = $input['meta'] ?? [];
    $filename = $input['filename'] ?? null;

    logDebug("Input received", [
        'type' => $type,
        'content_length' => strlen($content ?? ''),
        'filename' => $filename
    ]);

    // Validate type
    if (!$type || !in_array($type, ['file', 'url'])) {
        throw new Exception("Parameter 'type' harus 'file' atau 'url'");
    }

    // Validate content
    if (empty($content)) {
        throw new Exception("Parameter 'content' tidak boleh kosong");
    }

    // Session/Auth check
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        throw new Exception("Tidak terautentikasi");
    }
    // CSRF check
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['_csrf_token'], $csrfToken)) {
        throw new Exception("CSRF token tidak valid.");
    }
    $adminId = $_SESSION['admin_username'] ?? 'admin';
    logDebug("Admin ID: $adminId");

    // ========================================================================
    // 5. INITIALIZE SCRAPER & PARSER
    // ========================================================================

    logDebug("Initializing Parser and Scraper");
    $parser = new Parser();
    $scraper = new Scraper($parser);

    $result = null;
    $stats = null;

    // ========================================================================
    // 6. PROCESS BERDASARKAN TYPE
    // ========================================================================

    if ($type === 'url') {
        // Validate URL format
        if (!filter_var($content, FILTER_VALIDATE_URL)) {
            logError("Invalid URL format", ['url' => substr($content, 0, 100)]);
            throw new Exception("URL tidak valid: " . substr($content, 0, 100));
        }

        logDebug("Processing URL import", ['url' => substr($content, 0, 100)]);

        try {
            $result = $scraper->fetchAndParse($content);
            $questionCount = count($result['soal'] ?? []);
            logDebug("URL parsing success", ['questions' => $questionCount]);
        } catch (Exception $e) {
            logError("URL parsing failed", [
                'url' => $content,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Gagal fetch/parse URL: " . $e->getMessage());
        }

    } elseif ($type === 'file') {
        // Validate base64 content
        logDebug("Processing file upload", ['filename' => $filename]);
        
        $fileData = base64_decode($content, true);
        if ($fileData === false) {
            logError("Base64 decode failed", ['filename' => $filename]);
            throw new Exception("File content bukan valid base64");
        }

        logDebug("File decoded", ['size' => strlen($fileData)]);

        // Create temporary file
        $tempPath = tempnam(sys_get_temp_dir(), 'quiz_');
        if ($tempPath === false) {
            logError("Cannot create temp file");
            throw new Exception("Gagal membuat temporary file");
        }

        try {
            // Write decoded content
            $bytesWritten = file_put_contents($tempPath, $fileData);
            if ($bytesWritten === false) {
                logError("Cannot write to temp file", ['path' => $tempPath]);
                throw new Exception("Gagal menulis ke temporary file");
            }

            logDebug("Temp file created", [
                'path' => $tempPath,
                'size' => filesize($tempPath)
            ]);

            // Create $_FILES array structure
            $fileArray = [
                'tmp_name' => $tempPath,
                'name' => $filename ?? 'uploaded.html',
                'size' => filesize($tempPath),
                'error' => UPLOAD_ERR_OK,
                'type' => 'text/html'
            ];

            // Process file
            $result = $scraper->processUploadedFile($fileArray);
            $questionCount = count($result['soal'] ?? []);
            logDebug("File parsing success", ['questions' => $questionCount]);

        } catch (Exception $e) {
            logError("File parsing failed", [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Gagal parse file: " . $e->getMessage());
        } finally {
            // Cleanup temporary file
            if (file_exists($tempPath)) {
                @unlink($tempPath);
                logDebug("Temp file cleaned up");
            }
        }
    }

    // Validate result
    if (empty($result) || empty($result['soal'])) {
        logError("No questions found in result");
        throw new Exception("Tidak ada soal ditemukan dalam file/URL");
    }

    // Get statistics
    $stats = $scraper->getContentStats($result);
    logDebug("Content stats", $stats);

    // ========================================================================
    // 7. MERGE METADATA
    // ========================================================================

    if (empty($result['metadata'])) {
        $result['metadata'] = [];
    }

    if (!empty($meta['mata_pelajaran'])) {
        $result['metadata']['mata_pelajaran'] = htmlspecialchars($meta['mata_pelajaran']);
    }
    if (!empty($meta['kelas_target'])) {
        $result['metadata']['kelas_target'] = htmlspecialchars($meta['kelas_target']);
    }
    if (!empty($meta['judul'])) {
        $result['metadata']['judul'] = htmlspecialchars($meta['judul']);
    }

    // ========================================================================
    // 8. GENERATE UNIQUE ID & SAVE
    // ========================================================================

    $quizId = bin2hex(random_bytes(16));
    $result['metadata']['quiz_id'] = $quizId;
    $result['metadata']['created_at'] = date('c');
    $result['metadata']['created_by'] = $adminId;
    $result['metadata']['import_source'] = $type;

    logDebug("Quiz generated", [
        'quiz_id' => $quizId,
        'questions' => count($result['soal'] ?? [])
    ]);

    // Save to storage
    $configArray = require __DIR__ . '/../../config/config.php';
    $storageBase = rtrim($configArray['storage']['path'], '/');
    $storagePath = $storageBase . "/accounts/$adminId/soal/";
    if (!is_dir($storagePath)) {
        if (!@mkdir($storagePath, 0755, true)) {
            logError("Cannot create storage directory", ['path' => $storagePath]);
            throw new Exception("Gagal membuat folder storage");
        }
    }

    $saveFilePath = $storagePath . "quiz_{$quizId}.json";
    
    try {
        $saved = file_put_contents(
            $saveFilePath,
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        if ($saved === false) {
            logError("Failed to write quiz file", ['path' => $saveFilePath]);
            throw new Exception("Gagal menulis file quiz");
        }

        logDebug("Quiz saved successfully", [
            'path' => $saveFilePath,
            'size' => filesize($saveFilePath)
        ]);
        $savingSuccess = true;

    } catch (Exception $e) {
        logError("Failed to save quiz", [
            'path' => $saveFilePath,
            'error' => $e->getMessage()
        ]);
        $savingSuccess = false;
    }

    // ========================================================================
    // 9. RETURN SUCCESS RESPONSE
    // ========================================================================

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Questions berhasil di-import' . ($savingSuccess ? ' dan tersimpan' : ''),
        'data' => [
            'quiz_id' => $quizId,
            'total_questions' => count($result['soal'] ?? []),
            'save_path' => $savingSuccess ? $saveFilePath : null
        ],
        'stats' => $stats
    ], JSON_PRETTY_PRINT);

    logDebug("Import completed successfully", [
        'quiz_id' => $quizId,
        'questions' => count($result['soal'] ?? []),
        'saved' => $savingSuccess
    ]);

} catch (Exception $e) {
    // ========================================================================
    // 10. ERROR RESPONSE
    // ========================================================================

    logError("Import failed", [
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'IMPORT_FAILED',
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);

    logDebug("Request ended with error");
}
