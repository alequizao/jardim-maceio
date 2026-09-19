<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * visibilidade.php
 * Controle do que os condôminos (perfil morador) podem ver.
 * Admin/síndico sempre veem tudo; os demais só os módulos liberados em
 * configuracoes (chave vis_<modulo> = 1/0).
 */
require_once __DIR__ . '/../config/database.php';

class Vis
{
    /** módulo => [rótulo, padrão] */
    const MODULOS = [
        'dashboard'   => ['Dashboard', 1],
        'pagar'       => ['Contas a Pagar', 1],
        'receber'     => ['Contas a Receber', 1],
        'notas'       => ['Notas Fiscais', 1],
        'fluxo'       => ['Fluxo de Caixa', 1],
        'relatorios'  => ['Relatórios', 1],
        'balancete'   => ['Balancete', 1],
        'graficos'    => ['Gráficos', 1],
        'documentos'  => ['Documentos', 1],
        'assembleias' => ['Assembleia', 0],
    ];

    /** arquivo (views/ ou api/) => módulo */
    const ARQUIVOS = [
        'dashboard.php' => 'dashboard', 'contas_pagar.php' => 'pagar', 'contas_receber.php' => 'receber',
        'notas_fiscais.php' => 'notas', 'fluxo_caixa.php' => 'fluxo', 'relatorios.php' => 'relatorios',
        'balancete.php' => 'balancete', 'graficos.php' => 'graficos', 'documentos.php' => 'documentos',
        'assembleias.php' => 'assembleias', 'assembleia_ver.php' => 'assembleias', 'assembleia_relatorio.php' => 'assembleias',
        // APIs
        'despesas.php' => 'pagar', 'receitas.php' => 'receber', 'notas-fiscais.php' => 'notas',
    ];

    private static $cache = null;

    public static function ehAdmin(): bool
    {
        $u = $_SESSION['usuario'] ?? null;
        return $u !== null && in_array($u['tipo'], ['admin', 'sindico'], true);
    }

    /** Estado configurado (sem considerar o perfil). */
    public static function config(): array
    {
        if (self::$cache !== null) return self::$cache;
        $mapa = [];
        foreach (self::MODULOS as $m => $d) $mapa[$m] = (bool)$d[1];
        try {
            $rs = Database::conectar()->query("SELECT chave, valor FROM configuracoes WHERE chave LIKE 'vis\\_%'");
            foreach ($rs as $r) {
                $m = substr($r['chave'], 4);
                if (isset($mapa[$m])) $mapa[$m] = $r['valor'] === '1';
            }
        } catch (Throwable $e) {}
        return self::$cache = $mapa;
    }

    public static function salvar(string $m, bool $on): void
    {
        if (!isset(self::MODULOS[$m])) return;
        Database::conectar()->prepare(
            "INSERT INTO configuracoes (chave, valor) VALUES (:c, :v) ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
        )->execute([':c' => 'vis_' . $m, ':v' => $on ? '1' : '0']);
        self::$cache = null;
    }

    public static function liberado(string $m): bool
    {
        if (self::ehAdmin()) return true;
        $c = self::config();
        return $c[$m] ?? true;
    }

    /** Assinatura do estado — muda quando o admin altera algo. */
    public static function versao(): string
    {
        return substr(md5(json_encode(self::config())), 0, 12);
    }

    public static function primeiraLiberada(): ?string
    {
        foreach (self::MODULOS as $m => $d) if (self::liberado($m)) return $m;
        return null;
    }

    /** Checa a página/API atual pelo nome do arquivo. */
    public static function exigirArquivo(bool $api = false): void
    {
        $arq = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $m = self::ARQUIVOS[$arq] ?? null;
        if ($m === null || self::liberado($m)) return;
        if ($api) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['erro' => 'Este módulo não está liberado pela administração.', 'bloqueado' => $m]);
            exit;
        }
        $ir = self::primeiraLiberada();
        $arqs = array_flip(array_slice(self::ARQUIVOS, 0, 10, true));
        header('Location: ' . BASE_URL . '/views/' . ($ir ? $arqs[$ir] : 'login.php') . '?bloqueado=' . urlencode($m));
        exit;
    }

    /** Versão do sistema: muda sozinha a cada arquivo publicado. */
    public static function versaoApp(): string
    {
        $raiz = dirname(__DIR__);
        $m = 0;
        foreach (['includes/*.php', 'views/*.php', 'api/*.php', 'assets/js/*.js', 'assets/css/*.css', 'sw.js', 'config/*.php'] as $g)
            foreach (glob($raiz . '/' . $g) ?: [] as $f) $m = max($m, (int)@filemtime($f));
        return (string)$m;
    }

    /** atributo para os itens do menu */
    public static function nav(string $m): string
    {
        return 'data-mod="' . $m . '"' . (self::liberado($m) ? '' : ' hidden');
    }
}
