<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/senha.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$dono  = senhaUsuarioDoToken($token);

$erro    = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['_csrf'] ?? null)) {
        $erro = 'Sua sessão expirou. Peça um link novo.';
    } elseif (!$dono) {
        $erro = 'Este link não vale mais. Peça um novo.';
    } elseif (($_POST['senha'] ?? '') !== ($_POST['senha2'] ?? '')) {
        $erro = 'As duas senhas não são iguais.';
    } else {
        $r = senhaRedefinir($token, (string)($_POST['senha'] ?? ''));
        if ($r['ok']) {
            $sucesso = $r['msg'];
            $dono = null;            // some com o formulário
        } else {
            $erro = $r['msg'];
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
<title>Nova senha · <?= e(SISTEMA_NOME) ?></title>
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
      <h2>Escolha uma senha<br>que só você saiba.</h2>
      <p>Use pelo menos 8 caracteres. Depois de salvar, o link deste e-mail deixa de funcionar.</p>
    </div>
  </aside>

  <div class="login-form-wrap">
    <?php if ($sucesso): ?>
      <div class="login-form">
        <h1>Tudo certo!</h1>
        <div class="login-error" style="background:#F0FDF4;border-color:#BBF7D0;color:#166534"><?= e($sucesso) ?></div>
        <a class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;text-decoration:none"
           href="<?= BASE_URL ?>/views/login.php">Entrar no sistema</a>
      </div>
    <?php elseif (!$dono): ?>
      <div class="login-form">
        <h1>Link inválido</h1>
        <p class="sub">Este link já foi usado ou passou da validade de <?= (int)SENHA_TOKEN_VALIDADE_MIN ?> minutos.</p>
        <?php if ($erro): ?><div class="login-error"><?= e($erro) ?></div><?php endif; ?>
        <a class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;text-decoration:none"
           href="<?= BASE_URL ?>/views/esqueci_senha.php">Pedir um link novo</a>
        <p class="login-help"><a href="<?= BASE_URL ?>/views/login.php">Voltar para o login</a></p>
      </div>
    <?php else: ?>
      <form class="login-form" method="post" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <h1>Criar nova senha</h1>
        <p class="sub">Olá, <strong><?= e($dono['nome']) ?></strong>. Defina a senha de acesso ao painel.</p>

        <?php if ($erro): ?><div class="login-error"><?= e($erro) ?></div><?php endif; ?>

        <div class="form-group" style="margin-bottom: 14px;">
          <label>Nova senha</label>
          <input type="password" name="senha" class="form-control" required autofocus minlength="8" placeholder="mínimo de 8 caracteres">
        </div>
        <div class="form-group" style="margin-bottom: 18px;">
          <label>Repita a nova senha</label>
          <input type="password" name="senha2" class="form-control" required minlength="8" placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;">
          Salvar nova senha
        </button>
        <p class="login-help"><a href="<?= BASE_URL ?>/views/login.php">Voltar para o login</a></p>
      </form>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
