/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/* ============================================
   Dashboard — gráficos via Chart.js
   ============================================ */

document.addEventListener('DOMContentLoaded', async () => {
  const dados = await App.api('dashboard.php?acao=resumo');
  if (!dados) return;

  // ============ FLUXO DE CAIXA (line chart) ============
  const ctxFluxo = document.getElementById('chartFluxo');
  if (ctxFluxo) {
    const labels = dados.fluxo.labels;
    new Chart(ctxFluxo, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          { label: 'Receitas', data: dados.fluxo.receitas, borderColor: App.COLORS.verde2, backgroundColor: 'rgba(29,155,92,.10)', borderWidth: 2.5, tension: .35, fill: true, pointRadius: 0, pointHoverRadius: 4 },
          { label: 'Despesas', data: dados.fluxo.despesas, borderColor: App.COLORS.verm, backgroundColor: 'transparent',          borderWidth: 2.5, tension: .35, fill: false, pointRadius: 0, pointHoverRadius: 4 },
          { label: 'Saldo',    data: dados.fluxo.saldo,    borderColor: App.COLORS.preto, backgroundColor: 'transparent',          borderWidth: 2.5, tension: .35, fill: false, pointRadius: 0, pointHoverRadius: 4 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          tooltip: {
            callbacks: { label: (ctx) => ctx.dataset.label + ': ' + App.brl(ctx.parsed.y) }
          }
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: '#9CA3AF' } },
          y: {
            grid: { color: '#F0F1F4' },
            ticks: {
              color: '#9CA3AF',
              callback: v => 'R$ ' + (v/1000).toFixed(0) + 'k'
            }
          }
        }
      }
    });
  }

  // ============ DESPESAS POR CATEGORIA (donut) ============
  const ctxCat = document.getElementById('chartCategorias');
  if (ctxCat && dados.categorias) {
    const cats = dados.categorias;
    new Chart(ctxCat, {
      type: 'doughnut',
      data: {
        labels: cats.map(c => c.nome),
        datasets: [{
          data: cats.map(c => Number(c.valor)),
          backgroundColor: cats.map(c => c.cor),
          borderWidth: 0,
          spacing: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
          tooltip: {
            callbacks: {
              label: (ctx) => ctx.label + ': ' + App.brl(ctx.parsed)
            }
          }
        }
      }
    });
  }
});
