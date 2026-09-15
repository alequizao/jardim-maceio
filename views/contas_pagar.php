<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::exigirLogin();
$pdo = Database::conectar();

// Sub-aba ativa: despesas | fornecedores | categorias
$aba = $_GET['aba'] ?? 'despesas';
if (!in_array($aba, ['despesas','fornecedores','categorias'], true)) $aba = 'despesas';

/* ===== Salvar CATEGORIA (sub-aba) ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form'] ?? '')==='categoria' && Auth::podeEditar()) {
    $cid = (int)($_POST['id'] ?? 0);
    if ($cid > 0) {
        $pdo->prepare("UPDATE categorias SET nome=:n, tipo=:t, cor=:c WHERE id=:id")
            ->execute([':n'=>trim($_POST['nome']), ':t'=>$_POST['tipo'], ':c'=>$_POST['cor'] ?: '#0F7B3E', ':id'=>$cid]);
    } else {
        $pdo->prepare("INSERT INTO categorias (nome,tipo,cor) VALUES (:n,:t,:c)")
            ->execute([':n'=>trim($_POST['nome']), ':t'=>$_POST['tipo'], ':c'=>$_POST['cor'] ?: '#0F7B3E']);
    }
    header('Location: contas_pagar.php?aba=categorias&ok=1'); exit;
}
/* ===== Salvar FORNECEDOR (sub-aba) ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form'] ?? '')==='fornecedor' && Auth::podeEditar()) {
    $fid = (int)($_POST['id'] ?? 0);
    if ($fid > 0) {
        $pdo->prepare("UPDATE fornecedores SET nome=:n, cnpj_cpf=:c, telefone=:t, email=:e, categoria_id=:cat WHERE id=:id")
            ->execute([':n'=>trim($_POST['nome']), ':c'=>$_POST['cnpj_cpf']?:null, ':t'=>$_POST['telefone']?:null, ':e'=>$_POST['email']?:null, ':cat'=>(int)($_POST['categoria_id'] ?? 0) ?: null, ':id'=>$fid]);
    } else {
        $pdo->prepare("INSERT INTO fornecedores (nome,cnpj_cpf,telefone,email,categoria_id) VALUES (:n,:c,:t,:e,:cat)")
            ->execute([':n'=>trim($_POST['nome']), ':c'=>$_POST['cnpj_cpf']?:null, ':t'=>$_POST['telefone']?:null, ':e'=>$_POST['email']?:null, ':cat'=>(int)($_POST['categoria_id'] ?? 0) ?: null]);
    }
    header('Location: contas_pagar.php?aba=fornecedores&ok=1'); exit;
}
/* ===== Exclusões (inativar) ===== */
if (!empty($_GET['del_cat']) && Auth::podeEditar()) { $pdo->prepare("UPDATE categorias SET ativo=0 WHERE id=:id")->execute([':id'=>(int)$_GET['del_cat']]); header('Location: contas_pagar.php?aba=categorias&ok=1'); exit; }
if (!empty($_GET['del_for']) && Auth::podeEditar()) { $pdo->prepare("UPDATE fornecedores SET ativo=0 WHERE id=:id")->execute([':id'=>(int)$_GET['del_for']]); header('Location: contas_pagar.php?aba=fornecedores&ok=1'); exit; }

$paginaTitulo    = 'Contas a Pagar';
$paginaSubtitulo = 'Gestão de despesas, fornecedores e vencimentos';
$paginaAtiva     = 'pagar';
require_once __DIR__ . '/../includes/header.php';

// Filtros (aba despesas)
$status = $_GET['status'] ?? '';
$mes    = $_GET['mes'] ?? date('Y-m');

// Atualiza vencidas
$pdo->exec("UPDATE despesas SET status='vencido' WHERE status='pendente' AND data_vencimento < CURDATE()");

// Totais
$inicio = $mes . '-01';
$fim    = date('Y-m-t', strtotime($inicio));
$totMes    = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE data_competencia BETWEEN '$inicio' AND '$fim'")->fetchColumn();
$totPago   = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='pago' AND data_pagamento BETWEEN '$inicio' AND '$fim'")->fetchColumn();
$totPend   = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='pendente' AND data_competencia BETWEEN '$inicio' AND '$fim'")->fetchColumn();
$totVenc   = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='vencido'")->fetchColumn();

// Listagem de despesas
$sql = "SELECT d.id, d.descricao, d.valor, d.data_competencia, d.data_vencimento, d.data_pagamento, d.status,
               c.nome AS categoria, f.nome AS fornecedor
        FROM despesas d
        LEFT JOIN categorias c ON c.id=d.categoria_id
        LEFT JOIN fornecedores f ON f.id=d.fornecedor_id
        WHERE 1=1";
$par = [];
if ($status !== '') { $sql .= " AND d.status=:st"; $par[':st']=$status; }
if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $sql .= " AND DATE_FORMAT(d.data_competencia,'%Y-%m')=:m"; $par[':m']=$mes;
}
$sql .= " ORDER BY d.data_vencimento ASC, d.id DESC LIMIT 200";
$stmt = $pdo->prepare($sql); $stmt->execute($par);
$despesas = $stmt->fetchAll();

// Dados auxiliares
$categorias   = $pdo->query("SELECT id,nome FROM categorias WHERE tipo='despesa' AND ativo=1 ORDER BY nome")->fetchAll();
$fornecedores = $pdo->query("SELECT id,nome FROM fornecedores WHERE ativo=1 ORDER BY nome")->fetchAll();

// Dados das sub-abas (gestão)
$catsAll     = $pdo->query("SELECT * FROM categorias WHERE ativo=1 ORDER BY tipo, nome")->fetchAll();
$fornAll     = $pdo->query("SELECT f.*, c.nome AS cat FROM fornecedores f LEFT JOIN categorias c ON c.id=f.categoria_id WHERE f.ativo=1 ORDER BY f.nome")->fetchAll();
$catsDespesa = array_filter($catsAll, fn($c)=>$c['tipo']==='despesa');
?>

<style>
.subtabs { display:flex; gap:6px; border-bottom:2px solid var(--linha); margin-bottom:22px; flex-wrap:wrap; }
.subtab-link {
  padding:10px 18px; font-size:13px; font-weight:600; color:var(--texto-2);
  border:none; background:none; border-bottom:2px solid transparent; margin-bottom:-2px;
  cursor:pointer; text-decoration:none; transition:color .15s, border-color .15s;
}
.subtab-link:hover { color:var(--verde-800); }
.subtab-link.active { color:var(--verde-800); border-bottom-color:var(--verde-800); }
.modal-box { max-height:90vh; overflow-y:auto; }
</style>

<!-- Navegação das sub-abas -->
<div class="subtabs">
  <a href="contas_pagar.php?aba=despesas"    class="subtab-link <?= $aba==='despesas'?'active':'' ?>">Despesas</a>
  <a href="contas_pagar.php?aba=fornecedores" class="subtab-link <?= $aba==='fornecedores'?'active':'' ?>">Fornecedores</a>
  <a href="contas_pagar.php?aba=categorias"   class="subtab-link <?= $aba==='categorias'?'active':'' ?>">Categorias</a>
</div>

<?php if ($aba === 'despesas'): ?>
<!-- ============ ABA DESPESAS ============ -->
<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-icon vermelho"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/></svg></div>
    <div class="kpi-label">TOTAL DO MÊS</div>
    <div class="kpi-value vermelho"><?= brl($totMes) ?></div>
    <div class="kpi-sub"><?= mesPt((int)date('m', strtotime($inicio))) ?>/<?= date('Y', strtotime($inicio)) ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon verde"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
    <div class="kpi-label">PAGAS</div>
    <div class="kpi-value verde"><?= brl($totPago) ?></div>
    <div class="kpi-sub">No mês corrente</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon amarelo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
    <div class="kpi-label">PENDENTES</div>
    <div class="kpi-value amarelo"><?= brl($totPend) ?></div>
    <div class="kpi-sub">Aguardando pagamento</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon vermelho"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
    <div class="kpi-label">VENCIDAS</div>
    <div class="kpi-value vermelho"><?= brl($totVenc) ?></div>
    <div class="kpi-sub">Requer atenção</div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
    <span class="card-title">DESPESAS</span>
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <form method="get" style="display:flex; gap:8px;">
        <input type="hidden" name="aba" value="despesas">
        <select name="status" class="card-select">
          <option value="">Todos os status</option>
          <option value="pendente" <?= $status==='pendente'?'selected':'' ?>>Pendente</option>
          <option value="vencido"  <?= $status==='vencido' ?'selected':'' ?>>Vencido</option>
          <option value="pago"     <?= $status==='pago'    ?'selected':'' ?>>Pago</option>
          <option value="cancelado"<?= $status==='cancelado'?'selected':'' ?>>Cancelado</option>
        </select>
        <input type="month" name="mes" value="<?= e($mes) ?>" class="form-control" style="padding:6px 12px;">
        <button class="btn btn-outline">Filtrar</button>
      </form>
      <?php if ($podeEditar): ?><button class="btn btn-primary" onclick="abrirModal()">+ Nova Despesa</button><?php endif; ?>
    </div>
  </div>

  <table class="table-list">
    <thead>
      <tr>
        <th>Descrição</th>
        <th>Fornecedor</th>
        <th>Categoria</th>
        <th>Vencimento</th>
        <th class="text-right">Valor</th>
        <th class="text-center">Status</th>
        <th class="text-center">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($despesas)): ?>
        <tr><td colspan="7" class="muted text-center" style="padding:30px;">Nenhuma despesa cadastrada para esse filtro.</td></tr>
      <?php else: foreach ($despesas as $d): ?>
        <tr>
          <td style="font-weight:600;"><?= e($d['descricao']) ?></td>
          <td class="muted"><?= e($d['fornecedor'] ?? '-') ?></td>
          <td class="muted"><?= e($d['categoria'] ?? '-') ?></td>
          <td class="muted"><?= dataBr($d['data_vencimento']) ?></td>
          <td class="text-right" style="font-weight:600;"><?= brl((float)$d['valor']) ?></td>
          <td class="text-center"><span class="badge <?= statusBadge($d['status']) ?>"><?= statusTexto($d['status']) ?></span></td>
          <td class="text-center">
            <?php if ($podeEditar): ?>
            <button class="btn btn-outline" style="padding:4px 10px; font-size:11px;" onclick='editar(<?= (int)$d["id"] ?>)'>Editar</button>
            <button class="btn btn-danger"  style="padding:4px 10px; font-size:11px;" onclick='excluir(<?= (int)$d["id"] ?>)'>×</button>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal de cadastro/edição de despesa -->
<div id="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:999; align-items:center; justify-content:center; padding:20px;">
  <div class="modal-box" style="background:#fff; border-radius:14px; padding:28px; width:90%; max-width:560px; box-shadow:0 20px 40px rgba(0,0,0,.2);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
      <h2 style="font-size:18px;" id="modalTitle">Nova Despesa</h2>
      <button onclick="fecharModal()" style="font-size:22px; color:#999;">×</button>
    </div>
    <form id="formDespesa" enctype="multipart/form-data">
      <input type="hidden" name="id" id="f_id">
      <div class="form-grid form-grid-2">
        <div class="form-group" style="grid-column: 1 / -1;">
          <label>Descrição *</label>
          <input class="form-control" name="descricao" id="f_descricao" required>
        </div>
        <div class="form-group">
          <label>Categoria</label>
          <select class="form-control" name="categoria_id" id="f_categoria">
            <option value="">—</option>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Fornecedor</label>
          <div style="display:flex; gap:6px; align-items:stretch;">
            <select class="form-control" name="fornecedor_id" id="f_fornecedor" style="flex:1; min-width:0;">
              <option value="">—</option>
              <?php foreach ($fornecedores as $f): ?>
                <option value="<?= $f['id'] ?>"><?= e($f['nome']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline" style="white-space:nowrap; padding:0 12px;" onclick="novoFornecedor()" title="Cadastrar novo fornecedor">+ Novo</button>
          </div>
        </div>
        <div class="form-group">
          <label>Valor (R$) *</label>
          <input type="number" step="0.01" min="0" class="form-control" name="valor" id="f_valor" required>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select class="form-control" name="status" id="f_status">
            <option value="pendente">Pendente</option>
            <option value="pago">Pago</option>
            <option value="cancelado">Cancelado</option>
          </select>
        </div>
        <div class="form-group">
          <label>Competência</label>
          <input type="date" class="form-control" name="data_competencia" id="f_competencia">
        </div>
        <div class="form-group">
          <label>Vencimento</label>
          <input type="date" class="form-control" name="data_vencimento" id="f_vencimento">
        </div>
        <div class="form-group">
          <label>Data Pagamento</label>
          <input type="date" class="form-control" name="data_pagamento" id="f_pagamento">
        </div>
        <div class="form-group">
          <label>Forma Pagamento</label>
          <input class="form-control" name="forma_pagamento" id="f_forma">
        </div>
        <div class="form-group" style="grid-column: 1 / -1;">
          <label>Observações</label>
          <textarea class="form-control" name="observacoes" id="f_obs" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label>Número da NF (opcional)</label>
          <input class="form-control" name="nf_numero" id="f_nf_numero">
        </div>
        <div class="form-group">
          <label>Anexar Nota Fiscal</label>
          <input type="file" class="form-control" name="nota_fiscal" id="f_nota_fiscal" accept=".pdf,.png,.jpg,.jpeg,.xml">
          <small id="f_nf_atual" class="muted" style="display:none; margin-top:4px;"></small>
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
function abrirModal(){ modal.style.display='flex'; document.getElementById('modalTitle').innerText='Nova Despesa'; document.getElementById('formDespesa').reset(); document.getElementById('f_id').value=''; document.getElementById('f_nf_atual').style.display='none'; document.getElementById('f_competencia').value=new Date().toISOString().substr(0,10); document.getElementById('f_vencimento').value=new Date().toISOString().substr(0,10); }
function fecharModal(){ modal.style.display='none'; }
async function editar(id){
  const d = await App.api('despesas.php?id='+id);
  document.getElementById('modalTitle').innerText='Editar Despesa';
  document.getElementById('f_id').value = d.id;
  document.getElementById('f_descricao').value = d.descricao || '';
  document.getElementById('f_categoria').value = d.categoria_id || '';
  document.getElementById('f_fornecedor').value = d.fornecedor_id || '';
  document.getElementById('f_valor').value = d.valor;
  document.getElementById('f_status').value = d.status;
  document.getElementById('f_competencia').value = d.data_competencia || '';
  document.getElementById('f_vencimento').value = d.data_vencimento || '';
  document.getElementById('f_pagamento').value = d.data_pagamento || '';
  document.getElementById('f_forma').value = d.forma_pagamento || '';
  document.getElementById('f_obs').value = d.observacoes || '';
  document.getElementById('f_nf_numero').value = '';
  document.getElementById('f_nota_fiscal').value = '';
  const nfInfo = document.getElementById('f_nf_atual');
  if (d.nota_fiscal_id) { nfInfo.textContent = 'Já existe NF anexada (nº ' + (d.nota_fiscal_id) + '). Enviar um novo arquivo adiciona outra NF.'; nfInfo.style.display='block'; }
  else { nfInfo.style.display='none'; }
  modal.style.display='flex';
}
async function novoFornecedor(){
  const nome = prompt('Nome do novo fornecedor:');
  if (!nome || !nome.trim()) return;
  const r = await App.api('fornecedores.php', {method:'POST', body:{nome: nome.trim()}});
  if (r && r.ok) {
    const sel = document.getElementById('f_fornecedor');
    sel.add(new Option(r.nome, r.id, true, true));
  } else {
    alert('Erro ao cadastrar fornecedor: ' + (r?.erro || ''));
  }
}
async function excluir(id){
  if (!confirm('Excluir esta despesa?')) return;
  await App.api('despesas.php?id='+id, {method:'DELETE'});
  location.reload();
}
document.getElementById('formDespesa').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  // Envia como multipart (FormData) para suportar o anexo da nota fiscal.
  // O campo "id" no corpo indica edição; vazio indica novo lançamento.
  const fd = new FormData(ev.target);
  let r;
  try {
    const resp = await fetch(App.BASE_URL + '/api/despesas.php', {method:'POST', body:fd, credentials:'same-origin'});
    if (resp.status === 401) { window.location.href = App.BASE_URL + '/views/login.php'; return; }
    const txt = await resp.text();
    try { r = JSON.parse(txt); } catch(e) { r = {ok:false, erro:'Erro no servidor (HTTP '+resp.status+').'}; }
  } catch (e) { r = {ok:false, erro:'Falha de conexão.'}; }
  if (r && r.ok) location.reload();
  else alert('Erro ao salvar: ' + (r?.erro || ''));
});
</script>

<?php elseif ($aba === 'fornecedores'): ?>
<!-- ============ ABA FORNECEDORES ============ -->
<div class="card" id="fornecedores">
  <div class="card-header"><span class="card-title">FORNECEDORES</span></div>

  <?php if (($_GET['ok'] ?? '')==='1'): ?>
    <div class="badge badge-success" style="display:block; padding:10px 12px; margin-bottom:14px;">Fornecedor salvo com sucesso.</div>
  <?php endif; ?>

  <?php if ($podeEditar): ?>
  <form method="post" class="form-grid form-grid-2" style="margin-bottom:16px;">
    <input type="hidden" name="form" value="fornecedor">
    <input type="hidden" name="id" id="for_id">
    <div class="form-group" style="grid-column:1/-1;"><label>Nome *</label><input name="nome" id="for_nome" class="form-control" required></div>
    <div class="form-group"><label>CNPJ/CPF</label><input name="cnpj_cpf" id="for_doc" class="form-control"></div>
    <div class="form-group"><label>Telefone</label><input name="telefone" id="for_tel" class="form-control"></div>
    <div class="form-group" style="grid-column:1/-1;"><label>E-mail</label><input type="email" name="email" id="for_email" class="form-control"></div>
    <div class="form-group" style="grid-column:1/-1;">
      <label>Categoria padrão</label>
      <select name="categoria_id" id="for_cat" class="form-control">
        <option value="">—</option>
        <?php foreach ($catsDespesa as $c): ?>
          <option value="<?= $c['id'] ?>"><?= e($c['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="grid-column:1/-1; display:flex; gap:10px;">
      <button class="btn btn-primary" type="submit">Salvar Fornecedor</button>
      <button class="btn btn-outline" type="button" onclick="limparFor()">Limpar</button>
    </div>
  </form>
  <?php endif; ?>

  <table class="table-list">
    <thead><tr><th>Nome</th><th>Doc</th><th>Telefone</th><th>Categoria</th><th class="text-center">Ações</th></tr></thead>
    <tbody>
      <?php if (empty($fornAll)): ?>
        <tr><td colspan="5" class="muted text-center" style="padding:20px;">Nenhum fornecedor cadastrado.</td></tr>
      <?php else: foreach ($fornAll as $f): ?>
        <tr>
          <td style="font-weight:600;"><?= e($f['nome']) ?></td>
          <td class="muted"><?= e($f['cnpj_cpf'] ?? '-') ?></td>
          <td class="muted"><?= e($f['telefone'] ?? '-') ?></td>
          <td class="muted"><?= e($f['cat'] ?? '-') ?></td>
          <td class="text-center">
            <?php if ($podeEditar): ?>
            <button class="btn btn-outline" style="padding:4px 10px; font-size:11px;" onclick='editarFor(<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>)'>Editar</button>
            <a class="btn btn-danger" style="padding:4px 10px; font-size:11px;" href="contas_pagar.php?aba=fornecedores&del_for=<?= (int)$f['id'] ?>" onclick="return confirm('Excluir este fornecedor?')">×</a>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>
function editarFor(f) {
  document.getElementById('for_id').value = f.id;
  document.getElementById('for_nome').value = f.nome;
  document.getElementById('for_doc').value = f.cnpj_cpf || '';
  document.getElementById('for_tel').value = f.telefone || '';
  document.getElementById('for_email').value = f.email || '';
  document.getElementById('for_cat').value = f.categoria_id || '';
  window.scrollTo({top:0, behavior:'smooth'});
}
function limparFor() {
  document.getElementById('for_id').value = '';
  document.querySelector('#fornecedores form').reset();
}
</script>

<?php else: /* categorias */ ?>
<!-- ============ ABA CATEGORIAS ============ -->
<div class="card" id="categorias">
  <div class="card-header"><span class="card-title">CATEGORIAS</span></div>

  <?php if (($_GET['ok'] ?? '')==='1'): ?>
    <div class="badge badge-success" style="display:block; padding:10px 12px; margin-bottom:14px;">Categoria salva com sucesso.</div>
  <?php endif; ?>

  <?php if ($podeEditar): ?>
  <form method="post" class="form-grid form-grid-3" style="margin-bottom:16px;">
    <input type="hidden" name="form" value="categoria">
    <input type="hidden" name="id" id="cat_id">
    <div class="form-group"><label>Nome</label><input name="nome" id="cat_nome" class="form-control" required></div>
    <div class="form-group">
      <label>Tipo</label>
      <select name="tipo" id="cat_tipo" class="form-control">
        <option value="despesa">Despesa</option>
        <option value="receita">Receita</option>
      </select>
    </div>
    <div class="form-group"><label>Cor</label><input type="color" name="cor" id="cat_cor" class="form-control" value="#0F7B3E"></div>
    <div style="grid-column:1/-1; display:flex; gap:10px;">
      <button class="btn btn-primary" type="submit">Salvar Categoria</button>
      <button class="btn btn-outline" type="button" onclick="limparCat()">Limpar</button>
    </div>
  </form>
  <?php endif; ?>

  <table class="table-list">
    <thead><tr><th>Cor</th><th>Nome</th><th>Tipo</th><th class="text-center">Ações</th></tr></thead>
    <tbody>
      <?php if (empty($catsAll)): ?>
        <tr><td colspan="4" class="muted text-center" style="padding:20px;">Nenhuma categoria cadastrada.</td></tr>
      <?php else: foreach ($catsAll as $c): ?>
        <tr>
          <td><span style="display:inline-block; width:18px; height:18px; border-radius:50%; background:<?= e($c['cor']) ?>;"></span></td>
          <td style="font-weight:600;"><?= e($c['nome']) ?></td>
          <td><span class="badge <?= $c['tipo']==='receita'?'badge-success':'badge-danger' ?>"><?= strtoupper(e($c['tipo'])) ?></span></td>
          <td class="text-center">
            <?php if ($podeEditar): ?>
            <button class="btn btn-outline" style="padding:4px 10px; font-size:11px;" onclick='editarCat(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'>Editar</button>
            <a class="btn btn-danger" style="padding:4px 10px; font-size:11px;" href="contas_pagar.php?aba=categorias&del_cat=<?= (int)$c['id'] ?>" onclick="return confirm('Excluir esta categoria?')">×</a>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>
function editarCat(c) {
  document.getElementById('cat_id').value = c.id;
  document.getElementById('cat_nome').value = c.nome;
  document.getElementById('cat_tipo').value = c.tipo;
  document.getElementById('cat_cor').value = c.cor;
  window.scrollTo({top:0, behavior:'smooth'});
}
function limparCat() {
  document.getElementById('cat_id').value = '';
  document.querySelector('#categorias form').reset();
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
