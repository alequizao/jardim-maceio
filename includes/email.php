<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * email.php
 * Cliente SMTP próprio (sem dependências) + modelo de e-mail do sistema.
 */

function smtpConfig(): array {
    static $cfg = null;
    if ($cfg === null) {
        $arq = __DIR__ . '/../config/smtp.php';
        $cfg = is_file($arq) ? (array)require $arq : [];
    }
    return $cfg;
}

/**
 * Envia um e-mail HTML pelo SMTP configurado.
 * Devolve true/false e preenche $detalhe com o motivo da falha.
 */
function smtpEnviar(string $para, string $assunto, string $corpoHtml, string &$detalhe = ''): bool {
    $c = smtpConfig();
    $host = trim((string)($c['host'] ?? ''));
    if ($host === '') { $detalhe = 'SMTP não configurado (config/smtp.php)'; return false; }
    $porta = (int)($c['porta'] ?? 587);
    $seg   = (string)($c['seguranca'] ?? 'tls');
    $user  = (string)($c['usuario'] ?? '');
    $senha = (string)($c['senha'] ?? '');
    $de    = (string)($c['de'] ?? '') ?: $user;
    $nome  = (string)($c['nome'] ?? 'Sistema');

    $alvo = ($seg === 'ssl' ? 'ssl://' : '') . $host . ':' . $porta;
    $ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
    $fp = @stream_socket_client($alvo, $eno, $estr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $detalhe = "Não conectei em $alvo: $estr"; return false; }
    stream_set_timeout($fp, 20);

    $ler = function () use ($fp) {
        $saida = '';
        while (($linha = fgets($fp, 515)) !== false) { $saida .= $linha; if (strlen($linha) < 4 || $linha[3] === ' ') break; }
        return $saida;
    };
    $cmd = function (string $comando, string $esperado) use ($fp, $ler, &$detalhe) {
        if ($comando !== '') fwrite($fp, $comando . "\r\n");
        $r = $ler();
        if (strncmp($r, $esperado, strlen($esperado)) !== 0) {
            $detalhe = trim(($comando !== '' ? explode(' ', $comando)[0] . ': ' : '') . $r);
            return false;
        }
        return true;
    };

    $eu = gethostname() ?: 'localhost';
    if (!$cmd('', '220'))            { fclose($fp); return false; }
    if (!$cmd('EHLO ' . $eu, '250')) { fclose($fp); return false; }
    if ($seg === 'tls') {
        if (!$cmd('STARTTLS', '220')) { fclose($fp); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { $detalhe = 'Falha no STARTTLS'; fclose($fp); return false; }
        if (!$cmd('EHLO ' . $eu, '250')) { fclose($fp); return false; }
    }
    if ($user !== '') {
        if (!$cmd('AUTH LOGIN', '334'))        { fclose($fp); return false; }
        if (!$cmd(base64_encode($user), '334')) { fclose($fp); return false; }
        if (!$cmd(base64_encode($senha), '235')) { $detalhe = 'Usuário ou senha do SMTP recusados'; fclose($fp); return false; }
    }
    if (!$cmd('MAIL FROM:<' . $de . '>', '250'))  { fclose($fp); return false; }
    if (!$cmd('RCPT TO:<' . $para . '>', '250'))  { fclose($fp); return false; }
    if (!$cmd('DATA', '354'))                     { fclose($fp); return false; }

    $alt   = '=_alt_' . bin2hex(random_bytes(8));
    $texto = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $corpoHtml)), ENT_QUOTES, 'UTF-8'));
    $texto = preg_replace("/\n{3,}/", "\n\n", $texto);
    $cab = [
        'Date: ' . date('r'),
        'From: ' . mb_encode_mimeheader($nome, 'UTF-8') . ' <' . $de . '>',
        'To: <' . $para . '>',
        'Subject: ' . mb_encode_mimeheader($assunto, 'UTF-8'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $eu . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $alt . '"',
    ];
    $corpo = implode("\r\n", $cab) . "\r\n\r\n"
        . '--' . $alt . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($texto)) . "\r\n"
        . '--' . $alt . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($corpoHtml)) . "\r\n"
        . '--' . $alt . "--\r\n";
    $corpo = preg_replace('/^\./m', '..', $corpo);   /* protege linhas que começam com ponto */

    fwrite($fp, $corpo . "\r\n.\r\n");
    $r = $ler();
    $ok = strncmp($r, '250', 3) === 0;
    if (!$ok) $detalhe = trim($r);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok;
}

/**
 * Modelo do e-mail de redefinição de senha (HTML em tabelas, CSS inline).
 */
function emailRedefinicao(string $nome, string $link, int $validadeMin, string $ip = ''): string {
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $marca = cfg('marca_titulo', 'TRANSPARÊNCIA') . ' · ' . cfg('marca_subtitulo', 'JARDIM MACEIÓ');
    $rodapeIp = $ip !== '' ? '<br>Pedido feito pelo endereço ' . $h($ip) . ' em ' . date('d/m/Y \à\s H:i') . '.' : '';

    return '<!doctype html>
<html lang="pt-BR"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<title>Redefinir senha</title>
<style>@media (max-width:620px){.miolo{padding:26px 22px !important}.cabecalho{padding:26px 22px !important}}</style>
</head>
<body style="margin:0;padding:0;background:#F3F4F6;-webkit-font-smoothing:antialiased">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">Link para criar uma senha nova &#847;&nbsp;&#847;&nbsp;&#847;&nbsp;</div>
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#F3F4F6">
    <tr><td align="center" style="padding:32px 16px">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:100%;background:#FFFFFF;border-radius:18px;overflow:hidden;box-shadow:0 2px 8px rgba(16,24,40,.08)">
        <tr><td class="cabecalho" style="background:#0F7B3E;background-image:linear-gradient(135deg,#0B5C2E,#16A34A);padding:30px 34px">
          <div style="font:700 13px/1 Inter,Segoe UI,Arial,sans-serif;color:#DCFCE7;letter-spacing:.12em;text-transform:uppercase">' . $h($marca) . '</div>
          <div style="font:700 26px/1.3 Inter,Segoe UI,Arial,sans-serif;color:#FFFFFF;margin:14px 0 0">Redefinição de senha</div>
        </td></tr>
        <tr><td class="miolo" style="padding:30px 34px">
          <div style="font:600 16px/1.5 Inter,Segoe UI,Arial,sans-serif;color:#111827;margin:0 0 14px">Olá, ' . $h($nome) . '!</div>
          <div style="font:400 16px/1.7 Inter,Segoe UI,Arial,sans-serif;color:#374151;margin:0 0 24px">
            Recebemos um pedido para criar uma senha nova para o seu acesso ao portal do condomínio.
            Clique no botão abaixo para escolher a nova senha.
          </div>
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px">
            <tr><td style="background:#0F7B3E;border-radius:10px">
              <a href="' . $h($link) . '" style="display:inline-block;padding:14px 28px;font:600 15px/1 Inter,Segoe UI,Arial,sans-serif;color:#FFFFFF;text-decoration:none">Criar nova senha</a>
            </td></tr>
          </table>
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px">
            <tr><td style="background:#F0FDF4;border-left:4px solid #16A34A;border-radius:10px;padding:14px 16px;font:400 14px/1.6 Inter,Segoe UI,Arial,sans-serif;color:#166534">
              Este link vale por <strong>' . (int)$validadeMin . ' minutos</strong> e só pode ser usado uma vez.
            </td></tr>
          </table>
          <div style="font:400 13px/1.7 Inter,Segoe UI,Arial,sans-serif;color:#6B7280;word-break:break-all">
            Se o botão não funcionar, copie e cole este endereço no navegador:<br>
            <a href="' . $h($link) . '" style="color:#0F7B3E">' . $h($link) . '</a>
          </div>
        </td></tr>
        <tr><td style="background:#F9FAFB;border-top:1px solid #F3F4F6;padding:18px 34px;font:400 12px/1.6 Inter,Segoe UI,Arial,sans-serif;color:#9CA3AF">
          Se não foi você quem pediu, pode ignorar esta mensagem — sua senha atual continua valendo.' . $rodapeIp . '
        </td></tr>
      </table>
      <div style="font:400 12px/1.6 Inter,Segoe UI,Arial,sans-serif;color:#9CA3AF;margin:16px 0 0">Enviado automaticamente &middot; não é preciso responder</div>
    </td></tr>
  </table>
</body></html>';
}
