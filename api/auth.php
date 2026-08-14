<?php
/**
 * /api/auth.php
 * Endpoints de autenticação: login e logout
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';

switch ($acao) {

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResposta(['erro' => 'Método inválido'], 405);
        }
        $in = inputJson() ?: $_POST;
        $email = trim($in['email'] ?? '');
        $senha = $in['senha'] ?? '';

        if ($email === '' || $senha === '') {
            jsonResposta(['ok' => false, 'msg' => 'Informe e-mail e senha.'], 400);
        }
        $r = Auth::login($email, $senha);
        jsonResposta($r, $r['ok'] ? 200 : 401);
        break;

    case 'logout':
        Auth::logout();
        header('Location: ' . BASE_URL . '/views/login.php');
        exit;

    case 'me':
        Auth::exigirLoginApi();
        jsonResposta(['usuario' => Auth::usuario()]);
        break;

    default:
        jsonResposta(['erro' => 'Ação inválida'], 400);
}
