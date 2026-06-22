<?php

declare(strict_types=1);

namespace App\Controller;
use App\Database\DB;

final class Users extends Base
{

    // ── Tela de listagem ──────────────────────────────────────────────

      public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-Users'), [
                'titulo' => 'Lista de usuarios',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function listingdata($request, $response)
    {
        $users = $this->db->fetchAllAssociative(
            'SELECT id, nome, cpf_cnpj, tipo, ativo FROM users ORDER BY nome ASC'
        );

        $response->getBody()->write(json_encode(['data' => $users]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // ── Tela de detalhes (criação ou edição) ────────────────────────────

    public function details($request, $response, array $args)
    {
        $id = $args['id'] ?? null;

        $userData = [
            'tipo' => 'fisica',
            'cpf_cnpj' => '',
            'rg_ie' => '',
            'nome' => '',
            'observacoes' => '',
            'ativo' => true,
        ];
        $enderecos = [];
        $contatoTelefone = [];
        $contatoEmail = [];

        if ($id) {
            $user = $this->db->fetchAssociative('SELECT * FROM users WHERE id = ?', [$id]);

            if ($user === false) {
                return $response->withStatus(404);
            }
            $userData = $user;

            $enderecos = $this->db->fetchAllAssociative(
                'SELECT * FROM addresses WHERE user_id = ? ORDER BY id ASC',
                [$id]
            );

            $contatoTelefone = $this->db->fetchAssociative(
                "SELECT * FROM contacts WHERE user_id = ? AND tipo IN ('telefone', 'whatsapp')",
                [$id]
            ) ?: [];

            $contatoEmail = $this->db->fetchAssociative(
                "SELECT * FROM contacts WHERE user_id = ? AND tipo = 'email'",
                [$id]
            ) ?: [];
        }

        return $this->view->render($response, 'pages/users-details.html', [
            'action' => $id ? 'update' : 'insert',
            'id' => $id,
            'users' => $userData,
            'enderecos' => $enderecos,
            'contato_telefone' => $contatoTelefone,
            'contato_email' => $contatoEmail,
        ]);
    }

    // ── Insert completo (usuário + endereços + contatos, tudo de uma vez) ──

    public function insert($request, $response)
    {
        $body = json_decode((string) $request->getBody(), true) ?? [];

        $usuario = $body['usuario'] ?? [];
        $enderecos = $body['enderecos'] ?? [];
        $contatos = $body['contatos'] ?? [];

        $errors = $this->validateUser($usuario);
        if (!empty($errors)) {
            return $this->jsonError($response, 'Dados inválidos', 422, $errors);
        }

        $this->db->beginTransaction();

        try {
            $this->db->insert('users', [
                'tipo' => $usuario['tipo'],
                'cpf_cnpj' => $usuario['cpf_cnpj'],
                'rg_ie' => $usuario['rg_ie'] ?: null,
                'nome' => $usuario['nome'],
                'observacoes' => $usuario['observacoes'] ?: null,
                'ativo' => true,
                'criado_em' => date('Y-m-d H:i:s'),
            ]);

            $userId = (int) $this->db->lastInsertId();

            foreach ($enderecos as $index => $end) {
                if (empty($end['logradouro']) && empty($end['cep'])) {
                    continue;
                }

                $this->db->insert('addresses', [
                    'user_id' => $userId,
                    'nome' => $end['nome'] ?: null,
                    'cep' => $end['cep'] ?: null,
                    'logradouro' => $end['logradouro'] ?: null,
                    'numero' => $end['numero'] ?: null,
                    'complemento' => $end['complemento'] ?: null,
                    'bairro' => $end['bairro'] ?: null,
                    'cidade' => $end['cidade'] ?: null,
                    'estado' => $end['estado'] ?: null,
                    'principal' => $index === 0,
                    'criado_em' => date('Y-m-d H:i:s'),
                ]);
            }

            if (!empty($contatos['telefone'])) {
                $this->db->insert('contacts', [
                    'user_id' => $userId,
                    'tipo' => 'telefone',
                    'nome' => null,
                    'contato' => $contatos['telefone'],
                    'principal' => true,
                    'criado_em' => date('Y-m-d H:i:s'),
                ]);
            }

            if (!empty($contatos['email'])) {
                if (!filter_var($contatos['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('E-mail de contato inválido');
                }
                $this->db->insert('contacts', [
                    'user_id' => $userId,
                    'tipo' => 'email',
                    'nome' => null,
                    'contato' => $contatos['email'],
                    'principal' => true,
                    'criado_em' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->db->commit();
            return $this->jsonSuccess($response, ['id' => $userId], 201);
        } catch (\InvalidArgumentException $e) {
            $this->db->rollBack();
            return $this->jsonError($response, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return $this->jsonError($response, 'Erro ao salvar cadastro', 500, ['detail' => $e->getMessage()]);
        }
    }

    // ── Update completo ──────────────────────────────────────────────────

    public function update($request, $response)
    {
        $body = json_decode((string) $request->getBody(), true) ?? [];

        $userId = $body['id'] ?? null;
        $usuario = $body['usuario'] ?? [];
        $enderecos = $body['enderecos'] ?? [];
        $contatos = $body['contatos'] ?? [];

        if (!$userId) {
            return $this->jsonError($response, 'ID não informado', 422);
        }

        $errors = $this->validateUser($usuario);
        if (!empty($errors)) {
            return $this->jsonError($response, 'Dados inválidos', 422, $errors);
        }

        $this->db->beginTransaction();

        try {
            $this->db->update('users', [
                'tipo' => $usuario['tipo'],
                'cpf_cnpj' => $usuario['cpf_cnpj'],
                'rg_ie' => $usuario['rg_ie'] ?: null,
                'nome' => $usuario['nome'],
                'observacoes' => $usuario['observacoes'] ?: null,
                'ativo' => !empty($usuario['ativo']),
            ], ['id' => $userId]);

            // apaga e reinsere a lista inteira de endereços (estratégia simples,
            // válida porque nada mais referencia addresses.id por FK)
            $this->db->delete('addresses', ['user_id' => $userId]);

            foreach ($enderecos as $index => $end) {
                if (empty($end['logradouro']) && empty($end['cep'])) {
                    continue;
                }

                $this->db->insert('addresses', [
                    'user_id' => $userId,
                    'nome' => $end['nome'] ?: null,
                    'cep' => $end['cep'] ?: null,
                    'logradouro' => $end['logradouro'] ?: null,
                    'numero' => $end['numero'] ?: null,
                    'complemento' => $end['complemento'] ?: null,
                    'bairro' => $end['bairro'] ?: null,
                    'cidade' => $end['cidade'] ?: null,
                    'estado' => $end['estado'] ?: null,
                    'principal' => $index === 0,
                    'criado_em' => date('Y-m-d H:i:s'),
                ]);
            }

            // contatos seguem upsert manual: busca se já existe, atualiza ou insere
            foreach (['telefone' => $contatos['telefone'] ?? null, 'email' => $contatos['email'] ?? null] as $tipo => $valor) {
                if (empty($valor)) continue;

                if ($tipo === 'email' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('E-mail de contato inválido');
                }

                $tiposBusca = $tipo === 'telefone' ? "'telefone', 'whatsapp'" : "'email'";

                $existingContact = $this->db->fetchAssociative(
                    "SELECT id FROM contacts WHERE user_id = ? AND tipo IN ($tiposBusca)",
                    [$userId]
                );

                if ($existingContact) {
                    $this->db->update('contacts', ['contato' => $valor], ['id' => $existingContact['id']]);
                } else {
                    $this->db->insert('contacts', [
                        'user_id' => $userId,
                        'tipo' => $tipo,
                        'nome' => null,
                        'contato' => $valor,
                        'principal' => true,
                        'criado_em' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $this->db->commit();
            return $this->jsonSuccess($response, ['id' => $userId]);
        } catch (\InvalidArgumentException $e) {
            $this->db->rollBack();
            return $this->jsonError($response, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return $this->jsonError($response, 'Erro ao atualizar cadastro', 500, ['detail' => $e->getMessage()]);
        }
    }

    // ── Delete completo ──────────────────────────────────────────────────

    public function delete($request, $response)
    {
        $body = (array) $request->getParsedBody();
        $id = $body['id'] ?? null;

        if (!$id) {
            return $this->jsonError($response, 'ID não informado', 422);
        }

        try {
            $this->db->delete('users', ['id' => $id]);
            return $this->jsonSuccess($response, []);
        } catch (\Throwable $e) {
            return $this->jsonError($response, 'Erro ao excluir usuário', 500, ['detail' => $e->getMessage()]);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────
    private function validateUser(array $body): array
    {
        $errors = [];

        if (empty($body['nome'])) {
            $errors['nome'] = 'Nome é obrigatório';
        }
        if (empty($body['cpf_cnpj'])) {
            $errors['cpf_cnpj'] = 'CPF/CNPJ é obrigatório';
        }
        if (!in_array($body['tipo'] ?? '', ['fisica', 'juridica'], true)) {
            $errors['tipo'] = 'Tipo inválido';
        }

        return $errors;
    }

    private function jsonSuccess( $response, array $data, int $status = 200)
    {
        $response->getBody()->write(json_encode(array_merge(['success' => true], $data)));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function jsonError( $response, string $message, int $status, array $errors = [])
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
