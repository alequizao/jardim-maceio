<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (Auth::logado()) {
    header('Location: ' . BASE_URL . '/views/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/views/login.php');
}
exit;
