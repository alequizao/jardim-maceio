<?php
$paginaTitulo    = 'Assembleia';
$paginaSubtitulo = 'Convocação, votação e atas das assembleias do condomínio';
$paginaAtiva     = 'assembleias';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
Auth::exigirLogin();
$pdo = Database::conectar();
$u   = Auth::usuario();
$podeEditar = Auth::podeEditar();

/* Rótulos / cores de status */
function assStatusLabel(string $s): string {
    return ['rascunho'=>'RASCUNHO','agendada'=>'AGENDADA','em_votacao'=>'EM VOTAÇÃO','finalizada'=>'FINALIZADA'][$s] ?? strtoupper($s);
}
function assStatusBadge(string $s): string {
    return ['rascunho'=>'badge-muted','agendada'=>'badge-warning','em_votacao'=>'badge-success','finalizada'=>'badge-muted'][$s] ?? 'badge-muted';
}

$msg = '';

/* ====================== AÇÕES (somente admin/síndico) ====================== */
if ($podeEditar) {

    // ---- Criar / Editar (multipart por causa do edital) ----
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
        $id        = (int)($_POST['id'] ?? 0);
        $titulo    = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $data      = dateOrNull($_POST['data_assembleia'] ?? '');
        $horario   = trim($_POST['horario'] ?? '') ?: null;
        $tipo      = ($_POST['tipo'] ?? 'ordinaria') === 'extraordinaria' ? 'extraordinaria' : 'ordinaria';
        $link      = trim($_POST['link_reuniao'] ?? '') ?: null;
        $tempo     = (int)($_POST['tempo_votacao'] ?? 0) ?: null;
        $status    = in_array($_POST['status'] ?? '', ['rascunho','agendada'], true) ? $_POST['status'] : 'agendada';

        if ($titulo === '' || !$data) {
            $msg = 'Informe ao menos o título e a data da assembleia.';
        } else {
            // Upload do edital (opcional)
            $editalArq = null;
            if (!empty($_FILES['edital']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['edital']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf','png','jpg','jpeg','doc','docx'])) {
                    $msg = 'Edital: extensão não permitida (use PDF, imagem ou DOC).';
                } else {
                    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
                    $editalArq = 'edital_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (!move_uploaded_file($_FILES['edital']['tmp_name'], UPLOAD_DIR . $editalArq)) {
                        $editalArq = null;
                        $msg = 'Falha ao enviar o edital.';
                    }
                }
            }

            if ($msg === '') {
                if ($id > 0) {
                    // mantém edital atual se nenhum novo foi enviado
                    $sql = "UPDATE assembleias SET titulo=:t, descricao=:d, data_assembleia=:da, horario=:h,
                                   tipo=:tp, link_reuniao=:l, tempo_votacao=:tv, status=:st"
                         . ($editalArq ? ", edital_arquivo=:ea" : "")
                         . " WHERE id=:id";
                    $par = [':t'=>$titulo, ':d'=>$descricao ?: null, ':da'=>$data, ':h'=>$horario,
                            ':tp'=>$tipo, ':l'=>$link, ':tv'=>$tempo, ':st'=>$status, ':id'=>$id];
                    if ($editalArq) $par[':ea'] = $editalArq;
                    $pdo->prepare($sql)->execute($par);
                } else {
                    $pdo->prepare(
                        "INSERT INTO assembleias (token, titulo, descricao, data_assembleia, horario, tipo, link_reuniao, edital_arquivo, tempo_votacao, status, criado_por)
                         VALUES (:tk,:t,:d,:da,:h,:tp,:l,:ea,:tv,:st,:cp)"
                    )->execute([':tk'=>bin2hex(random_bytes(16)), ':t'=>$titulo, ':d'=>$descricao ?: null, ':da'=>$data, ':h'=>$horario,
                                ':tp'=>$tipo, ':l'=>$link, ':ea'=>$editalArq, ':tv'=>$tempo, ':st'=>$status, ':cp'=>$u['id']]);
                }
                header('Location: ' . BASE_URL . '/views/assembleias.php?ok=1');
                exit;
            }
        }
    }

    // ---- Iniciar votação ----
    if (!empty($_GET['iniciar'])) {
        $id = (int)$_GET['iniciar'];
        $a = $pdo->prepare("SELECT tempo_votacao FROM assembleias WHERE id=:id");
        $a->execute([':id'=>$id]);
        $tv = (int)($a->fetchColumn() ?: 0);
        $fim = $tv > 0 ? "DATE_ADD(NOW(), INTERVAL $tv MINUTE)" : "NULL";
        $pdo->exec("UPDATE assembleias SET status='em_votacao', votacao_inicio=NOW(), votacao_fim=$fim WHERE id=$id");
        header('Location: ' . BASE_URL . '/views/assembleias.php?ok=1'); exit;
    }

    // ---- Finalizar votação ----
    if (!empty($_GET['finalizar'])) {
        $id = (int)$_GET['finalizar'];
        $pdo->exec("UPDATE assembleias SET status='finalizada', votacao_fim=COALESCE(votacao_fim, NOW()) WHERE id=$id");
        header('Location: ' . BASE_URL . '/views/assembleias.php?ok=1'); exit;
    }

    // ---- Excluir ----
    if (!empty($_GET['del'])) {
        $id = (int)$_GET['del'];
        $arq = $pdo->query("SELECT edital_arquivo FROM assembleias WHERE id=$id")->fetchColumn();
        if ($arq && file_exists(UPLOAD_DIR.$arq)) @unlink(UPLOAD_DIR.$arq);
        $pdo->prepare("DELETE FROM assembleias WHERE id=:id")->execute([':id'=>$id]);
        header('Location: ' . BASE_URL . '/views/assembleias.php?ok=1'); exit;
    }
}

/* ====================== LISTAGEM ====================== */
$lista = $pdo->query(
    "SELECT a.*,
            (SELECT COUNT(*) FROM assembleia_pautas p WHERE p.assembleia_id=a.id) AS qtd_pautas,
            (SELECT COUNT(*) FROM assembleia_participantes pa WHERE pa.assembleia_id=a.id AND pa.status='pendente') AS qtd_pendentes
     FROM assembleias a
     ORDER BY a.data_assembleia DESC, a.id DESC LIMIT 200"
)->fetchAll();

$total   = count($lista);
$agend   = count(array_filter($lista, fn($a)=>$a['status']==='agendada'));
$emVot   = count(array_filter($lista, fn($a)=>$a['status']==='em_votacao'));
$final   = count(array_filter($lista, fn($a)=>$a['status']==='finalizada'));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-icon azul"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="9" width="20" height="12" rx="2"/><path d="M14 9V5a3 3 0 0 0-6 0v4"/></svg></div>
    <div class="kpi-label">ASSEMBLEIAS</div>
    <div class="kpi-value azul"><?= $total ?></div>
    <div class="kpi-sub">registradas</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon amarelo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg></div>
    <div class="kpi-label">AGENDADAS</div>
    <div class="kpi-value amarelo"><?= $agend ?></div>
    <div class="kpi-sub">aguardando votação</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon verde"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
    <div class="kpi-label">EM VOTAÇÃO</div>
    <div class="kpi-value verde"><?= $emVot ?></div>
    <div class="kpi-sub">urnas abertas</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon vermelho"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg></div>
    <div class="kpi-label">FINALIZADAS</div>
    <div class="kpi-value vermelho"><?= $final ?></div>
    <div class="kpi-sub">com resultados</div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="flex-wrap:wrap; gap:12px;">
    <span class="card-title">ASSEMBLEIAS</span>
    <?php if ($podeEditar): ?><button class="btn btn-primary" onclick="abrirModal()">+ Nova Assembleia</button><?php endif; ?>
  </div>

  <?php if (!empty($msg)): ?><div class="login-error" style="margin:0 0 12px;"><?= e($msg) ?></div><?php endif; ?>

  <table class="table-list">
    <thead>
      <tr>
        <th>Título</th>
        <th class="text-center">Tipo</th>
        <th>Data / Horário</th>
        <th class="text-center">Pautas</th>
        <th class="text-center">Edital</th>
        <th class="text-center">Status</th>
        <th class="text-center">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($lista)): ?>
        <tr><td colspan="7" class="muted text-center" style="padding:30px;">Nenhuma assembleia cadastrada.</td></tr>
      <?php else: foreach ($lista as $a): ?>
        <tr>
          <td style="font-weight:600;">
            <a href="<?= BASE_URL ?>/views/assembleia_ver.php?id=<?= (int)$a['id'] ?>" style="color:var(--verde-700);"><?= e($a['titulo']) ?></a>
          </td>
          <td class="text-center"><span class="badge <?= $a['tipo']==='ordinaria'?'badge-success':'badge-warning' ?>"><?= $a['tipo']==='ordinaria'?'ORDINÁRIA':'EXTRAORDINÁRIA' ?></span></td>
          <td class="muted"><?= dataBr($a['data_assembleia']) ?><?= $a['horario'] ? ' · '.substr($a['horario'],0,5) : '' ?></td>
          <td class="text-center muted"><?= (int)$a['qtd_pautas'] ?></td>
          <td class="text-center">
            <?php if ($a['edital_arquivo']): ?>
              <a class="btn btn-outline" style="padding:4px 10px; font-size:11px;" target="_blank" href="<?= UPLOAD_URL . e($a['edital_arquivo']) ?>">Edital</a>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
          <td class="text-center"><span class="badge <?= assStatusBadge($a['status']) ?>"><?= assStatusLabel($a['status']) ?></span></td>
          <td class="text-center" style="white-space:nowrap;">
            <a class="btn btn-outline" style="padding:4px 10px; font-size:11px; position:relative;" href="<?= BASE_URL ?>/views/assembleia_ver.php?id=<?= (int)$a['id'] ?>">Ver / Votar<?php if ($podeEditar && (int)$a['qtd_pendentes'] > 0): ?> <span class="badge badge-danger" style="padding:1px 6px;"><?= (int)$a['qtd_pendentes'] ?></span><?php endif; ?></a>
            <?php if ($podeEditar): ?>
              <button type="button" class="btn btn-outline" style="padding:4px 10px; font-size:11px;" onclick="copiarLink('<?= e(BASE_URL) ?>/views/assembleia_ver.php?t=<?= e($a['token']) ?>')">Copiar link</button>
              <?php if (in_array($a['status'], ['rascunho','agendada'], true)): ?>
                <a class="btn btn-primary" style="padding:4px 10px; font-size:11px;" href="?iniciar=<?= (int)$a['id'] ?>" onclick="return confirm('Iniciar a votação agora?')">Iniciar</a>
              <?php elseif ($a['status']==='em_votacao'): ?>
                <a class="btn btn-danger" style="padding:4px 10px; font-size:11px;" href="?finalizar=<?= (int)$a['id'] ?>" onclick="return confirm('Finalizar a votação?')">Finalizar</a>
              <?php endif; ?>
              <button class="btn btn-outline" style="padding:4px 10px; font-size:11px;" onclick='editar(<?= json_encode($a, JSON_UNESCAPED_UNICODE) ?>)'>Editar</button>
              <a class="btn btn-danger" style="padding:4px 10px; font-size:11px;" href="?del=<?= (int)$a['id'] ?>" onclick="return confirm('Excluir esta assembleia e todas as pautas/votos?')">×</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php if ($podeEditar): ?>
<!-- Modal cadastro -->
<div id="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:999; align-items:center; justify-content:center; overflow:auto;">
  <div style="background:#fff; border-radius:14px; padding:28px; width:92%; max-width:620px; margin:24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
      <h2 style="font-size:18px;" id="modalTitle">Nova Assembleia</h2>
      <button type="button" onclick="fecharModal()" style="font-size:22px; color:#999;">×</button>
    </div>
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <input type="hidden" name="acao" value="salvar">
      <input type="hidden" name="id" id="f_id">
      <div class="form-group">
        <label>Título *</label>
        <input class="form-control" name="titulo" id="f_titulo" required>
      </div>
      <div class="form-grid form-grid-2">
        <div class="form-group"><label>Data *</label><input type="date" class="form-control" name="data_assembleia" id="f_data" required></div>
        <div class="form-group"><label>Horário</label><input type="time" class="form-control" name="horario" id="f_horario"></div>
      </div>
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label>Tipo</label>
          <select class="form-control" name="tipo" id="f_tipo">
            <option value="ordinaria">Ordinária</option>
            <option value="extraordinaria">Extraordinária</option>
          </select>
        </div>
        <div class="form-group">
          <label>Tempo de votação (min)</label>
          <input type="number" min="1" max="10080" class="form-control" name="tempo_votacao" id="f_tempo" placeholder="ex.: 60">
        </div>
      </div>
      <div class="form-group">
        <label>Descrição</label>
        <textarea class="form-control" name="descricao" id="f_desc" rows="3"></textarea>
      </div>
      <div class="form-group">
        <label>Link da reunião</label>
        <input type="url" class="form-control" name="link_reuniao" id="f_link" placeholder="https://meet.google.com/...">
      </div>
      <div class="form-group">
        <label>Edital (PDF, imagem ou DOC)</label>
        <input type="file" class="form-control" name="edital" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
        <small class="muted" id="f_edital_atual"></small>
      </div>
      <div class="form-group">
        <label>Status inicial</label>
        <select class="form-control" name="status" id="f_status">
          <option value="agendada">Agendada (visível aos moradores)</option>
          <option value="rascunho">Rascunho (não convocada)</option>
        </select>
      </div>
      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:6px;">
        <button type="button" class="btn btn-outline" onclick="fecharModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>

<script>
function copiarLink(url){
  (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject())
    .then(()=>alert('Link copiado:\n' + url))
    .catch(()=>prompt('Copie o link da assembleia:', url));
}
const modal = document.getElementById('modal');
function abrirModal(){
  modal.style.display='flex';
  document.getElementById('modalTitle').innerText='Nova Assembleia';
  modal.querySelector('form').reset();
  document.getElementById('f_id').value='';
  document.getElementById('f_edital_atual').innerText='';
  document.getElementById('f_status').disabled=false;
}
function fecharModal(){ modal.style.display='none'; }
function editar(a){
  document.getElementById('modalTitle').innerText='Editar Assembleia';
  document.getElementById('f_id').value      = a.id;
  document.getElementById('f_titulo').value  = a.titulo || '';
  document.getElementById('f_data').value    = a.data_assembleia || '';
  document.getElementById('f_horario').value = a.horario ? a.horario.substring(0,5) : '';
  document.getElementById('f_tipo').value    = a.tipo || 'ordinaria';
  document.getElementById('f_tempo').value   = a.tempo_votacao || '';
  document.getElementById('f_desc').value    = a.descricao || '';
  document.getElementById('f_link').value    = a.link_reuniao || '';
  // Em edição mantemos o status atual no banco; o seletor só vale p/ rascunho/agendada
  const st = document.getElementById('f_status');
  if (a.status === 'rascunho' || a.status === 'agendada') { st.value = a.status; st.disabled = false; }
  else { st.disabled = true; }
  document.getElementById('f_edital_atual').innerText = a.edital_arquivo ? 'Edital atual mantido se nenhum arquivo for enviado.' : '';
  modal.style.display='flex';
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
