<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * /api/relatorios.php
 * Relatórios consolidados (balancete, fluxo, etc.)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::exigirLoginApi();
$pdo = Database::conectar();

$acao = $_GET['acao'] ?? 'balancete';
$mes  = $_GET['mes']  ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');
$inicio = $mes . '-01';
$fim    = date('Y-m-t', strtotime($inicio));

switch ($acao) {

    case 'balancete':
        // Saldo anterior
        $saldoAnt = (float)$pdo->query(
            "SELECT (SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='recebido' AND data_recebimento < '$inicio')
                  - (SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='pago'     AND data_pagamento   < '$inicio')"
        )->fetchColumn();

        // Receitas do período
        $receitas = $pdo->query(
            "SELECT c.nome AS categoria, COALESCE(SUM(r.valor),0) AS total
             FROM categorias c
             LEFT JOIN receitas r ON r.categoria_id=c.id AND r.status='recebido'
                                  AND r.data_recebimento BETWEEN '$inicio' AND '$fim'
             WHERE c.tipo='receita'
             GROUP BY c.id, c.nome
             HAVING total > 0
             ORDER BY total DESC"
        )->fetchAll();

        // Despesas do período
        $despesas = $pdo->query(
            "SELECT c.nome AS categoria, COALESCE(SUM(d.valor),0) AS total
             FROM categorias c
             LEFT JOIN despesas d ON d.categoria_id=c.id AND d.status='pago'
                                  AND d.data_pagamento BETWEEN '$inicio' AND '$fim'
             WHERE c.tipo='despesa'
             GROUP BY c.id, c.nome
             HAVING total > 0
             ORDER BY total DESC"
        )->fetchAll();

        $totalRec = array_sum(array_map(fn($r) => (float)$r['total'], $receitas));
        $totalDes = array_sum(array_map(fn($d) => (float)$d['total'], $despesas));
        $saldoFim = $saldoAnt + $totalRec - $totalDes;

        jsonResposta([
            'periodo' => ['inicio' => $inicio, 'fim' => $fim, 'mes_extenso' => mesPt((int)date('m', strtotime($inicio))) . '/' . date('Y', strtotime($inicio))],
            'saldo_anterior' => $saldoAnt,
            'receitas' => $receitas,
            'despesas' => $despesas,
            'total_receitas' => $totalRec,
            'total_despesas' => $totalDes,
            'saldo_final' => $saldoFim,
        ]);
        break;

    case 'inadimplencia':
        $rows = $pdo->query(
            "SELECT r.id, r.descricao, r.valor, r.data_competencia,
                    CONCAT(m.bloco,' - ', m.apartamento) AS unidade,
                    m.nome AS morador
             FROM receitas r
             LEFT JOIN moradores m ON m.id = r.morador_id
             WHERE r.status = 'pendente' AND r.data_competencia < CURDATE()
             ORDER BY r.data_competencia ASC"
        )->fetchAll();
        $total = array_sum(array_map(fn($r) => (float)$r['valor'], $rows));
        jsonResposta(['itens' => $rows, 'total' => $total]);
        break;

    default:
        jsonResposta(['erro' => 'Ação inválida'], 400);
}
