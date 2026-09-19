<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * /api/notificacoes.php
 * Limpa notificações do sino (por usuário).
 * POST {acao:"limpar", chaves:["d:12","r:5","a:3"]}  -> some com essas
 * POST {acao:"limpar_tudo"}                           -> some com todas as atuais
 * POST {acao:"restaurar"}                             -> volta a mostrar tudo
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notificacoes.php';

$u = Auth::exigirLoginApi();
header('Cache-Control: no-store');
$pdo = Database::conectar();
Notif::garantirTabela($pdo);
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $acao = $in['acao'] ?? '';
    if ($acao === 'restaurar') {
        $pdo->prepare("DELETE FROM notificacoes_limpas WHERE usuario_id = ?")->execute([$uid]);
    } else {
        $chaves = $acao === 'limpar_tudo' ? Notif::chavesAtuais($pdo, $uid) : (array)($in['chaves'] ?? []);
        $st = $pdo->prepare("INSERT IGNORE INTO notificacoes_limpas (usuario_id, chave) VALUES (?, ?)");
        foreach ($chaves as $k) {
            if (preg_match('/^[dra]:\d{1,10}$/', (string)$k)) $st->execute([$uid, $k]);
        }
    }
}
$n = Notif::carregar($pdo, $uid);
jsonResposta(['ok' => true, 'total' => $n['total']]);
