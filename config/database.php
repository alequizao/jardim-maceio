<?php
/*
 * Jardim Maceió · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
/**
 * database.php
 * Conexão PDO com MySQL — Banco: jardim_maceio
 */

class Database
{
    // Credenciais do banco
    private const DB_HOST = 'localhost';
    private const DB_NAME = 'jardim_maceio';
    private const DB_USER = 'jardim_maceio';
    private const DB_PASS = 'jardim_maceio';
    private const DB_CHARSET = 'utf8mb4';

    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function conectar(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::DB_HOST,
                self::DB_NAME,
                self::DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            } catch (PDOException $e) {
                // Em produção, logar em arquivo ao invés de exibir
                http_response_code(500);
                die('Erro de conexão com o banco: ' . htmlspecialchars($e->getMessage()));
            }
        }
        return self::$instance;
    }
}
