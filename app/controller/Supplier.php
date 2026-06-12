<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use DateTime;
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
        $id       = $args['id'] ?? null;
        $action   = ($id === null) ? 'c' : 'e';
        $supplier = [];
        $contacts = [];

        if ($id !== null) {
            $supplier = DB::queryOne(
                'SELECT * FROM suppliers WHERE id = :id AND excluido = false',
                ['id' => $id]
            );
            $contacts = DB::query(
                'SELECT * FROM contacts WHERE entidade = :entidade AND entidade_id = :id ORDER BY principal DESC, id ASC',
                ['entidade' => 'supplier', 'id' => $id]
            );
        }

        return $this->getTwig()
            ->render($response, $this->setView('supplier'), [
                'titulo'   => 'Detalhes do fornecedor',
                'id'       => $id,
                'action'   => $action,
                'supplier' => $supplier,
                'contacts' => $contacts,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();
        $nome = trim($form['nome'] ?? '');

        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo nome é obrigatório', 'id' => 0], 400);
        }

        try {
            $now = (new DateTime())->format('Y-m-d H:i:s');

            DB::execute(
                'INSERT INTO suppliers
                    (nome, cnpj, cep, logradouro, numero, complemento, bairro, cidade, estado, observacoes, ativo, excluido, criado_em, atualizado_em)
                 VALUES
                    (:nome, :cnpj, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado, :observacoes, :ativo, false, :criado_em, :atualizado_em)',
                [
                    'nome'          => $nome,
                    'cnpj'          => $form['cnpj']         ?? null,
                    'cep'           => $form['cep']          ?? null,
                    'logradouro'    => $form['logradouro']   ?? null,
                    'numero'        => $form['numero']       ?? null,
                    'complemento'   => $form['complemento']  ?? null,
                    'bairro'        => $form['bairro']       ?? null,
                    'cidade'        => $form['cidade']       ?? null,
                    'estado'        => $form['estado']       ?? null,
                    'observacoes'   => $form['observacoes']  ?? null,
                    'ativo'         => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
                    'criado_em'     => $now,
                    'atualizado_em' => $now,
                ]
            );

            $id = (int) DB::lastInsertId('suppliers_id_seq');

            return $this->json($response, ['status' => true, 'msg' => 'Fornecedor salvo com sucesso!', 'id' => $id], 201);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao inserir: ' . $e->getMessage(), 'id' => 0], 500);
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
            DB::execute(
                'UPDATE suppliers SET
                    nome = :nome, cnpj = :cnpj, cep = :cep, logradouro = :logradouro,
                    numero = :numero, complemento = :complemento, bairro = :bairro,
                    cidade = :cidade, estado = :estado, observacoes = :observacoes,
                    ativo = :ativo, atualizado_em = :atualizado_em
                 WHERE id = :id AND excluido = false',
                [
                    'nome'          => $nome,
                    'cnpj'          => $form['cnpj']         ?? null,
                    'cep'           => $form['cep']          ?? null,
                    'logradouro'    => $form['logradouro']   ?? null,
                    'numero'        => $form['numero']       ?? null,
                    'complemento'   => $form['complemento']  ?? null,
                    'bairro'        => $form['bairro']       ?? null,
                    'cidade'        => $form['cidade']       ?? null,
                    'estado'        => $form['estado']       ?? null,
                    'observacoes'   => $form['observacoes']  ?? null,
                    'ativo'         => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
                    'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
                    'id'            => $id,
                ]
            );

            return $this->json($response, ['status' => true, 'msg' => 'Fornecedor atualizado com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao atualizar: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do fornecedor', 'id' => 0], 403);
        }

        try {
            DB::execute(
                'UPDATE suppliers SET excluido = true, atualizado_em = :now WHERE id = :id',
                ['now' => (new DateTime())->format('Y-m-d H:i:s'), 'id' => $id]
            );

            return $this->json($response, ['status' => true, 'msg' => 'Fornecedor removido com sucesso!', 'id' => (int) $id]);
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
            2 => 'cnpj',
            3 => 'cidade',
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
                $where           .= ' AND (nome ILIKE :term OR cnpj ILIKE :term OR cidade ILIKE :term)';
                $params['term']   = '%' . $term . '%';
            }

            $totalRecords    = (int) DB::queryOne('SELECT COUNT(*) as total FROM suppliers WHERE excluido = false')['total'];
            $filteredRecords = (int) DB::queryOne("SELECT COUNT(*) as total FROM suppliers {$where}", $params)['total'];

            $params['limit']  = $length;
            $params['offset'] = $start;

            $suppliers = DB::query(
                "SELECT * FROM suppliers {$where} ORDER BY {$orderField} {$orderType} LIMIT :limit OFFSET :offset",
                $params
            );

            $rows = [];
            foreach ($suppliers as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $value['nome'],
                    $value['cnpj']   ?? '',
                    $value['cidade'] ?? '',
                    $value['ativo']  ? 'Ativo' : 'Inativo',
                    (new DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/fornecedor/detalhes/{$value['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
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

    // ── Contacts ─────────────────────────────────────────────────────────────

    public function contactInsert($request, $response, $args)
    {
        $id   = $args['id'] ?? null;
        $form = $request->getParsedBody();

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID do fornecedor não informado', 'id' => 0], 403);
        }

        $contato = trim($form['contato'] ?? '');
        if ($contato === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo contato é obrigatório', 'id' => 0], 400);
        }

        try {
            if (filter_var($form['principal'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                DB::execute(
                    'UPDATE contacts SET principal = false WHERE entidade = :entidade AND entidade_id = :id',
                    ['entidade' => 'supplier', 'id' => $id]
                );
            }

            DB::execute(
                'INSERT INTO contacts (entidade, entidade_id, tipo, nome, contato, principal, criado_em)
                 VALUES (:entidade, :entidade_id, :tipo, :nome, :contato, :principal, :criado_em)',
                [
                    'entidade'    => 'supplier',
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
                'entidade' => 'supplier',
            ]);

            return $this->json($response, ['status' => true, 'msg' => 'Contato removido!', 'id' => (int) $contactId]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
}