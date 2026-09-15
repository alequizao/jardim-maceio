<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * helpers.php
 * Funções utilitárias do sistema
 */

/**
 * Formata valor em Real brasileiro
 */
function brl(float $valor, bool $comSimbolo = true): string {
    $f = number_format($valor, 2, ',', '.');
    return $comSimbolo ? 'R$ ' . $f : $f;
}

/**
 * Formata data DD/MM/YYYY
 */
function dataBr(?string $data): string {
    if (empty($data) || $data === '0000-00-00') return '-';
    $ts = strtotime($data);
    return $ts ? date('d/m/Y', $ts) : '-';
}

/**
 * Formata data e hora DD/MM/YYYY HH:MM
 */
function dataHoraBr(?string $data): string {
    if (empty($data)) return '-';
    $ts = strtotime($data);
    return $ts ? date('d/m/Y H:i', $ts) : '-';
}

/**
 * Nome do mês em português
 */
function mesPt(int $mes): string {
    $meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
              'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    return $meses[$mes - 1] ?? '';
}

/**
 * Dia da semana em português
 */
function diaSemanaPt(string $data): string {
    $dias = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira',
             'Quinta-feira','Sexta-feira','Sábado'];
    return $dias[(int)date('w', strtotime($data))] ?? '';
}

/**
 * Escapa HTML
 */
function e($valor): string {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Token CSRF
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfValido(?string $token): bool {
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Resposta JSON
 */
function jsonResposta($dados, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Normaliza um valor de data vindo de formulário.
 * Campos <input type="date"> vazios chegam como "" (string vazia),
 * e o MySQL em modo STRICT rejeita "" para colunas DATE.
 * Retorna null quando vazio, para gravar NULL corretamente.
 */
function dateOrNull($valor): ?string {
    $valor = trim((string)($valor ?? ''));
    return $valor === '' ? null : $valor;
}

/**
 * Recebe input JSON em endpoints
 */
function inputJson(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Calcula variação percentual entre dois valores
 */
function variacao(float $atual, float $anterior): float {
    if ($anterior == 0) return 0;
    return (($atual - $anterior) / $anterior) * 100;
}

/**
 * Cor do badge de status
 */
function statusBadge(string $status): string {
    $map = [
        'pago'      => 'badge-success',
        'recebido'  => 'badge-success',
        'pendente'  => 'badge-warning',
        'vencido'   => 'badge-danger',
        'cancelado' => 'badge-muted',
    ];
    return $map[$status] ?? 'badge-muted';
}

/**
 * Texto amigável do status
 */
function statusTexto(string $status): string {
    $map = [
        'pago'      => 'PAGO',
        'recebido'  => 'RECEBIDO',
        'pendente'  => 'PENDENTE',
        'vencido'   => 'VENCIDO',
        'cancelado' => 'CANCELADO',
    ];
    return $map[$status] ?? strtoupper($status);
}

/* =====================================================================
   CONFIGURAÇÕES DO SISTEMA (chave/valor) — ex.: marca, logo, títulos
   ===================================================================== */

/**
 * Conexão garantindo a existência da tabela `configuracoes`.
 */
function cfgPdo(): PDO {
    require_once __DIR__ . '/../config/database.php';
    $pdo = Database::conectar();
    static $criada = false;
    if (!$criada) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS configuracoes (
                chave VARCHAR(60) NOT NULL PRIMARY KEY,
                valor TEXT NULL,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $criada = true;
    }
    return $pdo;
}

/**
 * Lê uma configuração (com cache em memória). Valor vazio devolve o padrão.
 */
function cfg(string $chave, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (cfgPdo()->query("SELECT chave, valor FROM configuracoes")->fetchAll() as $r) {
                $cache[$r['chave']] = $r['valor'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    $v = $cache[$chave] ?? '';
    return ($v === '' || $v === null) ? $default : (string)$v;
}

/**
 * Salva (upsert) um conjunto de configurações.
 */
function cfgSalvar(array $pares): void {
    $pdo  = cfgPdo();
    $stmt = $pdo->prepare(
        "INSERT INTO configuracoes (chave, valor) VALUES (:c, :v)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
    );
    foreach ($pares as $c => $v) {
        $stmt->execute([':c' => $c, ':v' => $v]);
    }
}

/**
 * SVG padrão da marca (usado quando não há logo enviada).
 */
function marcaLogoPadraoSvg(): string {
    return '<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">'
         . '<path d="M20 4 C12 12, 8 18, 8 24 C8 32, 14 36, 20 36 C26 36, 32 32, 32 24 C32 18, 28 12, 20 4 Z" fill="#FFFFFF" opacity="0.95"/>'
         . '<circle cx="20" cy="22" r="3" fill="#0F7B3E"/></svg>';
}
