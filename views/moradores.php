<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
$paginaTitulo    = 'Moradores';
$paginaSubtitulo = 'Cadastro de proprietários e inquilinos por unidade';
$paginaAtiva     = 'moradores';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::exigirLogin();
if (!Auth::podeEditar()) { header('Location: ' . BASE_URL . '/views/dashboard.php'); exit; }
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
$pdo = Database::conectar();

$busca = trim($_GET['q'] ?? '');
$sql = "SELECT id, nome, cpf, telefone, email, bloco, apartamento, proprietario
        FROM moradores WHERE ativo=1";
$par = [];
if ($busca !== '') {
    // O PDO deste sistema roda sem emulacao de prepare: o mesmo placeholder
    // nao pode se repetir na consulta, por isso :q1..:q5.
    $sql .= " AND (nome LIKE :q1 OR bloco LIKE :q2 OR apartamento LIKE :q3 OR cpf LIKE :q4 OR email LIKE :q5)";
    $like = '%'.$busca.'%';
    foreach (['q1','q2','q3','q4','q5'] as $ph) { $par[':'.$ph] = $like; }
}
$sql .= " ORDER BY LENGTH(bloco), bloco, LENGTH(apartamento), apartamento, nome";
$stmt = $pdo->prepare($sql); $stmt->execute($par);
$mor = $stmt->fetchAll();
$total = count($mor);
$props = count(array_filter($mor, fn($m)=>$m['proprietario']==1));
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-icon verde"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
    <div class="kpi-label">TOTAL DE MORADORES</div>
    <div class="kpi-value verde"><?= $total ?></div>
    <div class="kpi-sub">cadastros ativos</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon azul"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div>
    <div class="kpi-label">PROPRIETÁRIOS</div>
    <div class="kpi-value azul"><?= $props ?></div>
    <div class="kpi-sub">titulares de unidade</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon amarelo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>
    <div class="kpi-label">INQUILINOS</div>
    <div class="kpi-value amarelo"><?= $total - $props ?></div>
    <div class="kpi-sub">moradores não-proprietários</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon vermelho"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
    <div class="kpi-label">BLOCOS</div>
    <div class="kpi-value vermelho"><?= (int)$pdo->query("SELECT COUNT(DISTINCT bloco) FROM moradores WHERE ativo=1")->fetchColumn() ?></div>
    <div class="kpi-sub">blocos com moradores</div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="flex-wrap:wrap; gap:12px;">
    <span class="card-title">MORADORES</span>
    <div style="display:flex; gap:8px;">
      <form method="get" style="display:flex; gap:8px;">
        <input type="text" name="q" value="<?= e($busca) ?>" placeholder="Buscar por nome, bloco, apto…" class="form-control" style="padding:6px 10px; min-width:280px;">
        <button class="btn btn-outline">Buscar</button>
      </form>
      <?php if ($podeEditar): ?><button class="btn btn-primary" onclick="abrirModal()">+ Novo Morador</button><?php endif; ?>
    </div>
  </div>

  <table class="table-list">
    <thead>
      <tr>
        <th>Bloco / Apto</th>
        <th>Nome</th>
        <th>CPF</th>
        <th>Telefone</th>
        <th>E-mail</th>
        <th class="text-center">Tipo</th>
        <th class="text-center">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($mor)): ?>
        <tr><td colspan="7" class="muted text-center" style="padding:30px;">Nenhum morador cadastrado.</td></tr>
      <?php else: foreach ($mor as $m): ?>
        <tr>
          <td style="font-weight:700; color:var(--verde-700);"><?= e($m['bloco']) ?>/<?= e($m['apartamento']) ?></td>
          <td><?= e($m['nome']) ?></td>
          <td class="muted"><?= e($m['cpf'] ?? '-') ?></td>
          <td class="muted"><?= e($m['telefone'] ?? '-') ?></td>
          <td class="muted"><?= e($m['email'] ?? '-') ?></td>
          <td class="text-center">
            <span class="badge <?= $m['proprietario']?'badge-success':'badge-muted' ?>"><?= $m['proprietario']?'PROPRIETÁRIO':'INQUILINO' ?></span>
          </td>
          <td class="text-center">
            <?php if ($podeEditar): ?>
            <button class="btn btn-outline" style="padding:4px 10px; font-size:11px;" onclick='editar(<?= (int)$m["id"] ?>)'>Editar</button>
            <button class="btn btn-danger"  style="padding:4px 10px; font-size:11px;" onclick='excluir(<?= (int)$m["id"] ?>)'>×</button>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal -->
<div id="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:999; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:14px; padding:28px; width:90%; max-width:560px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
      <h2 style="font-size:18px;" id="modalTitle">Novo Morador</h2>
      <button onclick="fecharModal()" style="font-size:22px; color:#999;">×</button>
    </div>
    <form id="formMor">
      <input type="hidden" name="id" id="f_id">
      <div class="form-grid form-grid-2">
        <div class="form-group" style="grid-column:1/-1;">
          <label>Nome completo *</label>
          <input class="form-control" name="nome" id="f_nome" required>
        </div>
        <div class="form-group"><label>Bloco *</label><input class="form-control" name="bloco" id="f_bloco" required></div>
        <div class="form-group"><label>Apartamento *</label><input class="form-control" name="apartamento" id="f_apto" required></div>
        <div class="form-group"><label>CPF</label><input class="form-control" name="cpf" id="f_cpf"></div>
        <div class="form-group"><label>Telefone</label><input class="form-control" name="telefone" id="f_tel"></div>
        <div class="form-group" style="grid-column:1/-1;"><label>E-mail</label><input class="form-control" name="email" type="email" id="f_email"></div>
        <div class="form-group" style="grid-column:1/-1;">
          <label><input type="checkbox" name="proprietario" id="f_prop" value="1"> É proprietário da unidade</label>
        </div>
      </div>
      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
        <button type="button" class="btn btn-outline" onclick="fecharModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById('modal');
function abrirModal(){ modal.style.display='flex'; document.getElementById('modalTitle').innerText='Novo Morador'; document.getElementById('formMor').reset(); document.getElementById('f_id').value=''; document.getElementById('f_prop').checked=true; }
function fecharModal(){ modal.style.display='none'; }
async function editar(id){
  const m = await App.api('moradores.php?id='+id);
  document.getElementById('modalTitle').innerText='Editar Morador';
  document.getElementById('f_id').value = m.id;
  document.getElementById('f_nome').value = m.nome || '';
  document.getElementById('f_bloco').value = m.bloco || '';
  document.getElementById('f_apto').value = m.apartamento || '';
  document.getElementById('f_cpf').value = m.cpf || '';
  document.getElementById('f_tel').value = m.telefone || '';
  document.getElementById('f_email').value = m.email || '';
  document.getElementById('f_prop').checked = !!parseInt(m.proprietario);
  modal.style.display='flex';
}
async function excluir(id){
  if (!confirm('Excluir este morador?')) return;
  await App.api('moradores.php?id='+id, {method:'DELETE'});
  location.reload();
}
document.getElementById('formMor').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  const data = Object.fromEntries(fd.entries());
  data.proprietario = document.getElementById('f_prop').checked ? 1 : 0;
  const id = data.id;
  const method = id ? 'PUT' : 'POST';
  const url = 'moradores.php' + (id ? '?id='+id : '');
  const r = await App.api(url, {method, body: data});
  if (r && r.ok) location.reload();
  else alert('Erro: ' + (r?.erro || ''));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
