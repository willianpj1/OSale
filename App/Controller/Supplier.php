<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use DateTime;
use Doctrine\DBAL\ParameterType;
use Exception;

final class Supplier extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-supplier'), [
                'titulo' => 'Lista de fornecedores',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
    public function details($request, $response, $args)
    {
        $id        = $args['id'] ?? null;
        $action    = ($id === null) ? 'c' : 'e';
        $supplier  = [];
        $contacts  = [];
        $addresses = [];

        if ($id !== null) {
            $connection = \App\Database\DB::connection();

            // 1. Busca os detalhes do fornecedor (Retorna array associativo ou false)
            $supplier = $connection->fetchAssociative(
                'SELECT * FROM suppliers WHERE id = :id AND excluido = false',
                ['id' => $id]
            );

            // Se o fornecedor não existir, você pode tratar aqui (ex: zerar ou redirecionar)
            if ($supplier === false) {
                $supplier = [];
            }

            // 2. Busca os contatos vinculados
            $contacts = $connection->fetchAllAssociative(
                'SELECT * FROM contacts WHERE entidade = :entidade AND entidade_id = :id ORDER BY principal DESC, id ASC',
                ['entidade' => 'supplier', 'id' => $id]
            );

            // 3. Busca os endereços vinculados
            $addresses = $connection->fetchAllAssociative(
                'SELECT * FROM addresses WHERE entidade = :entidade AND entidade_id = :id ORDER BY principal DESC, id ASC',
                ['entidade' => 'supplier', 'id' => $id]
            );
        }

        return $this->getTwig()
            ->render($response, $this->setView('supplier'), [
                'titulo'    => 'Detalhes do fornecedor',
                'id'        => $id,
                'action'    => $action,
                'supplier'  => $supplier,
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
            'nome'          => $form['nome'],
            'cnpj'          => $form['cnpj'] ?? '',
            'observacoes'   => $form['observacoes'] ?? '',
            'ativo'         => true,
        ];
        try {
            $IsInserted = \App\Database\DB::connection()->insert('suppliers', $FieldsAndValues);
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
                'cnpj'          => $form['cnpj']    ?? null,
                'observacoes'   => $form['observacoes'] ?? null,
                'ativo'         => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
                'atualizado_em' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];

            // Condições para a cláusula WHERE
            $criteria = [
                'id'       => $id,
                'excluido' => false, // Garante que não altera registros deletados logicamente
            ];

            // Tipos explícitos evitam o erro de binding de boolean (SQLSTATE[22P02]) no PostgreSQL
            $types = [
                'ativo'    => \Doctrine\DBAL\ParameterType::BOOLEAN,
                'excluido' => \Doctrine\DBAL\ParameterType::BOOLEAN,
            ];
            // O Doctrine DBAL executa o UPDATE de forma totalmente segura
            $connection->update('suppliers', $data, $criteria, $types);

            return $this->json($response, ['status' => true, 'msg' => 'Fornecedor atualizado com sucesso!', 'id' => (int) $id], 200);
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
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do fornecedor', 'id' => 0], 403);
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
            $IsUpdated = $db->update('suppliers', $fieldsAndValues, $whereCondition);

            if (!$IsUpdated) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: O fornecedor não pôde ser atualizado.', 'id' => $id], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Fornecedor removido com sucesso!', 'id' => (int) $id]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
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
            2 => 'cnpj',
            3 => 'ativo',
            4 => 'criado_em',
        ];

        $orderField = $columns[(int) ($form['order'][0]['column'] ?? 0)] ?? 'id';
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? ''), ['ASC', 'DESC']) ? strtoupper($form['order'][0]['dir']) : 'DESC';

        try {
            // Captura a conexão padrão do Doctrine no modelo do seu professor
            $db = \App\Database\DB::connection();

            // 1. Total de registros sem filtro
            $totalRecords = (int) $db->createQueryBuilder()
                ->select('COUNT(*)')
                ->from('suppliers')
                ->where('excluido = false')
                ->executeQuery()
                ->fetchOne();

            // 2. Cria a Query principal de busca
            $query = $db->createQueryBuilder()
                ->select('*')
                ->from('suppliers')
                ->where('excluido = false');

            // Se houver termo de busca, aplica a filtragem com ILIKE
            if (!empty($term)) {
                $query->andWhere(
                    $query->expr()->or(
                        'nome ILIKE :term',
                        'cnpj ILIKE :term'
                    )
                )->setParameter('term', '%' . $term . '%');
            }

            // 3. Clona a query principal para calcular o total de registros filtrados
            $filteredRecords = (int) (clone $query)
                ->select('COUNT(*)')
                ->executeQuery()
                ->fetchOne();

            // 4. Executa a listagem final aplicando ordenação e paginação
            $suppliers = $query->orderBy($orderField, $orderType)
                ->setFirstResult($start)
                ->setMaxResults($length)
                ->executeQuery()
                ->fetchAllAssociative();

            // Formata os dados para o padrão esperado pelo DataTables
            $rows = array_map(fn($v) => [
                $v['id'],
                $v['nome'],
                $v['cnpj'] ?? '',
                $v['ativo'] ? 'Ativo' : 'Inativo',
                (new \DateTime($v['criado_em']))->format('d/m/Y H:i:s'),
                "<td>
                <a class='btn btn-sm btn-warning' href='/fornecedor/detalhes/{$v['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
                <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$v['id']});'><i class='bi bi-trash'></i> Excluir</button>
            </td>",
            ], $suppliers);

            // Retorno JSON de sucesso estruturado para o DataTables
            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Restrição: ' . $e->getMessage()
            ], 500);
        }
    }
    // ── Contacts ──────────────────────────────────────────────────────────────

    public function contactInsert($request, $response, $args)
    {
        $id   = $args['id'] ?? null;
        $form = $request->getParsedBody();

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'ID do fornecedor não informado', 'id' => 0], 403);
        }

        $contato = trim($form['c-contato'] ?? '');
        if ($contato === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo contato é obrigatório', 'id' => 0], 400);
        }

        try {
            $db = \App\Database\DB::connection();
            $isPrincipal = (bool) filter_var($form['c-principal'] ?? false, FILTER_VALIDATE_BOOLEAN);

            // Se for o contato principal, desmarca os outros usando o update nativo do Doctrine
            if ($isPrincipal) {
                $db->update(
                    'contacts',
                    ['principal' => 0], // Colunas para atualizar
                    ['entidade' => 'supplier', 'entidade_id' => $id] // Cláusula WHERE
                );
            }

            // Insere o novo contato usando o insert nativo do Doctrine
            $db->insert('contacts', [
                'entidade'    => 'supplier',
                'entidade_id' => (int) $id,
                'tipo'        => $form['c-tipo'] ?? 'telefone',
                'nome'        => !empty(trim($form['c-nome'] ?? '')) ? trim($form['c-nome']) : null,
                'contato'     => $contato,
                'principal'   => $isPrincipal ? 1 : 0,
                'criado_em'   => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            $contactId = (int) $db->lastInsertId();

            return $this->json($response, [
                'status' => true,
                'msg'    => 'Contato adicionado!',
                'id'     => $contactId
            ], 201);
        } catch (Exception $e) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Restrição: ' . $e->getMessage(),
                'id'     => 0
            ], 500);
        }
    }
    public function contactListingData($request, $response, $args)
    {
        $id = $args['id'] ?? null; // ID do fornecedor vindo da rota

        // Validação estrita do ID conforme o padrão do professor
        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'ID do fornecedor não informado'], 403);
        }

        try {
            // Captura a conexão do Doctrine no padrão do professor
            $db = \App\Database\DB::connection();

            // Busca trazendo apenas os dados necessários, ordenando pelo principal primeiro
            $contacts = $db->executeQuery(
                "SELECT 
                id,
                nome AS label,
                tipo,
                contato,
                principal
             FROM contacts 
             WHERE entidade_id = :fornecedor_id 
               AND entidade = :entidade 
               --AND excluido = false 
             ORDER BY principal DESC, id DESC",
                ['fornecedor_id' => $id, 'entidade' => 'supplier']
            )->fetchAllAssociative();

            // Formata os dados limpando valores nulos para strings vazias e tratando booleano
            $formattedData = [];
            foreach ($contacts as $item) {
                $formattedData[] = [
                    'id'        => (int) $item['id'],
                    'label'     => $item['label'] ?? 'Geral / Padrão',
                    'tipo'      => $item['tipo'] ?? '',
                    'contato'   => $item['contato'] ?? '',
                    'principal' => (bool) filter_var($item['principal'] ?? false, FILTER_VALIDATE_BOOLEAN),
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
                'entidade' => 'supplier'
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
            return $this->json($response, ['status' => false, 'msg' => 'ID do fornecedor não informado', 'id' => 0], 403);
        }

        $logradouro = trim($form['a-logradouro'] ?? '');
        if ($logradouro === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo logradouro é obrigatório', 'id' => 0], 400);
        }

        try {
            $connection = \App\Database\DB::connection();

            // 2. Mapeia os dados para a inserção limpa
            $data = [
                'entidade'    => 'supplier',
                'entidade_id' => $id,
                'nome'        => $form['nome']        ?? null,
                'cep'         => $form['a-cep']         ?? null,
                'logradouro'  => $logradouro,
                'numero'      => $form['a-numero']      ?? null,
                'complemento' => $form['a-complemento'] ?? null,
                'bairro'      => $form['a-bairro']      ?? null,
                'cidade'      => $form['a-cidade']      ?? null,
                'estado'      => $form['a-estado']      ?? null,
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
        $id = $args['id'] ?? null; // ID do fornecedor vindo da rota

        // Validação estrita do ID conforme o padrão do professor
        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'ID do fornecedor não informado'], 403);
        }

        try {
            // Captura a conexão do Doctrine no padrão do professor
            $db = \App\Database\DB::connection();

            // Busca trazendo apenas os dados necessários, ordenando pelo principal primeiro
            $addresses = $db->executeQuery(
                "SELECT 
                id,
                nome,
                logradouro,
                numero,
                bairro,
                cidade,
                estado,
                principal
             FROM addresses 
             WHERE entidade_id = :fornecedor_id 
               AND entidade = :entidade 
               --AND excluido = false 
             ORDER BY principal DESC, id DESC",
                ['fornecedor_id' => $id, 'entidade' => 'supplier']
            )->fetchAllAssociative();

            // Formata os dados limpando valores nulos para strings vazias e tratando booleano
            $formattedData = [];
            foreach ($addresses as $item) {
                $formattedData[] = [
                    'id'         => (int) $item['id'],
                    'label'      => $item['nome'],
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
