<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


use Firebase\JWT\JWT;

final class Login extends Base
{
    private const JWT_EXPIRY  = 28800;
    private const COOKIE_NAME = 'auth_token';

    public function login($request, $response)
    {
        return $this->getTwig()->render($response, $this->setView('login'), [
            'titulo' => 'Login',
        ])->withHeader('Content-Type', 'text/html')->withStatus(200);
    }
    public function googleOneTap($request, $response)
    {
        $form       = $request->getParsedBody();
        $credential = $form['credential'] ?? '';

        if ($credential === '') {
            return $response->withHeader('Location', '/login?erro=credencial_ausente')->withStatus(302);
        }

        try {
            $payload = $this->verifyGoogleIdToken($credential);

            $user = $this->findOrCreateGoogleUser(
                $payload['sub'],
                $payload['email']       ?? '',
                $payload['given_name']  ?? ($payload['name'] ?? 'Usuário'),
                $payload['family_name'] ?? ''
            );

            if (!(bool) $user['ativo']) {
                return $response->withHeader('Location', '/login?erro=usuario_inativo')->withStatus(302);
            }

            $this->buildCookie($user);
            $this->buildSession($user);

            return $response->withHeader('Location', '/home')->withStatus(302);
        } catch (\Throwable $e) {
            error_log('[Login::googleOneTap] ERRO: ' . $e->getMessage());
            return $response->withHeader('Location', '/login?erro=falha_google')->withStatus(302);
        }
    }
    public function preRegister(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        # Coleta defensiva: todo input externo é tratado como não confiável
        $form = (array) $request->getParsedBody();
        $nome      = trim((string) ($form['nome']      ?? ''));
        $sobrenome = trim((string) ($form['sobrenome'] ?? ''));
        $rg        = trim((string) ($form['rg']        ?? ''));
        $senha     = (string)      ($form['senha']     ?? '');
        $email     = trim((string) ($form['email']     ?? ''));
        $telefone  = trim((string) ($form['telefone']  ?? ''));
        $cpf       = (string)      ($form['cpf']       ?? '');

        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o nome.', 'id' => 0], 422);
        }
        if (strlen($senha) < 3) {
            return $this->json($response, ['status' => false, 'msg' => 'A senha deve ter ao menos 3 caracteres.', 'id' => 0], 422);
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->json($response, ['status' => false, 'msg' => 'E-mail inválido.', 'id' => 0], 422);
        }
        if ($telefone === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o telefone.', 'id' => 0], 422);
        }
        # Hash caro de CPU calculado UMA vez e FORA da transação
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $conn = \app\database\DB::connection();
        try {
            # transactional(): commit no sucesso, rollBack automático em qualquer
            # exceção. O retorno do closure é o id gerado.
            $idUsuario = $conn->transactional(
                function (DBALConnection $conn) use (
                    $nome,
                    $sobrenome,
                    $cpf,
                    $rg,
                    $senhaHash,
                    $email,
                    $telefone
                ): int {
                    # Pré-check amigável de duplicidade. A constraint UNIQUE(cpf)
                    # do banco é a fonte de verdade final (ver catch abaixo).
                    $duplicado = $conn->fetchOne(
                        'SELECT 1 FROM users WHERE cpf = ? LIMIT 1',
                        [$cpf]
                    );
                    if ($duplicado !== false) {
                        throw new \DomainException('CPF já cadastrado.');
                    }
                    # INSERT ... RETURNING id: insere o usuário E recupera o ID
                    # gerado em UMA ÚNICA query. Forma mais rápida e correta no PG.
                    $idUsuario = (int) $conn->fetchOne(
                        'INSERT INTO users (nome, sobrenome, cpf, rg, senha)
                         VALUES (?, ?, ?, ?, ?)
                         RETURNING id',
                        [$nome, $sobrenome, $cpf, $rg, $senhaHash]
                    );
                    # Os DOIS contatos em UM ÚNICO INSERT em lote (1 round-trip)
                    $conn->executeStatement(
                        'INSERT INTO contact (id_usuario, tipo, contato)
                         VALUES (?, ?, ?), (?, ?, ?)',
                        [
                            $idUsuario,
                            'EMAIL',
                            $email,
                            $idUsuario,
                            'TELEFONE',
                            $telefone,
                        ]
                    );
                    return $idUsuario;
                }
            );
        } catch (\DomainException $e) {
            # Regra de negócio violada (CPF duplicado) 409 Conflict
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage(), 'id' => 0], 409);
        } catch (UniqueConstraintViolationException $e) {
            # Rede de segurança contra a race condition entre pré-check e insert
            error_log('[preRegister][UNIQUE] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Usuário já cadastrado.', 'id' => 0], 409);
        } catch (\Throwable $e) {
            # Qualquer outra falha: o rollback já ocorreu. Loga e responde genérico.
            error_log('[preRegister][GERAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível concluir o cadastro. Tente novamente.', 'id' => 0], 500);
        }
        # Happy path: último bloco, com estado garantido como válido
        return $this->json($response, [
            'status' => true,
            'msg'    => 'Usuário cadastrado com sucesso!',
            'id'     => $idUsuario,
        ], 201);
    }
    public function authenticate($request, $response)
    {
        $json = function (bool $status, string $message, int $httpCode) use ($response) {
            $response->getBody()->write(json_encode([
                'status'  => $status,
                'message' => $message,
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($httpCode);
        };

        try {
            $form  = $request->getParsedBody();
            $login = trim($form['login'] ?? '');
            $senha = $form['senha'] ?? '';

            if ($login === '' || $senha === '') {
                return $json(false, 'Preencha todos os campos.', 400);
            }

            $conn = \App\Database\DB::connection();

            $user = \App\Database\DB::select('*')
                ->from('users')
                ->where(
                    ' cpf_cnpj = ' . $conn->quote($login)
                )
                ->fetchAssociative();

            if (!$user || !password_verify($senha, $user['senha'])) {
                return $json(false, 'Login ou senha incorretos.', 401);
            }

            if (!(bool) $user['ativo']) {
                return $json(false, 'Usuário inativo. Contate o administrador.', 403);
            }

            // Erros comuns costumam acontecer nos dois métodos abaixo:
            $this->buildCookie($user);
            $this->buildSession($user);

            return $json(true, 'Autenticado com sucesso.', 200);
        } catch (\Throwable $e) {
            // Captura o erro exato e devolve no JSON estruturado para o SweetAlert mostrar
            return $json(false, 'Erro Interno: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine(), 500);
        }
    }
    public function logout($request, $response)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();

        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    private function findOrCreateGoogleUser(string $googleId, string $email, string $nome, string $sobrenome): array
    {
        $conn = \App\Database\DB::connection();

        $user = \App\Database\DB::select('*')->from('users')
            ->where('google_id = ' . $conn->quote($googleId))
            ->fetchAssociative();

        if ($user) return $user;

        if ($email !== '') {
            $user = \App\Database\DB::select('*')->from('users')
                ->where('email = ' . $conn->quote($email))
                ->fetchAssociative();

            if ($user) {
                $conn->update('users', ['google_id' => $googleId], ['id' => $user['id']]);
                return $user;
            }
        }

        $conn->insert('users', [
            'nome'          => $nome,
            'sobrenome'     => $sobrenome,
            'email'         => $email,
            'google_id'     => $googleId,
            'senha'         => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
            'cpf'           => '',
            'rg'            => '',
            'ativo'         => 1,
            'administrador' => 0,
        ]);

        return \App\Database\DB::select('*')->from('users')
            ->where('google_id = ' . $conn->quote($googleId))
            ->fetchAssociative();
    }
    private function verifyGoogleIdToken(string $idToken): array
    {
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
        $res = @file_get_contents($url);

        if (!$res) {
            throw new \RuntimeException('Falha ao comunicar com a API do Google');
        }

        $payload = json_decode($res, true);

        if (($payload['aud'] ?? '') !== $_ENV['GOOGLE_CLIENT_ID']) {
            throw new \RuntimeException('Token inválido: audience não confere');
        }

        return $payload;
    }
    private function buildCookie(array $user): void
    {
        $now     = time();
        $payload = [
            'iss' => $_ENV['APP_URL'],
            'sub' => (int) $user['id'],
            'iat' => $now,
            'exp' => $now + self::JWT_EXPIRY,
        ];

        $token = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');

        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => $now + self::JWT_EXPIRY,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => ($_ENV['APP_ENV'] ?? 'production') === 'production',
        ]);
    }
    private function buildSession(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        //session_regenerate_id(true);

        $_SESSION['user'] = [
            'logado' => true,
            'id'     => (int) $user['id'],
            'nome'   => $user['nome'] ?? '',
        ];
    }
}
