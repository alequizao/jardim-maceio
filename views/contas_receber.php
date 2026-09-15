<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
$paginaTitulo    = 'Contas a Receber';
$paginaSubtitulo = 'Taxas condominiais, aluguéis e demais receitas';
$paginaAtiva     = 'receber';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
$pdo = Database::conectar();

$status = $_GET['status'] ?? '';
$mes    = $_GET['mes'] ?? date('Y-m');
$inicio = $mes . '-01';
$fim    = date('Y-m-t', strtotime($inicio));

$totMes      = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE data_competencia BETWEEN '$inicio' AND '$fim'")->fetchColumn();
$totRecebido = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='recebido' AND data_recebimento BETWEEN '$inicio' AND '$fim'")->fetchColumn();
$totPend     = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='pendente' AND data_competencia BETWEEN '$inicio' AND '$fim' AND data_competencia >= CURDATE()")->fetchColumn();
$totInad     = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='pendente' AND data_competencia < CURDATE()")->fetchColumn();

$sql = "SELECT r.id, r.descricao, r.valor, r.data_competencia, r.data_recebimento, r.status,
               c.nome AS categoria,
               CONCAT(IFNULL(m.bloco,'-'),' / ', IFNULL(m.apartamento,'-')) AS unidade,
               m.nome AS morador
        FROM receitas r
        LEFT JOIN categorias c ON c.id = r.categoria_id
        LEFT JOIN moradores  m ON m.id = r.morador_id
        WHERE 1=1";
$par = [];
if ($status !== '') {
    if ($status === 'inadimplente') {
        $sql .= " AND r.status='pendente' AND r.data_competencia < CURDATE()";
    } else {
        $sql .= " AND r.status=:st"; $par[':st']=$status;
    }
}
if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $sql .= " AND DATE_FORMAT(r.data_competencia,'%Y-%m')=:m"; $par[':m']=$mes;
}
$sql .= " ORDER BY r.data_competencia DESC, r.id DESC LIMIT 200";
$stmt = $pdo->prepare($sql); $stmt->execute($par);
$receitas = $stmt->fetchAll();

$categorias = $pdo->query("SELECT id,nome FROM categorias WHERE tipo='receita' AND ativo=1 ORDER BY nome")->fetchAll();
$moradores  = $pdo->query("SELECT id, CONCAT(bloco,'/',apartamento,' - ',nome) AS rotulo FROM moradores WHERE ativo=1 ORDER BY bloco, apartamento")->fetchAll();
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-icon azul"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg></div>
    <div class="kpi-label">TOTAL PREVISTO</div>
    <div class="kpi-value azul"><?= brl($totMes) ?></div>
    <div class="kpi-sub"><?= mesPt((int)date('m', strtotime($inicio))) ?>/<?= date('Y', strtotime($inicio)) ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon verde"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
    <div class="kpi-label">RECEBIDO</div>
    <div class="kpi-value verde"><?= brl($totRecebido) ?></div>
    <div class="kpi-sub">No mês corrente</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon amarelo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
    <div class="kpi-label">EM ABERTO</div>
    <div class="kpi-value amarelo"><?= brl($totPend) ?></div>
    <div class="kpi-sub">Aguardando recebimento</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon vermelho"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
    <div class="kpi-label">INADIMPLENTES</div>
    <div class="kpi-value vermelho"><?= brl($totInad) ?></div>
    <div class="kpi-sub">Em atraso</div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
    <span class="card-title">RECEITAS</span>
    <div style="display:flex; gap:8px; align-items:center;">
      <form method="get" style="display:flex; gap:8px;">
        <select name="status" class="card-select">
          <option value="">Todos os status</option>
          <option value="pendente"     <?= $status==='pendente'    ?'selected':'' ?>>Pendente</option>
          <option value="inadimplente" <?= $status==='inadimplente'?'selected':'' ?>>Inadimplentes</option>
          <option value="recebido"     <?= $status==='recebido'    ?'selected':'' ?>>Recebido</option>
          <option value="cancelado"    <?= $status==='cancelado'   ?'selected':'' ?>>Cancelado</option>
        </select>
        <input type="month" name="mes" value="<?= e($mes) ?>" class="form-control" style="padding:6px 12px;">
        <button class="btn btn-outline">Filtrar</button>
      </form>
      <?php if ($podeEditar): ?><button class="btn btn-primary" onclick="abrirModal()">+ Nova Receita</button><?php endif; ?>
    </div>
  </div>

  <table class="table-list">
    <thead>
      <tr>
        <th>Descrição</th>
        <th>Categoria</th>
        <th>Morador / Unidade</th>
        <th>Competência</th>
        <th>Recebimento</th>
        <th class="text-right">Valor</th>
        <th class="text-center">Status</th>
        <th class="text-center">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($receitas)): ?>
        <tr><td colspan="8" class="muted text-center" style="padding:30px;">Nenhuma receita cadastrada.</td></tr>
      <?php else: foreach ($receitas as $r):
          $inad = ($r['status']==='pendente' && strtotime($r['data_competencia']) < strtotime(date('Y-m-d')));
      ?>
        <tr>
          <td style="font-weight:600;"><?= e($r['descricao']) ?></td>
          <td class="muted"><?= e($r['categoria'] ?? '-') ?></td>
          <td class="muted"><?= e($r['morador'] ?? '-') ?> <small style="color:#9CA3AF;">(<?= e($r['unidade']) ?>)</small></td>
          <td class="muted"><?= dataBr($r['data_competencia']) ?></td>
          <td class="muted"><?= dataBr($r['data_recebimento']) ?></td>
          <td class="text-right" style="font-weight:600;"><?= brl((float)$r['valor']) ?></td>
          <td class="text-center">
            <?php if ($inad): ?>
              <span class="badge badge-danger">INADIMPLENTE</span>
            <?php else: ?>
              <span class="badge <?= statusBadge($r['status']) ?>"><?= statusTexto($r['status']) ?></span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <?php if ($podeEditar): ?>
            <button class="btn btn-outline" style="padding:4px 10px; font-size:11px;" onclick='editar(<?= (int)$r["id"] ?>)'>Editar</button>
            <button class="btn btn-danger"  style="padding:4px 10px; font-size:11px;" onclick='excluir(<?= (int)$r["id"] ?>)'>×</button>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal -->
<div id="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:999; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:14px; padding:28px; width:90%; max-width:560px; box-shadow:0 20px 40px rgba(0,0,0,.2);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
      <h2 style="font-size:18px;" id="modalTitle">Nova Receita</h2>
      <button onclick="fecharModal()" style="font-size:22px; color:#999;">×</button>
    </div>
    <form id="formReceita">
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
          <label>Morador / Unidade</label>
          <select class="form-control" name="morador_id" id="f_morador">
            <option value="">—</option>
            <?php foreach ($moradores as $m): ?>
              <option value="<?= $m['id'] ?>"><?= e($m['rotulo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Valor (R$) *</label>
          <input type="number" step="0.01" min="0" class="form-control" name="valor" id="f_valor" required>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select class="form-control" name="status" id="f_status">
            <option value="pendente">Pendente</option>
            <option value="recebido">Recebido</option>
            <option value="cancelado">Cancelado</option>
          </select>
        </div>
        <div class="form-group">
          <label>Competência</label>
          <input type="date" class="form-control" name="data_competencia" id="f_competencia">
        </div>
        <div class="form-group">
          <label>Data Recebimento</label>
          <input type="date" class="form-control" name="data_recebimento" id="f_recebimento">
        </div>
        <div class="form-group" style="grid-column: 1 / -1;">
          <label>Forma de Pagamento</label>
          <input class="form-control" name="forma_pagamento" id="f_forma">
        </div>
        <div class="form-group" style="grid-column: 1 / -1;">
          <label>Observações</label>
          <textarea class="form-control" name="observacoes" id="f_obs" rows="2"></textarea>
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
function abrirModal(){ modal.style.display='flex'; document.getElementById('modalTitle').innerText='Nova Receita'; document.getElementById('formReceita').reset(); document.getElementById('f_id').value=''; document.getElementById('f_competencia').value=new Date().toISOString().substr(0,10); }
function fecharModal(){ modal.style.display='none'; }
async function editar(id){
  const r = await App.api('receitas.php?id='+id);
  document.getElementById('modalTitle').innerText='Editar Receita';
  document.getElementById('f_id').value = r.id;
  document.getElementById('f_descricao').value = r.descricao || '';
  document.getElementById('f_categoria').value = r.categoria_id || '';
  document.getElementById('f_morador').value = r.morador_id || '';
  document.getElementById('f_valor').value = r.valor;
  document.getElementById('f_status').value = r.status;
  document.getElementById('f_competencia').value = r.data_competencia || '';
  document.getElementById('f_recebimento').value = r.data_recebimento || '';
  document.getElementById('f_forma').value = r.forma_pagamento || '';
  document.getElementById('f_obs').value = r.observacoes || '';
  modal.style.display='flex';
}
async function excluir(id){
  if (!confirm('Excluir esta receita?')) return;
  await App.api('receitas.php?id='+id, {method:'DELETE'});
  location.reload();
}
document.getElementById('formReceita').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  const data = Object.fromEntries(fd.entries());
  const id = data.id;
  const method = id ? 'PUT' : 'POST';
  const url = 'receitas.php' + (id ? '?id='+id : '');
  const r = await App.api(url, {method, body: data});
  if (r && r.ok) location.reload();
  else alert('Erro ao salvar: ' + (r?.erro || ''));
});
<?= isset($_GET['novo']) ? "abrirModal();" : "" ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
