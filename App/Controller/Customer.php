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

        // Validação estrita do ID seguindo o exemplo do professor
        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do cliente', 'id' => 0], 403);
        }

        try {
            // Captura a conexão do Doctrine DBAL
            $db = \App\Database\DB::connection();

            // Valores que serão atualizados no banco
            $fieldsAndValues = [
                'excluido'      => true,
                'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s')
            ];

            // Condição WHERE para aplicar a alteração apenas no ID correto
            $whereCondition = ['id' => $id];

            // Executa o UPDATE de forma segura através do Doctrine
            $IsUpdated = $db->update('customers', $fieldsAndValues, $whereCondition);

            if (!$IsUpdated) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: O cliente não pôde ser atualizado.', 'id' => $id], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Cliente removido com sucesso!', 'id' => (int) $id]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function listingdata($request, $response)
    {
        $form = $request->getParsedBody();

        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);

        # Whitelist de colunas — proteção contra SQL injection no orderBy
        $columns = [
            0 => 'id',
            1 => 'nome',
            2 => 'cpf_cnpj',
            3 => 'tipo',
            4 => 'ativo',
            5 => 'criado_em',
        ];

        $posField = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']]))
            ? (int) $form['order'][0]['column']
            : 0;

        # Validação da direção evita SQL injection no ORDER BY
        $orderType  = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderType  = in_array($orderType, ['ASC', 'DESC'], true) ? $orderType : 'DESC';
        $orderField = $columns[$posField];

        try {
            # Total geral DataTables: recordsTotal (adicionado o filtro excluido = false da sua regra original)
            $totalRecords = (int) \App\Database\DB::select('COUNT(*)')
                ->from('customers')
                ->where('excluido = false')
                ->fetchOne();

            # Query principal montando a estrutura fluída do Doctrine baseada no exemplo do professor
            $query = \App\Database\DB::select("
            id,
            nome,
            cpf_cnpj,
            tipo,
            ativo,
            to_char(criado_em, 'DD/MM/YYYY HH24:MI:SS') AS criado_em
        ")
                ->from('customers')
                ->where('excluido = false');

            # Se houver termo de busca, aplica os filtros de OR de forma encadeada
            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');

                $query->andWhere(
                    $query->expr()->orX(
                        'CAST(id AS TEXT) ILIKE :term',
                        'nome ILIKE :term',
                        'cpf_cnpj ILIKE :term',
                        "TO_CHAR(criado_em, 'DD/MM/YYYY HH24:MI:SS') ILIKE :term"
                    )
                );
            }

            # Total com filtro aplicado — clona o query e troca o SELECT por COUNT
            $filteredRecords = (int) (clone $query)
                ->select('COUNT(*)')
                ->fetchOne();

            # Resultados paginados e ordenados usando os métodos do Doctrine
            $customers = $query
                ->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->fetchAllAssociative();

            # Formatação para o DataTables mantendo seus campos originais (nome, tipo, ativo)
            $rows = [];
            foreach ($customers as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $value['nome'],
                    $value['cpf_cnpj'] ?? '',
                    $value['tipo'] === 'fisica' ? 'Pessoa Física' : 'Pessoa Jurídica',
                    ($value['ativo'] === true || $value['ativo'] == 1) ? 'Ativo' : 'Inativo',
                    $value['criado_em'],
                    "<td>
                    <a class='btn btn-sm btn-warning' href='/cliente/detalhes/" . $value['id'] . "'> <i class='bi bi-pencil-square'></i> Editar</a>
                    <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal(" . $value['id'] . ");'> <i class='bi bi-trash'></i> Excluir</button>
                </td>",
                ];
            }

            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Restrição: ' . $e->getMessage(),
                'id'     => 0,
            ], 500);
        }
    }

    // ── Contacts ──────────────────────────────────────────────────────────────

    public function contactInsert($request, $response, $args)
    {
        $id   = $args['id'] ?? null;
        $form = $request->getParsedBody();

        // Validação estrita do ID 
        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'ID do cliente não informado', 'id' => 0], 403);
        }

        $contato = trim($form['contato'] ?? '');
        if ($contato === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo contato é obrigatório', 'id' => 0], 400);
        }

        try {
            // Captura a conexão do Doctrine 
            $db = \App\Database\DB::connection();

            // Converte o valor recebido para um booleano real
            $isPrincipal = filter_var($form['principal'] ?? false, FILTER_VALIDATE_BOOLEAN);

            // Se o novo contato for o principal, desmarca os outros usando o padrão $db->update()
            if ($isPrincipal === true) {
                $db->update(
                    'contacts',
                    ['principal' => false],
                    ['entidade' => 'customer', 'entidade_id' => $id]
                );
            }

            // Mapeia os campos e valores para a inserção do novo contato
            $fieldsAndValues = [
                'entidade'    => 'customer',
                'entidade_id' => $id,
                'tipo'        => $form['tipo'] ?? 'telefone',
                'nome'        => $form['nome'] ?? null,
                'contato'     => $contato,
                'principal'   => $isPrincipal,
                'criado_em'   => (new DateTime())->format('Y-m-d H:i:s'),
            ];

            // Insere o registro utilizando o método nativo ensinado em aula
            $IsInserted = $db->insert('contacts', $fieldsAndValues);

            if (!$IsInserted) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: O contato não pôde ser inserido.', 'id' => 0], 500);
            }

            // Captura o último ID inserido passando o nome da sequence para o PostgreSQL
            $contactId = (int) $db->lastInsertId('contacts_id_seq');

            return $this->json($response, ['status' => true, 'msg' => 'Contato adicionado!', 'id' => $contactId], 201);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function contactDelete($request, $response, $args)
    {
        $contactId = $args['contactId'] ?? null;

        // Validação estrita do ID conforme o padrão do professor
        if (is_null($contactId) || $contactId === '') {
            return $this->json($response, ['status' => false, 'msg' => 'ID do contato não informado', 'id' => 0], 403);
        }

        try {
            // Captura a conexão do Doctrine no padrão estabelecido
            $db = \App\Database\DB::connection();

            // Define os critérios do WHERE para garantir que exclua o ID correto e da entidade certa
            $whereCondition = [
                'id'       => $contactId,
                'entidade' => 'customer'
            ];

            // Executa o DELETE físico no banco de dados através do Doctrine
            $IsDeleted = $db->delete('contacts', $whereCondition);

            if (!$IsDeleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: O contato não pôde ser removido.', 'id' => $contactId], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Contato removido!', 'id' => (int) $contactId]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
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
                'principal'   => isset($form['principal']) ? $form['principal'] : 'false',
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

    public function addressListingData($request, $response, $args)
    {
        $id = $args['id'] ?? null; // ID do cliente vindo da rota

        // Validação estrita do ID conforme o padrão do professor
        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'ID do cliente não informado'], 403);
        }

        try {
            // Captura a conexão do Doctrine no padrão do professor
            $db = \App\Database\DB::connection();

            // Busca trazendo apenas os dados necessários, ordenando pelo principal primeiro
            $addresses = $db->executeQuery(
                "SELECT 
                id,
                nome AS label,
                logradouro,
                numero,
                bairro,
                cidade,
                estado,
                principal
             FROM addresses 
             WHERE entidade_id = :cliente_id 
               AND entidade = :entidade 
               --AND excluido = false 
             ORDER BY principal DESC, id DESC",
                ['cliente_id' => $id, 'entidade' => 'customer']
            )->fetchAllAssociative();

            // Formata os dados limpando valores nulos para strings vazias e tratando booleano
            $formattedData = [];
            foreach ($addresses as $item) {
                $formattedData[] = [
                    'id'         => (int) $item['id'],
                    'label'      => $item['label'] ?? 'Geral / Padrão',
                    'logradouro' => $item['logradouro'],
                    'numero'     => $item['numero'] ?? 'S/N',
                    'bairro'     => $item['bairro'] ?? '',
                    'cidade'     => $item['cidade'],
                    'estado'     => $item['estado'],
                    'principal'  => (bool) filter_var($item['principal'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }

            // Retorna APENAS o status e o array de dados brutos
            return $this->json($response, [
                'status' => true,
                'data'   => $formattedData
            ], 200);
        } catch (Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Restrição: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addressDelete($request, $response, $args)
    {
        $form = $request->getParsedBody();
        $id = $form['id_endereco'] ?? null;
        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do endereço', 'id' => 0], 403);
        }
        try {
            $IsDeleted = \App\Database\DB::connection()->delete('addresses', ['id' => $id]);
            if (!$IsDeleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $IsDeleted, 'id' => $id], 403);
            }
            return $this->json($response, ['status' => true, 'msg' => 'Removido com sucesso!', 'id' => $id]);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
}
