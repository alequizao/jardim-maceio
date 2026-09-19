/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/* PWA — Transparência Jardim Maceió: service worker + aviso de instalação */
(function () {
  var s = document.currentScript;
  var BASE = s ? s.src.replace(/\/assets\/js\/.*$/, '') : '';
  var ua = navigator.userAgent;
  var ios = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var safariIOS = ios && !/crios|fxios|edgios/i.test(ua);
  var instalado = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  var pedido = null;


  /* Sem zoom: pinça (iOS ignora user-scalable=no), toque duplo e zoom automático nos campos */
  (function () {
    var st = document.createElement('style');
    st.textContent = 'html,body{touch-action:pan-x pan-y;-webkit-text-size-adjust:100%;text-size-adjust:100%}'
      + 'a,button,input,select,textarea,label,[role=button]{touch-action:manipulation}'
      + '@media (pointer:coarse){input,select,textarea{font-size:16px!important}}';
    document.head.appendChild(st);
    ['gesturestart', 'gesturechange', 'gestureend'].forEach(function (ev) {
      document.addEventListener(ev, function (e) { e.preventDefault(); }, { passive: false });
    });
    document.addEventListener('touchmove', function (e) { if (e.touches.length > 1) e.preventDefault(); }, { passive: false });
    var ultimo = 0;
    document.addEventListener('touchend', function (e) {
      var agora = Date.now();
      if (agora - ultimo < 300 && !/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) e.preventDefault();
      ultimo = agora;
    }, { passive: false });
    document.addEventListener('wheel', function (e) { if (e.ctrlKey) e.preventDefault(); }, { passive: false });
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && ['+', '-', '=', '0'].indexOf(e.key) > -1) e.preventDefault();
    });
  })();

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(BASE + '/sw.js', { scope: BASE + '/' }).catch(function () {});
    });
  }
  if (instalado) return;

  function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  var css = '.pwa-bar{position:fixed;left:12px;right:12px;bottom:calc(12px + env(safe-area-inset-bottom));z-index:9998;max-width:460px;margin:0 auto;background:#fff;color:#1F2937;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.22);padding:14px;display:flex;gap:12px;align-items:center;font-family:Inter,system-ui,sans-serif;animation:pwaIn .3s ease}'
    + '@keyframes pwaIn{from{transform:translateY(30px);opacity:0}}'
    + '.pwa-bar img{width:48px;height:48px;border-radius:12px;flex:0 0 48px}'
    + '.pwa-bar .t{flex:1;min-width:0}.pwa-bar b{display:block;font-size:14px}.pwa-bar small{display:block;font-size:12.5px;color:#6B7280;line-height:1.35;margin-top:2px}'
    + '.pwa-bar .ok{background:#0F7B3E;color:#fff;border:0;border-radius:10px;padding:10px 14px;font:600 13px Inter,sans-serif;cursor:pointer;white-space:nowrap}'
    + '.pwa-bar .x{background:none;border:0;font-size:22px;line-height:1;color:#9CA3AF;cursor:pointer;padding:4px;align-self:flex-start}'
    + '.pwa-ios{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);display:flex;align-items:flex-end;justify-content:center;font-family:Inter,system-ui,sans-serif}'
    + '.pwa-ios .c{background:#fff;color:#1F2937;width:100%;max-width:460px;border-radius:20px 20px 0 0;padding:22px 20px calc(22px + env(safe-area-inset-bottom))}'
    + '.pwa-ios h3{margin:0 0 14px;font-size:18px}.pwa-ios ol{margin:0 0 18px;padding-left:20px;line-height:1.9;font-size:15px}'
    + '.pwa-ios svg{vertical-align:-4px}.pwa-ios button{width:100%;background:#0F7B3E;color:#fff;border:0;border-radius:12px;padding:13px;font:600 15px Inter,sans-serif}'
    + '@media (prefers-color-scheme:dark){.pwa-bar,.pwa-ios .c{background:#1F2937;color:#F3F4F6}.pwa-bar small{color:#9CA3AF}}'
    + '.pwa-ios .c{max-height:92vh;overflow:hidden;display:flex;flex-direction:column}'
    + '.pwa-ios .topo{display:flex;justify-content:space-between;align-items:center}.pwa-ios .topo h3{margin:0}'
    + '.pwa-ios .fx{width:auto!important;background:none!important;color:#9CA3AF!important;font-size:26px!important;padding:0 4px!important}'
    + '.pwa-ios .trilho{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;margin:10px -20px 0}.pwa-ios .trilho::-webkit-scrollbar{display:none}'
    + '.pwa-ios .passo{flex:0 0 100%;scroll-snap-align:center;padding:0 20px;box-sizing:border-box;text-align:center}'
    + '.pwa-ios .n{font-size:12px;color:#6B7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em}'
    + '.pwa-ios .ilu{width:100%;max-width:260px;height:auto;margin:8px auto;display:block}'
    + '.pwa-ios h4{margin:6px 0;font-size:17px}.pwa-ios h4 svg{vertical-align:-3px}.pwa-ios p{margin:0;font-size:14.5px;line-height:1.5;color:#4B5563;min-height:44px}'
    + '.pwa-ios .pontos{display:flex;gap:6px;justify-content:center;margin:14px 0}.pwa-ios .pontos i{width:7px;height:7px;border-radius:9px;background:#D1D5DB;cursor:pointer;transition:.2s}.pwa-ios .pontos i.on{width:20px;background:#0F7B3E}'
    + '.pwa-ios .pwa-nav{display:flex!important;flex-direction:row!important;gap:10px}.pwa-ios .pwa-nav button{flex:1;width:auto!important}.pwa-ios .pwa-nav .ant{background:#F3F4F6!important;color:#374151!important}'
    + '.pwa-ios .pulsa{animation:pwaPulsa 1.2s ease-in-out infinite;transform-box:fill-box;transform-origin:center}@keyframes pwaPulsa{50%{opacity:.45;transform:scale(1.25)}}'
    + '.pwa-alerta{background:#FEF3C7;color:#92400E;border-radius:10px;padding:10px 12px;font-size:13.5px;margin-top:10px;line-height:1.45}.pwa-alerta .copiar{display:block;width:100%;margin-top:8px;background:#92400E!important;padding:9px!important;font-size:13px!important}'
    + '.pwa-ios .seta{position:fixed;bottom:calc(2px + env(safe-area-inset-bottom));font-size:26px;color:#fff;text-shadow:0 2px 6px rgba(0,0,0,.5);animation:pwaSeta 1s ease-in-out infinite;z-index:10000;pointer-events:none}'
    + '.pwa-ios .seta.meio{left:50%;margin-left:-10px}.pwa-ios .seta.dir{right:22px}.pwa-ios .seta.topo-dir{top:4px;bottom:auto;right:60px;transform:rotate(180deg)}'
    + '@keyframes pwaSeta{50%{translate:0 6px}}'
    + '@media (prefers-color-scheme:dark){.pwa-ios p{color:#D1D5DB}.pwa-ios .pwa-nav .ant{background:#374151!important;color:#F3F4F6!important}}';
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  var shareIco = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><path d="M12 3v13"/><path d="m7 8 5-5 5 5"/><path d="M5 12v8h14v-8"/></svg>';
  function instrucoes() {
    var m = document.createElement('div'); m.className = 'pwa-ios';
    var passos = ios
      ? (safariIOS
          ? '<li>Toque em ' + shareIco + ' <b>Compartilhar</b> na barra do Safari</li><li>Role e toque em <b>Adicionar à Tela de Início</b></li><li>Toque em <b>Adicionar</b></li>'
          : '<li>Abra este endereço no <b>Safari</b> (no iPhone só o Safari instala apps)</li><li>Toque em ' + shareIco + ' <b>Compartilhar</b></li><li>Toque em <b>Adicionar à Tela de Início</b></li>')
      : '<li>Abra o menu <b>⋮</b> do navegador</li><li>Toque em <b>Instalar aplicativo</b> ou <b>Adicionar à tela inicial</b></li><li>Confirme em <b>Instalar</b></li>';
    m.innerHTML = '<div class="c"><h3>Instalar Jardim Maceió</h3><ol>' + passos + '</ol><button type="button">Entendi</button></div>';
    m.addEventListener('click', function (e) { if (e.target === m || e.target.tagName === 'BUTTON') m.remove(); });
    document.body.appendChild(m);
  }

  /* ===== Tutorial ilustrado de instalação no iPhone/iPad (Safari) ===== */
  function versaoSafari() { var m = ua.match(/Version\/(\d+)/); return m ? +m[1] : 0; }
  function tutorialIOS() {
    var novo = versaoSafari() >= 26;              // iOS 26: Compartilhar fica no menu •••
    var ipad = /ipad/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var V = '#0F7B3E', A = '#2563EB';
    function tela(conteudo) {
      return '<svg viewBox="0 0 200 150" class="ilu"><rect x="45" y="4" width="110" height="142" rx="16" fill="#F3F4F6" stroke="#D1D5DB"/>' + conteudo + '</svg>';
    }
    var barraAntiga = '<rect x="50" y="118" width="100" height="22" rx="6" fill="#fff"/>'
      + '<text x="62" y="133" font-size="11" fill="#9CA3AF">‹</text><text x="78" y="133" font-size="11" fill="#9CA3AF">›</text>'
      + '<g transform="translate(94 122)"><circle cx="6" cy="7" r="10" fill="' + A + '" opacity=".15" class="pulsa"/><path d="M6 1v9M2.5 4.5 6 1l3.5 3.5M1 7v7h10V7" fill="none" stroke="' + A + '" stroke-width="1.6"/></g>'
      + '<text x="115" y="133" font-size="10" fill="#9CA3AF">▢</text><text x="133" y="133" font-size="10" fill="#9CA3AF">⧉</text>';
    var barraNova = '<rect x="50" y="118" width="100" height="22" rx="11" fill="#fff"/>'
      + '<text x="58" y="133" font-size="10" fill="#9CA3AF">‹</text><rect x="70" y="123" width="52" height="12" rx="6" fill="#E5E7EB"/>'
      + '<text x="76" y="132" font-size="7" fill="#6B7280">alequizao.com</text>'
      + '<g transform="translate(135 129)"><circle r="9" fill="' + A + '" opacity=".15" class="pulsa"/><text x="-6" y="3" font-size="10" font-weight="700" fill="' + A + '">•••</text></g>';
    var pag = '<rect x="55" y="14" width="90" height="20" rx="4" fill="' + V + '"/><rect x="55" y="40" width="90" height="10" rx="3" fill="#E5E7EB"/><rect x="55" y="55" width="60" height="10" rx="3" fill="#E5E7EB"/>';
    var menu = function (itens, destaque) {
      var h = '<rect x="52" y="40" width="96" height="' + (itens.length * 16 + 8) + '" rx="8" fill="#fff" stroke="#E5E7EB"/>';
      itens.forEach(function (t, i) {
        var y = 44 + i * 16;
        if (i === destaque) h += '<rect x="55" y="' + y + '" width="90" height="14" rx="4" fill="' + A + '" opacity=".15" class="pulsa"/>';
        h += '<text x="60" y="' + (y + 10) + '" font-size="8" fill="' + (i === destaque ? A : '#374151') + '" font-weight="' + (i === destaque ? 700 : 400) + '">' + t + '</text>';
      });
      return h;
    };
    var shareIco = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="' + A + '" stroke-width="2"><path d="M12 3v13"/><path d="m7 8 5-5 5 5"/><path d="M5 12v8h14v-8"/></svg>';
    var passos = [];
    if (novo) {
      passos.push({ t: 'Toque em <b>•••</b>', d: 'Na barra de baixo do Safari, toque nos <b>três pontinhos</b> ao lado do endereço.', s: tela(pag + barraNova) });
      passos.push({ t: 'Toque em ' + shareIco + ' <b>Compartilhar</b>', d: 'No menu que abriu, escolha <b>Compartilhar</b>.', s: tela(pag + menu(['Compartilhar', 'Adicionar aos Favoritos', 'Nova Aba'], 0)) });
    } else {
      passos.push({ t: 'Toque em ' + shareIco + ' <b>Compartilhar</b>', d: ipad ? 'No iPad o botão fica <b>no alto, à direita</b> do endereço.' : 'É o quadrado com a seta para cima, <b>na barra de baixo</b> do Safari (se a barra sumiu, role a página um pouco para cima).', s: tela(pag + barraAntiga) });
    }
    passos.push({ t: 'Toque em <b>Adicionar à Tela de Início</b>', d: 'Role a lista para baixo até achar a opção <b>Adicionar à Tela de Início</b> (ícone de ⊞).', s: tela(menu(['Copiar', 'Adicionar ao Favoritos', 'Adicionar à Tela de Início', 'Imprimir'], 2)) });
    passos.push({ t: 'Toque em <b>Adicionar</b>', d: (novo ? 'Deixe ligado <b>Abrir como App Web</b> e toque' : 'Toque') + ' em <b>Adicionar</b>, no canto de cima.', s: tela('<rect x="52" y="14" width="96" height="16" fill="#fff"/><text x="56" y="25" font-size="8" fill="#374151">Cancelar</text><text x="122" y="25" font-size="8" font-weight="700" fill="' + A + '">Adicionar</text><circle cx="133" cy="22" r="11" fill="' + A + '" opacity=".15" class="pulsa"/><image href="' + BASE + '/assets/icons/icon-192.png" x="58" y="40" width="26" height="26"/><text x="90" y="56" font-size="8" fill="#374151">Jardim Maceió</text>' + (novo ? '<text x="58" y="86" font-size="7" fill="#374151">Abrir como App Web</text><rect x="124" y="79" width="18" height="10" rx="5" fill="#34C759"/><circle cx="137" cy="84" r="4" fill="#fff"/>' : '')) });
    passos.push({ t: 'Pronto! 🎉', d: 'O ícone <b>Jardim Maceió</b> aparece na Tela de Início. Abra sempre por ele: o sistema abre em tela cheia, como um aplicativo.', s: tela('<g>' + [0, 1, 2, 3].map(function (i) { return '<rect x="' + (58 + i * 22) + '" y="20" width="16" height="16" rx="4" fill="#D1D5DB"/>'; }).join('') + '</g><image href="' + BASE + '/assets/icons/icon-192.png" x="58" y="46" width="16" height="16"/><circle cx="66" cy="54" r="12" fill="' + V + '" opacity=".18" class="pulsa"/><text x="54" y="71" font-size="5" fill="#374151">Jardim Maceió</text>') });

    var aviso = safariIOS ? '' : '<div class="pwa-alerta">No iPhone, a instalação funciona melhor pelo <b>Safari</b>. <button type="button" class="copiar">Copiar link para abrir no Safari</button></div>';
    var m = document.createElement('div'); m.className = 'pwa-ios';
    m.innerHTML = '<div class="c"><div class="topo"><h3>Instalar no ' + (ipad ? 'iPad' : 'iPhone') + '</h3><button type="button" class="fx" aria-label="Fechar">×</button></div>' + aviso
      + '<div class="trilho">' + passos.map(function (p, i) { return '<div class="passo"><div class="n">Passo ' + (i + 1) + ' de ' + passos.length + '</div>' + p.s + '<h4>' + p.t + '</h4><p>' + p.d + '</p></div>'; }).join('') + '</div>'
      + '<div class="pontos">' + passos.map(function (_, i) { return '<i data-i="' + i + '"></i>'; }).join('') + '</div>'
      + '<div class="pwa-nav"><button type="button" class="ant">Voltar</button><button type="button" class="prox">Próximo</button></div>'
      + (novo ? '' : '') + '</div>'
      + (safariIOS ? '<div class="seta ' + (novo ? 'dir' : (ipad ? 'topo-dir' : 'meio')) + '">▼</div>' : '');
    document.body.appendChild(m);
    var tr = m.querySelector('.trilho'), n = passos.length, atual = 0;
    function ir(i) {
      atual = Math.max(0, Math.min(n - 1, i));
      tr.scrollTo({ left: atual * tr.clientWidth, behavior: 'smooth' });
      pintar();
    }
    function pintar() {
      m.querySelectorAll('.pontos i').forEach(function (p, i) { p.className = i === atual ? 'on' : ''; });
      m.querySelector('.ant').style.visibility = atual ? 'visible' : 'hidden';
      m.querySelector('.prox').textContent = atual === n - 1 ? 'Entendi' : 'Próximo';
      var s = m.querySelector('.seta'); if (s) s.style.display = atual === 0 ? '' : 'none';
    }
    tr.addEventListener('scroll', function () { var i = Math.round(tr.scrollLeft / tr.clientWidth); if (i !== atual) { atual = i; pintar(); } });
    m.querySelector('.prox').onclick = function () { if (atual === n - 1) m.remove(); else ir(atual + 1); };
    m.querySelector('.ant').onclick = function () { ir(atual - 1); };
    m.querySelector('.fx').onclick = function () { m.remove(); };
    m.querySelectorAll('.pontos i').forEach(function (p) { p.onclick = function () { ir(+p.dataset.i); }; });
    var cp = m.querySelector('.copiar');
    if (cp) cp.onclick = function () {
      var url = BASE + '/';
      (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () { cp.textContent = 'Link copiado! Cole no Safari'; }, function () { prompt('Copie o link e abra no Safari:', url); });
    };
    pintar();
  }
  window.JMInstalarIOS = tutorialIOS;

  async function instalar() {
    if (pedido) {
      pedido.prompt();
      var r = await pedido.userChoice; pedido = null;
      if (r && r.outcome === 'accepted') fecharBarra(true);
      return;
    }
    if (ios) tutorialIOS(); else instrucoes();
  }

  var barra = null;
  function fecharBarra(sempre) {
    if (barra) { barra.remove(); barra = null; }
    lsSet('jm_pwa_fechado', sempre ? '9999999999999' : String(Date.now() + 3 * 864e5));
  }
  function mostrarBarra() {
    if (barra || Number(lsGet('jm_pwa_fechado') || 0) > Date.now()) return;
    barra = document.createElement('div'); barra.className = 'pwa-bar';
    barra.innerHTML = '<img src="' + BASE + '/assets/icons/icon-192.png" alt="">'
      + '<div class="t"><b>Instale o app Jardim Maceió</b><small>' + (ios ? 'Veja como instalar pelo Safari em 3 toques.' : 'Acesso rápido pela tela inicial, em tela cheia.') + '</small></div>'
      + '<button type="button" class="ok">' + (ios ? 'Ver como' : 'Instalar') + '</button><button type="button" class="x" aria-label="Fechar">×</button>';
    barra.querySelector('.ok').onclick = instalar;
    barra.querySelector('.x').onclick = function () { fecharBarra(false); };
    document.body.appendChild(barra);
  }

  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); pedido = e; mostrarBarra(); });
  window.addEventListener('appinstalled', function () { fecharBarra(true); var b = document.getElementById('pwaInstalar'); if (b) b.hidden = true; });

  document.addEventListener('DOMContentLoaded', function () {
    var b = document.getElementById('pwaInstalar');
    if (b) { b.hidden = false; b.addEventListener('click', instalar); }
    // celular/tablet sem o evento automático (iPhone, Samsung, Firefox…): mostra o aviso com instruções
    var movel = /android|iphone|ipad|ipod|mobile/i.test(ua) || ios;
    setTimeout(function () { if (!pedido && movel) mostrarBarra(); }, 1500);
  });
})();
