<?php

declare(strict_types=1);

namespace App\Controller;

final class Register extends Base
{
    public function register($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('register'), [
                'titulo' => 'Cadastro',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function store($request, $response)
    {
        $form = $request->getParsedBody();

        $nome      = trim($form['nome']);
        $sobrenome = trim($form['sobrenome']);
        $email     = strtolower(trim($form['email']));
        $cpf       = preg_replace('/\D/', '', $form['cpf']);
        $rg        = preg_replace('/\D/', '', $form['rg']);
        $senha     = $form['senha'];

        try {
            $existeEmail = \App\Database\DB::select('id')
                ->from('users')
                ->where('email = ' . \App\Database\DB::connection()->quote($email))
                ->fetchOne();

            if ($existeEmail) {
                return $this->json($response, [
                    'status'  => false,
                    'message' => 'Este e-mail já está cadastrado.',
                ], 409);
            }

            $existeCpf = \App\Database\DB::select('id')
                ->from('users')
                ->where('cpf = ' . \App\Database\DB::connection()->quote($cpf))
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

        try {
            \App\Database\DB::connection()->insert('users', [
                'nome'      => $nome,
                'sobrenome' => $sobrenome,
                'email'     => $email,
                'cpf'       => $cpf,
                'rg'        => $rg,
                'senha'     => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]),
                'google_id' => null,
            ]);

            return $this->json($response, [
                'status'  => true,
                'message' => 'Conta criada com sucesso!',
            ], 201);
        } catch (\Throwable $e) {
            return $this->json($response, [
                'status'  => false,
                'message' => 'Restrição: ' . $e->getMessage(),
            ], 500);
        }
    }
}