<?php
/**
 * Sistema de Tutoriais e POP's - Configuração
 * Versão: 2.0 (Revisada)
 * 
 * Arquivo de configuração centralizado com suporte a banco de dados SQLite
 */

// ============================================================================
// CONFIGURAÇÕES BÁSICAS
// ============================================================================

// Tenta detectar a URL base automaticamente
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$baseUrl = $protocol . "://" . $host . ($scriptName == '/' ? '' : $scriptName) . "/";

define('BASE_URL', $baseUrl);
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('THUMB_DIR', __DIR__ . '/thumbs/');
define('DB_PATH', __DIR__ . '/data/database.db');
define('DATA_DIR', __DIR__ . '/data/');
define('QR_DIR', __DIR__ . '/qrcodes/');

// Configurações de segurança
define('SESSION_TIMEOUT', 3600); // 1 hora em segundos
define('MAX_UPLOAD_SIZE', 500 * 1024 * 1024); // 500 MB
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/ogg']);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// ============================================================================
// INICIALIZAÇÃO DE DIRETÓRIOS
// ============================================================================

$directories = [UPLOAD_DIR, THUMB_DIR, DATA_DIR, QR_DIR];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ============================================================================
// INICIALIZAÇÃO DO BANCO DE DADOS
// ============================================================================

class Database {
    private static $instance = null;
    private $pdo = null;

    private function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initializeTables();
        } catch (PDOException $e) {
            die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    private function initializeTables() {
        // Tabela de usuários
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                email TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME
            )
        ");

        // Tabela de categorias
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Tabela de vídeos
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS videos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                filename TEXT NOT NULL,
                thumbnail TEXT,
                description TEXT,
                duration INTEGER,
                file_size INTEGER,
                views INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            )
        ");

        // Tabela de logs de auditoria
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                description TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        // Inserir usuário padrão se não existir
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            $passwordHash = password_hash('admin123', PASSWORD_BCRYPT);
            $this->pdo->prepare("
                INSERT INTO users (username, password, email) 
                VALUES (?, ?, ?)
            ")->execute(['admin', $passwordHash, 'admin@example.com']);
        }
    }
}

// ============================================================================
// FUNÇÕES UTILITÁRIAS
// ============================================================================

/**
 * Sanitiza entrada de usuário
 * @param string $data Dados a sanitizar
 * @return string Dados sanitizados
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Valida se o usuário está autenticado
 * @return bool True se autenticado
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Redireciona para login se não autenticado
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Gera token CSRF
 * @return string Token CSRF
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida token CSRF
 * @param string $token Token a validar
 * @return bool True se válido
 */
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Registra ação na auditoria
 * @param int $userId ID do usuário
 * @param string $action Ação realizada
 * @param string $description Descrição da ação
 */
function log_audit($userId, $action, $description = '') {
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$userId, $action, $description, $ip, $userAgent]);
    } catch (Exception $e) {
        error_log('Erro ao registrar auditoria: ' . $e->getMessage());
    }
}

/**
 * Obtém o IP local do servidor
 * @return string IP local
 */
function get_local_ip() {
    $ip = gethostbyname(gethostname());
    if ($ip === gethostname()) {
        $ip = '127.0.0.1';
    }
    return $ip;
}

/**
 * Formata tamanho de arquivo em formato legível
 * @param int $bytes Tamanho em bytes
 * @return string Tamanho formatado
 */
function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Valida arquivo enviado
 * @param array $file Arquivo do $_FILES
 * @param array $allowedTypes Tipos MIME permitidos
 * @param int $maxSize Tamanho máximo em bytes
 * @return array Array com 'valid' e 'message'
 */
function validate_upload($file, $allowedTypes, $maxSize = MAX_UPLOAD_SIZE) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'Erro ao enviar arquivo'];
    }

    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'message' => 'Arquivo muito grande'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['valid' => false, 'message' => 'Tipo de arquivo não permitido'];
    }

    return ['valid' => true, 'message' => 'OK'];
}

// ============================================================================
// INICIALIZAÇÃO DE SESSÃO
// ============================================================================

session_start();

// Validar timeout de sessão
if (is_logged_in()) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// Cabeçalhos de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
?>
