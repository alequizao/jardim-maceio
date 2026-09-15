<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * /api/despesas.php
 * CRUD de despesas
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

/**
 * Se houver arquivo de NF enviado junto da despesa, faz o upload,
 * cria o registro em notas_fiscais e vincula nos dois sentidos.
 */
function anexarNotaFiscalDespesa(PDO $pdo, int $despesaId, array $in, array $u): void {
    if (empty($_FILES['nota_fiscal']['tmp_name'])) return;
    if ($_FILES['nota_fiscal']['error'] !== UPLOAD_ERR_OK) {
        jsonResposta(['erro' => 'Falha no upload da nota fiscal.'], 400);
    }
    $ext       = strtolower(pathinfo($_FILES['nota_fiscal']['name'], PATHINFO_EXTENSION));
    $permitido = ['pdf' => 'pdf', 'png' => 'imagem', 'jpg' => 'imagem', 'jpeg' => 'imagem', 'xml' => 'xml'];
    if (!isset($permitido[$ext])) {
        jsonResposta(['erro' => 'Extensão da NF não permitida (PDF, PNG, JPG ou XML).'], 400);
    }
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
    $arquivo = 'nf_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    move_uploaded_file($_FILES['nota_fiscal']['tmp_name'], UPLOAD_DIR . $arquivo);

    // Usa os dados da despesa para preencher a nota fiscal
    $d = $pdo->query("SELECT valor, data_competencia, fornecedor_id FROM despesas WHERE id = " . (int)$despesaId)->fetch();
    $numero = trim($in['nf_numero'] ?? '');
    if ($numero === '') $numero = 'S/N';

    $stmt = $pdo->prepare(
        "INSERT INTO notas_fiscais
           (numero, fornecedor_id, despesa_id, valor, data_emissao, arquivo, tipo_arquivo, observacoes, criado_por)
         VALUES (:n,:f,:dp,:v,:de,:a,:t,:o,:cp)"
    );
    $stmt->execute([
        ':n'  => $numero,
        ':f'  => $d['fornecedor_id'] ?: null,
        ':dp' => $despesaId,
        ':v'  => (float)$d['valor'],
        ':de' => dateOrNull($d['data_competencia']) ?? date('Y-m-d'),
        ':a'  => $arquivo,
        ':t'  => $permitido[$ext],
        ':o'  => 'Anexada no lançamento da despesa.',
        ':cp' => $u['id'],
    ]);
    $nfId = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE despesas SET nota_fiscal_id = :nf WHERE id = :id")
        ->execute([':nf' => $nfId, ':id' => $despesaId]);
}

switch ($metodo) {

    case 'GET':
        if ($id > 0) {
            $stmt = $pdo->prepare(
                "SELECT d.*, c.nome AS categoria_nome, f.nome AS fornecedor_nome
                 FROM despesas d
                 LEFT JOIN categorias c   ON c.id = d.categoria_id
                 LEFT JOIN fornecedores f ON f.id = d.fornecedor_id
                 WHERE d.id = :id"
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) jsonResposta(['erro' => 'Despesa não encontrada'], 404);
            jsonResposta($row);
        }

        // Atualiza status de vencidas antes de listar
        $pdo->exec("UPDATE despesas SET status='vencido'
                    WHERE status='pendente' AND data_vencimento < CURDATE()");

        $filtroStatus    = $_GET['status']  ?? '';
        $filtroMes       = $_GET['mes']     ?? '';
        $filtroCategoria = (int)($_GET['categoria_id'] ?? 0);
        $proximas        = isset($_GET['proximas']);

        $sql = "SELECT d.id, d.descricao, d.valor, d.data_competencia,
                       d.data_vencimento, d.data_pagamento, d.status,
                       c.nome AS categoria,
                       f.nome AS fornecedor
                FROM despesas d
                LEFT JOIN categorias c   ON c.id = d.categoria_id
                LEFT JOIN fornecedores f ON f.id = d.fornecedor_id
                WHERE 1=1";
        $par = [];
        if ($filtroStatus !== '')   { $sql .= " AND d.status = :st";          $par[':st']  = $filtroStatus; }
        if ($filtroCategoria > 0)   { $sql .= " AND d.categoria_id = :cat";   $par[':cat'] = $filtroCategoria; }
        if ($filtroMes !== '' && preg_match('/^\d{4}-\d{2}$/', $filtroMes)) {
            $sql .= " AND DATE_FORMAT(d.data_competencia,'%Y-%m') = :mes";
            $par[':mes'] = $filtroMes;
        }
        if ($proximas) {
            $sql .= " AND d.status IN ('pendente','vencido')
                      AND d.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        }
        $sql .= " ORDER BY d.data_vencimento ASC, d.id DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($par);
        jsonResposta(['itens' => $stmt->fetchAll()]);
        break;

    case 'POST':
        // Aceita multipart/form-data (com anexo de NF) ou JSON.
        $isMultipart = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
        $in = $isMultipart ? $_POST : inputJson();
        $u  = Auth::usuario();

        // id no corpo => edição; ausente => criação.
        // (Usamos sempre POST porque o PHP não popula $_FILES em requisições PUT.)
        $editId = (int)($in['id'] ?? 0);

        $campos = [
            ':d'   => trim($in['descricao'] ?? ''),
            ':cat' => !empty($in['categoria_id'])  ? (int)$in['categoria_id']  : null,
            ':f'   => !empty($in['fornecedor_id']) ? (int)$in['fornecedor_id'] : null,
            ':v'   => (float)($in['valor'] ?? 0),
            ':dc'  => dateOrNull($in['data_competencia'] ?? null) ?? date('Y-m-d'),
            ':dv'  => dateOrNull($in['data_vencimento']  ?? null) ?? date('Y-m-d'),
            ':dp'  => dateOrNull($in['data_pagamento']   ?? null),
            ':s'   => $in['status'] ?? 'pendente',
            ':fp'  => $in['forma_pagamento'] ?? null,
            ':o'   => $in['observacoes'] ?? null,
        ];

        if ($editId > 0) {
            $campos[':id'] = $editId;
            $pdo->prepare(
                "UPDATE despesas SET descricao=:d, categoria_id=:cat, fornecedor_id=:f,
                    valor=:v, data_competencia=:dc, data_vencimento=:dv, data_pagamento=:dp,
                    status=:s, forma_pagamento=:fp, observacoes=:o WHERE id=:id"
            )->execute($campos);
            $despesaId = $editId;
        } else {
            $campos[':cp'] = $u['id'];
            $pdo->prepare(
                "INSERT INTO despesas
                   (descricao, categoria_id, fornecedor_id, valor, data_competencia,
                    data_vencimento, data_pagamento, status, forma_pagamento, observacoes, criado_por)
                 VALUES (:d,:cat,:f,:v,:dc,:dv,:dp,:s,:fp,:o,:cp)"
            )->execute($campos);
            $despesaId = (int)$pdo->lastInsertId();
        }

        // Anexo de nota fiscal (opcional) — cria registro em notas_fiscais e vincula.
        anexarNotaFiscalDespesa($pdo, $despesaId, $in, $u);

        jsonResposta(['ok' => true, 'id' => $despesaId], $editId > 0 ? 200 : 201);
        break;

    case 'PUT':
        if ($id <= 0) jsonResposta(['erro' => 'ID obrigatório'], 400);
        $in = inputJson();
        $stmt = $pdo->prepare(
            "UPDATE despesas SET
                descricao        = :d,
                categoria_id     = :cat,
                fornecedor_id    = :f,
                valor            = :v,
                data_competencia = :dc,
                data_vencimento  = :dv,
                data_pagamento   = :dp,
                status           = :s,
                forma_pagamento  = :fp,
                observacoes      = :o
             WHERE id = :id"
        );
        $stmt->execute([
            ':d'   => trim($in['descricao'] ?? ''),
            ':cat' => !empty($in['categoria_id'])  ? (int)$in['categoria_id']  : null,
            ':f'   => !empty($in['fornecedor_id']) ? (int)$in['fornecedor_id'] : null,
            ':v'   => (float)($in['valor'] ?? 0),
            ':dc'  => dateOrNull($in['data_competencia'] ?? null) ?? date('Y-m-d'),
            ':dv'  => dateOrNull($in['data_vencimento']  ?? null) ?? date('Y-m-d'),
            ':dp'  => dateOrNull($in['data_pagamento']   ?? null),
            ':s'   => $in['status'] ?? 'pendente',
            ':fp'  => $in['forma_pagamento'] ?? null,
            ':o'   => $in['observacoes'] ?? null,
            ':id'  => $id,
        ]);
        jsonResposta(['ok' => true]);
        break;

    case 'DELETE':
        if ($id <= 0) jsonResposta(['erro' => 'ID obrigatório'], 400);
        $pdo->prepare("DELETE FROM despesas WHERE id = :id")->execute([':id' => $id]);
        jsonResposta(['ok' => true]);
        break;

    default:
        jsonResposta(['erro' => 'Método não permitido'], 405);
}
