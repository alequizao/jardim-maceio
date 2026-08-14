    </section>

    <footer class="footer">
      <div class="footer-left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Sistema seguro e dados protegidos
      </div>
      <div class="footer-center">Transparência é o nosso compromisso com você.</div>
      <div class="footer-right">© <?= date('Y') ?> <?= e(SISTEMA_NOME) ?></div>
    </footer>

  </main>
</div>

<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?: time() ?>"></script>
<?php if (!empty($scriptPagina)): ?>
<script src="<?= BASE_URL ?>/assets/js/<?= e($scriptPagina) ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/' . $scriptPagina) ?: time() ?>"></script>
<?php endif; ?>
</body>
</html>
