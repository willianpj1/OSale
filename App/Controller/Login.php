<?php

declare(strict_types=1);

namespace App\Controller;

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
    public function preRegister($request, $response)
    {
        $json = function (bool $status, string $msg, int $httpCode) use ($response) {
            $response->getBody()->write(json_encode([
                'status' => $status,
                'msg'    => $msg,
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($httpCode);
        };
        $form      = $request->getParsedBody();
        $nome      = trim($form['nome']      ?? '');
        $sobrenome = trim($form['sobrenome'] ?? '');
        $cpf       = trim($form['cpf']       ?? '');
        $rg        = trim($form['rg']        ?? '');
        $senha     = $form['senha']          ?? '';
        $email     = trim($form['email']     ?? '');
        if ($nome === '' || $sobrenome === '' || $cpf === '' || $rg === '' || $senha === '' || $email === '') {
            return $json(false, 'Preencha todos os campos.', 400);
        }
        $conn = \App\Database\DB::connection();
        $existing = \App\Database\DB::select('id')->from('users')
            ->where(
                'email = ' . $conn->quote($email) .
                    ' OR cpf = ' . $conn->quote($cpf)
            )
            ->fetchAssociative();
        if ($existing) {
            return $json(false, 'E-mail ou CPF já cadastrado.', 409);
        }
        $conn->insert('users', [
            'nome'      => $nome,
            'sobrenome' => $sobrenome,
            'cpf_cnpj'  => $cpf,
            'rg_ie'     => $rg,
            'senha'     => password_hash($senha, PASSWORD_BCRYPT),
            'email'     => $email,
            'ativo'     => true,
            'tipo'      => 'USER',
        ]);
        return $json(true, 'Usuário cadastrado com sucesso!', 201);
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
            $loginEmail = strtolower($login);
            $loginCpf   = preg_replace('/\D/', '', $login);
            $where = 'email = ' . $conn->quote($loginEmail);
            if ($loginCpf !== '') {
                $where .= ' OR cpf_cnpj = ' . $conn->quote($loginCpf);
            }
            $user = \App\Database\DB::select('*')
                ->from('users')
                ->where($where)
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
            'nome'      => $nome,
            'sobrenome' => $sobrenome,
            'email'     => $email,
            'google_id' => $googleId,
            'senha'     => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
            'cpf_cnpj'  => null,
            'rg_ie'     => null,
            'ativo'     => true,
            'tipo'      => 'USER',
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
        $now        = time();
        $payload    = [
            'iss'   => $_ENV['APP_URL'],
            'sub'   => (int) $user['id'],
            'iat'   => $now,
            'exp'   => $now + self::JWT_EXPIRY,
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
