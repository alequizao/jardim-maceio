<?php
$paginaTitulo    = 'Documentos';
$paginaSubtitulo = 'Repositório de balancetes, atas, convenções e relatórios';
$paginaAtiva     = 'documentos';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
$pdo = Database::conectar();

// Upload de documento
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['arquivo']['tmp_name']) && $podeEditar) {
    $titulo = trim($_POST['titulo'] ?? '');
    $tipo   = $_POST['tipo'] ?? 'outro';
    $mesRef = (int)($_POST['mes_referencia'] ?? 0);
    $anoRef = (int)($_POST['ano_referencia'] ?? 0);

    $ext = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
    $permitido = ['pdf','png','jpg','jpeg','doc','docx','xls','xlsx'];
    if (!in_array($ext, $permitido)) {
        $msg = 'Extensão não permitida.';
    } else {
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
        $arq = 'doc_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($_FILES['arquivo']['tmp_name'], UPLOAD_DIR . $arq)) {
            $u = Auth::usuario();
            $pdo->prepare("INSERT INTO documentos (titulo, tipo, arquivo, mes_referencia, ano_referencia, publico, criado_por)
                           VALUES (:t,:tp,:a,:m,:y,1,:cp)")
                ->execute([':t'=>$titulo, ':tp'=>$tipo, ':a'=>$arq, ':m'=>$mesRef ?: null, ':y'=>$anoRef ?: null, ':cp'=>$u['id']]);
            header('Location: ' . BASE_URL . '/views/documentos.php?ok=1');
            exit;
        }
    }
}

if (!empty($_GET['del']) && $podeEditar) {
    $id = (int)$_GET['del'];
    $arq = $pdo->query("SELECT arquivo FROM documentos WHERE id=$id")->fetchColumn();
    if ($arq && file_exists(UPLOAD_DIR.$arq)) @unlink(UPLOAD_DIR.$arq);
    $pdo->prepare("DELETE FROM documentos WHERE id=:id")->execute([':id'=>$id]);
    header('Location: ' . BASE_URL . '/views/documentos.php?ok=1');
    exit;
}

$docs = $pdo->query(
    "SELECT id, titulo, tipo, arquivo, mes_referencia, ano_referencia, criado_em
     FROM documentos ORDER BY ano_referencia DESC, mes_referencia DESC, criado_em DESC LIMIT 200"
)->fetchAll();
?>

<div class="row row-7-5">
  <div class="card">
    <div class="card-header"><span class="card-title">DOCUMENTOS ARMAZENADOS</span></div>
    <table class="table-list">
      <thead>
        <tr><th>Título</th><th>Tipo</th><th>Referência</th><th>Enviado em</th><th class="text-center">Ações</th></tr>
      </thead>
      <tbody>
        <?php if (empty($docs)): ?>
          <tr><td colspan="5" class="muted text-center" style="padding:30px;">Nenhum documento publicado.</td></tr>
        <?php else: foreach ($docs as $d): ?>
          <tr>
            <td style="font-weight:600;"><?= e($d['titulo']) ?></td>
            <td><span class="badge badge-muted"><?= strtoupper(e($d['tipo'])) ?></span></td>
            <td class="muted"><?= $d['mes_referencia'] ? mesPt((int)$d['mes_referencia']).'/'.$d['ano_referencia'] : '-' ?></td>
            <td class="muted"><?= dataHoraBr($d['criado_em']) ?></td>
            <td class="text-center">
              <a class="btn btn-outline" style="padding:4px 10px; font-size:11px;" target="_blank" href="<?= UPLOAD_URL . e($d['arquivo']) ?>">Abrir</a>
              <?php if ($podeEditar): ?><a class="btn btn-danger"  style="padding:4px 10px; font-size:11px;" href="?del=<?= (int)$d['id'] ?>" onclick="return confirm('Excluir este documento?')">×</a><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($podeEditar): ?>
  <div class="card">
    <div class="card-header"><span class="card-title">ENVIAR NOVO DOCUMENTO</span></div>
    <?php if (!empty($msg)): ?><div class="login-error"><?= e($msg) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <div class="form-group">
        <label>Título *</label>
        <input class="form-control" name="titulo" required>
      </div>
      <div class="form-group">
        <label>Tipo</label>
        <select name="tipo" class="form-control">
          <option value="balancete">Balancete</option>
          <option value="ata">Ata de Assembleia</option>
          <option value="relatorio">Relatório</option>
          <option value="convencao">Convenção</option>
          <option value="outro">Outro</option>
        </select>
      </div>
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label>Mês</label>
          <select name="mes_referencia" class="form-control">
            <option value="">—</option>
            <?php for ($i=1;$i<=12;$i++): ?>
              <option value="<?= $i ?>" <?= (int)date('m')===$i?'selected':'' ?>><?= mesPt($i) ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Ano</label>
          <input type="number" min="2020" max="2099" value="<?= date('Y') ?>" name="ano_referencia" class="form-control">
        </div>
      </div>
      <div class="form-group">
        <label>Arquivo *</label>
        <input type="file" name="arquivo" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx" required>
      </div>
      <button class="btn btn-primary" type="submit">Enviar documento</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
