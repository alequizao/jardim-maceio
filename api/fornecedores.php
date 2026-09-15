<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * /api/fornecedores.php
 * Listagem e cadastro rápido de fornecedores (usado no lançamento de despesas)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::exigirLoginApi();
Auth::exigirEscritaApi(); // morador: somente leitura
$pdo    = Database::conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    case 'GET':
        $itens = $pdo->query(
            "SELECT id, nome FROM fornecedores WHERE ativo = 1 ORDER BY nome"
        )->fetchAll();
        jsonResposta(['itens' => $itens]);
        break;

    case 'POST':
        $in   = inputJson();
        $nome = trim($in['nome'] ?? '');
        if ($nome === '') {
            jsonResposta(['erro' => 'Informe o nome do fornecedor.'], 400);
        }
        $stmt = $pdo->prepare(
            "INSERT INTO fornecedores (nome, cnpj_cpf, telefone, email, categoria_id)
             VALUES (:n, :c, :t, :e, :cat)"
        );
        $stmt->execute([
            ':n'   => $nome,
            ':c'   => trim($in['cnpj_cpf'] ?? '') ?: null,
            ':t'   => trim($in['telefone'] ?? '') ?: null,
            ':e'   => trim($in['email'] ?? '') ?: null,
            ':cat' => !empty($in['categoria_id']) ? (int)$in['categoria_id'] : null,
        ]);
        jsonResposta(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'nome' => $nome], 201);
        break;

    default:
        jsonResposta(['erro' => 'Método não permitido'], 405);
}
