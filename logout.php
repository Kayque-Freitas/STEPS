<?php
/**
 * Sistema de Tutoriais e POP's - Logout
 * Versão: 2.0 (Revisada)
 */

require_once 'config.php';

if (is_logged_in()) {
    log_audit($_SESSION['user_id'], 'LOGOUT', 'Usuário fez logout');
}

// Destruir sessão
$_SESSION = [];
session_destroy();

// Redirecionar para login
header('Location: login.php?logout=1');
exit;
?>
