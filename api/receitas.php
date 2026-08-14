<?php
/**
 * /api/receitas.php
 * CRUD de receitas
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::exigirLoginApi();
Auth::exigirEscritaApi(); // morador: somente leitura
$pdo = Database::conectar();
$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($metodo) {

    case 'GET':
        if ($id > 0) {
            $stmt = $pdo->prepare(
                "SELECT r.*, c.nome AS categoria_nome,
                        CONCAT(m.bloco,' - ', m.apartamento) AS unidade
                 FROM receitas r
                 LEFT JOIN categorias c ON c.id = r.categoria_id
                 LEFT JOIN moradores  m ON m.id = r.morador_id
                 WHERE r.id = :id"
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) jsonResposta(['erro' => 'Receita não encontrada'], 404);
            jsonResposta($row);
        }

        $filtroStatus    = $_GET['status']  ?? '';
        $filtroMes       = $_GET['mes']     ?? '';
        $filtroCategoria = (int)($_GET['categoria_id'] ?? 0);

        $sql = "SELECT r.id, r.descricao, r.valor, r.data_competencia,
                       r.data_recebimento, r.status,
                       c.nome AS categoria,
                       CONCAT(IFNULL(m.bloco,'-'),' / ', IFNULL(m.apartamento,'-')) AS unidade
                FROM receitas r
                LEFT JOIN categorias c ON c.id = r.categoria_id
                LEFT JOIN moradores  m ON m.id = r.morador_id
                WHERE 1=1";
        $par = [];
        if ($filtroStatus !== '')    { $sql .= " AND r.status = :st"; $par[':st'] = $filtroStatus; }
        if ($filtroCategoria > 0)    { $sql .= " AND r.categoria_id = :cat"; $par[':cat'] = $filtroCategoria; }
        if ($filtroMes !== '' && preg_match('/^\d{4}-\d{2}$/', $filtroMes)) {
            $sql .= " AND DATE_FORMAT(r.data_competencia,'%Y-%m') = :mes";
            $par[':mes'] = $filtroMes;
        }
        $sql .= " ORDER BY r.data_competencia DESC, r.id DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($par);
        jsonResposta(['itens' => $stmt->fetchAll()]);
        break;

    case 'POST':
        $in = inputJson();
        $u  = Auth::usuario();
        $stmt = $pdo->prepare(
            "INSERT INTO receitas
               (descricao, categoria_id, morador_id, valor, data_competencia,
                data_recebimento, status, forma_pagamento, observacoes, criado_por)
             VALUES (:d,:cat,:mor,:v,:dc,:dr,:s,:fp,:o,:cp)"
        );
        $stmt->execute([
            ':d'   => trim($in['descricao'] ?? ''),
            ':cat' => !empty($in['categoria_id']) ? (int)$in['categoria_id'] : null,
            ':mor' => !empty($in['morador_id'])   ? (int)$in['morador_id']   : null,
            ':v'   => (float)($in['valor'] ?? 0),
            ':dc'  => dateOrNull($in['data_competencia'] ?? null) ?? date('Y-m-d'),
            ':dr'  => dateOrNull($in['data_recebimento'] ?? null),
            ':s'   => $in['status'] ?? 'pendente',
            ':fp'  => $in['forma_pagamento'] ?? null,
            ':o'   => $in['observacoes'] ?? null,
            ':cp'  => $u['id'],
        ]);
        jsonResposta(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
        break;

    case 'PUT':
        if ($id <= 0) jsonResposta(['erro' => 'ID obrigatório'], 400);
        $in = inputJson();
        $stmt = $pdo->prepare(
            "UPDATE receitas SET
                descricao        = :d,
                categoria_id     = :cat,
                morador_id       = :mor,
                valor            = :v,
                data_competencia = :dc,
                data_recebimento = :dr,
                status           = :s,
                forma_pagamento  = :fp,
                observacoes      = :o
             WHERE id = :id"
        );
        $stmt->execute([
            ':d'   => trim($in['descricao'] ?? ''),
            ':cat' => !empty($in['categoria_id']) ? (int)$in['categoria_id'] : null,
            ':mor' => !empty($in['morador_id'])   ? (int)$in['morador_id']   : null,
            ':v'   => (float)($in['valor'] ?? 0),
            ':dc'  => dateOrNull($in['data_competencia'] ?? null) ?? date('Y-m-d'),
            ':dr'  => dateOrNull($in['data_recebimento'] ?? null),
            ':s'   => $in['status'] ?? 'pendente',
            ':fp'  => $in['forma_pagamento'] ?? null,
            ':o'   => $in['observacoes'] ?? null,
            ':id'  => $id,
        ]);
        jsonResposta(['ok' => true]);
        break;

    case 'DELETE':
        if ($id <= 0) jsonResposta(['erro' => 'ID obrigatório'], 400);
        $pdo->prepare("DELETE FROM receitas WHERE id = :id")->execute([':id' => $id]);
        jsonResposta(['ok' => true]);
        break;

    default:
        jsonResposta(['erro' => 'Método não permitido'], 405);
}
