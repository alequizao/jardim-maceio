<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

Auth::exigirLogin();
$usuario = Auth::usuario();
// Perfil "morador" tem acesso somente de leitura.
$podeEditar = Auth::podeEditar();

$paginaTitulo    = $paginaTitulo    ?? 'Dashboard Financeiro';
$paginaSubtitulo = $paginaSubtitulo ?? 'Visão geral das finanças do condomínio em tempo real';
$paginaAtiva     = $paginaAtiva     ?? 'dashboard';

// ===== Notificações (contas vencidas + avisos) — cada usuário pode limpar as suas =====
require_once __DIR__ . '/notificacoes.php';
$notif  = Notif::carregar(Database::conectar(), (int)$usuario['id']);
$dvVenc = $notif['dv']; $rvVenc = $notif['rv']; $avisos = $notif['av'];
$dvTot  = $notif['dvTot']; $rvTot = $notif['rvTot']; $avTot = $notif['avTot'];
$notifTotal = $notif['total'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title><?= e($paginaTitulo) ?> · <?= e(SISTEMA_NOME) ?></title>
<link rel="icon" href="<?= BASE_URL ?>/assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php include __DIR__ . '/pwa-head.php'; ?>
</head>
<body data-mod="<?= e($paginaAtiva) ?>">
<div class="layout">

  <!-- ============ OVERLAY (mobile/tablet) ============ -->
  <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

  <!-- ============ SIDEBAR ============ -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-logo">
        <?php $marcaLogo = cfg('marca_logo'); ?>
        <?php if ($marcaLogo): ?>
          <img src="<?= UPLOAD_URL . e($marcaLogo) ?>" alt="Logo">
        <?php else: ?>
          <?= marcaLogoPadraoSvg() ?>
        <?php endif; ?>
      </div>
      <div class="brand-text">
        <span class="brand-title"><?= e(cfg('marca_titulo', 'TRANSPARÊNCIA')) ?></span>
        <span class="brand-sub"><?= e(cfg('marca_subtitulo', 'JARDIM MACEIÓ')) ?></span>
        <?php $marcaTag = cfg('marca_tag', ''); ?>
        <?php if ($marcaTag !== ''): ?><span class="brand-tag"><?= e($marcaTag) ?></span><?php endif; ?>
      </div>
    </div>

    <nav class="nav">
      <a href="<?= BASE_URL ?>/views/dashboard.php" class="nav-item <?= $paginaAtiva==='dashboard'?'active':'' ?>" <?= Vis::nav('dashboard') ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
        Dashboard
      </a>
      <a href="<?= BASE_URL ?>/views/contas_pagar.php" class="nav-item <?= $paginaAtiva==='pagar'?'active':'' ?>" <?= Vis::nav('pagar') ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/></svg>
        Contas a Pagar
      </a>
      <a href="<?= BASE_URL ?>/views/contas_receber.php" class="nav-item <?= $paginaAtiva==='receber'?'active':'' ?>" <?= Vis::nav('receber') ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        Contas a Receber
      </a>
      <a href="<?= BASE_URL ?>/views/notas_fiscais.php" class="nav-item <?= $paginaAtiva==='notas'?'active':'' ?>" <?= Vis::nav('notas') ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h6"/></svg>
        Notas Fiscais
      </a>
      <a href="<?= BASE_URL ?>/views/fluxo_caixa.php" class="nav-item <?= $paginaAtiva==='fluxo'?'active':'' ?>" <?= Vis::nav('fluxo') ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 6-6"/></svg>
        Fluxo de Caixa
      </a>
      <a href="<?= BASE_URL ?>/views/relatorios.php" class="nav-item <?= $paginaAtiva==='relatorios'?'active':'' ?>" <?= Vis::nav('relatorios') ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="5" width="3" height="13"/></svg>
        Relatórios
      </a>
      <a href="<?= BASE_URL ?>/views/balancete.php" class="nav-item <?= $paginaAtiva==='balancete'?'active':'' ?>" <?= Vis::nav('balancete') ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18"/><path d="M5 9h14"/><path d="M5 9l-2 6h4z"/><path d="M19 9l-2 6h4z"/></svg>
        Balancete
      </a>
      <a href="<?= BASE_URL ?>/views/graficos.php" class="nav-item <?= $paginaAtiva==='graficos'?'active':'' ?>" <?= Vis::nav('graficos') ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10H12z"/></svg>
        Gráficos
      </a>
      <a href="<?= BASE_URL ?>/views/documentos.php" class="nav-item <?= $paginaAtiva==='documentos'?'active':'' ?>" <?= Vis::nav('documentos') ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
        Documentos
      </a>
      <a href="<?= BASE_URL ?>/views/assembleias.php" class="nav-item <?= $paginaAtiva==='assembleias'?'active':'' ?>" <?= Vis::nav('assembleias') ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-6 0v4"/><rect x="2" y="9" width="20" height="12" rx="2"/><path d="M12 13v4"/></svg>
        Assembleia
      </a>
      <?php if ($podeEditar): ?>
      <a href="<?= BASE_URL ?>/views/moradores.php" class="nav-item <?= $paginaAtiva==='moradores'?'active':'' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Moradores
      </a>
      <a href="<?= BASE_URL ?>/views/configuracoes.php" class="nav-item <?= $paginaAtiva==='config'?'active':'' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Configurações
      </a>
      <?php endif; ?>
      <button type="button" id="pwaInstalar" class="btn-instalar" hidden>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
    Instalar aplicativo
  </button>
</nav>

    <div class="sidebar-user">
      <div class="user-avatar"><?= e(Auth::iniciais($usuario['nome'])) ?></div>
      <div class="user-info">
        <span class="user-name"><?= e($usuario['nome']) ?></span>
        <span class="user-role"><?= e(ucfirst($usuario['tipo'])) ?></span>
      </div>
      <a href="<?= BASE_URL ?>/api/auth.php?acao=logout" class="user-logout" title="Sair">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
      </a>
    </div>
  </aside>

  <!-- ============ CONTEÚDO ============ -->
  <main class="main">
    <header class="topbar">
      <button class="menu-toggle" type="button" id="menuToggle" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="topbar-titles">
        <h1><?= e($paginaTitulo) ?></h1>
        <p><?= e($paginaSubtitulo) ?></p>
      </div>
      <div class="topbar-meta">
        <div class="meta-pill" id="metaPill" title="Sincronização automática com o servidor">
          <span class="mp-data">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <span id="mpData"><?= mb_substr(diaSemanaPt(date('Y-m-d')), 0, 3) . ', ' . date('d') . ' de ' . mb_strtolower(mesPt((int)date('m'))) ?></span>
            <b id="mpHora"><?= date('H:i') ?></b>
          </span>
          <span class="mp-sep"></span>
          <span class="mp-status ok" id="mpStatus"><i></i><span id="mpTxt">Ao vivo</span></span>
        </div>
        <div class="notif">
          <button class="bell" type="button" id="bellBtn" aria-label="Notificações" aria-haspopup="true" aria-expanded="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <?php if ($notifTotal > 0): ?><span class="bell-badge"><?= $notifTotal > 9 ? '9+' : $notifTotal ?></span><?php endif; ?>
          </button>

          <div class="notif-panel" id="notifPanel" hidden>
            <div class="notif-head">Notificações <span id="notifCont"><?= (int)$notifTotal ?></span>
              <button type="button" class="notif-limpar" id="notifLimparTudo" <?= $notifTotal ? '' : 'hidden' ?>>Limpar tudo</button>
            </div>
            <div class="notif-body">
              <?php if ($notifTotal === 0): ?>
                <div class="notif-empty">Nenhuma notificação no momento. 🎉</div>
              <?php else: ?>

                <?php if ($dvTot > 0): ?>
                  <div class="notif-group">Contas a pagar vencidas (<?= $dvTot ?>)</div>
                  <?php foreach ($dvVenc as $d): ?>
                    <a class="notif-item" data-chave="d:<?= (int)$d['id'] ?>" href="<?= BASE_URL ?>/views/contas_pagar.php?status=vencido">
                      <span class="notif-dot danger"></span>
                      <div class="notif-txt"><strong><?= e($d['descricao']) ?></strong><small>Venceu em <?= dataBr($d['data_vencimento']) ?></small></div>
                      <span class="notif-val"><?= brl((float)$d['valor']) ?></span><button type="button" class="notif-x" aria-label="Limpar esta notificação" title="Limpar">×</button>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($rvTot > 0): ?>
                  <div class="notif-group">Contas a receber em atraso (<?= $rvTot ?>)</div>
                  <?php foreach ($rvVenc as $r): ?>
                    <a class="notif-item" data-chave="r:<?= (int)$r['id'] ?>" href="<?= BASE_URL ?>/views/contas_receber.php">
                      <span class="notif-dot warn"></span>
                      <div class="notif-txt"><strong><?= e($r['descricao']) ?></strong><small>Desde <?= dataBr($r['data_competencia']) ?></small></div>
                      <span class="notif-val"><?= brl((float)$r['valor']) ?></span><button type="button" class="notif-x" aria-label="Limpar esta notificação" title="Limpar">×</button>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($avTot > 0): ?>
                  <div class="notif-group">Avisos ativos (<?= $avTot ?>)</div>
                  <?php foreach ($avisos as $a): ?>
                    <a class="notif-item" data-chave="a:<?= (int)$a['id'] ?>" href="<?= BASE_URL ?>/views/dashboard.php">
                      <span class="notif-dot info"></span>
                      <div class="notif-txt"><strong><?= e($a['titulo']) ?></strong><small><?= dataBr($a['data_publicacao']) ?></small></div><button type="button" class="notif-x" aria-label="Limpar esta notificação" title="Limpar">×</button>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>

              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- app.js aqui (e nao no rodape) para que os scripts embutidos das telas enxerguem o objeto App -->
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?: time() ?>"></script>
<section class="content">
