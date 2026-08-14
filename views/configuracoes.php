<?php
$paginaTitulo    = 'Configurações';
$paginaSubtitulo = 'Categorias, fornecedores e usuários do sistema';
$paginaAtiva     = 'config';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::exigirLogin();
if (!Auth::podeEditar()) { header('Location: ' . BASE_URL . '/views/dashboard.php'); exit; }
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
$pdo = Database::conectar();

// Salvar identidade / marca (logo + títulos)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form'] ?? '')==='marca'
    && in_array(Auth::usuario()['tipo'], ['admin','sindico'], true)) {

    $dados = [
        'marca_titulo'    => trim($_POST['marca_titulo'] ?? ''),
        'marca_subtitulo' => trim($_POST['marca_subtitulo'] ?? ''),
        'marca_tag'       => trim($_POST['marca_tag'] ?? ''),
    ];

    // Remover logo atual (voltar ao ícone padrão)
    if (!empty($_POST['remover_logo'])) {
        $atual = cfg('marca_logo');
        if ($atual && is_file(UPLOAD_DIR . $atual)) @unlink(UPLOAD_DIR . $atual);
        $dados['marca_logo'] = '';
    }

    // Upload de nova logo
    if (!empty($_FILES['logo']['tmp_name'])) {
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) { header('Location: ?erro=upload#marca'); exit; }
        $ext       = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $permitido = ['png','jpg','jpeg','svg','webp','gif'];
        if (!in_array($ext, $permitido, true)) { header('Location: ?erro=ext#marca'); exit; }
        if ($_FILES['logo']['size'] > 2 * 1024 * 1024) { header('Location: ?erro=tam#marca'); exit; }

        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
        $antiga = cfg('marca_logo');
        if ($antiga && is_file(UPLOAD_DIR . $antiga)) @unlink(UPLOAD_DIR . $antiga);

        $nome = 'logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        move_uploaded_file($_FILES['logo']['tmp_name'], UPLOAD_DIR . $nome);
        $dados['marca_logo'] = $nome;
    }

    cfgSalvar($dados);
    header('Location: ?ok=marca#marca'); exit;
}
// Categorias e Fornecedores foram movidos para Contas a Pagar (sub-abas).

$usuarios     = $pdo->query("SELECT id,nome,email,tipo,ativo,ultimo_login FROM usuarios ORDER BY nome")->fetchAll();
$usuarioAtual = Auth::usuario();
?>

<?php if (in_array($usuarioAtual['tipo'], ['admin','sindico'], true)): ?>
<!-- IDENTIDADE / MARCA -->
<div class="card" id="marca" style="margin-bottom:22px;">
  <div class="card-header"><span class="card-title">IDENTIDADE / MARCA</span></div>

  <?php if (($_GET['ok'] ?? '')==='marca'): ?>
    <div class="badge badge-success" style="display:block; padding:10px 12px; margin-bottom:14px;">Identidade atualizada com sucesso.</div>
  <?php elseif (isset($_GET['erro'])): ?>
    <div class="badge badge-danger" style="display:block; padding:10px 12px; margin-bottom:14px;">
      <?= ['ext'=>'Formato de logo não permitido (use PNG, JPG, SVG, WEBP ou GIF).',
           'tam'=>'A logo excede o limite de 2 MB.',
           'upload'=>'Falha ao enviar a logo.'][$_GET['erro']] ?? 'Não foi possível salvar.' ?>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="form-grid form-grid-3">
    <input type="hidden" name="form" value="marca">

    <div class="form-group" style="grid-column:1/-1; flex-direction:row; align-items:center; gap:16px;">
      <div style="width:64px; height:64px; flex-shrink:0; border-radius:12px; background:linear-gradient(180deg,var(--verde-900),var(--verde-800)); display:grid; place-items:center; padding:8px;">
        <?php $logoAtual = cfg('marca_logo'); ?>
        <?php if ($logoAtual): ?>
          <img src="<?= UPLOAD_URL . e($logoAtual) ?>" alt="Logo atual" style="width:100%; height:100%; object-fit:contain;">
        <?php else: ?>
          <span style="width:40px; height:40px; display:block;"><?= marcaLogoPadraoSvg() ?></span>
        <?php endif; ?>
      </div>
      <div style="flex:1;">
        <label>Logo (PNG, JPG, SVG, WEBP ou GIF — máx. 2 MB)</label>
        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp,.gif" class="form-control">
        <?php if ($logoAtual): ?>
          <label style="font-weight:400; text-transform:none; letter-spacing:0; margin-top:8px; display:flex; align-items:center; gap:6px;">
            <input type="checkbox" name="remover_logo" value="1"> Remover logo e voltar ao ícone padrão
          </label>
        <?php endif; ?>
      </div>
    </div>

    <div class="form-group"><label>Título (linha 1)</label><input name="marca_titulo" class="form-control" value="<?= e(cfg('marca_titulo','TRANSPARÊNCIA')) ?>"></div>
    <div class="form-group"><label>Subtítulo (linha 2)</label><input name="marca_subtitulo" class="form-control" value="<?= e(cfg('marca_subtitulo','JARDIM MACEIÓ')) ?>"></div>
    <div class="form-group"><label>Tag (linha 3)</label><input name="marca_tag" class="form-control" value="<?= e(cfg('marca_tag','')) ?>" placeholder="(deixe em branco para ocultar)"></div>

    <button class="btn btn-primary" style="grid-column:1/-1;" type="submit">Salvar Identidade</button>
  </form>
</div>
<?php endif; ?>

<!-- USUÁRIOS -->
<?php if ($usuarioAtual['tipo'] === 'admin'): ?>
<div class="card">
  <div class="card-header">
    <span class="card-title">USUÁRIOS DO SISTEMA</span>
    <button class="btn btn-primary" onclick="abrirModalUsr()">+ Novo Usuário</button>
  </div>
  <table class="table-list">
    <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Último login</th><th class="text-center">Status</th><th class="text-center">Ações</th></tr></thead>
    <tbody>
      <?php foreach ($usuarios as $u): ?>
        <tr>
          <td style="font-weight:600;"><?= e($u['nome']) ?></td>
          <td class="muted"><?= e($u['email']) ?></td>
          <td><span class="badge <?= $u['tipo']==='admin'?'badge-success':'badge-muted' ?>"><?= strtoupper(e($u['tipo'])) ?></span></td>
          <td class="muted"><?= dataHoraBr($u['ultimo_login']) ?></td>
          <td class="text-center"><span class="badge <?= $u['ativo']?'badge-success':'badge-danger' ?>"><?= $u['ativo']?'ATIVO':'INATIVO' ?></span></td>
          <td class="text-center">
            <button class="btn btn-outline" style="padding:4px 10px; font-size:11px;" onclick='editarUsr(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)'>Editar</button>
            <?php if ((int)$u['id'] !== (int)$usuarioAtual['id']): ?>
              <button class="btn btn-danger" style="padding:4px 10px; font-size:11px;" onclick="excluirUsr(<?= (int)$u['id'] ?>)">×</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div id="modalUsr" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:999; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:14px; padding:28px; width:90%; max-width:480px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
      <h2 style="font-size:18px;" id="modalUsrTitle">Novo Usuário</h2>
      <button onclick="document.getElementById('modalUsr').style.display='none'" style="font-size:22px; color:#999;">×</button>
    </div>
    <form id="formUsr">
      <input type="hidden" name="id" id="u_id">
      <div class="form-grid">
        <div class="form-group"><label>Nome *</label><input class="form-control" name="nome" id="u_nome" required></div>
        <div class="form-group"><label>E-mail *</label><input type="email" class="form-control" name="email" id="u_email" required></div>
        <div class="form-group"><label>Perfil</label>
          <select class="form-control" name="tipo" id="u_tipo">
            <option value="morador">Morador</option>
            <option value="sindico">Síndico</option>
            <option value="admin">Administrador</option>
          </select>
        </div>
        <div class="form-group"><label>Senha (deixe em branco para manter)</label><input type="password" class="form-control" name="senha" id="u_senha"></div>
        <div class="form-group"><label><input type="checkbox" name="ativo" id="u_ativo" value="1" checked> Usuário ativo</label></div>
      </div>
      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('modalUsr').style.display='none'">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function abrirModalUsr(){ document.getElementById('modalUsr').style.display='flex'; document.getElementById('modalUsrTitle').innerText='Novo Usuário'; document.getElementById('formUsr').reset(); document.getElementById('u_id').value=''; document.getElementById('u_ativo').checked=true; }
function editarUsr(u){
  document.getElementById('modalUsr').style.display='flex';
  document.getElementById('modalUsrTitle').innerText='Editar Usuário';
  document.getElementById('u_id').value = u.id;
  document.getElementById('u_nome').value = u.nome;
  document.getElementById('u_email').value = u.email;
  document.getElementById('u_tipo').value = u.tipo;
  document.getElementById('u_senha').value = '';
  document.getElementById('u_ativo').checked = !!parseInt(u.ativo);
}
async function excluirUsr(id){
  if (!confirm('Inativar este usuário?')) return;
  await App.api('usuarios.php?id='+id, {method:'DELETE'});
  location.reload();
}
const fU = document.getElementById('formUsr');
if (fU) fU.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  const data = Object.fromEntries(fd.entries());
  data.ativo = document.getElementById('u_ativo').checked ? 1 : 0;
  const id = data.id;
  const method = id ? 'PUT' : 'POST';
  const url = 'usuarios.php' + (id ? '?id='+id : '');
  const r = await App.api(url, {method, body: data});
  if (r && r.ok) location.reload();
  else alert('Erro: ' + (r?.erro || ''));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
