/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/* ============================================
   Transparência Jardim Maceió — JS principal
   ============================================ */

const App = {
  BASE_URL: (function() {
    // Derivada do diretório /assets/js/
    const s = document.currentScript;
    if (s) return s.src.replace(/\/assets\/js\/.*$/, '');
    return '';
  })(),

  brl(v) {
    return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    });
  },

  async api(path, options = {}) {
    const url = this.BASE_URL + '/api/' + path.replace(/^\/+/, '');
    const opts = {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      ...options
    };
    if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }
    const r = await fetch(url, opts);
    if (r.status === 401) { window.location.href = this.BASE_URL + '/views/login.php'; return; }
    const texto = await r.text();
    try {
      return JSON.parse(texto);
    } catch (e) {
      // Resposta não-JSON (erro do servidor): não falha em silêncio
      return { ok: false, erro: 'Erro no servidor (HTTP ' + r.status + ').' };
    }
  },

  // Paleta padrão
  COLORS: {
    verde: '#0F7B3E',
    verde2:'#1D9B5C',
    azul:  '#2563EB',
    verm:  '#DC2626',
    amar:  '#F59E0B',
    cinza: '#9CA3AF',
    preto: '#1F2937',
  }
};

/* ============================================
   Menu lateral responsivo (mobile / tablet)
   ============================================ */
(function () {
  const toggle  = document.getElementById('menuToggle');
  const overlay = document.getElementById('sidebarOverlay');
  if (!toggle) return;

  function abrir() {
    document.body.classList.add('nav-open');
    toggle.setAttribute('aria-expanded', 'true');
    if (overlay) overlay.hidden = false;
  }
  function fechar() {
    document.body.classList.remove('nav-open');
    toggle.setAttribute('aria-expanded', 'false');
    if (overlay) overlay.hidden = true;
  }
  function alternar() {
    document.body.classList.contains('nav-open') ? fechar() : abrir();
  }

  toggle.addEventListener('click', alternar);
  if (overlay) overlay.addEventListener('click', fechar);

  // Fecha ao tocar em um item de navegação
  document.querySelectorAll('.sidebar .nav-item').forEach(function (a) {
    a.addEventListener('click', fechar);
  });

  // Fecha com ESC
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') fechar();
  });

  // Garante estado limpo ao voltar para o desktop
  window.addEventListener('resize', function () {
    if (window.innerWidth > 1024) fechar();
  });
})();

/* ============================================
   Painel de notificações (sino)
   ============================================ */
(function () {
  const bell  = document.getElementById('bellBtn');
  const panel = document.getElementById('notifPanel');
  if (!bell || !panel) return;

  function fechar() {
    panel.hidden = true;
    bell.setAttribute('aria-expanded', 'false');
  }

  bell.addEventListener('click', function (ev) {
    ev.stopPropagation();
    const abrir = panel.hidden;
    panel.hidden = !abrir;
    bell.setAttribute('aria-expanded', abrir ? 'true' : 'false');
  });

  // Fecha ao clicar fora ou pressionar ESC
  document.addEventListener('click', function (ev) {
    if (!panel.hidden && !panel.contains(ev.target)) fechar();
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') fechar();
  });
})();

// Defaults do Chart.js
if (window.Chart) {
  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.font.size = 11.5;
  Chart.defaults.color = '#6B7280';
  Chart.defaults.plugins.legend.display = false;
  Chart.defaults.plugins.tooltip.backgroundColor = '#111827';
  Chart.defaults.plugins.tooltip.padding = 10;
  Chart.defaults.plugins.tooltip.cornerRadius = 8;
  Chart.defaults.plugins.tooltip.titleFont = { weight: '600', size: 12 };
  Chart.defaults.plugins.tooltip.bodyFont = { size: 12 };
}
