<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * /api/notas-fiscais.php
 * CRUD de notas fiscais com upload de arquivo (PDF/imagem/XML)
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
                "SELECT n.*, f.nome AS fornecedor_nome, d.descricao AS despesa_descricao
                 FROM notas_fiscais n
                 LEFT JOIN fornecedores f ON f.id = n.fornecedor_id
                 LEFT JOIN despesas d     ON d.id = n.despesa_id
                 WHERE n.id = :id"
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) jsonResposta(['erro' => 'Nota não encontrada'], 404);
            jsonResposta($row);
        }

        $stmt = $pdo->query(
            "SELECT n.id, n.numero, n.valor, n.data_emissao, n.arquivo, n.tipo_arquivo,
                    f.nome AS fornecedor, d.descricao AS despesa
             FROM notas_fiscais n
             LEFT JOIN fornecedores f ON f.id = n.fornecedor_id
             LEFT JOIN despesas d     ON d.id = n.despesa_id
             ORDER BY n.data_emissao DESC, n.id DESC
             LIMIT 200"
        );
        jsonResposta(['itens' => $stmt->fetchAll()]);
        break;

    case 'POST':
        // Aceita multipart/form-data (com arquivo) ou JSON
        $temArquivo = !empty($_FILES['arquivo']['tmp_name']);
        $in = $temArquivo ? $_POST : inputJson();
        $u  = Auth::usuario();

        $arquivoNome = null;
        $tipoArq     = 'pdf';

        if ($temArquivo) {
            if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
                jsonResposta(['erro' => 'Falha no upload'], 400);
            }
            $ext = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
            $permitido = ['pdf' => 'pdf', 'png' => 'imagem', 'jpg' => 'imagem', 'jpeg' => 'imagem', 'xml' => 'xml'];
            if (!isset($permitido[$ext])) {
                jsonResposta(['erro' => 'Extensão não permitida (PDF, PNG, JPG, XML)'], 400);
            }
            $tipoArq = $permitido[$ext];

            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
            $arquivoNome = 'nf_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            move_uploaded_file($_FILES['arquivo']['tmp_name'], UPLOAD_DIR . $arquivoNome);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO notas_fiscais
               (numero, fornecedor_id, despesa_id, valor, data_emissao,
                arquivo, tipo_arquivo, observacoes, criado_por)
             VALUES (:n,:f,:dp,:v,:de,:a,:t,:o,:cp)"
        );
        $stmt->execute([
            ':n'  => trim($in['numero'] ?? ''),
            ':f'  => !empty($in['fornecedor_id']) ? (int)$in['fornecedor_id'] : null,
            ':dp' => !empty($in['despesa_id'])    ? (int)$in['despesa_id']    : null,
            ':v'  => (float)($in['valor'] ?? 0),
            ':de' => $in['data_emissao'] ?? date('Y-m-d'),
            ':a'  => $arquivoNome,
            ':t'  => $tipoArq,
            ':o'  => $in['observacoes'] ?? null,
            ':cp' => $u['id'],
        ]);
        jsonResposta(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
        break;

    case 'DELETE':
        if ($id <= 0) jsonResposta(['erro' => 'ID obrigatório'], 400);
        $arquivo = $pdo->query("SELECT arquivo FROM notas_fiscais WHERE id={$id}")->fetchColumn();
        if ($arquivo && file_exists(UPLOAD_DIR . $arquivo)) @unlink(UPLOAD_DIR . $arquivo);
        $pdo->prepare("DELETE FROM notas_fiscais WHERE id = :id")->execute([':id' => $id]);
        jsonResposta(['ok' => true]);
        break;

    default:
        jsonResposta(['erro' => 'Método não permitido'], 405);
}
