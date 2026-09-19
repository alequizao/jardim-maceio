<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * senha.php
 * Redefinição de senha por e-mail: geração, validação e uso dos tokens.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/email.php';

define('SENHA_TOKEN_VALIDADE_MIN', 60);   // minutos de validade do link
define('SENHA_TOKEN_MAX_POR_HORA', 5);    // pedidos por usuário na última hora

/** Conexão garantindo a tabela de tokens. */
function senhaPdo(): PDO {
    $pdo = Database::conectar();
    static $criada = false;
    if (!$criada) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS senha_tokens (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expira_em DATETIME NOT NULL,
                usado_em DATETIME NULL,
                ip VARCHAR(45) NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_token (token_hash),
                KEY idx_usuario (usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $criada = true;
    }
    return $pdo;
}

function senhaIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        $v = trim(explode(',', (string)($_SERVER[$k] ?? ''))[0]);
        if ($v !== '') return substr($v, 0, 45);
    }
    return '';
}

/**
 * E-mail para onde mandar o link. Usa `usuarios.email` quando é um endereço
 * de verdade (o master entra por usuário simples) e senão `email_recuperacao`.
 */
function senhaEmailDestino(array $u): string {
    $e = trim((string)($u['email'] ?? ''));
    if (filter_var($e, FILTER_VALIDATE_EMAIL)) return $e;
    $alt = trim((string)($u['email_recuperacao'] ?? ''));
    return filter_var($alt, FILTER_VALIDATE_EMAIL) ? $alt : '';
}

/**
 * Cria o token e manda o e-mail. A resposta é sempre genérica para quem pede
 * (não revela se o cadastro existe); o retorno aqui é só para o log interno.
 */
function senhaSolicitar(string $identificador): array {
    $pdo = senhaPdo();
    $identificador = trim($identificador);
    if ($identificador === '') return ['ok' => false, 'motivo' => 'vazio'];

    $st = $pdo->prepare("SELECT id, nome, email, email_recuperacao, ativo
                         FROM usuarios WHERE email = :a OR email_recuperacao = :b LIMIT 1");
    $st->execute([':a' => $identificador, ':b' => $identificador]);
    $u = $st->fetch();
    if (!$u || !$u['ativo']) return ['ok' => false, 'motivo' => 'sem_usuario'];

    $para = senhaEmailDestino($u);
    if ($para === '') return ['ok' => false, 'motivo' => 'sem_email'];

    // Limite de pedidos por hora
    $st = $pdo->prepare("SELECT COUNT(*) FROM senha_tokens
                         WHERE usuario_id = :id AND criado_em > (NOW() - INTERVAL 1 HOUR)");
    $st->execute([':id' => $u['id']]);
    if ((int)$st->fetchColumn() >= SENHA_TOKEN_MAX_POR_HORA) {
        return ['ok' => false, 'motivo' => 'limite'];
    }

    // Invalida os links anteriores que ainda estavam de pé
    $pdo->prepare("UPDATE senha_tokens SET usado_em = NOW()
                   WHERE usuario_id = :id AND usado_em IS NULL")->execute([':id' => $u['id']]);

    $token = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO senha_tokens (usuario_id, token_hash, expira_em, ip)
                   VALUES (:u, :t, (NOW() + INTERVAL " . (int)SENHA_TOKEN_VALIDADE_MIN . " MINUTE), :ip)")
        ->execute([
            ':u'  => $u['id'],
            ':t'  => hash('sha256', $token),
            ':ip' => senhaIp(),
        ]);

    $link = BASE_URL . '/views/redefinir_senha.php?token=' . $token;
    $detalhe = '';
    $ok = smtpEnviar(
        $para,
        'Redefinição de senha · ' . SISTEMA_NOME,
        emailRedefinicao((string)$u['nome'], $link, SENHA_TOKEN_VALIDADE_MIN, senhaIp()),
        $detalhe
    );
    return ['ok' => $ok, 'motivo' => $ok ? 'enviado' : 'smtp', 'detalhe' => $detalhe, 'destino' => $para];
}

/** Devolve o usuário do token se ele existir, não estiver usado e não tiver vencido. */
function senhaUsuarioDoToken(string $token): ?array {
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) return null;
    $st = senhaPdo()->prepare(
        "SELECT t.id AS token_id, u.id, u.nome, u.email
         FROM senha_tokens t JOIN usuarios u ON u.id = t.usuario_id
         WHERE t.token_hash = :t AND t.usado_em IS NULL AND t.expira_em > NOW() AND u.ativo = 1
         LIMIT 1");
    $st->execute([':t' => hash('sha256', $token)]);
    return $st->fetch() ?: null;
}

/** Grava a senha nova e queima o token. */
function senhaRedefinir(string $token, string $nova): array {
    $u = senhaUsuarioDoToken($token);
    if (!$u) return ['ok' => false, 'msg' => 'Este link não vale mais. Peça um novo.'];
    if (strlen($nova) < 8) return ['ok' => false, 'msg' => 'A senha precisa ter pelo menos 8 caracteres.'];

    $pdo = senhaPdo();
    $pdo->prepare("UPDATE usuarios SET senha = :s WHERE id = :id")
        ->execute([':s' => password_hash($nova, PASSWORD_DEFAULT), ':id' => $u['id']]);
    $pdo->prepare("UPDATE senha_tokens SET usado_em = NOW() WHERE id = :t")
        ->execute([':t' => $u['token_id']]);
    // Qualquer outro link pendente do mesmo usuário também morre
    $pdo->prepare("UPDATE senha_tokens SET usado_em = NOW() WHERE usuario_id = :u AND usado_em IS NULL")
        ->execute([':u' => $u['id']]);

    return ['ok' => true, 'msg' => 'Senha alterada. Você já pode entrar com ela.'];
}
