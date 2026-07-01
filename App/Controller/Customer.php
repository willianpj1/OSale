<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use DateTime;
use Exception;

final class Customer extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-customer'), [
                'titulo' => 'Lista de clientes',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id        = $args['id'] ?? null;
        $action    = ($id === null) ? 'c' : 'e';
        $customer  = [];
        $contacts  = [];
        $addresses = [];

        if ($id !== null) {
            $connection = \App\Database\DB::connection();

            // 1. Busca os detalhes do cliente (Retorna array associativo ou false)
            $customer = $connection->fetchAssociative(
                'SELECT * FROM customers WHERE id = :id AND excluido = false',
                ['id' => $id]
            );

            // Se o cliente não existir, você pode tratar aqui (ex: zerar ou redirecionar)
            if ($customer === false) {
                $customer = [];
            }

            // 2. Busca os contatos vinculados
            $contacts = $connection->fetchAllAssociative(
                'SELECT * FROM contacts WHERE entidade = :entidade AND entidade_id = :id ORDER BY principal DESC, id ASC',
                ['entidade' => 'customer', 'id' => $id]
            );

            // 3. Busca os endereços vinculados
            $addresses = $connection->fetchAllAssociative(
                'SELECT * FROM addresses WHERE entidade = :entidade AND entidade_id = :id ORDER BY principal DESC, id ASC',
                ['entidade' => 'customer', 'id' => $id]
            );
        }

        return $this->getTwig()
            ->render($response, $this->setView('customer'), [
                'titulo'    => 'Detalhes do cliente',
                'id'        => $id,
                'action'    => $action,
                'customer'  => $customer,
                'contacts'  => $contacts,
                'addresses' => $addresses,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }


    public function insert($request, $response)
    {
        $form = $request->getParsedBody();
        $FieldsAndValues = [
            'nome' => $form['nome'],
            'tipo' => $form['tipo'] ?? 'fisica',
            'cpf_cnpj' => $form['cpf_cnpj'] ?? '',
            'rg_ie' => $form['rg_ie'] ?? '',
            'observacoes' => $form['observacoes'] ?? '',
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];
        try {
            $IsInserted = \App\Database\DB::connection()->insert('customers', $FieldsAndValues);
            if (!$IsInserted) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $IsInserted, 'id' => 0], 500);
            }
            $id = \App\Database\DB::connection()->lastInsertId('id');

            return $this->json($response, ['status' => true, 'msg' => 'Salvo com sucesso!', 'id' => $id], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID não informado', 'id' => 0], 403);
        }

        $nome = trim($form['nome'] ?? '');
        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo nome é obrigatório', 'id' => 0], 400);
        }

        try {
            $connection = \App\Database\DB::connection();

            // Dados que serão atualizados na tabela
            $data = [
                'nome'          => $nome,
                'tipo'          => $form['tipo']        ?? 'fisica',
                'cpf_cnpj'      => $form['cpf_cnpj']    ?? null,
                'rg_ie'         => $form['rg_ie']       ?? null,
                'observacoes'   => $form['observacoes'] ?? null,
                'ativo'         => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
                'atualizado_em' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];

            // Condições para a cláusula WHERE
            $criteria = [
                'id'       => $id,
                'excluido' => false, // Garante que não altera registros deletados logicamente
            ];

            // O Doctrine DBAL executa o UPDATE de forma totalmente segura
            $connection->update('customers', $data, $criteria);

            return $this->json($response, ['status' => true, 'msg' => 'Cliente atualizado com sucesso!', 'id' => (int) $id], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao atualizar: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }


    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do cliente', 'id' => 0], 403);
        }

        try {
            DB::execute(
                'UPDATE customers SET excluido = true, atualizado_em = :now WHERE id = :id',
                ['now' => (new DateTime())->format('Y-m-d H:i:s'), 'id' => $id]
            );

            return $this->json($response, ['status' => true, 'msg' => 'Cliente removido com sucesso!', 'id' => (int) $id]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao excluir: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function listingdata($request, $response)
    {
        $form   = $request->getParsedBody();
        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);

        $columns = [
            0 => 'id',
            1 => 'nome',
            2 => 'cpf_cnpj',
            3 => 'tipo',
            4 => 'ativo',
            5 => 'criado_em',
        ];

        $posField   = isset($columns[(int) ($form['order'][0]['column'] ?? 0)]) ? (int) $form['order'][0]['column'] : 0;
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? 'DESC'), ['ASC', 'DESC']) ? strtoupper($form['order'][0]['dir']) : 'DESC';
        $orderField = $columns[$posField];

        try {
            $where  = 'WHERE excluido = false';
            $params = [];

            if (!empty($term)) {
                $where           .= ' AND (nome ILIKE :term OR cpf_cnpj ILIKE :term)';
                $params['term']   = '%' . $term . '%';
            }

            $totalRecords    = (int) DB::queryOne('SELECT COUNT(*) as total FROM customers WHERE excluido = false')['total'];
            $filteredRecords = (int) DB::queryOne("SELECT COUNT(*) as total FROM customers {$where}", $params)['total'];

            $params['limit']  = $length;
            $params['offset'] = $start;

            $customers = DB::query(
                "SELECT * FROM customers {$where} ORDER BY {$orderField} {$orderType} LIMIT :limit OFFSET :offset",
                $params
            );

            $rows = [];
            foreach ($customers as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $value['nome'],
                    $value['cpf_cnpj'] ?? '',
                    $value['tipo'] === 'fisica' ? 'Pessoa Física' : 'Pessoa Jurídica',
                    $value['ativo'] ? 'Ativo' : 'Inativo',
                    (new DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/cliente/detalhes/{$value['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
                        <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$value['id']});'><i class='bi bi-trash'></i> Excluir</button>
                    </td>",
                ];
            }

            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    // ── Contacts ──────────────────────────────────────────────────────────────

    public function contactInsert($request, $response, $args)
    {
        $id   = $args['id'] ?? null;
        $form = $request->getParsedBody();

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID do cliente não informado', 'id' => 0], 403);
        }

        $contato = trim($form['contato'] ?? '');
        if ($contato === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo contato é obrigatório', 'id' => 0], 400);
        }

        try {
            if (filter_var($form['principal'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                DB::execute(
                    'UPDATE contacts SET principal = false WHERE entidade = :entidade AND entidade_id = :id',
                    ['entidade' => 'customer', 'id' => $id]
                );
            }

            DB::execute(
                'INSERT INTO contacts (entidade, entidade_id, tipo, nome, contato, principal, criado_em)
                 VALUES (:entidade, :entidade_id, :tipo, :nome, :contato, :principal, :criado_em)',
                [
                    'entidade'    => 'customer',
                    'entidade_id' => $id,
                    'tipo'        => $form['tipo']      ?? 'telefone',
                    'nome'        => $form['nome']      ?? null,
                    'contato'     => $contato,
                    'principal'   => filter_var($form['principal'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'criado_em'   => (new DateTime())->format('Y-m-d H:i:s'),
                ]
            );

            $contactId = (int) DB::lastInsertId('contacts_id_seq');

            return $this->json($response, ['status' => true, 'msg' => 'Contato adicionado!', 'id' => $contactId], 201);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function contactDelete($request, $response, $args)
    {
        $contactId = $args['contactId'] ?? null;

        if (!$contactId) {
            return $this->json($response, ['status' => false, 'msg' => 'ID do contato não informado', 'id' => 0], 403);
        }

        try {
            DB::execute('DELETE FROM contacts WHERE id = :id AND entidade = :entidade', [
                'id'       => $contactId,
                'entidade' => 'customer',
            ]);

            return $this->json($response, ['status' => true, 'msg' => 'Contato removido!', 'id' => (int) $contactId]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ── Addresses ─────────────────────────────────────────────────────────────

    public function addressInsert($request, $response, $args)
    {
        $id   = $args['id'] ?? null;
        $form = $request->getParsedBody();

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID do cliente não informado', 'id' => 0], 403);
        }

        $logradouro = trim($form['logradouro'] ?? '');
        if ($logradouro === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo logradouro é obrigatório', 'id' => 0], 400);
        }

        try {
            $connection = \App\Database\DB::connection();           

            // 2. Mapeia os dados para a inserção limpa
            $data = [
                'entidade'    => 'customer',
                'entidade_id' => $id,
                'nome'        => $form['nome']        ?? null,
                'cep'         => $form['cep']         ?? null,
                'logradouro'  => $logradouro,
                'numero'      => $form['numero']      ?? null,
                'complemento' => $form['complemento'] ?? null,
                'bairro'      => $form['bairro']      ?? null,
                'cidade'      => $form['cidade']      ?? null,
                'estado'      => $form['estado']      ?? null,
                'principal'   => filter_var($form['principal'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];

            // 3. Insere o registro na tabela de forma segura
            $connection->insert('addresses', $data);

            // 4. Captura o ID gerado diretamente da instância da conexão ativa
            $addressId = (int) $connection->lastInsertId('addresses_id_seq');

            return $this->json($response, ['status' => true, 'msg' => 'Endereço adicionado!', 'id' => $addressId], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }


    public function addressDelete($request, $response, $args)
    {
        $addressId = $args['addressId'] ?? null;

        if (!$addressId) {
            return $this->json($response, ['status' => false, 'msg' => 'ID do endereço não informado', 'id' => 0], 403);
        }

        try {
            DB::execute('DELETE FROM addresses WHERE id = :id AND entidade = :entidade', [
                'id'       => $addressId,
                'entidade' => 'customer',
            ]);

            return $this->json($response, ['status' => true, 'msg' => 'Endereço removido!', 'id' => (int) $addressId]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
}
