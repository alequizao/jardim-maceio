<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
$paginaTitulo    = 'Balancete Mensal';
$paginaSubtitulo = 'Demonstrativo financeiro consolidado';
$paginaAtiva     = 'balancete';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
$pdo = Database::conectar();

$mes = $_GET['mes'] ?? date('Y-m');
$inicio = $mes . '-01';
$fim    = date('Y-m-t', strtotime($inicio));

$saldoAnt = (float)$pdo->query(
    "SELECT (SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='recebido' AND data_recebimento<'$inicio')
          - (SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='pago'     AND data_pagamento  <'$inicio')"
)->fetchColumn();

$receitas = $pdo->query(
    "SELECT c.nome AS categoria, COALESCE(SUM(r.valor),0) AS total
     FROM categorias c
     LEFT JOIN receitas r ON r.categoria_id=c.id AND r.status='recebido' AND r.data_recebimento BETWEEN '$inicio' AND '$fim'
     WHERE c.tipo='receita'
     GROUP BY c.id, c.nome
     HAVING total>0
     ORDER BY total DESC"
)->fetchAll();

$despesas = $pdo->query(
    "SELECT c.nome AS categoria, COALESCE(SUM(d.valor),0) AS total
     FROM categorias c
     LEFT JOIN despesas d ON d.categoria_id=c.id AND d.status='pago' AND d.data_pagamento BETWEEN '$inicio' AND '$fim'
     WHERE c.tipo='despesa'
     GROUP BY c.id, c.nome
     HAVING total>0
     ORDER BY total DESC"
)->fetchAll();

$totRec = array_sum(array_map(fn($r)=>(float)$r['total'], $receitas));
$totDes = array_sum(array_map(fn($d)=>(float)$d['total'], $despesas));
$saldoFim = $saldoAnt + $totRec - $totDes;
?>

<div class="card" style="margin-bottom: 16px;">
  <div class="card-header" style="flex-wrap:wrap; gap:12px;">
    <span class="card-title">BALANCETE — <?= strtoupper(mesPt((int)date('m', strtotime($inicio)))) ?>/<?= date('Y', strtotime($inicio)) ?></span>
    <form method="get" style="display:flex; gap:8px;">
      <input type="month" name="mes" value="<?= e($mes) ?>" class="form-control" style="padding:6px 10px;">
      <button class="btn btn-outline">Atualizar</button>
      <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir / PDF</button>
      <?php if (!empty($_GET['imprimir'])): ?>
      <script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 500); });</script>
      <?php endif; ?>
    </form>
  </div>

  <div style="background: var(--verde-50); border-radius: 12px; padding: 18px 22px; margin-bottom: 18px;">
    <div class="resumo-row" style="border:0; padding:6px 0;">
      <span class="lbl"><strong>SALDO ANTERIOR</strong></span>
      <span class="val"><?= brl($saldoAnt) ?></span>
    </div>
  </div>
</div>

<div class="row row-2">
  <!-- RECEITAS -->
  <div class="card">
    <div class="card-header"><span class="card-title" style="color: var(--verde-700);">+ RECEITAS</span></div>
    <table class="table-list">
      <thead><tr><th>Categoria</th><th class="text-right">Valor</th></tr></thead>
      <tbody>
        <?php if (empty($receitas)): ?>
          <tr><td colspan="2" class="muted text-center" style="padding:24px;">Sem receitas no período.</td></tr>
        <?php else: foreach ($receitas as $r): ?>
          <tr>
            <td><?= e($r['categoria']) ?></td>
            <td class="text-right" style="font-weight:600;"><?= brl((float)$r['total']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <tfoot>
        <tr><td style="font-weight:700; padding-top:14px; border-top:2px solid var(--verde-700);">TOTAL RECEITAS</td>
            <td class="text-right" style="font-weight:700; color: var(--verde-700); padding-top:14px; border-top:2px solid var(--verde-700);"><?= brl($totRec) ?></td></tr>
      </tfoot>
    </table>
  </div>

  <!-- DESPESAS -->
  <div class="card">
    <div class="card-header"><span class="card-title" style="color: var(--vermelho);">− DESPESAS</span></div>
    <table class="table-list">
      <thead><tr><th>Categoria</th><th class="text-right">Valor</th></tr></thead>
      <tbody>
        <?php if (empty($despesas)): ?>
          <tr><td colspan="2" class="muted text-center" style="padding:24px;">Sem despesas no período.</td></tr>
        <?php else: foreach ($despesas as $d): ?>
          <tr>
            <td><?= e($d['categoria']) ?></td>
            <td class="text-right" style="font-weight:600;"><?= brl((float)$d['total']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <tfoot>
        <tr><td style="font-weight:700; padding-top:14px; border-top:2px solid var(--vermelho);">TOTAL DESPESAS</td>
            <td class="text-right" style="font-weight:700; color: var(--vermelho); padding-top:14px; border-top:2px solid var(--vermelho);"><?= brl($totDes) ?></td></tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- SALDO FINAL -->
<div class="card" style="background: linear-gradient(135deg, var(--verde-900), var(--verde-700)); color:#fff; border:0;">
  <div style="display:flex; justify-content:space-between; align-items:center;">
    <div>
      <div style="font-size:11.5px; letter-spacing:.6px; opacity:.8;">SALDO FINAL DO PERÍODO</div>
      <div style="font-size:14px; opacity:.85; margin-top:4px;">Saldo Anterior <?= brl($saldoAnt) ?> + Receitas <?= brl($totRec) ?> − Despesas <?= brl($totDes) ?></div>
    </div>
    <div style="font-size:36px; font-weight:700; letter-spacing:-.5px;">
      <?= brl($saldoFim) ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
