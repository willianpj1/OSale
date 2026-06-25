<?php

# Ativa a checagem estrita de tipos em todo este arquivo
declare(strict_types=1);

# Namespace da camada de banco de dados (mapeia para a pasta app/database via PSR-4)
namespace App\Database;

# Importa a conexão de alto nível do Doctrine DBAL e dá um apelido para evitar conflito de nome
use Doctrine\DBAL\Connection as DBALConnection;
# Importa a fábrica oficial do Doctrine que cria conexões a partir de um array de parâmetros
use Doctrine\DBAL\DriverManager;
# Importa a exceção do DBAL para tipar a captura de falhas de conexão
use Doctrine\DBAL\Exception as DBALException;

# Singleton de conexão: garante UMA única conexão por processo (essencial no worker mode)
final class Connection
{
    # Guarda a única instância da conexão; fica nula até ser criada pela primeira vez
    private static ?DBALConnection $instance = null;

    # Ponto único de acesso à conexão DBAL em todo o sistema
    public static function get(): DBALConnection
    {
        # Se a conexão já foi criada, devolve a mesma sem reabrir (reuso = performance)
        if (self::$instance !== null) {
            # Retorna a instância já existente
            return self::$instance;
        }

        # Lê e valida o host do banco — falha imediatamente se estiver ausente
        $host = self::requiredEnv('DB_HOST');
        # Lê e valida o nome do banco — sem ele a conexão não faz sentido
        $dbname = self::requiredEnv('DB_NAME');
        # Lê e valida o usuário do banco — obrigatório para autenticar
        $user = self::requiredEnv('DB_USER');
        # Lê e valida a senha — conexão sem senha é proibida por política de segurança
        $password = self::requiredEnv('DB_PASSWORD');

        # Porta TCP do banco, com fallback seguro para a porta padrão do PostgreSQL
        $port = (int) ($_ENV['DB_PORT'] ?? 5432);

        # Modo TLS: 'prefer' criptografa quando o servidor suporta e cai para texto puro quando não
        # Em produção com banco externo, defina DB_SSLMODE=verify-full no .env para exigir TLS validado
        $sslmode = (string) ($_ENV['DB_SSLMODE'] ?? 'prefer');

        # Monta o array de parâmetros da conexão com valores endurecidos
        $params = [
            # Driver PDO específico do PostgreSQL
            'driver' => 'pdo_pgsql',
            # Endereço do servidor de banco
            'host' => $host,
            # Porta TCP do servidor
            'port' => $port,
            # Banco de dados alvo da conexão
            'dbname' => $dbname,
            # Usuário usado na autenticação
            'user' => $user,
            # Senha usada na autenticação
            'password' => $password,
            # Codificação de caracteres da conexão
            'charset' => 'UTF8',
            # Política de criptografia TLS aplicada ao canal
            'sslmode' => $sslmode,
            # Identifica a aplicação no pg_stat_activity para auditoria e diagnóstico
            'application_name' => (string) ($_ENV['DB_APP_NAME'] ?? 'jaiminho3'),
        ];

        # Se a versão do servidor for informada, evita uma consulta extra de detecção de plataforma
        if (!empty($_ENV['DB_SERVER_VERSION'])) {
            # Informa a versão para o DBAL não precisar perguntar ao banco no primeiro acesso
            $params['serverVersion'] = (string) $_ENV['DB_SERVER_VERSION'];
        }

        # Tenta abrir a conexão; qualquer falha vira um erro claro e sem vazar a senha
        try {
            # Cria a conexão DBAL a partir dos parâmetros validados
            self::$instance = DriverManager::getConnection($params);
        } catch (DBALException $e) {
            # Relança com mensagem genérica — nunca expõe credenciais no log de erro
            throw new \RuntimeException('Falha ao estabelecer a conexão com o banco de dados.', 0, $e);
        }

        # Devolve a conexão recém-criada
        return self::$instance;
    }

    # Lê uma variável de ambiente obrigatória e falha na hora se estiver ausente ou vazia (Fail Fast)
    private static function requiredEnv(string $key): string
    {
        # Captura o valor do ambiente, ou null quando a chave não existe
        $value = $_ENV[$key] ?? null;

        # Guard clause: rejeita valor que não seja string ou que esteja em branco
        if (!is_string($value) || trim($value) === '') {
            # Lança o erro citando apenas o NOME da chave faltante, jamais o valor
            throw new \RuntimeException("Variável de ambiente obrigatória ausente ou vazia: {$key}");
        }

        # Retorna a credencial já validada
        return $value;
    }

    # Construtor privado impede 'new Connection()' — o uso é exclusivo via Connection::get()
    private function __construct() {}
}
