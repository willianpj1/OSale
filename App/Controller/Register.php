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
        $form      = $request->getParsedBody();
        $nome      = trim($form['nome']      ?? '');
        $sobrenome = trim($form['sobrenome'] ?? '');
        $email     = strtolower(trim($form['email'] ?? ''));
        $cpf       = preg_replace('/\D/', '', $form['cpf'] ?? '');
        $rg        = preg_replace('/\D/', '', $form['rg']  ?? '');
        $senha     = $form['senha'] ?? '';
        if (!$nome || !$sobrenome || !$email || !$cpf || !$rg || !$senha) {
            if (ob_get_length()) ob_clean();
            return $this->json($response, [
                'status'  => false,
                'message' => 'Preencha todos os campos.',
            ], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (ob_get_length()) ob_clean();
            return $this->json($response, [
                'status'  => false,
                'message' => 'Ensira um E-mail válido. Ex: usuario@gmail.com',
            ], 400);
        }
        try {
            $conn = \App\Database\DB::connection();
            $existeEmail = \App\Database\DB::select('id')
                ->from('users')
                ->where("email = " . $conn->quote($email))
                ->fetchOne();
            if ($existeEmail) {
                if (ob_get_length()) ob_clean();
                return $this->json($response, [
                    'status'  => false,
                    'message' => 'Este e-mail já está cadastrado.',
                ], 409);
            }
            // Ajustado para funcionar com o nosso novo DB.php em PDO Puro
            $existeCpf = \App\Database\DB::select('id')
                ->from('users')
                ->where("cpf_cnpj = " . $conn->quote($cpf))
                ->fetchOne();
            if ($existeCpf) {
                if (ob_get_length()) ob_clean();
                return $this->json($response, [
                    'status'  => false,
                    'message' => 'Este CPF já está cadastrado.',
                ], 409);
            }
        } catch (\Throwable $e) {
            if (ob_get_length()) ob_clean();
            return $this->json($response, [
                'status'  => false,
                'message' => 'Erro ao verificar dados: ' . $e->getMessage(),
            ], 500);
        }
        try {
            if (ob_get_length()) ob_clean();
            $IsInserted = \App\Database\DB::connection()->insert('users', [
                'nome'      => $nome,
                'sobrenome' => $sobrenome,
                'email'     => $email,
                'tipo'      => 'USER',
                'cpf_cnpj'  => $cpf,
                'rg_ie'     => $rg,
                'senha'     => password_hash($senha, PASSWORD_DEFAULT),
                'ativo'     => 'true',
                'excluido'  => 'false'
            ]);
            #Caso de erro ao inserir matamos o processo arqui.
            if (!$IsInserted) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $IsInserted, 'id' => 0]);
            }
            #Captura o ultimo ID cadastrado na tabela de usuario
            $id = \App\Database\DB::connection()->lastInsertId();
            return $this->json($response, [
                'status'  => true,
                'msg' => 'Conta criada com sucesso!',
                'id' => $id
            ], 201);
        } catch (\Throwable $e) {
            if (ob_get_length()) ob_clean();
            return $this->json($response, [
                'status'  => false,
                'message' => 'Erro ao cadastrar: ' . $e->getMessage(),
            ], 500);
        }
    }
}