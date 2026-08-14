<?php
$paginaTitulo    = 'Fluxo de Caixa';
$paginaSubtitulo = 'Extrato completo de entradas e saídas';
$paginaAtiva     = 'fluxo';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
$pdo = Database::conectar();

$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fim    = $_GET['fim']    ?? date('Y-m-t');

$movs = $pdo->prepare(
    "(SELECT 'receita' AS tipo, r.id, r.descricao, r.valor,
             COALESCE(r.data_recebimento, r.data_competencia) AS data,
             c.nome AS categoria,
             CONCAT(IFNULL(m.bloco,'-'),'/',IFNULL(m.apartamento,'-')) AS detalhe,
             r.status
      FROM receitas r
      LEFT JOIN categorias c ON c.id=r.categoria_id
      LEFT JOIN moradores m  ON m.id=r.morador_id
      WHERE COALESCE(r.data_recebimento, r.data_competencia) BETWEEN :i AND :f)
     UNION ALL
     (SELECT 'despesa' AS tipo, d.id, d.descricao, d.valor,
             COALESCE(d.data_pagamento, d.data_vencimento) AS data,
             c.nome AS categoria,
             COALESCE(f.nome,'-') AS detalhe,
             d.status
      FROM despesas d
      LEFT JOIN categorias c   ON c.id=d.categoria_id
      LEFT JOIN fornecedores f ON f.id=d.fornecedor_id
      WHERE COALESCE(d.data_pagamento, d.data_vencimento) BETWEEN :i2 AND :f2)
     ORDER BY data DESC, id DESC LIMIT 500"
);
$movs->execute([':i'=>$inicio, ':f'=>$fim, ':i2'=>$inicio, ':f2'=>$fim]);
$movs = $movs->fetchAll();

$totEnt = 0; $totSai = 0;
foreach ($movs as $m) {
    if ($m['tipo']==='receita' && $m['status']==='recebido') $totEnt += (float)$m['valor'];
    if ($m['tipo']==='despesa' && $m['status']==='pago')     $totSai += (float)$m['valor'];
}
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-icon verde"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg></div>
    <div class="kpi-label">ENTRADAS</div>
    <div class="kpi-value verde"><?= brl($totEnt) ?></div>
    <div class="kpi-sub">no período selecionado</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon vermelho"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/></svg></div>
    <div class="kpi-label">SAÍDAS</div>
    <div class="kpi-value vermelho"><?= brl($totSai) ?></div>
    <div class="kpi-sub">no período selecionado</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon azul"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="13" rx="2"/></svg></div>
    <div class="kpi-label">SALDO DO PERÍODO</div>
    <div class="kpi-value <?= ($totEnt-$totSai)>=0?'verde':'vermelho' ?>"><?= brl($totEnt-$totSai) ?></div>
    <div class="kpi-sub">entradas - saídas</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon amarelo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg></div>
    <div class="kpi-label">MOVIMENTAÇÕES</div>
    <div class="kpi-value amarelo"><?= count($movs) ?></div>
    <div class="kpi-sub">registros encontrados</div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="flex-wrap:wrap; gap:12px;">
    <span class="card-title">EXTRATO DE MOVIMENTAÇÕES</span>
    <form method="get" style="display:flex; gap:8px; align-items:center;">
      <label class="muted" style="font-size:11px;">De</label>
      <input type="date" name="inicio" value="<?= e($inicio) ?>" class="form-control" style="padding:6px 10px;">
      <label class="muted" style="font-size:11px;">Até</label>
      <input type="date" name="fim" value="<?= e($fim) ?>" class="form-control" style="padding:6px 10px;">
      <button class="btn btn-outline">Filtrar</button>
    </form>
  </div>

  <table class="table-list">
    <thead>
      <tr>
        <th>Data</th>
        <th>Tipo</th>
        <th>Descrição</th>
        <th>Categoria</th>
        <th>Detalhe</th>
        <th class="text-right">Valor</th>
        <th class="text-center">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($movs)): ?>
        <tr><td colspan="7" class="muted text-center" style="padding:30px;">Nenhuma movimentação no período.</td></tr>
      <?php else: foreach ($movs as $m): $up = $m['tipo']==='receita'; ?>
        <tr>
          <td class="muted"><?= dataBr($m['data']) ?></td>
          <td>
            <span class="badge <?= $up?'badge-success':'badge-danger' ?>"><?= $up?'ENTRADA':'SAÍDA' ?></span>
          </td>
          <td style="font-weight:600;"><?= e($m['descricao']) ?></td>
          <td class="muted"><?= e($m['categoria'] ?? '-') ?></td>
          <td class="muted"><?= e($m['detalhe']) ?></td>
          <td class="text-right" style="font-weight:700; color: <?= $up?'#0F7B3E':'#DC2626' ?>;">
            <?= $up?'+':'-' ?><?= brl((float)$m['valor']) ?>
          </td>
          <td class="text-center"><span class="badge <?= statusBadge($m['status']) ?>"><?= statusTexto($m['status']) ?></span></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
