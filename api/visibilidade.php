<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * /api/visibilidade.php
 * GET  -> módulos liberados para o usuário atual (+ config, se admin)
 * POST -> {modulo, liberado} (somente admin/síndico)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::exigirLoginApi();
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Vis::ehAdmin()) jsonResposta(['erro' => 'Somente administradores.'], 403);
    $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $m = (string)($in['modulo'] ?? '');
    if (!isset(Vis::MODULOS[$m])) jsonResposta(['erro' => 'Módulo inválido.'], 400);
    Vis::salvar($m, !empty($in['liberado']));
}

$liberados = [];
foreach (Vis::MODULOS as $m => $d) $liberados[$m] = Vis::liberado($m);
$out = ['ok' => true, 'versao' => Vis::versao(), 'liberados' => $liberados, 'admin' => Vis::ehAdmin(), 'app' => Vis::versaoApp()];
if (Vis::ehAdmin()) {
    $cfg = [];
    foreach (Vis::config() as $m => $on) $cfg[] = ['modulo' => $m, 'nome' => Vis::MODULOS[$m][0], 'liberado' => $on];
    $out['config'] = $cfg;
}
jsonResposta($out);
