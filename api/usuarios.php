<?php
/**
 * /api/usuarios.php
 * CRUD de usuários do sistema (apenas admin)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::exigirLoginApi();
Auth::exigirEscritaApi(); // morador: somente leitura
$u = Auth::usuario();
if ($u['tipo'] !== 'admin') jsonResposta(['erro' => 'Acesso restrito a administradores'], 403);

$pdo = Database::conectar();
$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($metodo) {

    case 'GET':
        $rows = $pdo->query(
            "SELECT id, nome, email, tipo, ativo, ultimo_login, criado_em
             FROM usuarios ORDER BY nome"
        )->fetchAll();
        jsonResposta(['itens' => $rows]);
        break;

    case 'POST':
        $in = inputJson();
        if (empty($in['email']) || empty($in['senha']) || empty($in['nome'])) {
            jsonResposta(['erro' => 'Nome, e-mail e senha são obrigatórios'], 400);
        }
        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (nome,email,senha,tipo,ativo)
             VALUES (:n,:e,:s,:t,1)"
        );
        try {
            $stmt->execute([
                ':n' => trim($in['nome']),
                ':e' => trim($in['email']),
                ':s' => password_hash($in['senha'], PASSWORD_DEFAULT),
                ':t' => $in['tipo'] ?? 'morador',
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') jsonResposta(['erro' => 'E-mail já cadastrado'], 409);
            throw $e;
        }
        jsonResposta(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
        break;

    case 'PUT':
        if ($id <= 0) jsonResposta(['erro' => 'ID obrigatório'], 400);
        $in = inputJson();
        $sql = "UPDATE usuarios SET nome=:n, email=:e, tipo=:t, ativo=:a";
        $par = [
            ':n' => trim($in['nome'] ?? ''),
            ':e' => trim($in['email'] ?? ''),
            ':t' => $in['tipo'] ?? 'morador',
            ':a' => !empty($in['ativo']) ? 1 : 0,
            ':id'=> $id,
        ];
        if (!empty($in['senha'])) {
            $sql .= ", senha=:s";
            $par[':s'] = password_hash($in['senha'], PASSWORD_DEFAULT);
        }
        $sql .= " WHERE id=:id";
        $pdo->prepare($sql)->execute($par);
        jsonResposta(['ok' => true]);
        break;

    case 'DELETE':
        if ($id <= 0) jsonResposta(['erro' => 'ID obrigatório'], 400);
        if ($id === (int)$u['id']) jsonResposta(['erro' => 'Não é possível excluir o próprio usuário'], 400);
        $pdo->prepare("UPDATE usuarios SET ativo=0 WHERE id=:id")->execute([':id' => $id]);
        jsonResposta(['ok' => true]);
        break;

    default:
        jsonResposta(['erro' => 'Método não permitido'], 405);
}
