<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * notificacoes.php
 * Notificações do sino: contas vencidas, recebimentos em atraso e avisos.
 * Cada usuário pode limpar as suas (tabela notificacoes_limpas, chave d:/r:/a:<id>).
 */
class Notif
{
    public static function garantirTabela(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notificacoes_limpas (
            usuario_id INT NOT NULL, chave VARCHAR(20) NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (usuario_id, chave)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function sqlBase(string $tipo): string
    {
        $limpa = "NOT EXISTS (SELECT 1 FROM notificacoes_limpas nl WHERE nl.usuario_id = :uid AND nl.chave = CONCAT('%s:', t.id))";
        switch ($tipo) {
            case 'd': return "FROM despesas t WHERE (t.status='vencido' OR (t.status='pendente' AND t.data_vencimento < CURDATE())) AND " . sprintf($limpa, 'd');
            case 'r': return "FROM receitas t WHERE t.status='pendente' AND t.data_competencia < CURDATE() AND " . sprintf($limpa, 'r');
            default:  return "FROM avisos t WHERE t.ativo=1 AND " . sprintf($limpa, 'a');
        }
    }

    public static function carregar(PDO $pdo, int $uid): array
    {
        $o = ['dv' => [], 'rv' => [], 'av' => [], 'dvTot' => 0, 'rvTot' => 0, 'avTot' => 0, 'total' => 0];
        try {
            self::garantirTabela($pdo);
            $q = function ($sql) use ($pdo, $uid) { $s = $pdo->prepare($sql); $s->execute([':uid' => $uid]); return $s; };
            $o['dv']    = $q("SELECT t.id, t.descricao, t.valor, t.data_vencimento " . self::sqlBase('d') . " ORDER BY t.data_vencimento ASC LIMIT 5")->fetchAll();
            $o['dvTot'] = (int)$q("SELECT COUNT(*) " . self::sqlBase('d'))->fetchColumn();
            $o['rv']    = $q("SELECT t.id, t.descricao, t.valor, t.data_competencia " . self::sqlBase('r') . " ORDER BY t.data_competencia ASC LIMIT 5")->fetchAll();
            $o['rvTot'] = (int)$q("SELECT COUNT(*) " . self::sqlBase('r'))->fetchColumn();
            $o['av']    = $q("SELECT t.id, t.titulo, t.data_publicacao " . self::sqlBase('a') . " ORDER BY t.data_publicacao DESC, t.id DESC LIMIT 5")->fetchAll();
            $o['avTot'] = (int)$q("SELECT COUNT(*) " . self::sqlBase('a'))->fetchColumn();
        } catch (Throwable $e) {}
        $o['total'] = $o['dvTot'] + $o['rvTot'] + $o['avTot'];
        return $o;
    }

    /** Todas as chaves ainda visíveis (não só as 5 exibidas de cada grupo). */
    public static function chavesAtuais(PDO $pdo, int $uid): array
    {
        $k = [];
        foreach (['d', 'r', 'a'] as $t) {
            $s = $pdo->prepare("SELECT t.id " . self::sqlBase($t));
            $s->execute([':uid' => $uid]);
            foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) $k[] = $t . ':' . $id;
        }
        return $k;
    }
}
