<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/senha.php';

if (Auth::logado()) {
    header('Location: ' . BASE_URL . '/views/dashboard.php');
    exit;
}

$aviso = '';
$erro  = '';
$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['_csrf'] ?? null)) {
        $erro = 'Sua sessão expirou. Tente de novo.';
    } elseif ($email === '') {
        $erro = 'Informe o e-mail cadastrado.';
    } else {
        $r = senhaSolicitar($email);
        if (!$r['ok'] && ($r['motivo'] ?? '') === 'smtp') {
            $erro = 'Não consegui enviar o e-mail agora. Tente novamente em alguns minutos.';
            error_log('[jardimmaceio] falha SMTP na redefinicao: ' . ($r['detalhe'] ?? ''));
        } else {
            // Resposta igual para cadastro existente ou não — não entrega quem tem conta.
            $aviso = 'Se houver uma conta com esse e-mail, o link para criar a senha nova acabou de sair. '
                   . 'Confira também a caixa de spam — o link vale por ' . SENHA_TOKEN_VALIDADE_MIN . ' minutos.';
            $email = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="icon" href="<?= BASE_URL ?>/assets/favicon.svg" type="image/svg+xml">
<title>Recuperar senha · <?= e(SISTEMA_NOME) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php include __DIR__ . '/../includes/pwa-head.php'; ?>
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
      </div>
    </div>

    <div class="login-hero">
      <h2>Esqueceu a senha?<br>A gente resolve em 1 minuto.</h2>
      <p>Enviamos um link seguro para o seu e-mail cadastrado. Ele vale por <?= (int)SENHA_TOKEN_VALIDADE_MIN ?> minutos e só pode ser usado uma vez.</p>
    </div>

    <div class="login-feats">
      <div class="login-feat">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
        Link enviado por e-mail
      </div>
      <div class="login-feat">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
        Uso único e com validade
      </div>
    </div>
  </aside>

  <div class="login-form-wrap">
    <form class="login-form" method="post" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
      <h1>Recuperar senha</h1>
      <p class="sub">Informe o e-mail cadastrado e enviaremos o link para criar uma senha nova</p>

      <?php if ($erro): ?><div class="login-error"><?= e($erro) ?></div><?php endif; ?>
      <?php if ($aviso): ?>
        <div class="login-error" style="background:#F0FDF4;border-color:#BBF7D0;color:#166534"><?= e($aviso) ?></div>
      <?php endif; ?>

      <div class="form-group" style="margin-bottom: 18px;">
        <label>E-mail</label>
        <input type="email" name="email" class="form-control" required autofocus
               value="<?= e($email) ?>" placeholder="seu@email.com">
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;">
        Enviar link de recuperação
      </button>

      <p class="login-help">
        Lembrou a senha? <a href="<?= BASE_URL ?>/views/login.php">Voltar para o login</a>
      </p>
    </form>
  </div>

</div>
</body>
</html>
