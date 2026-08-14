<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

if (Auth::logado()) {
    header('Location: ' . BASE_URL . '/views/dashboard.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if ($email === '' || $senha === '') {
        $erro = 'Informe e-mail e senha.';
    } else {
        $r = Auth::login($email, $senha);
        if ($r['ok']) {
            $dest = $_GET['redirect'] ?? (BASE_URL . '/views/dashboard.php');
            header('Location: ' . $dest);
            exit;
        }
        $erro = $r['msg'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar · <?= e(SISTEMA_NOME) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="login-wrap">

  <aside class="login-aside">
    <div class="login-brand">
      <div class="login-brand-logo">
        <?php $marcaLogo = cfg('marca_logo'); ?>
        <?php if ($marcaLogo): ?>
          <img src="<?= UPLOAD_URL . e($marcaLogo) ?>" alt="Logo">
        <?php else: ?>
          <?= marcaLogoPadraoSvg() ?>
        <?php endif; ?>
      </div>
      <div class="login-brand-text">
        <strong><?= e(cfg('marca_titulo', 'TRANSPARÊNCIA')) ?></strong>
        <span><?= e(cfg('marca_subtitulo', 'JARDIM MACEIÓ')) ?></span>
        <?php $marcaTag = cfg('marca_tag', ''); ?>
        <?php if ($marcaTag !== ''): ?><small><?= e($marcaTag) ?></small><?php endif; ?>
      </div>
    </div>

    <div class="login-hero">
      <h2>Gestão financeira<br>com transparência total.</h2>
      <p>Acompanhe em tempo real receitas, despesas e o balanço completo do condomínio. Dados protegidos, decisões compartilhadas.</p>
    </div>

    <div class="login-feats">
      <div class="login-feat">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Dados seguros e auditáveis
      </div>
      <div class="login-feat">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 6-6"/></svg>
        Relatórios em tempo real
      </div>
      <div class="login-feat">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        Acesso 24h pelo morador
      </div>
    </div>
  </aside>

  <div class="login-form-wrap">
    <form class="login-form" method="post" autocomplete="off">
      <h1>Bem-vindo de volta</h1>
      <p class="sub">Acesse o painel administrativo do condomínio</p>

      <?php if ($erro): ?>
        <div class="login-error"><?= e($erro) ?></div>
      <?php endif; ?>

      <div class="form-group" style="margin-bottom: 14px;">
        <label>E-mail</label>
        <input type="email" name="email" class="form-control" required autofocus
               value="<?= e($_POST['email'] ?? '') ?>" placeholder="seu@email.com">
      </div>
      <div class="form-group" style="margin-bottom: 18px;">
        <label>Senha</label>
        <input type="password" name="senha" class="form-control" required placeholder="••••••••">
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;">
        Entrar no sistema
      </button>

      <p class="login-help">
        Acesso padrão: <strong>admin@jardimmaceio.com.br</strong> / <strong>admin123</strong>
      </p>
    </form>
  </div>

</div>
</body>
</html>
