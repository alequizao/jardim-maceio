<?php
/**
 * assembleia_relatorio.php
 * Relatório de votação pronto para impressão / "Salvar como PDF".
 * ?id=<assembleia>&modo=nominal|anonimo
 *   nominal  = mostra quem votou e em quê (admin/síndico)
 *   anonimo  = apenas a apuração, sem identificar votantes
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
Auth::exigirLogin();
if (!Auth::podeEditar()) { header('Location: ' . BASE_URL . '/views/assembleias.php'); exit; }
$pdo = Database::conectar();

$id   = (int)($_GET['id'] ?? 0);
$modo = ($_GET['modo'] ?? 'anonimo') === 'nominal' ? 'nominal' : 'anonimo';

$st = $pdo->prepare("SELECT * FROM assembleias WHERE id=:id");
$st->execute([':id'=>$id]);
$a = $st->fetch();
if (!$a) { header('Location: ' . BASE_URL . '/views/assembleias.php'); exit; }

$pautas = $pdo->query("SELECT * FROM assembleia_pautas WHERE assembleia_id=$id ORDER BY ordem, id")->fetchAll();

// opções + contagem de votos por pauta
$opcoes = [];
foreach ($pdo->query(
    "SELECT o.pauta_id, o.id, o.texto,
            (SELECT COUNT(*) FROM assembleia_opcao_votos v WHERE v.opcao_id=o.id) AS votos
     FROM assembleia_opcoes o JOIN assembleia_pautas p ON p.id=o.pauta_id
     WHERE p.assembleia_id=$id ORDER BY o.pauta_id, o.ordem, o.id") as $r) {
    $opcoes[(int)$r['pauta_id']][] = ['texto'=>$r['texto'], 'votos'=>(int)$r['votos']];
}
// votantes distintos por pauta
$votantes = [];
foreach ($pdo->query("SELECT pauta_id, COUNT(DISTINCT usuario_id) c FROM assembleia_opcao_votos
                      WHERE pauta_id IN (SELECT id FROM assembleia_pautas WHERE assembleia_id=$id)
                      GROUP BY pauta_id") as $r) {
    $votantes[(int)$r['pauta_id']] = (int)$r['c'];
}

// detalhe nominal: quem votou e em quê
$nominal = [];
if ($modo === 'nominal') {
    foreach ($pdo->query(
        "SELECT v.pauta_id, us.nome, us.email, m.bloco, m.apartamento, o.texto AS opcao
         FROM assembleia_opcao_votos v
         JOIN assembleia_opcoes o ON o.id = v.opcao_id
         JOIN usuarios us         ON us.id = v.usuario_id
         LEFT JOIN moradores m    ON m.usuario_id = us.id
         WHERE v.pauta_id IN (SELECT id FROM assembleia_pautas WHERE assembleia_id=$id)
         ORDER BY v.pauta_id, us.nome, o.ordem") as $r) {
        $pid = (int)$r['pauta_id'];
        $uni = $r['bloco'] ? ($r['bloco'].'/'.$r['apartamento']) : '—';
        $key = $r['nome'].'|'.$uni;
        if (!isset($nominal[$pid][$key])) $nominal[$pid][$key] = ['nome'=>$r['nome'], 'unidade'=>$uni, 'email'=>$r['email'], 'opcoes'=>[]];
        $nominal[$pid][$key]['opcoes'][] = $r['opcao'];
    }
}

function statusPautaTxt(string $s): string {
    return ['aprovada'=>'APROVADA','reprovada'=>'REPROVADA','pendente'=>'PENDENTE'][$s] ?? strtoupper($s);
}
$marcaTitulo = cfg('marca_titulo', 'Transparência');
$marcaSub    = cfg('marca_subtitulo', 'Jardim Maceió');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Relatório de Votação · <?= e($a['titulo']) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #1f2937; margin: 0; padding: 0; background: #f3f4f6; }
  .page { max-width: 820px; margin: 16px auto; background: #fff; padding: 32px 40px; }
  .toolbar { max-width: 820px; margin: 16px auto 0; display: flex; gap: 10px; justify-content: flex-end; }
  .btn { font: inherit; padding: 8px 16px; border-radius: 8px; border: 1px solid #0F7B3E; background: #0F7B3E; color: #fff; cursor: pointer; text-decoration: none; }
  .btn.outline { background: #fff; color: #0F7B3E; }
  .head { border-bottom: 3px solid #0F7B3E; padding-bottom: 14px; margin-bottom: 20px; }
  .head .brand { font-size: 12px; letter-spacing: .5px; color: #0F7B3E; font-weight: 700; text-transform: uppercase; }
  .head h1 { font-size: 22px; margin: 6px 0 2px; }
  .head .meta { font-size: 13px; color: #6b7280; }
  .tag { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px; background: #e5e7eb; color: #374151; }
  .tag.green { background: #dcfce7; color: #065f46; }
  .tag.red { background: #fee2e2; color: #991b1b; }
  .pauta { margin: 22px 0; page-break-inside: avoid; }
  .pauta h2 { font-size: 16px; margin: 0 0 4px; }
  .pauta .sub { font-size: 12px; color: #6b7280; margin: 0 0 10px; }
  .bar-row { display: flex; align-items: center; gap: 10px; font-size: 13px; margin: 4px 0; }
  .bar-row .lbl { width: 200px; }
  .bar { flex: 1; background: #f1f3f5; border-radius: 6px; height: 12px; overflow: hidden; }
  .bar > span { display: block; height: 100%; background: #0F7B3E; }
  .bar-row .num { width: 80px; text-align: right; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
  th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
  th { background: #f9fafb; }
  .foot { margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 10px; font-size: 11px; color: #9ca3af; display: flex; justify-content: space-between; }
  .aviso { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 12px; padding: 8px 12px; border-radius: 8px; margin-bottom: 16px; }
  @media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .page { margin: 0; max-width: none; padding: 0 6mm; }
  }
</style>
</head>
<body>

<div class="toolbar">
  <a class="btn outline" href="<?= BASE_URL ?>/views/assembleia_ver.php?id=<?= $id ?>">← Voltar</a>
  <button class="btn" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
</div>

<div class="page">
  <div class="head">
    <div class="brand"><?= e($marcaTitulo) ?> · <?= e($marcaSub) ?></div>
    <h1>Relatório de Votação</h1>
    <div class="meta">
      <strong><?= e($a['titulo']) ?></strong> ·
      Assembleia <?= $a['tipo']==='ordinaria'?'Ordinária':'Extraordinária' ?> ·
      <?= dataBr($a['data_assembleia']) ?><?= $a['horario'] ? ' às '.substr($a['horario'],0,5) : '' ?>
      <?php if ($a['votacao_inicio']): ?><br>Votação: <?= dataHoraBr($a['votacao_inicio']) ?> — <?= $a['votacao_fim'] ? dataHoraBr($a['votacao_fim']) : 'em aberto' ?><?php endif; ?>
    </div>
  </div>

  <?php if ($modo === 'nominal'): ?>
    <div class="aviso">⚠️ Documento <strong>nominal</strong> — contém a identificação de cada votante. Trate com confidencialidade.</div>
  <?php endif; ?>

  <?php if (empty($pautas)): ?>
    <p>Esta assembleia não possui pautas.</p>
  <?php else: foreach ($pautas as $i => $p):
      $pid = (int)$p['id'];
      $ops = $opcoes[$pid] ?? [];
      $totVotos = array_sum(array_column($ops, 'votos'));
      $nVot = $votantes[$pid] ?? 0;
      $base = max(1, $p['multipla'] ? $totVotos : $nVot);
  ?>
    <div class="pauta">
      <h2><?= ($i+1) ?>. <?= e($p['titulo']) ?>
        <span class="tag <?= $p['status']==='aprovada'?'green':($p['status']==='reprovada'?'red':'') ?>"><?= statusPautaTxt($p['status']) ?></span>
      </h2>
      <p class="sub">
        <?= $p['multipla'] ? 'Escolha múltipla' : 'Escolha única' ?> ·
        <?= $nVot ?> votante<?= $nVot==1?'':'s' ?><?= $p['multipla'] ? ' · '.$totVotos.' voto'.($totVotos==1?'':'s') : '' ?>
      </p>
      <?php if ($p['descricao']): ?><p class="sub" style="color:#374151;"><?= e($p['descricao']) ?></p><?php endif; ?>

      <?php foreach ($ops as $o): $pct = round($o['votos']/$base*100); ?>
        <div class="bar-row">
          <span class="lbl"><?= e($o['texto']) ?></span>
          <span class="bar"><span style="width:<?= $pct ?>%;"></span></span>
          <span class="num"><?= $o['votos'] ?> (<?= $pct ?>%)</span>
        </div>
      <?php endforeach; ?>

      <?php if ($modo === 'nominal'): $votos = $nominal[$pid] ?? []; ?>
        <table>
          <thead><tr><th style="width:45%;">Votante</th><th style="width:20%;">Unidade</th><th>Voto</th></tr></thead>
          <tbody>
            <?php if (empty($votos)): ?>
              <tr><td colspan="3" style="text-align:center; color:#9ca3af;">Nenhum voto registrado.</td></tr>
            <?php else: foreach ($votos as $v): ?>
              <tr>
                <td><?= e($v['nome']) ?></td>
                <td><?= e($v['unidade']) ?></td>
                <td><?= e(implode(', ', $v['opcoes'])) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>

  <div class="foot">
    <span><?= e(SISTEMA_NOME) ?></span>
    <span>Gerado em <?= dataHoraBr(date('Y-m-d H:i:s')) ?> · <?= $modo==='nominal'?'Relatório nominal':'Relatório anônimo' ?></span>
  </div>
</div>

<script>
  // Abre o diálogo de impressão automaticamente (para salvar como PDF)
  window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 400); });
</script>
</body>
</html>
