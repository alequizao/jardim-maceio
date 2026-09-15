<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
$paginaTitulo    = 'Notas Fiscais';
$paginaSubtitulo = 'Upload e histórico de notas fiscais de fornecedores';
$paginaAtiva     = 'notas';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
$pdo = Database::conectar();

$notas = $pdo->query(
    "SELECT n.id, n.numero, n.valor, n.data_emissao, n.arquivo, n.tipo_arquivo, n.observacoes,
            f.nome AS fornecedor, d.descricao AS despesa
     FROM notas_fiscais n
     LEFT JOIN fornecedores f ON f.id=n.fornecedor_id
     LEFT JOIN despesas d    ON d.id=n.despesa_id
     ORDER BY n.data_emissao DESC, n.id DESC LIMIT 200"
)->fetchAll();

$fornecedores = $pdo->query("SELECT id,nome FROM fornecedores WHERE ativo=1 ORDER BY nome")->fetchAll();
$despesas     = $pdo->query("SELECT id, CONCAT(descricao,' - ',DATE_FORMAT(data_vencimento,'%d/%m/%Y')) AS rotulo FROM despesas ORDER BY data_vencimento DESC LIMIT 100")->fetchAll();

$totMes  = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM notas_fiscais WHERE DATE_FORMAT(data_emissao,'%Y-%m')='".date('Y-m')."'")->fetchColumn();
$qtdMes  = (int)$pdo->query("SELECT COUNT(*) FROM notas_fiscais WHERE DATE_FORMAT(data_emissao,'%Y-%m')='".date('Y-m')."'")->fetchColumn();
$qtdTot  = (int)$pdo->query("SELECT COUNT(*) FROM notas_fiscais")->fetchColumn();
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-icon azul"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
    <div class="kpi-label">NF DO MÊS</div>
    <div class="kpi-value azul"><?= $qtdMes ?></div>
    <div class="kpi-sub">notas emitidas em <?= mesPt((int)date('m')) ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon verde"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20"/></svg></div>
    <div class="kpi-label">VALOR DO MÊS</div>
    <div class="kpi-value verde"><?= brl($totMes) ?></div>
    <div class="kpi-sub">soma das NF do mês</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon amarelo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg></div>
    <div class="kpi-label">TOTAL ARMAZENADO</div>
    <div class="kpi-value amarelo"><?= $qtdTot ?></div>
    <div class="kpi-sub">histórico completo</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon vermelho"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg></div>
    <div class="kpi-label">SEM ANEXO</div>
    <div class="kpi-value vermelho"><?= (int)$pdo->query("SELECT COUNT(*) FROM notas_fiscais WHERE arquivo IS NULL")->fetchColumn() ?></div>
    <div class="kpi-sub">requer regularização</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">NOTAS FISCAIS</span>
    <?php if ($podeEditar): ?><button class="btn btn-primary" onclick="abrirModal()">+ Anexar NF</button><?php endif; ?>
  </div>

  <table class="table-list">
    <thead>
      <tr>
        <th>Número</th>
        <th>Fornecedor</th>
        <th>Despesa vinculada</th>
        <th>Emissão</th>
        <th class="text-right">Valor</th>
        <th class="text-center">Arquivo</th>
        <th class="text-center">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($notas)): ?>
        <tr><td colspan="7" class="muted text-center" style="padding:30px;">Nenhuma nota fiscal anexada.</td></tr>
      <?php else: foreach ($notas as $n): ?>
        <tr>
          <td style="font-weight:600;"><?= e($n['numero']) ?></td>
          <td class="muted"><?= e($n['fornecedor'] ?? '-') ?></td>
          <td class="muted"><?= e($n['despesa'] ?? '-') ?></td>
          <td class="muted"><?= dataBr($n['data_emissao']) ?></td>
          <td class="text-right" style="font-weight:600;"><?= brl((float)$n['valor']) ?></td>
          <td class="text-center">
            <?php if ($n['arquivo']): ?>
              <a href="<?= UPLOAD_URL . e($n['arquivo']) ?>" target="_blank" class="badge badge-success">Baixar (<?= strtoupper(e($n['tipo_arquivo'])) ?>)</a>
            <?php else: ?>
              <span class="badge badge-muted">Sem arquivo</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <?php if ($podeEditar): ?>
            <button class="btn btn-danger" style="padding:4px 10px; font-size:11px;" onclick='excluir(<?= (int)$n["id"] ?>)'>×</button>
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
      <h2 style="font-size:18px;">Anexar Nota Fiscal</h2>
      <button onclick="fecharModal()" style="font-size:22px; color:#999;">×</button>
    </div>
    <form id="formNF" enctype="multipart/form-data">
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label>Número da NF *</label>
          <input class="form-control" name="numero" required>
        </div>
        <div class="form-group">
          <label>Valor (R$) *</label>
          <input type="number" step="0.01" min="0" class="form-control" name="valor" required>
        </div>
        <div class="form-group">
          <label>Data de Emissão *</label>
          <input type="date" class="form-control" name="data_emissao" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label>Fornecedor</label>
          <select class="form-control" name="fornecedor_id">
            <option value="">—</option>
            <?php foreach ($fornecedores as $f): ?>
              <option value="<?= $f['id'] ?>"><?= e($f['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="grid-column: 1 / -1;">
          <label>Vincular a uma despesa</label>
          <select class="form-control" name="despesa_id">
            <option value="">—</option>
            <?php foreach ($despesas as $d): ?>
              <option value="<?= $d['id'] ?>"><?= e($d['rotulo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="grid-column: 1 / -1;">
          <label>Arquivo (PDF, PNG, JPG ou XML)</label>
          <input type="file" class="form-control" name="arquivo" accept=".pdf,.png,.jpg,.jpeg,.xml">
        </div>
        <div class="form-group" style="grid-column: 1 / -1;">
          <label>Observações</label>
          <textarea class="form-control" name="observacoes" rows="2"></textarea>
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
function abrirModal(){ modal.style.display='flex'; }
function fecharModal(){ modal.style.display='none'; }
async function excluir(id){
  if (!confirm('Excluir esta nota?')) return;
  await App.api('notas-fiscais.php?id='+id, {method:'DELETE'});
  location.reload();
}
document.getElementById('formNF').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  const r = await fetch(App.BASE_URL+'/api/notas-fiscais.php', {method:'POST', body: fd, credentials:'same-origin'}).then(r=>r.json());
  if (r && r.ok) location.reload();
  else alert('Erro: ' + (r?.erro || ''));
});
<?= isset($_GET['novo']) ? "abrirModal();" : "" ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
