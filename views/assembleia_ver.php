<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
Auth::exigirLogin();
require_once __DIR__ . '/../config/database.php';
$pdo = Database::conectar();
$u   = Auth::usuario();

/* Resolve a assembleia pelo link próprio (?t=token) ou pelo ?id= */
$token = trim($_GET['t'] ?? '');
if ($token !== '') {
    $stmt = $pdo->prepare("SELECT * FROM assembleias WHERE token=:t");
    $stmt->execute([':t'=>$token]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM assembleias WHERE id=:id");
    $stmt->execute([':id'=>(int)($_GET['id'] ?? 0)]);
}
$a = $stmt->fetch();
if (!$a) { header('Location: ' . BASE_URL . '/views/assembleias.php'); exit; }
$id = (int)$a['id'];
/* Statement para recarregar a assembleia por id */
$reload = $pdo->prepare("SELECT * FROM assembleias WHERE id=:id");

/* morador não enxerga rascunho */
$podeEditarPg = Auth::podeEditar();
if ($a['status'] === 'rascunho' && !$podeEditarPg) { header('Location: ' . BASE_URL . '/views/assembleias.php'); exit; }

/* Participação do usuário atual nesta assembleia */
$partStmt = $pdo->prepare("SELECT status FROM assembleia_participantes WHERE assembleia_id=:a AND usuario_id=:u");
$partStmt->execute([':a'=>$id, ':u'=>$u['id']]);
$participacao = $partStmt->fetchColumn() ?: null;   // null | pendente | aprovado | recusado
$aprovado = $podeEditarPg || $participacao === 'aprovado';

/* Votação aberta? */
$votacaoAberta = $a['status'] === 'em_votacao'
    && (empty($a['votacao_fim']) || strtotime($a['votacao_fim']) >= time());

$msg = '';

/* ====================== AÇÕES ====================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // --- Solicitar participação (qualquer usuário logado) ---
    if ($acao === 'solicitar') {
        $pdo->prepare(
            "INSERT INTO assembleia_participantes (assembleia_id, usuario_id, status) VALUES (:a,:u,'pendente')
             ON DUPLICATE KEY UPDATE
                solicitado_em = IF(status='recusado', NOW(), solicitado_em),
                status        = IF(status='recusado', 'pendente', status)"
        )->execute([':a'=>$id, ':u'=>$u['id']]);
        header('Location: ' . BASE_URL . '/views/assembleia_ver.php?id=' . $id); exit;
    }

    // --- Votar (somente participantes aprovados) ---
    if ($acao === 'votar') {
        $pautaId  = (int)($_POST['pauta_id'] ?? 0);
        // opções escolhidas (array em múltipla; valor único em escolha única)
        $escolhas = $_POST['opcoes'] ?? [];
        if (!is_array($escolhas)) $escolhas = [$escolhas];
        $escolhas = array_values(array_unique(array_map('intval', $escolhas)));

        // pauta precisa pertencer à assembleia
        $pst = $pdo->prepare("SELECT multipla FROM assembleia_pautas WHERE id=:p AND assembleia_id=:a");
        $pst->execute([':p'=>$pautaId, ':a'=>$id]);
        $multipla = $pst->fetchColumn();

        if (!$votacaoAberta) {
            $msg = 'A votação não está aberta.';
        } elseif (!$aprovado) {
            $msg = 'Sua participação ainda não foi aprovada pelo administrador.';
        } elseif ($multipla === false) {
            $msg = 'Pauta inválida.';
        } elseif (empty($escolhas)) {
            $msg = 'Selecione ao menos uma opção.';
        } elseif (!$multipla && count($escolhas) > 1) {
            $msg = 'Esta pauta permite apenas uma opção.';
        } else {
            // valida que todas as opções pertencem à pauta
            $in  = implode(',', array_fill(0, count($escolhas), '?'));
            $chk = $pdo->prepare("SELECT id FROM assembleia_opcoes WHERE pauta_id=? AND id IN ($in)");
            $chk->execute(array_merge([$pautaId], $escolhas));
            $validas = array_map('intval', $chk->fetchAll(PDO::FETCH_COLUMN));

            if (count($validas) !== count($escolhas)) {
                $msg = 'Opção inválida.';
            } else {
                // substitui os votos do usuário nesta pauta
                $pdo->prepare("DELETE FROM assembleia_opcao_votos WHERE pauta_id=:p AND usuario_id=:u")
                    ->execute([':p'=>$pautaId, ':u'=>$u['id']]);
                $ins = $pdo->prepare("INSERT INTO assembleia_opcao_votos (pauta_id, opcao_id, usuario_id) VALUES (:p,:o,:u)");
                foreach ($validas as $oid) $ins->execute([':p'=>$pautaId, ':o'=>$oid, ':u'=>$u['id']]);
                header('Location: ' . BASE_URL . '/views/assembleia_ver.php?id=' . $id . '#pauta-' . $pautaId);
                exit;
            }
        }
    }

    // --- Ações restritas (admin/síndico) ---
    if ($podeEditarPg) {
        if ($acao === 'decidir_part') {
            $partId = (int)($_POST['part_id'] ?? 0);
            $dec    = in_array($_POST['decisao'] ?? '', ['aprovado','recusado','pendente'], true) ? $_POST['decisao'] : 'pendente';
            $pdo->prepare("UPDATE assembleia_participantes SET status=:s, decidido_em=NOW(), decidido_por=:dp WHERE id=:p AND assembleia_id=:a")
                ->execute([':s'=>$dec, ':dp'=>$u['id'], ':p'=>$partId, ':a'=>$id]);
            header('Location: ' . BASE_URL . '/views/assembleia_ver.php?id=' . $id . '#participantes'); exit;
        }
        if ($acao === 'add_pauta') {
            $t    = trim($_POST['titulo'] ?? '');
            $d    = trim($_POST['descricao'] ?? '');
            $tipo = $_POST['tipo_votacao'] ?? 'sim_nao';   // sim_nao | unica | multipla

            // monta a lista de opções conforme o tipo
            if ($tipo === 'sim_nao') {
                $opcoes   = ['Sim', 'Não', 'Abstenção'];
                $multipla = 0;
            } else {
                // uma opção por linha do textarea
                $linhas = preg_split('/\r\n|\r|\n/', $_POST['opcoes_texto'] ?? '');
                $opcoes = [];
                foreach ($linhas as $ln) { $ln = trim($ln); if ($ln !== '') $opcoes[] = mb_substr($ln, 0, 255); }
                $multipla = $tipo === 'multipla' ? 1 : 0;
            }

            if ($t === '') {
                $msg = 'Informe o título da pauta.';
            } elseif (count($opcoes) < 2) {
                $msg = 'Cadastre ao menos 2 opções para a enquete.';
            } else {
                $ord = (int)$pdo->query("SELECT COALESCE(MAX(ordem),0)+1 FROM assembleia_pautas WHERE assembleia_id=$id")->fetchColumn();
                $pdo->prepare("INSERT INTO assembleia_pautas (assembleia_id, titulo, descricao, ordem, multipla) VALUES (:a,:t,:d,:o,:m)")
                    ->execute([':a'=>$id, ':t'=>$t, ':d'=>$d ?: null, ':o'=>$ord, ':m'=>$multipla]);
                $pid = (int)$pdo->lastInsertId();
                $insOp = $pdo->prepare("INSERT INTO assembleia_opcoes (pauta_id, texto, ordem) VALUES (:p,:tx,:o)");
                foreach ($opcoes as $i => $tx) $insOp->execute([':p'=>$pid, ':tx'=>$tx, ':o'=>$i]);
                header('Location: ' . BASE_URL . '/views/assembleia_ver.php?id=' . $id); exit;
            }
        }
        if ($acao === 'del_pauta') {
            $pid = (int)($_POST['pauta_id'] ?? 0);
            $pdo->prepare("DELETE FROM assembleia_pautas WHERE id=:p AND assembleia_id=:a")->execute([':p'=>$pid, ':a'=>$id]);
            header('Location: ' . BASE_URL . '/views/assembleia_ver.php?id=' . $id); exit;
        }
        if ($acao === 'decidir_pauta') {
            $pid = (int)($_POST['pauta_id'] ?? 0);
            $dec = in_array($_POST['decisao'] ?? '', ['aprovada','reprovada','pendente'], true) ? $_POST['decisao'] : 'pendente';
            $pdo->prepare("UPDATE assembleia_pautas SET status=:s WHERE id=:p AND assembleia_id=:a")
                ->execute([':s'=>$dec, ':p'=>$pid, ':a'=>$id]);
            header('Location: ' . BASE_URL . '/views/assembleia_ver.php?id=' . $id . '#pauta-' . $pid); exit;
        }
        if ($acao === 'iniciar') {
            $tv = (int)$a['tempo_votacao'];
            $fim = $tv > 0 ? "DATE_ADD(NOW(), INTERVAL $tv MINUTE)" : "NULL";
            $pdo->exec("UPDATE assembleias SET status='em_votacao', votacao_inicio=NOW(), votacao_fim=$fim WHERE id=$id");
            header('Location: ' . BASE_URL . '/views/assembleia_ver.php?id=' . $id); exit;
        }
        if ($acao === 'finalizar') {
            $pdo->exec("UPDATE assembleias SET status='finalizada', votacao_fim=COALESCE(votacao_fim, NOW()) WHERE id=$id");
            header('Location: ' . BASE_URL . '/views/assembleia_ver.php?id=' . $id); exit;
        }
        if ($acao === 'ata' && !empty($_FILES['ata']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['ata']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','doc','docx'])) {
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
                $arq = 'ata_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['ata']['tmp_name'], UPLOAD_DIR . $arq)) {
                    $old = $a['ata_arquivo'];
                    if ($old && file_exists(UPLOAD_DIR.$old)) @unlink(UPLOAD_DIR.$old);
                    $pdo->prepare("UPDATE assembleias SET ata_arquivo=:x WHERE id=:id")->execute([':x'=>$arq, ':id'=>$id]);
                    // registra também no repositório de documentos
                    $pdo->prepare("INSERT INTO documentos (titulo, tipo, arquivo, publico, criado_por) VALUES (:t,'ata',:a,1,:cp)")
                        ->execute([':t'=>'Ata - '.$a['titulo'], ':a'=>$arq, ':cp'=>$u['id']]);
                }
            } else { $msg = 'Ata: use PDF ou DOC.'; }
            if ($msg === '') { header('Location: ' . BASE_URL . '/views/assembleia_ver.php?id=' . $id); exit; }
        }
    }
    // recarrega assembleia (status pode ter mudado em erro de ata)
    $reload->execute([':id'=>$id]); $a = $reload->fetch();
    $votacaoAberta = $a['status'] === 'em_votacao' && (empty($a['votacao_fim']) || strtotime($a['votacao_fim']) >= time());
}

/* ====================== DADOS PARA EXIBIÇÃO ====================== */
$paginaTitulo    = $a['titulo'];
$paginaSubtitulo = 'Assembleia ' . ($a['tipo']==='ordinaria'?'Ordinária':'Extraordinária') . ' · ' . dataBr($a['data_assembleia']) . ($a['horario'] ? ' às '.substr($a['horario'],0,5) : '');
$paginaAtiva     = 'assembleias';
require_once __DIR__ . '/../includes/header.php';

function assStatusLabel(string $s): string {
    return ['rascunho'=>'RASCUNHO','agendada'=>'AGENDADA','em_votacao'=>'EM VOTAÇÃO','finalizada'=>'FINALIZADA'][$s] ?? strtoupper($s);
}
function assStatusBadge(string $s): string {
    return ['rascunho'=>'badge-muted','agendada'=>'badge-warning','em_votacao'=>'badge-success','finalizada'=>'badge-muted'][$s] ?? 'badge-muted';
}

$pautas = $pdo->query("SELECT * FROM assembleia_pautas WHERE assembleia_id=$id ORDER BY ordem, id")->fetchAll();

$uid = (int)$u['id'];

// opções de cada pauta + contagem de votos
$opcoes = [];   // [pauta_id => [ [id,texto,votos], ... ]]
foreach ($pdo->query(
    "SELECT o.pauta_id, o.id, o.texto,
            (SELECT COUNT(*) FROM assembleia_opcao_votos v WHERE v.opcao_id=o.id) AS votos
     FROM assembleia_opcoes o
     JOIN assembleia_pautas p ON p.id=o.pauta_id
     WHERE p.assembleia_id=$id
     ORDER BY o.pauta_id, o.ordem, o.id") as $r) {
    $opcoes[(int)$r['pauta_id']][] = ['id'=>(int)$r['id'], 'texto'=>$r['texto'], 'votos'=>(int)$r['votos']];
}

// opções escolhidas pelo usuário atual (por pauta)
$minhasOpcoes = [];  // [pauta_id => [opcao_id, ...]]
foreach ($pdo->query("SELECT pauta_id, opcao_id FROM assembleia_opcao_votos WHERE usuario_id=$uid") as $r) {
    $minhasOpcoes[(int)$r['pauta_id']][] = (int)$r['opcao_id'];
}

// nº de votantes distintos por pauta
$votantes = [];
foreach ($pdo->query("SELECT pauta_id, COUNT(DISTINCT usuario_id) c FROM assembleia_opcao_votos
                      WHERE pauta_id IN (SELECT id FROM assembleia_pautas WHERE assembleia_id=$id)
                      GROUP BY pauta_id") as $r) {
    $votantes[(int)$r['pauta_id']] = (int)$r['c'];
}

// Link próprio da assembleia
$linkAssembleia = BASE_URL . '/views/assembleia_ver.php?t=' . $a['token'];

// Participantes (somente para gestão)
$participantes = [];
$qtdPendentes = 0;
if ($podeEditarPg) {
    $participantes = $pdo->query(
        "SELECT pa.id, pa.status, pa.solicitado_em, us.nome, us.email, us.tipo
         FROM assembleia_participantes pa JOIN usuarios us ON us.id = pa.usuario_id
         WHERE pa.assembleia_id = $id
         ORDER BY FIELD(pa.status,'pendente','aprovado','recusado'), us.nome"
    )->fetchAll();
    $qtdPendentes = count(array_filter($participantes, fn($p)=>$p['status']==='pendente'));
}
?>

<a href="<?= BASE_URL ?>/views/assembleias.php" class="btn btn-outline" style="margin-bottom:14px;">← Voltar</a>

<?php if (!empty($msg)): ?><div class="login-error" style="margin-bottom:12px;"><?= e($msg) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:18px;">
  <div class="card-header" style="flex-wrap:wrap; gap:10px;">
    <span class="card-title"><?= e($a['titulo']) ?></span>
    <span class="badge <?= assStatusBadge($a['status']) ?>"><?= assStatusLabel($a['status']) ?></span>
  </div>
  <div class="form-grid form-grid-2" style="gap:10px 24px;">
    <div><strong>Tipo:</strong> <?= $a['tipo']==='ordinaria'?'Ordinária':'Extraordinária' ?></div>
    <div><strong>Data:</strong> <?= dataBr($a['data_assembleia']) ?><?= $a['horario'] ? ' às '.substr($a['horario'],0,5) : '' ?></div>
    <?php if ($a['tempo_votacao']): ?><div><strong>Tempo de votação:</strong> <?= (int)$a['tempo_votacao'] ?> min</div><?php endif; ?>
    <?php if ($a['status']==='em_votacao' && $a['votacao_fim']): ?>
      <div><strong>Votação até:</strong> <span id="deadline" data-fim="<?= e($a['votacao_fim']) ?>"><?= dataHoraBr($a['votacao_fim']) ?></span></div>
    <?php endif; ?>
  </div>
  <?php if ($a['descricao']): ?><p style="margin-top:12px; color:#374151; white-space:pre-line;"><?= e($a['descricao']) ?></p><?php endif; ?>
  <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:14px;">
    <?php if ($a['link_reuniao'] && $aprovado): ?><a class="btn btn-primary" target="_blank" href="<?= e($a['link_reuniao']) ?>">Entrar na reunião</a><?php endif; ?>
    <?php if ($a['edital_arquivo']): ?><a class="btn btn-outline" target="_blank" href="<?= UPLOAD_URL . e($a['edital_arquivo']) ?>">Ver edital</a><?php endif; ?>
    <?php if ($a['ata_arquivo']): ?><a class="btn btn-outline" target="_blank" href="<?= UPLOAD_URL . e($a['ata_arquivo']) ?>">Ver ata</a><?php endif; ?>
    <?php if ($podeEditarPg): ?>
      <?php if (in_array($a['status'], ['rascunho','agendada'], true)): ?>
        <form method="post" onsubmit="return confirm('Iniciar a votação agora?')"><input type="hidden" name="acao" value="iniciar"><button class="btn btn-primary">Iniciar votação</button></form>
      <?php elseif ($a['status']==='em_votacao'): ?>
        <form method="post" onsubmit="return confirm('Finalizar a votação?')"><input type="hidden" name="acao" value="finalizar"><button class="btn btn-danger">Finalizar votação</button></form>
      <?php endif; ?>
      <?php if (in_array($a['status'], ['em_votacao','finalizada'], true)): ?>
        <a class="btn btn-outline" target="_blank" href="<?= BASE_URL ?>/views/assembleia_relatorio.php?id=<?= $id ?>&modo=anonimo">📄 PDF do resultado</a>
        <a class="btn btn-outline" target="_blank" href="<?= BASE_URL ?>/views/assembleia_relatorio.php?id=<?= $id ?>&modo=nominal">📄 PDF nominal (com votantes)</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($votacaoAberta): ?>
  <div class="login-error" style="background:#ecfdf5; color:#065f46; border-color:#a7f3d0; margin-bottom:14px;">🗳️ Votação aberta — registre seu voto em cada pauta abaixo.</div>
<?php elseif ($a['status']==='agendada'): ?>
  <div class="login-error" style="background:#fffbeb; color:#92400e; border-color:#fde68a; margin-bottom:14px;">A votação ainda não foi iniciada. Acompanhe esta página na data da assembleia.</div>
<?php elseif ($a['status']==='finalizada'): ?>
  <div class="login-error" style="background:#f3f4f6; color:#374151; border-color:#e5e7eb; margin-bottom:14px;">Votação encerrada. Veja abaixo o resultado de cada pauta.</div>
<?php endif; ?>

<?php /* ===== PARTICIPAÇÃO DO MORADOR (solicitar entrada) ===== */ ?>
<?php if (!$podeEditarPg): ?>
  <?php if ($participacao === 'aprovado'): ?>
    <div class="login-error" style="background:#ecfdf5; color:#065f46; border-color:#a7f3d0; margin-bottom:14px;">✅ Sua participação foi aprovada. Você já pode entrar na reunião e votar.</div>
  <?php elseif ($participacao === 'pendente'): ?>
    <div class="login-error" style="background:#fffbeb; color:#92400e; border-color:#fde68a; margin-bottom:14px;">⏳ Solicitação enviada — aguardando aprovação do administrador.</div>
  <?php else: ?>
    <div class="card" style="margin-bottom:14px;">
      <div class="card-header"><span class="card-title">PARTICIPAR DA ASSEMBLEIA</span></div>
      <?php if ($participacao === 'recusado'): ?>
        <p style="color:#b91c1c; margin-bottom:10px;">Sua solicitação anterior foi recusada. Você pode solicitar novamente.</p>
      <?php else: ?>
        <p class="muted" style="margin-bottom:10px;">Para entrar na reunião e votar, solicite participação. O administrador precisa aprovar seu acesso.</p>
      <?php endif; ?>
      <form method="post"><input type="hidden" name="acao" value="solicitar"><button class="btn btn-primary">Solicitar participação</button></form>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php /* ===== ACESSO E PARTICIPANTES (admin/síndico) ===== */ ?>
<?php if ($podeEditarPg): ?>
<div class="card" id="participantes" style="margin-bottom:14px;">
  <div class="card-header"><span class="card-title">ACESSO E PARTICIPANTES <?php if ($qtdPendentes>0): ?><span class="badge badge-danger"><?= $qtdPendentes ?> pendente<?= $qtdPendentes>1?'s':'' ?></span><?php endif; ?></span></div>

  <div class="form-group" style="margin-bottom:14px;">
    <label>Link próprio desta assembleia (envie aos moradores)</label>
    <div style="display:flex; gap:8px;">
      <input class="form-control" id="linkAss" readonly value="<?= e($linkAssembleia) ?>" onclick="this.select()">
      <button type="button" class="btn btn-outline" onclick="copiarLink(document.getElementById('linkAss').value)">Copiar</button>
    </div>
  </div>

  <table class="table-list">
    <thead><tr><th>Morador</th><th>E-mail</th><th class="text-center">Status</th><th class="text-center">Solicitado em</th><th class="text-center">Ações</th></tr></thead>
    <tbody>
      <?php if (empty($participantes)): ?>
        <tr><td colspan="5" class="muted text-center" style="padding:20px;">Nenhuma solicitação de participação ainda.</td></tr>
      <?php else: foreach ($participantes as $p): ?>
        <tr>
          <td style="font-weight:600;"><?= e($p['nome']) ?> <span class="muted" style="font-weight:400;">(<?= e($p['tipo']) ?>)</span></td>
          <td class="muted"><?= e($p['email']) ?></td>
          <td class="text-center"><span class="badge <?= $p['status']==='aprovado'?'badge-success':($p['status']==='recusado'?'badge-danger':'badge-warning') ?>"><?= strtoupper($p['status']) ?></span></td>
          <td class="text-center muted"><?= dataHoraBr($p['solicitado_em']) ?></td>
          <td class="text-center" style="white-space:nowrap;">
            <?php if ($p['status'] !== 'aprovado'): ?>
              <form method="post" style="display:inline"><input type="hidden" name="acao" value="decidir_part"><input type="hidden" name="part_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="decisao" value="aprovado"><button class="btn btn-primary" style="padding:4px 10px; font-size:11px;">Aprovar</button></form>
            <?php endif; ?>
            <?php if ($p['status'] !== 'recusado'): ?>
              <form method="post" style="display:inline"><input type="hidden" name="acao" value="decidir_part"><input type="hidden" name="part_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="decisao" value="recusado"><button class="btn btn-danger" style="padding:4px 10px; font-size:11px;"><?= $p['status']==='aprovado'?'Revogar':'Recusar' ?></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><span class="card-title">PAUTAS <?= count($pautas) ? '('.count($pautas).')' : '' ?></span></div>

  <?php if (empty($pautas)): ?>
    <p class="muted text-center" style="padding:24px;">Nenhuma pauta cadastrada.</p>
  <?php else: foreach ($pautas as $i => $p):
      $pid_      = (int)$p['id'];
      $ops       = $opcoes[$pid_] ?? [];
      $minhas    = $minhasOpcoes[$pid_] ?? [];
      $totVotos  = array_sum(array_column($ops, 'votos'));
      $nVotantes = $votantes[$pid_] ?? 0;
      // base do percentual: votantes (escolha única) ou total de votos (múltipla)
      $base      = max(1, $p['multipla'] ? $totVotos : $nVotantes);
      $mostraResultado = $totVotos > 0 || $a['status']==='finalizada' || $votacaoAberta;
  ?>
    <div id="pauta-<?= $pid_ ?>" style="padding:16px 0; border-top:1px solid #eef1f4;">
      <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <h3 style="font-size:15px; font-weight:700;"><?= ($i+1) ?>. <?= e($p['titulo']) ?></h3>
        <span class="badge <?= $p['status']==='aprovada'?'badge-success':($p['status']==='reprovada'?'badge-danger':'badge-muted') ?>"><?= strtoupper($p['status']) ?></span>
      </div>
      <?php if ($p['descricao']): ?><p class="muted" style="margin:6px 0; white-space:pre-line;"><?= e($p['descricao']) ?></p><?php endif; ?>
      <p class="muted" style="font-size:11px; margin:4px 0;"><?= $p['multipla'] ? '🗳️ Escolha múltipla — pode marcar mais de uma opção.' : 'Escolha única.' ?></p>

      <!-- Votação -->
      <?php if ($votacaoAberta && $aprovado): ?>
        <form method="post" style="margin:10px 0;">
          <input type="hidden" name="acao" value="votar">
          <input type="hidden" name="pauta_id" value="<?= $pid_ ?>">
          <?php foreach ($ops as $o): $sel = in_array($o['id'], $minhas, true); ?>
            <label style="display:flex; align-items:center; gap:8px; padding:5px 0; cursor:pointer;">
              <input type="<?= $p['multipla'] ? 'checkbox' : 'radio' ?>" name="opcoes<?= $p['multipla'] ? '[]' : '' ?>" value="<?= $o['id'] ?>" <?= $sel ? 'checked' : '' ?>>
              <span><?= e($o['texto']) ?></span>
            </label>
          <?php endforeach; ?>
          <button class="btn btn-primary" style="padding:6px 16px; margin-top:6px;">Votar</button>
          <?php if ($minhas): ?><span class="muted" style="margin-left:8px;">Voto registrado — pode alterar.</span><?php endif; ?>
        </form>
      <?php elseif ($votacaoAberta && !$aprovado): ?>
        <p class="muted" style="margin:8px 0;">Aprovação de participação necessária para votar nesta pauta.</p>
      <?php endif; ?>

      <!-- Resultado / apuração -->
      <?php if ($mostraResultado): ?>
        <div style="margin-top:8px; max-width:540px;">
          <?php foreach ($ops as $o): $pct = round($o['votos']/$base*100); $eu = in_array($o['id'], $minhas, true); ?>
            <div style="display:flex; align-items:center; gap:8px; margin:3px 0; font-size:12px;">
              <span style="width:150px; color:#6B7280; <?= $eu?'font-weight:700; color:#0F7B3E;':'' ?>"><?= e($o['texto']) ?><?= $eu?' ✓':'' ?></span>
              <span style="flex:1; background:#f1f3f5; border-radius:6px; height:10px; overflow:hidden;">
                <span style="display:block; height:100%; width:<?= $pct ?>%; background:#0F7B3E;"></span>
              </span>
              <span style="width:60px; text-align:right; color:#374151;"><?= $o['votos'] ?> (<?= $pct ?>%)</span>
            </div>
          <?php endforeach; ?>
          <div class="muted" style="font-size:11px; margin-top:4px;"><?= $nVotantes ?> votante<?= $nVotantes==1?'':'s' ?><?= $p['multipla'] ? ' · '.$totVotos.' voto'.($totVotos==1?'':'s').' no total' : '' ?></div>
        </div>
      <?php endif; ?>

      <!-- Gestão da pauta (síndico/admin): aprovação e exclusão -->
      <?php if ($podeEditarPg): ?>
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
          <?php if (in_array($a['status'], ['em_votacao','finalizada'], true)): ?>
            <form method="post" style="display:inline"><input type="hidden" name="acao" value="decidir_pauta"><input type="hidden" name="pauta_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="decisao" value="aprovada"><button class="btn btn-outline" style="padding:4px 10px; font-size:11px;">Aprovar pauta</button></form>
            <form method="post" style="display:inline"><input type="hidden" name="acao" value="decidir_pauta"><input type="hidden" name="pauta_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="decisao" value="reprovada"><button class="btn btn-outline" style="padding:4px 10px; font-size:11px;">Reprovar</button></form>
          <?php endif; ?>
          <?php if (in_array($a['status'], ['rascunho','agendada'], true)): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Excluir esta pauta?')"><input type="hidden" name="acao" value="del_pauta"><input type="hidden" name="pauta_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-danger" style="padding:4px 10px; font-size:11px;">Excluir pauta</button></form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>

  <!-- Adicionar pauta (antes da votação) — pode adicionar quantas quiser -->
  <?php if ($podeEditarPg && in_array($a['status'], ['rascunho','agendada'], true)): ?>
    <form method="post" class="form-grid" style="border-top:1px dashed #d1d5db; margin-top:14px; padding-top:16px;">
      <input type="hidden" name="acao" value="add_pauta">
      <strong style="font-size:13px;">Nova pauta</strong>
      <div class="form-group"><label>Título da pauta *</label><input class="form-control" name="titulo" required></div>
      <div class="form-group"><label>Descrição</label><textarea class="form-control" name="descricao" rows="2"></textarea></div>
      <div class="form-group">
        <label>Tipo de votação</label>
        <select class="form-control" name="tipo_votacao" id="tipoVot" onchange="toggleOpcoes()">
          <option value="sim_nao">Sim / Não / Abstenção</option>
          <option value="unica">Opções personalizadas — escolha única</option>
          <option value="multipla">Opções personalizadas — escolha múltipla</option>
        </select>
      </div>
      <div class="form-group" id="boxOpcoes" style="display:none;">
        <label>Opções da enquete (uma por linha, mínimo 2)</label>
        <textarea class="form-control" name="opcoes_texto" rows="4" placeholder="Empresa Alfa&#10;Empresa Beta&#10;Empresa Gama"></textarea>
      </div>
      <div><button class="btn btn-primary">+ Adicionar pauta</button></div>
    </form>
    <script>
    function toggleOpcoes(){
      document.getElementById('boxOpcoes').style.display =
        document.getElementById('tipoVot').value === 'sim_nao' ? 'none' : 'block';
    }
    toggleOpcoes();
    </script>
  <?php endif; ?>
</div>

<!-- Emissão de ata (admin/síndico, após finalizar) -->
<?php if ($podeEditarPg && $a['status']==='finalizada'): ?>
<div class="card" style="margin-top:18px;">
  <div class="card-header"><span class="card-title">EMITIR ATA</span></div>
  <form method="post" enctype="multipart/form-data" class="form-grid">
    <input type="hidden" name="acao" value="ata">
    <div class="form-group">
      <label>Arquivo da ata (PDF ou DOC)</label>
      <input type="file" name="ata" class="form-control" accept=".pdf,.doc,.docx" required>
      <small class="muted">A ata também será publicada no repositório de Documentos.</small>
    </div>
    <div><button class="btn btn-primary">Publicar ata</button></div>
  </form>
</div>
<?php endif; ?>

<script>
function copiarLink(url){
  (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject())
    .then(()=>alert('Link copiado:\n' + url))
    .catch(()=>prompt('Copie o link da assembleia:', url));
}
// Contagem regressiva da votação
(function(){
  const el = document.getElementById('deadline');
  if (!el) return;
  const fim = new Date(el.dataset.fim.replace(' ', 'T')).getTime();
  function tick(){
    const d = fim - Date.now();
    if (d <= 0) { el.innerHTML = '<strong style="color:#DC2626;">encerrada</strong>'; return; }
    const m = Math.floor(d/60000), s = Math.floor((d%60000)/1000);
    el.querySelector('.cd')?.remove();
    if (!el.dataset.base) el.dataset.base = el.textContent;
    el.innerHTML = el.dataset.base + ' <span class="cd" style="color:#0F7B3E; font-weight:700;">(' + m + 'm ' + (s<10?'0':'') + s + 's)</span>';
    setTimeout(tick, 1000);
  }
  tick();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
