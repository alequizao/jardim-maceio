<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
$paginaTitulo    = 'Dashboard Financeiro';
$paginaSubtitulo = 'Visão geral das finanças do condomínio em tempo real';
$paginaAtiva     = 'dashboard';
$scriptPagina    = 'dashboard.js';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
$pdo = Database::conectar();

// ===== Calcula KPIs em PHP para a primeira renderização (sem esperar JS) =====
$hoje      = new DateTimeImmutable('now');
$mes       = (int)$hoje->format('m');
$ano       = (int)$hoje->format('Y');
$diasMes   = (int)$hoje->format('t');
$inicioMes = $hoje->format('Y-m-01');
$fimMes    = $hoje->format("Y-m-$diasMes");
$inicioMesAnt = (new DateTimeImmutable($inicioMes))->modify('-1 month')->format('Y-m-01');
$fimMesAnt    = (new DateTimeImmutable($inicioMes))->modify('-1 day')->format('Y-m-d');

$tot = function($sql) use ($pdo) { return (float)$pdo->query($sql)->fetchColumn(); };

$saldoAtual = $tot("SELECT (SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='recebido')
                         - (SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='pago')");
$saldoIniMes= $tot("SELECT (SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='recebido' AND data_recebimento<'$inicioMes')
                         - (SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='pago'     AND data_pagamento  <'$inicioMes')");
$recMes    = $tot("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='recebido' AND data_recebimento BETWEEN '$inicioMes' AND '$fimMes'");
$desMes    = $tot("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='pago'     AND data_pagamento   BETWEEN '$inicioMes' AND '$fimMes'");
$recMesAnt = $tot("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='recebido' AND data_recebimento BETWEEN '$inicioMesAnt' AND '$fimMesAnt'");
$desMesAnt = $tot("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='pago'     AND data_pagamento   BETWEEN '$inicioMesAnt' AND '$fimMesAnt'");
$recPend   = $tot("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='pendente' AND data_competencia BETWEEN '$inicioMes' AND '$fimMes'");
$desPend   = $tot("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status IN ('pendente','vencido') AND data_vencimento BETWEEN '$inicioMes' AND '$fimMes'");
$previsao  = $saldoAtual + $recPend - $desPend;

$varSaldo    = $saldoIniMes != 0 ? round((($saldoAtual - $saldoIniMes)/$saldoIniMes)*100, 1) : 0;
$varRec      = $recMesAnt   != 0 ? round((($recMes - $recMesAnt)/$recMesAnt)*100, 1)         : 0;
$varDes      = $desMesAnt   != 0 ? round((($desMes - $desMesAnt)/$desMesAnt)*100, 1)         : 0;
$varPrevisao = $saldoAtual  != 0 ? round((($previsao - $saldoAtual)/$saldoAtual)*100, 1)     : 0;

// Próximas contas a pagar
$proximas = $pdo->query(
    "SELECT d.descricao, d.data_vencimento, d.valor, d.status,
            COALESCE(f.nome,'-') AS fornecedor
     FROM despesas d
     LEFT JOIN fornecedores f ON f.id = d.fornecedor_id
     WHERE d.status IN ('pendente','vencido')
       AND d.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY d.data_vencimento ASC
     LIMIT 5"
)->fetchAll();

// Movimentações do dia
$hojeStr = $hoje->format('Y-m-d');
$movsDia = $pdo->query(
    "(SELECT 'receita' AS tipo, r.id, r.descricao, r.valor,
             TIME_FORMAT(r.atualizado_em,'%H:%i') AS hora,
             COALESCE(r.data_recebimento, r.data_competencia) AS data
      FROM receitas r
      WHERE DATE(r.data_recebimento) = '$hojeStr' AND r.status='recebido')
     UNION ALL
     (SELECT 'despesa' AS tipo, d.id, d.descricao, d.valor,
             TIME_FORMAT(d.atualizado_em,'%H:%i') AS hora,
             COALESCE(d.data_pagamento, d.data_vencimento) AS data
      FROM despesas d
      WHERE DATE(d.data_pagamento) = '$hojeStr' AND d.status='pago')
     ORDER BY hora DESC
     LIMIT 5"
)->fetchAll();

// Contas a receber (totais)
$totReceberMes = $tot("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE data_competencia BETWEEN '$inicioMes' AND '$fimMes'");
$totRecebido   = $tot("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='recebido' AND data_competencia BETWEEN '$inicioMes' AND '$fimMes'");
$totEmAberto   = $tot("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='pendente' AND data_competencia BETWEEN '$inicioMes' AND '$fimMes' AND data_competencia >= CURDATE()");
$totInad       = $tot("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='pendente' AND data_competencia < CURDATE()");

$pRecebido = $totReceberMes > 0 ? round(($totRecebido/$totReceberMes)*100, 1) : 0;
$pAberto   = $totReceberMes > 0 ? round(($totEmAberto/$totReceberMes)*100, 1) : 0;
$pInad     = $totReceberMes > 0 ? round(($totInad/$totReceberMes)*100, 1)     : 0;
?>

<!-- ============== KPIs ============== -->
<div class="kpi-grid">

  <div class="kpi-card">
    <div class="kpi-icon verde">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20"/></svg>
    </div>
    <div class="kpi-label">SALDO ATUAL <span class="info">i</span></div>
    <div class="kpi-value verde"><?= brl($saldoAtual) ?></div>
    <div class="kpi-sub">Disponível em conta</div>
    <div class="kpi-trend <?= $varSaldo < 0 ? 'down' : '' ?>">
      <span class="arrow"><?= $varSaldo < 0 ? '▼' : '▲' ?></span>
      <?= abs($varSaldo) ?>% em relação ao mês anterior
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon azul">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
    </div>
    <div class="kpi-label">RECEITAS DO MÊS <span class="info">i</span></div>
    <div class="kpi-value azul"><?= brl($recMes) ?></div>
    <div class="kpi-sub">Total arrecadado</div>
    <div class="kpi-trend <?= $varRec < 0 ? 'down' : '' ?>">
      <span class="arrow"><?= $varRec < 0 ? '▼' : '▲' ?></span>
      <?= abs($varRec) ?>% em relação ao mês anterior
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon vermelho">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
    </div>
    <div class="kpi-label">DESPESAS DO MÊS <span class="info">i</span></div>
    <div class="kpi-value vermelho"><?= brl($desMes) ?></div>
    <div class="kpi-sub">Total de despesas</div>
    <div class="kpi-trend <?= $varDes > 0 ? 'down' : '' ?>">
      <span class="arrow"><?= $varDes > 0 ? '▲' : '▼' ?></span>
      <?= abs($varDes) ?>% em relação ao mês anterior
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon amarelo">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="5" width="3" height="13"/></svg>
    </div>
    <div class="kpi-label">PREVISÃO DE SALDO <span class="info">i</span></div>
    <div class="kpi-value amarelo"><?= brl($previsao) ?></div>
    <div class="kpi-sub">Até o final do mês</div>
    <div class="kpi-trend <?= $varPrevisao < 0 ? 'down' : '' ?>">
      <span class="arrow"><?= $varPrevisao < 0 ? '▼' : '▲' ?></span>
      <?= abs($varPrevisao) ?>% em relação ao mês anterior
    </div>
  </div>

</div>

<!-- ============== FLUXO + CATEGORIAS + MOVIMENTAÇÕES DO DIA ============== -->
<div class="row row-3">

  <div class="card">
    <div class="card-header">
      <span class="card-title">FLUXO DE CAIXA — <?= strtoupper(mesPt($mes)) ?>/<?= $ano ?></span>
      <select class="card-select">
        <option>Mensal</option><option>Semanal</option>
      </select>
    </div>
    <div class="chart-legend">
      <span><span class="dot dot-verde"></span> Receitas</span>
      <span><span class="dot dot-vermelho"></span> Despesas</span>
      <span><span class="dot dot-preto"></span> Saldo</span>
    </div>
    <div class="chart-wrap"><canvas id="chartFluxo"></canvas></div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">DESPESAS POR CATEGORIA — <?= strtoupper(mesPt($mes)) ?>/<?= $ano ?></span>
    </div>
    <div class="donut-row">
      <div class="donut-wrap">
        <canvas id="chartCategorias"></canvas>
        <div class="donut-center">
          <small>Total</small>
          <strong><?= brl($desMes) ?></strong>
        </div>
      </div>
      <ul class="cat-list" id="catList">
        <li class="muted">Carregando categorias…</li>
      </ul>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">MOVIMENTAÇÕES DO DIA</span>
    </div>
    <div class="mov-list">
      <?php if (empty($movsDia)): ?>
        <p class="muted" style="padding: 20px 0;">Nenhuma movimentação hoje.</p>
      <?php else: foreach ($movsDia as $m):
        $up = $m['tipo'] === 'receita';
      ?>
        <div class="mov-item">
          <div class="mov-icon <?= $up ? 'up' : 'down' ?>">
            <?php if ($up): ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
            <?php else: ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
            <?php endif; ?>
          </div>
          <div class="mov-desc">
            <strong><?= e($m['descricao']) ?></strong>
            <small><?= e($m['hora']) ?></small>
          </div>
          <div></div>
          <div class="mov-valor <?= $up ? 'up' : 'down' ?>">
            <?= $up ? '' : '-' ?><?= brl((float)$m['valor']) ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <a href="<?= BASE_URL ?>/views/fluxo_caixa.php" class="btn-block" style="text-align:center; display:block; text-decoration:none;">
      Ver todas as movimentações
    </a>
  </div>

</div>

<!-- ============== CONTAS A PAGAR + CONTAS A RECEBER + RESUMO ============== -->
<div class="row row-3">

  <div class="card">
    <div class="card-header">
      <span class="card-title">CONTAS A PAGAR (PRÓXIMOS VENCIMENTOS)</span>
      <a href="<?= BASE_URL ?>/views/contas_pagar.php" class="card-link">Ver todas</a>
    </div>
    <table class="table-list">
      <thead>
        <tr>
          <th>Descrição</th>
          <th>Fornecedor</th>
          <th>Vencimento</th>
          <th class="text-right">Valor</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($proximas)): ?>
          <tr><td colspan="5" class="muted text-center" style="padding: 24px;">Nenhuma conta a pagar nos próximos 30 dias.</td></tr>
        <?php else: foreach ($proximas as $p): ?>
          <tr>
            <td><?= e($p['descricao']) ?></td>
            <td class="muted"><?= e($p['fornecedor']) ?></td>
            <td class="muted"><?= dataBr($p['data_vencimento']) ?></td>
            <td class="text-right" style="font-weight:600;"><?= brl((float)$p['valor']) ?></td>
            <td class="text-center"><span class="badge <?= statusBadge($p['status']) ?>"><?= statusTexto($p['status']) ?></span></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">CONTAS A RECEBER (RESUMO)</span>
      <a href="<?= BASE_URL ?>/views/contas_receber.php" class="card-link">Ver todas</a>
    </div>

    <div class="recebimento-item">
      <div class="ic" style="background: var(--verde-100); color: var(--verde-700);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div>
        <div class="titulo">Taxa Condominial — <?= mesPt($mes) ?>/<?= $ano ?></div>
        <div class="progress"><div class="fill verde" style="width: <?= $pRecebido ?>%"></div></div>
        <div class="perc">Recebido — <?= $pRecebido ?>%</div>
      </div>
      <div class="valor" style="color: var(--verde-700);"><?= brl($totRecebido) ?></div>
    </div>

    <div class="recebimento-item">
      <div class="ic" style="background: var(--amarelo-100); color: var(--amarelo);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div>
        <div class="titulo">Em Aberto</div>
        <div class="progress"><div class="fill amarelo" style="width: <?= $pAberto ?>%"></div></div>
        <div class="perc"><?= $pAberto ?>%</div>
      </div>
      <div class="valor" style="color: var(--amarelo);"><?= brl($totEmAberto) ?></div>
    </div>

    <div class="recebimento-item">
      <div class="ic" style="background: var(--vermelho-100); color: var(--vermelho);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><polyline points="7 14 12 9 16 13 21 8"/></svg>
      </div>
      <div>
        <div class="titulo">Inadimplentes</div>
        <div class="progress"><div class="fill vermelho" style="width: <?= $pInad ?>%"></div></div>
        <div class="perc"><?= $pInad ?>%</div>
      </div>
      <div class="valor" style="color: var(--vermelho);"><?= brl($totInad) ?></div>
    </div>

    <a href="<?= BASE_URL ?>/views/contas_receber.php?status=inadimplente" class="btn-block" style="text-align:center; display:block; text-decoration:none;">
      Ver inadimplentes
    </a>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">RESUMO FINANCEIRO — <?= strtoupper(mesPt($mes)) ?>/<?= $ano ?></span>
    </div>
    <div class="resumo-list">
      <div class="resumo-row">
        <span class="lbl">Saldo Anterior (<?= dataBr($fimMesAnt) ?>)</span>
        <span class="val"><?= brl($saldoIniMes) ?></span>
      </div>
      <div class="resumo-row">
        <span class="lbl">(+) Receitas do Mês</span>
        <span class="val verde"><?= brl($recMes) ?></span>
      </div>
      <div class="resumo-row">
        <span class="lbl">(−) Despesas do Mês</span>
        <span class="val vermelho">-<?= brl($desMes) ?></span>
      </div>
      <div class="resumo-row total">
        <span class="lbl">(=) Saldo Atual</span>
        <span class="val verde"><?= brl($saldoAtual) ?></span>
      </div>
    </div>
  </div>

</div>

<!-- ============== AÇÕES RÁPIDAS (igual à parte inferior da imagem 2) ============== -->
<div class="action-row">
  <a href="<?= BASE_URL ?>/views/contas_receber.php?novo=1" class="action-btn">
    <span class="ic up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="5 15 12 8 19 15"/></svg></span>
    Nova Receita
  </a>
  <a href="<?= BASE_URL ?>/views/contas_pagar.php?novo=1" class="action-btn">
    <span class="ic down"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="5 9 12 16 19 9"/></svg></span>
    Nova Despesa
  </a>
  <a href="<?= BASE_URL ?>/views/notas_fiscais.php?novo=1" class="action-btn">
    <span class="ic azul"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span>
    Anexar Nota Fiscal
  </a>
  <a href="<?= BASE_URL ?>/views/relatorios.php" class="action-btn">
    <span class="ic roxo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/></svg></span>
    Gerar Relatório
  </a>
  <a href="<?= BASE_URL ?>/views/balancete.php" class="action-btn">
    <span class="ic amarelo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18"/><path d="M5 9h14"/><path d="M5 9l-2 6h4z"/><path d="M19 9l-2 6h4z"/></svg></span>
    Balancete Mensal
  </a>
  <a href="<?= BASE_URL ?>/api/relatorios.php?acao=balancete&mes=<?= date('Y-m') ?>&export=pdf" class="action-btn">
    <span class="ic cinza"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>
    Exportar PDF
  </a>
</div>

<script>
// Renderiza lista de categorias após carregar dados do dashboard
document.addEventListener('DOMContentLoaded', async () => {
  const dados = await App.api('dashboard.php?acao=resumo');
  if (!dados || !dados.categorias) return;
  const ul = document.getElementById('catList');
  if (!ul) return;
  if (!dados.categorias.length) {
    ul.innerHTML = '<li class="muted">Sem despesas neste mês.</li>';
    return;
  }
  ul.innerHTML = dados.categorias.map(c => `
    <li>
      <span class="dot" style="background:${c.cor}"></span>
      <span class="cat-nome">${c.nome}</span>
      <span class="cat-valor">${App.brl(c.valor)}</span>
      <span class="cat-perc">(${c.percentual}%)</span>
    </li>
  `).join('');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
