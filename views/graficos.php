<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
$paginaTitulo    = 'Gráficos';
$paginaSubtitulo = 'Visão gráfica consolidada do desempenho financeiro';
$paginaAtiva     = 'graficos';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
$pdo = Database::conectar();

// Últimos 12 meses
$labels = []; $recArr = []; $desArr = [];
for ($i = 11; $i >= 0; $i--) {
    $d = (new DateTimeImmutable("first day of -$i month"));
    $ini = $d->format('Y-m-01');
    $fim = $d->format('Y-m-t');
    $labels[] = mesPt((int)$d->format('m')) . '/' . $d->format('y');
    $recArr[] = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status='recebido' AND data_recebimento BETWEEN '$ini' AND '$fim'")->fetchColumn();
    $desArr[] = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status='pago'     AND data_pagamento   BETWEEN '$ini' AND '$fim'")->fetchColumn();
}
?>

<div class="row row-2">
  <div class="card">
    <div class="card-header"><span class="card-title">EVOLUÇÃO ANUAL (12 MESES)</span></div>
    <div class="chart-legend">
      <span><span class="dot dot-verde"></span> Receitas</span>
      <span><span class="dot dot-vermelho"></span> Despesas</span>
    </div>
    <div class="chart-wrap"><canvas id="chartAnual"></canvas></div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">SALDO MENSAL (12 MESES)</span></div>
    <div class="chart-wrap"><canvas id="chartSaldo"></canvas></div>
  </div>
</div>

<div class="row row-2">
  <div class="card">
    <div class="card-header"><span class="card-title">DESPESAS POR CATEGORIA (12 MESES)</span></div>
    <div class="chart-wrap"><canvas id="chartCatDesp"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">RECEITAS POR CATEGORIA (12 MESES)</span></div>
    <div class="chart-wrap"><canvas id="chartCatRec"></canvas></div>
  </div>
</div>

<script>
const labels = <?= json_encode($labels) ?>;
const rec    = <?= json_encode($recArr) ?>;
const des    = <?= json_encode($desArr) ?>;
const saldo  = rec.map((v,i)=>v - des[i]);

new Chart(document.getElementById('chartAnual'), {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [
      {label:'Receitas', data: rec, backgroundColor: '#1D9B5C', borderRadius: 4},
      {label:'Despesas', data: des, backgroundColor: '#DC2626', borderRadius: 4}
    ]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins: { tooltip: { callbacks: { label: c => c.dataset.label+': '+App.brl(c.parsed.y) } } },
    scales: { y: { ticks: { callback: v => 'R$ '+(v/1000).toFixed(0)+'k' } } }
  }
});

new Chart(document.getElementById('chartSaldo'), {
  type: 'line',
  data: { labels, datasets: [
    {label:'Saldo', data: saldo, borderColor:'#0F7B3E', backgroundColor:'rgba(15,123,62,.12)', borderWidth:2.5, tension:.35, fill:true, pointRadius:3}
  ]},
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins: { tooltip: { callbacks: { label: c => 'Saldo: '+App.brl(c.parsed.y) } } },
    scales: { y: { ticks: { callback: v => 'R$ '+(v/1000).toFixed(0)+'k' } } }
  }
});

(async () => {
  const dadosCatDesp = await fetch(App.BASE_URL+"/api/relatorios.php?acao=balancete&mes=<?= date('Y-m') ?>", {credentials:'same-origin'}).then(r=>r.json());
  if (dadosCatDesp && dadosCatDesp.despesas) {
    new Chart(document.getElementById('chartCatDesp'), {
      type:'doughnut',
      data:{
        labels: dadosCatDesp.despesas.map(d=>d.categoria),
        datasets:[{
          data: dadosCatDesp.despesas.map(d=>Number(d.total)),
          backgroundColor:['#0F7B3E','#D97757','#E8B86D','#5BA8C2','#9B7BB8','#B8B8B8','#1D9B5C','#67C39A']
        }]
      },
      options:{responsive:true, maintainAspectRatio:false, cutout:'62%', plugins:{legend:{display:true, position:'right', labels:{boxWidth:12}}}}
    });
  }
  if (dadosCatDesp && dadosCatDesp.receitas) {
    new Chart(document.getElementById('chartCatRec'), {
      type:'doughnut',
      data:{
        labels: dadosCatDesp.receitas.map(d=>d.categoria),
        datasets:[{
          data: dadosCatDesp.receitas.map(d=>Number(d.total)),
          backgroundColor:['#0F7B3E','#1D9B5C','#67C39A','#A8DDC1','#2563EB','#5BA8C2']
        }]
      },
      options:{responsive:true, maintainAspectRatio:false, cutout:'62%', plugins:{legend:{display:true, position:'right', labels:{boxWidth:12}}}}
    });
  }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
