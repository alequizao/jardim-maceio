<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * /api/moradores.php
 * CRUD de moradores
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
            $stmt = $pdo->prepare("SELECT * FROM moradores WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) jsonResposta(['erro' => 'Morador não encontrado'], 404);
            jsonResposta($row);
        }
        $rows = $pdo->query(
            "SELECT id, nome, cpf, telefone, email, bloco, apartamento, proprietario, ativo
             FROM moradores
             WHERE ativo = 1
             ORDER BY LENGTH(bloco), bloco, LENGTH(apartamento), apartamento"
        )->fetchAll();
        jsonResposta(['itens' => $rows]);
        break;

    case 'POST':
        $in = inputJson();
        $stmt = $pdo->prepare(
            "INSERT INTO moradores (nome, cpf, telefone, email, bloco, apartamento, proprietario)
             VALUES (:n,:c,:t,:e,:b,:a,:p)"
        );
        $stmt->execute([
            ':n' => trim($in['nome'] ?? ''),
            ':c' => $in['cpf'] ?? null,
            ':t' => $in['telefone'] ?? null,
            ':e' => $in['email'] ?? null,
            ':b' => $in['bloco'] ?? '',
            ':a' => $in['apartamento'] ?? '',
            ':p' => !empty($in['proprietario']) ? 1 : 0,
        ]);
        jsonResposta(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
        break;

    case 'PUT':
        if ($id <= 0) jsonResposta(['erro' => 'ID obrigatório'], 400);
        $in = inputJson();
        $stmt = $pdo->prepare(
            "UPDATE moradores SET nome=:n, cpf=:c, telefone=:t, email=:e,
                                  bloco=:b, apartamento=:a, proprietario=:p
             WHERE id=:id"
        );
        $stmt->execute([
            ':n' => trim($in['nome'] ?? ''),
            ':c' => $in['cpf'] ?? null,
            ':t' => $in['telefone'] ?? null,
            ':e' => $in['email'] ?? null,
            ':b' => $in['bloco'] ?? '',
            ':a' => $in['apartamento'] ?? '',
            ':p' => !empty($in['proprietario']) ? 1 : 0,
            ':id'=> $id,
        ]);
        jsonResposta(['ok' => true]);
        break;

    case 'DELETE':
        if ($id <= 0) jsonResposta(['erro' => 'ID obrigatório'], 400);
        // Soft delete
        $pdo->prepare("UPDATE moradores SET ativo=0 WHERE id=:id")->execute([':id' => $id]);
        jsonResposta(['ok' => true]);
        break;

    default:
        jsonResposta(['erro' => 'Método não permitido'], 405);
}
