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

        try {
            $conn = \App\Database\DB::connection();

            // Ajustado para funcionar com o nosso novo DB.php em PDO Puro
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
                ->where("cpf = " . $conn->quote($cpf))
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

            // Insere usando comando PDO nativo preparado (seguro contra SQL Injection)
            $sql = "INSERT INTO users (nome, sobrenome, email, cpf, rg, senha, google_id, ativo, administrador) 
                    VALUES (:nome, :sobrenome, :email, :cpf, :rg, :senha, :google_id, :ativo, :administrador)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'nome'          => $nome,
                'sobrenome'     => $sobrenome,
                'email'         => $email,
                'cpf'           => $cpf,
                'rg'            => $rg,
                'senha'         => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]),
                'google_id'     => null,
                'ativo'         => 1,
                'administrador' => 0,
            ]);

            return $this->json($response, [
                'status'  => true,
                'message' => 'Conta criada com sucesso!',
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