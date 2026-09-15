<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * /api/dashboard.php
 * Endpoints de agregação financeira do Dashboard.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::exigirLoginApi();
$pdo = Database::conectar();

$acao = $_GET['acao'] ?? 'resumo';

switch ($acao) {

    case 'resumo':
        $hoje      = new DateTimeImmutable('now');
        $mesAtual  = (int)$hoje->format('m');
        $anoAtual  = (int)$hoje->format('Y');
        $diasMes   = (int)$hoje->format('t');
        $inicioMes = $hoje->format('Y-m-01');
        $fimMes    = $hoje->format("Y-m-{$diasMes}");

        $primeiroMesPassado = (new DateTimeImmutable($inicioMes))->modify('-1 month')->format('Y-m-01');
        $ultimoMesPassado   = (new DateTimeImmutable($inicioMes))->modify('-1 day')->format('Y-m-d');

        // -------- KPIs --------
        $totalReceitas = (float)$pdo->query(
            "SELECT COALESCE(SUM(valor),0) FROM receitas
             WHERE status='recebido' AND data_recebimento BETWEEN '$inicioMes' AND '$fimMes'"
        )->fetchColumn();

        $totalDespesas = (float)$pdo->query(
            "SELECT COALESCE(SUM(valor),0) FROM despesas
             WHERE status='pago' AND data_pagamento BETWEEN '$inicioMes' AND '$fimMes'"
        )->fetchColumn();

        $receitasMesAnt = (float)$pdo->query(
            "SELECT COALESCE(SUM(valor),0) FROM receitas
             WHERE status='recebido' AND data_recebimento BETWEEN '$primeiroMesPassado' AND '$ultimoMesPassado'"
        )->fetchColumn();

        $despesasMesAnt = (float)$pdo->query(
            "SELECT COALESCE(SUM(valor),0) FROM despesas
             WHERE status='pago' AND data_pagamento BETWEEN '$primeiroMesPassado' AND '$ultimoMesPassado'"
        )->fetchColumn();

        // Saldo atual = soma de toda receita recebida - toda despesa paga (histórico completo)
        $saldoAtual = (float)$pdo->query(
            "SELECT (SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='recebido')
                  - (SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='pago')"
        )->fetchColumn();

        // Saldo no início do mês (anterior)
        $saldoAnterior = (float)$pdo->query(
            "SELECT (SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='recebido' AND data_recebimento < '$inicioMes')
                  - (SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='pago' AND data_pagamento < '$inicioMes')"
        )->fetchColumn();

        // Previsão saldo fim do mês = saldo atual + receitas pendentes - despesas pendentes do mês
        $receitasPendMes = (float)$pdo->query(
            "SELECT COALESCE(SUM(valor),0) FROM receitas
             WHERE status='pendente' AND data_competencia BETWEEN '$inicioMes' AND '$fimMes'"
        )->fetchColumn();
        $despesasPendMes = (float)$pdo->query(
            "SELECT COALESCE(SUM(valor),0) FROM despesas
             WHERE status IN ('pendente','vencido') AND data_vencimento BETWEEN '$inicioMes' AND '$fimMes'"
        )->fetchColumn();
        $previsaoSaldo = $saldoAtual + $receitasPendMes - $despesasPendMes;

        // -------- FLUXO DE CAIXA (dia a dia do mês) --------
        $labels = [];
        $recDia = [];
        $despDia = [];
        $saldoDia = [];
        $saldo = $saldoAnterior;

        for ($d = 1; $d <= $diasMes; $d++) {
            $diaStr = sprintf('%04d-%02d-%02d', $anoAtual, $mesAtual, $d);
            $labels[] = sprintf('%02d', $d);

            $r = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='recebido' AND data_recebimento='$diaStr'")->fetchColumn();
            $de = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='pago' AND data_pagamento='$diaStr'")->fetchColumn();

            $recDia[]  = $r;
            $despDia[] = $de;
            $saldo += ($r - $de);
            $saldoDia[] = round($saldo, 2);
        }

        // -------- DESPESAS POR CATEGORIA (mês) --------
        $stmt = $pdo->prepare(
            "SELECT c.id, c.nome, c.cor, COALESCE(SUM(d.valor),0) AS valor
             FROM categorias c
             LEFT JOIN despesas d ON d.categoria_id = c.id
                AND d.status='pago'
                AND d.data_pagamento BETWEEN :i AND :f
             WHERE c.tipo='despesa' AND c.ativo=1
             GROUP BY c.id, c.nome, c.cor
             HAVING valor > 0
             ORDER BY valor DESC"
        );
        $stmt->execute([':i' => $inicioMes, ':f' => $fimMes]);
        $cats = $stmt->fetchAll();
        $totalCat = array_sum(array_column($cats, 'valor'));
        foreach ($cats as &$c) {
            $c['valor'] = (float)$c['valor'];
            $c['percentual'] = $totalCat > 0 ? round(($c['valor']/$totalCat)*100, 1) : 0;
        }

        // -------- ÚLTIMAS MOVIMENTAÇÕES --------
        $movs = $pdo->query(
            "(SELECT 'receita' AS tipo, r.id, r.descricao, r.valor,
                     COALESCE(r.data_recebimento, r.data_competencia) AS data,
                     CONCAT('Bloco ', m.bloco, ' - Apto ', m.apartamento) AS detalhe
              FROM receitas r
              LEFT JOIN moradores m ON m.id = r.morador_id
              WHERE r.status IN ('recebido','pendente'))
             UNION ALL
             (SELECT 'despesa' AS tipo, d.id, d.descricao, d.valor,
                     COALESCE(d.data_pagamento, d.data_vencimento) AS data,
                     COALESCE(f.nome, '-') AS detalhe
              FROM despesas d
              LEFT JOIN fornecedores f ON f.id = d.fornecedor_id
              WHERE d.status IN ('pago','pendente'))
             ORDER BY data DESC
             LIMIT 5"
        )->fetchAll();

        // -------- AVISOS --------
        $avisos = $pdo->query(
            "SELECT id, titulo, mensagem, data_publicacao, tipo
             FROM avisos
             WHERE ativo=1
             ORDER BY data_publicacao DESC
             LIMIT 3"
        )->fetchAll();

        jsonResposta([
            'kpis' => [
                'saldo_atual'     => $saldoAtual,
                'receitas_mes'    => $totalReceitas,
                'despesas_mes'    => $totalDespesas,
                'previsao_saldo'  => $previsaoSaldo,
                'var_receitas'    => round(variacao($totalReceitas, $receitasMesAnt), 1),
                'var_despesas'    => round(variacao($totalDespesas, $despesasMesAnt), 1),
                'var_saldo'       => round(variacao($saldoAtual, $saldoAnterior), 1),
                'var_previsao'    => round(variacao($previsaoSaldo, $saldoAtual), 1),
            ],
            'fluxo' => [
                'labels'   => $labels,
                'receitas' => $recDia,
                'despesas' => $despDia,
                'saldo'    => $saldoDia,
            ],
            'categorias' => $cats,
            'movimentacoes' => $movs,
            'avisos' => $avisos,
            'periodo' => [
                'inicio' => $inicioMes,
                'fim'    => $fimMes,
                'mes'    => mesPt($mesAtual),
                'ano'    => $anoAtual,
            ]
        ]);
        break;

    default:
        jsonResposta(['erro' => 'Ação inválida'], 400);
}
