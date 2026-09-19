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

/* ============================================
   Visibilidade dos módulos em tempo real (AJAX)
   O admin libera/bloqueia em Configurações e o menu
   de todos os condôminos se ajusta sozinho.
   ============================================ */
(function () {
  if (!document.querySelector('.sidebar')) return;
  let versao = null;
  const paginaMod = document.body.dataset.mod || '';

  function aplicar(d) {
    document.querySelectorAll('.nav-item[data-mod]').forEach(function (a) {
      a.hidden = !d.liberados[a.dataset.mod];
    });
    if (paginaMod && d.liberados[paginaMod] === false) {
      App.toast && App.toast('Este módulo foi bloqueado pela administração.');
      setTimeout(function () { location.reload(); }, 900);
    }
    document.dispatchEvent(new CustomEvent('jm:visibilidade', { detail: d }));
  }

  async function checar() {
    if (document.hidden || !navigator.onLine) return;
    const d = await App.api('visibilidade.php');
    if (!d || !d.ok) return;
    if (versao !== null && d.versao !== versao) {
      const liberou = Object.keys(d.liberados).filter(function (m) {
        const a = document.querySelector('.nav-item[data-mod="' + m + '"]');
        return a && a.hidden && d.liberados[m];
      });
      if (liberou.length && App.toast) App.toast('Novo módulo liberado no menu!');
    }
    versao = d.versao;
    aplicar(d);
    atualizarSistema(d.app);
  }
  /* Atualização forçada: nova versão publicada => todas as abas recarregam */
  let appVer = null, pendente = false;
  function editando() {
    const a = document.activeElement;
    if (a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) && a.type !== 'checkbox') return true;
    return !!document.querySelector('.modal.open, .modal.show, .modal-overlay:not([hidden]).open, dialog[open]');
  }
  async function recarregar() {
    try {
      const reg = navigator.serviceWorker && await navigator.serviceWorker.getRegistration();
      if (reg) await reg.update();
      if (window.caches) { const ks = await caches.keys(); await Promise.all(ks.map(function (k) { return caches.delete(k); })); }
    } catch (e) {}
    location.reload();
  }
  function atualizarSistema(v) {
    if (!v) return;
    if (appVer === null) { appVer = v; return; }
    if (v === appVer && !pendente) return;
    pendente = true;
    if (editando()) { App.toast && App.toast('Nova versão disponível — atualiza ao terminar a edição.'); return; }
    App.toast && App.toast('Atualizando para a nova versão…');
    setTimeout(recarregar, 1200);
  }
  App.checarVisibilidade = checar;
  checar();
  setInterval(checar, 10000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) checar(); });
})();

/* Toast simples */
App.toast = function (msg) {
  let t = document.getElementById('jmToast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'jmToast';
    t.className = 'jm-toast';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.classList.add('on');
  clearTimeout(t._h);
  t._h = setTimeout(function () { t.classList.remove('on'); }, 3000);
};

/* PWA (service worker + instalação): assets/js/pwa.js */
(function () {
  window.addEventListener('offline', function () { App.toast && App.toast('Sem conexão — mostrando o que está salvo.'); });
  window.addEventListener('online',  function () { App.toast && App.toast('Conexão restabelecida.'); });
})();

/* ============================================
   Visual v2: tabelas em cartões no celular + barra de abas
   ============================================ */
(function () {
  // rótulos das colunas em cada célula (o CSS mostra como cartão no celular)
  function rotular() {
    document.querySelectorAll('table.table-list').forEach(function (t) {
      var ths = Array.prototype.map.call(t.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
      if (!ths.length) return;
      t.classList.add('cartoes');
      t.querySelectorAll('tbody tr').forEach(function (tr) {
        Array.prototype.forEach.call(tr.children, function (td, i) {
          if (!td.hasAttribute('data-label')) td.setAttribute('data-label', ths[i] || '');
          if (td.colSpan > 1) td.setAttribute('data-label', '');
        });
      });
    });
  }
  rotular();
  new MutationObserver(function () { clearTimeout(rotular._t); rotular._t = setTimeout(rotular, 60); })
    .observe(document.body, { childList: true, subtree: true });

  // barra de abas: 4 atalhos + Menu, seguindo o que está liberado no menu lateral
  var nav = document.querySelector('.sidebar');
  if (!nav) return;
  var prefer = ['dashboard', 'pagar', 'receber', 'documentos', 'assembleias', 'balancete', 'relatorios', 'fluxo', 'notas', 'graficos'];
  var bar = document.createElement('nav');
  bar.className = 'tabbar';
  bar.setAttribute('aria-label', 'Atalhos');
  document.body.appendChild(bar);
  document.body.classList.add('tem-tabbar');
  var nomesCurtos = { pagar: 'Pagar', receber: 'Receber', dashboard: 'Início', assembleias: 'Assembleia', relatorios: 'Relatórios', notas: 'Notas', fluxo: 'Fluxo', graficos: 'Gráficos', balancete: 'Balancete', documentos: 'Documentos' };
  function montar() {
    var itens = [];
    prefer.forEach(function (m) {
      var a = nav.querySelector('.nav-item[data-mod="' + m + '"]');
      if (a && !a.hidden && itens.length < 4) itens.push(a);
    });
    bar.innerHTML = itens.map(function (a) {
      var svg = a.querySelector('svg'); var m = a.dataset.mod;
      return '<a href="' + a.getAttribute('href') + '" class="' + (a.classList.contains('active') ? 'on' : '') + '">' + (svg ? svg.outerHTML : '') + '<span>' + (nomesCurtos[m] || a.textContent.trim()) + '</span></a>';
    }).join('') + '<button type="button" id="tabMenu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/></svg><span>Menu</span></button>';
    var mb = document.getElementById('tabMenu');
    mb.onclick = function () { var t = document.getElementById('menuToggle'); if (t) t.click(); };
    if (!bar.querySelector('.on')) mb.classList.add('on');
  }
  montar();
  document.addEventListener('jm:visibilidade', montar);
})();

/* Pílula do topo: relógio */
(function () {
  var hora = document.getElementById('mpHora'); if (!hora) return;
  function t() { hora.textContent = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Maceio' }); }
  setInterval(t, 10000); t();
})();

/* ============================================
   Modo offline: fila de envios + diagnóstico da conexão
   - Gravações (POST/PUT/DELETE em JSON) feitas sem conexão ficam guardadas
     no aparelho (localStorage "jm_fila") e são enviadas sozinhas depois.
   - Envio de arquivos e formulários tradicionais exigem internet: avisamos
     antes, sem perder o que foi digitado.
   ============================================ */
(function () {
  var CHAVE = 'jm_fila';
  function ler() { try { return JSON.parse(localStorage.getItem(CHAVE) || '[]'); } catch (e) { return []; } }
  function gravar(f) { try { localStorage.setItem(CHAVE, JSON.stringify(f)); } catch (e) {} avisar(); }
  var estado = { motivo: 'ok', detalhe: '' };      // ok | sem_internet | servidor | sessao | enviando
  function avisar() { document.dispatchEvent(new CustomEvent('jm:conexao', { detail: { motivo: estado.motivo, detalhe: estado.detalhe, pendentes: ler().length } })); }
  function definir(m, d) { if (estado.motivo !== m || estado.detalhe !== (d || '')) { estado.motivo = m; estado.detalhe = d || ''; avisar(); } }
  App.conexao = function () { return { motivo: estado.motivo, detalhe: estado.detalhe, pendentes: ler().length }; };

  var descricoes = { 'moradores.php': 'morador', 'despesas.php': 'despesa', 'receitas.php': 'receita', 'fornecedores.php': 'fornecedor', 'usuarios.php': 'usuário', 'notas-fiscais.php': 'nota fiscal', 'visibilidade.php': 'visibilidade' };
  function descrever(path, metodo) {
    var arq = path.split('?')[0].split('/').pop();
    var acao = metodo === 'DELETE' ? 'Excluir' : metodo === 'PUT' ? 'Alterar' : 'Cadastrar';
    return acao + ' ' + (descricoes[arq] || arq.replace('.php', ''));
  }

  App.api = async function (path, options) {
    options = options || {};
    var url = this.BASE_URL + '/api/' + path.replace(/^\/+/, '');
    var metodo = (options.method || 'GET').toUpperCase();
    var opts = Object.assign({ method: 'GET', credentials: 'same-origin', headers: { 'Accept': 'application/json' } }, options);
    var ehArquivo = opts.body instanceof FormData;
    var corpo = opts.body;
    if (opts.body && typeof opts.body === 'object' && !ehArquivo) {
      opts.headers = Object.assign({}, opts.headers, { 'Content-Type': 'application/json' });
      opts.body = JSON.stringify(opts.body);
    }
    var escrita = metodo !== 'GET';
    var guardar = function (motivo) {
      if (ehArquivo) return { ok: false, erro: 'Sem conexão (' + motivo + '). Envio de arquivos precisa de internet — tente de novo quando a conexão voltar.' };
      var f = ler();
      f.push({ id: (crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2)), path: path, method: metodo, body: corpo === undefined ? null : corpo, quando: Date.now(), desc: descrever(path, metodo) });
      gravar(f);
      try { sessionStorage.setItem('jm_msg', 'Sem conexão (' + motivo + '). "' + descrever(path, metodo) + '" ficou salvo no aparelho e será enviado sozinho.'); } catch (e) {}
      App.toast && App.toast('Salvo no aparelho — será enviado quando a conexão voltar.');
      return { ok: true, offline: true, pendente: true };
    };
    if (escrita && !navigator.onLine) { definir('sem_internet'); return guardar('sem internet'); }
    var r;
    try { r = await fetch(url, opts); }
    catch (e) {
      definir(navigator.onLine ? 'servidor' : 'sem_internet');
      if (escrita) return guardar(navigator.onLine ? 'servidor fora do ar' : 'sem internet');
      return { ok: false, rede: true, erro: navigator.onLine ? 'Servidor fora do ar no momento.' : 'Sem conexão com a internet.' };
    }
    if (r.headers.get('X-JM-Reserva')) {
      // resposta veio da cópia guardada no aparelho: a rede falhou
      definir(navigator.onLine ? 'servidor' : 'sem_internet', r.headers.get('X-JM-Salvo-Em') ? 'mostrando dados de ' + new Date(r.headers.get('X-JM-Salvo-Em')).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '');
    }
    if (r.status === 401) {
      definir('sessao');
      if (escrita) return guardar('sessão expirada');
      if (!ler().length) { window.location.href = this.BASE_URL + '/views/login.php'; }
      return { ok: false, erro: 'Sessão expirada.' };
    }
    if (r.status >= 502 && r.status <= 504 || r.status === 530) {
      definir('servidor', 'HTTP ' + r.status);
      if (escrita) return guardar('servidor fora do ar');
    } else if (!r.headers.get('X-JM-Reserva') && estado.motivo !== 'enviando') definir('ok');
    var texto = await r.text();
    try { return JSON.parse(texto); }
    catch (e) { return { ok: false, erro: 'Erro no servidor (HTTP ' + r.status + ').' }; }
  };

  // envia a fila, na ordem, quando a conexão volta
  var enviando = false;
  async function enviarFila() {
    var f = ler();
    if (enviando || !f.length || !navigator.onLine) return;
    enviando = true; definir('enviando');
    var ok = 0, falhas = [];
    while (f.length) {
      var it = f[0], r;
      try {
        r = await fetch(App.BASE_URL + '/api/' + it.path, {
          method: it.method, credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Offline-Fila': '1', 'X-JM-Idem': it.id || '' },
          body: it.body === null ? undefined : JSON.stringify(it.body)
        });
      } catch (e) { definir(navigator.onLine ? 'servidor' : 'sem_internet'); break; }
      if (r.status === 401) { definir('sessao'); break; }
      if (r.status >= 500) { definir('servidor', 'HTTP ' + r.status); break; }
      var j = null; try { j = await r.json(); } catch (e) {}
      f.shift(); gravar(f);
      if (r.ok && (!j || j.ok !== false)) ok++; else falhas.push(it.desc + ': ' + ((j && j.erro) || 'HTTP ' + r.status));
    }
    enviando = false;
    if (!f.length) definir('ok');
    if (ok && App.toast) App.toast(ok + (ok > 1 ? ' alterações enviadas' : ' alteração enviada') + ' ao servidor.');
    if (falhas.length) alert('Algumas alterações feitas offline foram recusadas pelo servidor:\n\n' + falhas.join('\n'));
    if (ok && !falhas.length && !document.querySelector('input:focus,textarea:focus')) setTimeout(function () { location.reload(); }, 1500);
  }
  App.enviarFila = enviarFila;
  window.addEventListener('online', function () { definir('ok'); enviarFila(); App.checarVisibilidade && App.checarVisibilidade(); });
  window.addEventListener('offline', function () { definir('sem_internet'); });
  document.addEventListener('jm:visibilidade', function () { if (estado.motivo !== 'enviando') definir('ok'); enviarFila(); });
  setInterval(enviarFila, 15000);

  // formulários tradicionais (upload, assembleia, marca…) precisam de internet
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (navigator.onLine || ev.defaultPrevented) return;
    if ((f.method || '').toLowerCase() !== 'post') return;
    ev.preventDefault();
    alert('Você está sem internet.\n\nEste formulário' + (f.querySelector('input[type=file]') ? ' envia arquivo e' : '') + ' precisa de conexão para ser salvo. O que você digitou continua na tela — tente de novo quando a internet voltar.');
  }, true);

  // mensagem guardada antes de recarregar
  try { var m = sessionStorage.getItem('jm_msg'); if (m) { sessionStorage.removeItem('jm_msg'); setTimeout(function () { App.toast && App.toast(m); }, 400); } } catch (e) {}
  if (!navigator.onLine) definir('sem_internet');
  setTimeout(avisar, 0);

  // ao sair, apaga os dados guardados no aparelho (menos a fila, que avisa)
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest && ev.target.closest('a.user-logout');
    if (!a) return;
    if (ler().length && !confirm('Há ' + ler().length + ' alteração(ões) feitas offline ainda não enviadas. Se sair agora, elas serão perdidas. Sair mesmo assim?')) { ev.preventDefault(); return; }
    try { localStorage.removeItem(CHAVE); } catch (e) {}
    if (navigator.serviceWorker && navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage('limparDados');
  });
})();

/* Pílula do topo: mostra o motivo real */
(function () {
  var st = document.getElementById('mpStatus'), txt = document.getElementById('mpTxt'), pill = document.getElementById('metaPill');
  if (!st) return;
  var TXT = {
    ok: ['ok', 'Ao vivo', 'Conectado · dados atualizam sozinhos a cada 10 s'],
    sem_internet: ['off', 'Sem internet', 'Este aparelho está sem internet. Você pode consultar o que já foi aberto; alterações ficam guardadas e serão enviadas quando voltar.'],
    servidor: ['off', 'Servidor fora', 'A internet funciona, mas o servidor não respondeu. Alterações ficam guardadas e serão enviadas automaticamente.'],
    sessao: ['lento', 'Entre de novo', 'Sua sessão expirou. Entre novamente para continuar e enviar o que ficou pendente.'],
    enviando: ['lento', 'Enviando…', 'Enviando as alterações feitas offline.']
  };
  document.addEventListener('jm:conexao', function (e) {
    var d = e.detail, t = TXT[d.motivo] || TXT.ok;
    st.className = 'mp-status ' + t[0];
    var curto = window.innerWidth <= 640;
    var CURTO = { ok: 'Ao vivo', sem_internet: 'Offline', servidor: 'Offline', sessao: 'Entrar', enviando: 'Enviando' };
    txt.textContent = (curto ? CURTO[d.motivo] || t[1] : t[1]) + (d.pendentes ? (curto ? ' · ' + d.pendentes : ' · ' + d.pendentes + ' pendente' + (d.pendentes > 1 ? 's' : '')) : '');
    pill.title = t[2] + (d.detalhe ? ' (' + d.detalhe + ')' : '') + (d.pendentes ? '\n' + d.pendentes + ' alteração(ões) aguardando envio.' : '');
    pill.dataset.motivo = d.motivo;
    var faixa = document.getElementById('jmFaixaOff');
    if (d.motivo === 'ok' && !d.pendentes) { if (faixa) faixa.remove(); return; }
    if (d.motivo === 'enviando') return;
    if (!faixa) { faixa = document.createElement('div'); faixa.id = 'jmFaixaOff'; faixa.className = 'jm-faixa'; var c = document.querySelector('.content'); c && c.prepend(faixa); }
    faixa.className = 'jm-faixa ' + t[0];
    faixa.innerHTML = '<b>' + t[1] + '</b><span>' + t[2] + (d.pendentes ? ' <u>' + d.pendentes + ' alteração(ões) aguardando envio.</u>' : '') + '</span>'
      + (d.motivo === 'sessao' ? '<a class="btn btn-primary" href="' + App.BASE_URL + '/views/login.php?redirect=' + encodeURIComponent(location.pathname) + '">Entrar</a>' : '');
  });
  pill.onclick = function () {
    var c = App.conexao();
    if (c.pendentes && navigator.onLine) { App.enviarFila(); return; }
    App.checarVisibilidade && App.checarVisibilidade();
    App.toast && App.toast((TXT[c.motivo] || TXT.ok)[2]);
  };
})();

/* ============================================
   Limpar notificações do sino (AJAX, por usuário)
   ============================================ */
(function () {
  var panel = document.getElementById('notifPanel'); if (!panel) return;
  var cont = document.getElementById('notifCont'), tudo = document.getElementById('notifLimparTudo');
  function atualizar(total) {
    if (cont) cont.textContent = total;
    var badge = document.querySelector('.bell-badge');
    if (badge) { badge.textContent = total > 9 ? '9+' : total; badge.hidden = !total; badge.style.display = total ? '' : 'none'; }
    if (tudo) tudo.hidden = !total;
    panel.querySelectorAll('.notif-group').forEach(function (g) {
      var n = g.nextElementSibling, tem = false;
      while (n && !n.classList.contains('notif-group')) { if (n.classList.contains('notif-item')) tem = true; n = n.nextElementSibling; }
      if (!tem) g.remove();
    });
    if (!total) panel.querySelector('.notif-body').innerHTML = '<div class="notif-empty">Tudo limpo por aqui. ✨</div>';
    else if (!panel.querySelector('.notif-item')) panel.querySelector('.notif-body').innerHTML = '<div class="notif-empty">Há mais ' + total + ' notificação(ões). <a href="#" id="notifMais">Mostrar</a></div>';
  }
  async function enviar(corpo) {
    var r = await App.api('notificacoes.php', { method: 'POST', body: corpo });
    if (r && r.ok && !r.offline) atualizar(r.total);
    return r;
  }
  panel.addEventListener('click', function (ev) {
    var x = ev.target.closest('.notif-x');
    if (x) {
      ev.preventDefault(); ev.stopPropagation();
      var it = x.closest('.notif-item');
      it.classList.add('saindo');
      setTimeout(function () { it.remove(); }, 220);
      enviar({ acao: 'limpar', chaves: [it.dataset.chave] });
      return;
    }
    if (ev.target.id === 'notifMais') { ev.preventDefault(); location.reload(); }
  });
  if (tudo) tudo.addEventListener('click', async function (ev) {
    ev.stopPropagation();
    panel.querySelectorAll('.notif-item').forEach(function (it) { it.classList.add('saindo'); });
    var r = await enviar({ acao: 'limpar_tudo' });
    if (r && r.ok) {
      App.toast && App.toast('Notificações limpas.');
      setTimeout(function () { panel.querySelectorAll('.notif-item').forEach(function (it) { it.remove(); }); atualizar(r.offline ? 0 : r.total); }, 230);
    }
  });
})();
