<?php
/**
 * Pata Feliz — modelo de configuração
 *
 * Copie este arquivo para config/config.php e preencha com os dados
 * do ambiente. O config.php de verdade NÃO vai para o Git.
 */

// ---- Banco de dados (Hostinger: veja em Bancos de Dados MySQL) ----
define('BD_HOST', 'localhost');
define('BD_NOME', 'seu_banco');
define('BD_USUARIO', 'seu_usuario');
define('BD_SENHA', 'sua_senha');

// ---- Dados do estabelecimento ----
define('LOJA_NOME', 'Pata Feliz Banho e Tosa');
define('LOJA_WHATSAPP', '5511987654321');   // DDI + DDD + número, só dígitos
define('LOJA_ENDERECO', 'Rua das Flores, 123 — Centro, São Paulo – SP');
define('LOJA_EMAIL', 'contato@patafeliz.com.br');

// ---- Caminho base da instalação (vazio se estiver na raiz do domínio) ----
define('BASE_URL', '');

date_default_timezone_set('America/Sao_Paulo');

/**
 * Conexão PDO reaproveitada em toda a aplicação.
 */
function bd(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . BD_HOST . ';dbname=' . BD_NOME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, BD_USUARIO, BD_SENHA, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '-03:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Não foi possível conectar ao banco de dados.');
        }
    }

    return $pdo;
}
