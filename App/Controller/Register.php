<?php

declare(strict_types=1);

namespace app\controller;

final class Register extends Base
{
    // ─── Página ───────────────────────────────────────────────────────────────

    public function register($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('register'), [
                'titulo' => 'Cadastro',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // ─── Inserção ─────────────────────────────────────────────────────────────

    public function store($request, $response)
    {
        $form = $request->getParsedBody();

        // ── 1. Validação dos campos obrigatórios ──────────────────────────────
        /*$erros = $this->validar($form);
        if ($erros !== []) {
            return $this->json($response, [
                'status'  => false,
                'message' => implode(' ', $erros),
            ], 422);
        }*/

        $nome      = trim($form['nome']);
        $sobrenome = trim($form['sobrenome']);
        $email     = strtolower(trim($form['email']));
        $cpf       = preg_replace('/\D/', '', $form['cpf']);
        $rg        = preg_replace('/\D/', '', $form['rg']);
        $senha     = $form['senha'];
        
           

        // ── 2. Unicidade de e-mail e CPF ──────────────────────────────────────
        try {
            $existeEmail = \App\database\DB::select('id')
                ->from('users')
                ->where('email = ' . \App\database\DB::connection()->quote($email))
                ->fetchOne();

            if ($existeEmail) {
                return $this->json($response, [
                    'status'  => false,
                    'message' => 'Este e-mail já está cadastrado.',
                ], 409);
            }

            $existeCpf = \App\database\DB::select('id')
                ->from('users')
                ->where('cpf = ' . \App\database\DB::connection()->quote($cpf))
                ->fetchOne();

            if ($existeCpf) {
                return $this->json($response, [
                    'status'  => false,
                    'message' => 'Este CPF já está cadastrado.',
                ], 409);
            }
        } catch (\Throwable $e) {
            return $this->json($response, [
                'status'  => false,
                'message' => 'Erro ao verificar dados. Tente novamente.',
            ], 500);
        }

        // ── 3. Inserção ───────────────────────────────────────────────────────
        try {
            
            \App\database\DB::connection()->insert('users', [
                'nome'          => $nome,
                'sobrenome'     => $sobrenome,
                'email'         => $email,
                'cpf'           => $cpf,
                'rg'            => $rg,
                'senha'         => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]),
                'google_id'     => null,
                'salario'       => 0,
            ]);

            return $this->json($response, [
                'status'  => true,
                'message' => 'Conta criada com sucesso!',
            ], 201);
        } catch (\Throwable $e) {
            return $this->json($response, ['status' => false, 
            'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ─── Validação interna ────────────────────────────────────────────────────

    /*private function validar(array $form): array
    {
        $erros = [];

        if (empty(trim($form['nome'] ?? ''))) {
            $erros[] = 'Nome é obrigatório.';
        }

        if (empty(trim($form['sobrenome'] ?? ''))) {
            $erros[] = 'Sobrenome é obrigatório.';
        }

        $email = trim($form['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido.';
        }

        $cpf = preg_replace('/\D/', '', $form['cpf'] ?? '');
        if (!$this->validarCpf($cpf)) {
            $erros[] = 'CPF inválido.';
        }

        $rg = preg_replace('/\D/', '', $form['rg'] ?? '');
        if (strlen($rg) < 5) {
            $erros[] = 'RG inválido.';
        }

        $senha = $form['senha'] ?? '';
        if (strlen($senha) < 8) {
            $erros[] = 'A senha deve ter pelo menos 8 caracteres.';
        }

        $confirmar = $form['confirmarSenha'] ?? '';
        if ($senha !== $confirmar) {
            $erros[] = 'As senhas não coincidem.';
        }

        return $erros;
    }

    private function validarCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        $calc = function (int $limit) use ($cpf): int {
            $soma = 0;
            for ($i = 0; $i < $limit; $i++) {
                $soma += (int) $cpf[$i] * ($limit + 1 - $i);
            }
            $resto = ($soma * 10) % 11;
            return $resto > 9 ? 0 : $resto;
        };

        return $calc(9) === (int) $cpf[9] && $calc(10) === (int) $cpf[10];
    }*/
}
