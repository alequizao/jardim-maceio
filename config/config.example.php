<?php
/**
 * config.php
 * Configurações globais do sistema Transparência Jardim Maceió
 */

// Timezone Brasil
date_default_timezone_set('America/Maceio');

// Locale para formatação em português
setlocale(LC_ALL, 'pt_BR.UTF-8', 'Portuguese_Brazil.1252', 'pt_BR', 'portuguese');

// Configurações do sistema
define('SISTEMA_NOME', 'Transparência Jardim Maceió');
define('SISTEMA_VERSAO', '1.0.0');
define('SISTEMA_SUBTITULO', 'Portal Financeiro Condominial');

// URL base (ajustar conforme servidor)
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script    = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$script    = rtrim(str_replace('\\', '/', $script), '/');
// Remove sufixos /api ou /views da base
$script    = preg_replace('#/(api|views)$#', '', $script);
define('BASE_URL', $protocolo . $host . $script);

// Diretório de uploads
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Segurança
define('SESSION_LIFETIME', 60 * 60 * 4); // 4h

// Exibir erros em desenvolvimento — em produção desligue!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurar sessão segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}
