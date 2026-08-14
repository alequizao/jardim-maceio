<?php
/**
 * auth.php
 * Funções de autenticação e controle de acesso
 */

require_once __DIR__ . '/../config/database.php';

class Auth
{
    public static function login(string $email, string $senha): array
    {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("SELECT id, nome, email, senha, tipo, ativo
                               FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['ok' => false, 'msg' => 'E-mail ou senha inválidos.'];
        }
        if (!$user['ativo']) {
            return ['ok' => false, 'msg' => 'Usuário inativo. Contate o administrador.'];
        }
        if (!password_verify($senha, $user['senha'])) {
            return ['ok' => false, 'msg' => 'E-mail ou senha inválidos.'];
        }

        // Atualiza último login
        $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id")
            ->execute([':id' => $user['id']]);

        // Salva sessão
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id'    => (int)$user['id'],
            'nome'  => $user['nome'],
            'email' => $user['email'],
            'tipo'  => $user['tipo'],
        ];

        return ['ok' => true, 'usuario' => $_SESSION['usuario']];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function logado(): bool
    {
        return isset($_SESSION['usuario']['id']);
    }

    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function exigirLogin(): void
    {
        if (!self::logado()) {
            // Redireciona para login
            $redirect = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($redirect));
            exit;
        }
    }

    public static function exigirLoginApi(): array
    {
        if (!self::logado()) {
            jsonResposta(['erro' => 'Não autenticado'], 401);
        }
        return self::usuario();
    }

    public static function exigirPerfil(array $perfis): void
    {
        self::exigirLogin();
        if (!in_array($_SESSION['usuario']['tipo'], $perfis, true)) {
            http_response_code(403);
            die('Acesso negado.');
        }
    }

    /**
     * Perfis com permissão de alterar dados.
     * O perfil "morador" tem acesso somente de leitura.
     */
    public static function podeEditar(): bool
    {
        $u = self::usuario();
        return $u !== null && in_array($u['tipo'], ['admin', 'sindico'], true);
    }

    /**
     * Bloqueia métodos de escrita (POST/PUT/PATCH/DELETE) para perfis
     * sem permissão de edição. Use em APIs após exigirLoginApi().
     */
    public static function exigirEscritaApi(): void
    {
        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($metodo, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !self::podeEditar()) {
            jsonResposta(['erro' => 'Seu perfil não tem permissão para alterar dados.'], 403);
        }
    }

    public static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome));
        if (count($partes) === 1) return strtoupper(substr($partes[0], 0, 2));
        return strtoupper(substr($partes[0], 0, 1) . substr(end($partes), 0, 1));
    }
}
