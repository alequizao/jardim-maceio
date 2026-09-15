<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
$paginaTitulo    = 'Relatórios';
$paginaSubtitulo = 'Relatórios gerenciais e demonstrativos';
$paginaAtiva     = 'relatorios';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row row-3-eq">

  <a href="<?= BASE_URL ?>/views/balancete.php" class="card" style="text-decoration:none; transition: all .15s;">
    <div class="kpi-icon verde" style="position:relative; top:0; right:0; margin-bottom:14px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18"/><path d="M5 9h14"/><path d="M5 9l-2 6h4z"/><path d="M19 9l-2 6h4z"/></svg>
    </div>
    <h3 style="font-size:16px; margin-bottom:6px; color: var(--texto);">Balancete Mensal</h3>
    <p class="muted">Demonstrativo financeiro consolidado com saldo anterior, receitas, despesas e saldo final.</p>
  </a>

  <a href="<?= BASE_URL ?>/views/fluxo_caixa.php" class="card" style="text-decoration:none;">
    <div class="kpi-icon azul" style="position:relative; top:0; right:0; margin-bottom:14px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 6-6"/></svg>
    </div>
    <h3 style="font-size:16px; margin-bottom:6px; color: var(--texto);">Fluxo de Caixa</h3>
    <p class="muted">Extrato completo de entradas e saídas com filtro por período.</p>
  </a>

  <a href="<?= BASE_URL ?>/views/contas_receber.php?status=inadimplente" class="card" style="text-decoration:none;">
    <div class="kpi-icon vermelho" style="position:relative; top:0; right:0; margin-bottom:14px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>
    <h3 style="font-size:16px; margin-bottom:6px; color: var(--texto);">Inadimplência</h3>
    <p class="muted">Lista de unidades com taxas em atraso e total geral inadimplente.</p>
  </a>

  <a href="<?= BASE_URL ?>/views/graficos.php" class="card" style="text-decoration:none;">
    <div class="kpi-icon amarelo" style="position:relative; top:0; right:0; margin-bottom:14px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10H12z"/></svg>
    </div>
    <h3 style="font-size:16px; margin-bottom:6px; color: var(--texto);">Gráficos Consolidados</h3>
    <p class="muted">Visão gráfica de evolução mensal e distribuição por categorias.</p>
  </a>

  <a href="<?= BASE_URL ?>/views/notas_fiscais.php" class="card" style="text-decoration:none;">
    <div class="kpi-icon azul" style="position:relative; top:0; right:0; margin-bottom:14px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
    </div>
    <h3 style="font-size:16px; margin-bottom:6px; color: var(--texto);">Notas Fiscais</h3>
    <p class="muted">Histórico completo de notas emitidas por fornecedores.</p>
  </a>

  <a href="<?= BASE_URL ?>/views/documentos.php" class="card" style="text-decoration:none;">
    <div class="kpi-icon verde" style="position:relative; top:0; right:0; margin-bottom:14px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
    </div>
    <h3 style="font-size:16px; margin-bottom:6px; color: var(--texto);">Documentos & Atas</h3>
    <p class="muted">Repositório de balancetes, atas e relatórios institucionais.</p>
  </a>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
