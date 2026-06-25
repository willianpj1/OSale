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
            $supplier = DB::select('*')
                ->from('suppliers')
                ->where('id = :id')
                ->andWhere('excluido = false')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();

            $contacts = DB::select('*')
                ->from('contacts')
                ->where('entidade = :entidade')
                ->andWhere('entidade_id = :id')
                ->setParameter('entidade', 'supplier')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->orderBy('principal', 'DESC')
                ->addOrderBy('id', 'ASC')
                ->fetchAllAssociative();

            $addresses = DB::select('*')
                ->from('addresses')
                ->where('entidade = :entidade')
                ->andWhere('entidade_id = :id')
                ->setParameter('entidade', 'supplier')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->orderBy('principal', 'DESC')
                ->addOrderBy('id', 'ASC')
                ->fetchAllAssociative();
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
        $nome = trim($form['nome'] ?? '');

        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo nome é obrigatório', 'id' => 0], 400);
        }

        try {
            DB::connection()->insert('suppliers', [
                'nome'          => $nome,
                'cnpj'          => $form['cnpj']        ?? null,
                'observacoes'   => $form['observacoes'] ?? null,
                'ativo'         => filter_var($form['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'excluido'      => false,
                'criado_em'     => $this->now(),
                'atualizado_em' => $this->now(),
            ], [
                'ativo'    => ParameterType::BOOLEAN,
                'excluido' => ParameterType::BOOLEAN,
            ]);

            $id = (int) DB::connection()->lastInsertId();

            return $this->json($response, ['status' => true, 'msg' => 'Fornecedor salvo com sucesso!', 'id' => $id], 201);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao inserir: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;
        $nome = trim($form['nome'] ?? '');

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID não informado', 'id' => 0], 403);
        }
        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo nome é obrigatório', 'id' => 0], 400);
        }

        try {
            DB::connection()->update('suppliers', [
                'nome'          => $nome,
                'cnpj'          => $form['cnpj']        ?? null,
                'observacoes'   => $form['observacoes'] ?? null,
                'ativo'         => filter_var($form['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'atualizado_em' => $this->now(),
            ], ['id' => $id], ['ativo' => ParameterType::BOOLEAN]);
            return $this->json($response, ['status' => true, 'msg' => 'Fornecedor atualizado com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            error_log('[Supplier::update] ' . $e->getMessage());
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
            DB::connection()->update('suppliers', [
                'excluido'      => true,
                'atualizado_em' => $this->now(),
            ], ['id' => $id], ['excluido' => ParameterType::BOOLEAN]);

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
            3 => 'ativo',
            4 => 'criado_em',
        ];

        $orderField = $columns[(int) ($form['order'][0]['column'] ?? 0)] ?? 'id';
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? ''), ['ASC', 'DESC']) ? strtoupper($form['order'][0]['dir']) : 'DESC';

        try {
            $totalRecords = (int) DB::select('COUNT(*)')
                ->from('suppliers')
                ->where('excluido = false')
                ->fetchOne();

            $query = DB::select('*')
                ->from('suppliers')
                ->where('excluido = false');

            if (!empty($term)) {
                $query->andWhere($query->expr()->or('nome ILIKE :term', 'cnpj ILIKE :term'))
                    ->setParameter('term', '%' . $term . '%');
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $suppliers = $query->orderBy($orderField, $orderType)
                ->setFirstResult($start)->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = array_map(fn($v) => [
                $v['id'],
                $v['nome'],
                $v['cnpj'] ?? '',
                $v['ativo'] ? 'Ativo' : 'Inativo',
                (new DateTime($v['criado_em']))->format('d/m/Y H:i:s'),
                "<td>
                    <a class='btn btn-sm btn-warning' href='/fornecedor/detalhes/{$v['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
                    <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$v['id']});'><i class='bi bi-trash'></i> Excluir</button>
                </td>",
            ], $suppliers);

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
        $id       = $args['id'] ?? null;
        $form     = $request->getParsedBody();
        $contato  = trim($form['contato'] ?? '');
        $principal = filter_var($form['principal'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID do fornecedor não informado', 'id' => 0], 403);
        }
        if ($contato === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo contato é obrigatório', 'id' => 0], 400);
        }

        try {
            if ($principal) {
                DB::connection()->update('contacts', ['principal' => false], [
                    'entidade'    => 'supplier',
                    'entidade_id' => $id,
                ], ['principal' => ParameterType::BOOLEAN]);
            }

            DB::connection()->insert('contacts', [
                'entidade'    => 'supplier',
                'entidade_id' => $id,
                'tipo'        => $form['tipo']  ?? 'telefone',
                'nome'        => $form['nome']  ?? null,
                'contato'     => $contato,
                'principal'   => $principal,
                'criado_em'   => $this->now(),
            ], ['principal' => ParameterType::BOOLEAN]);

            $contactId = (int) DB::connection()->lastInsertId();

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
            DB::connection()->delete('contacts', ['id' => $contactId, 'entidade' => 'supplier']);

            return $this->json($response, ['status' => true, 'msg' => 'Contato removido!', 'id' => (int) $contactId]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ── Addresses ────────────────────────────────────────────────────────────

    public function addressInsert($request, $response, $args)
    {
        $id         = $args['id'] ?? null;
        $form       = $request->getParsedBody();
        $logradouro = trim($form['logradouro'] ?? '');
        $principal  = filter_var($form['principal'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID do fornecedor não informado', 'id' => 0], 403);
        }
        if ($logradouro === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo logradouro é obrigatório', 'id' => 0], 400);
        }

        try {
            if ($principal) {
                DB::connection()->update('addresses', ['principal' => false], [
                    'entidade'    => 'supplier',
                    'entidade_id' => $id,
                ], ['principal' => ParameterType::BOOLEAN]);
            }

            DB::connection()->insert('addresses', [
                'entidade'    => 'supplier',
                'entidade_id' => $id,
                'nome'        => $form['nome']        ?? null,
                'cep'         => $form['cep']         ?? null,
                'logradouro'  => $logradouro,
                'numero'      => $form['numero']      ?? null,
                'complemento' => $form['complemento'] ?? null,
                'bairro'      => $form['bairro']      ?? null,
                'cidade'      => $form['cidade']      ?? null,
                'estado'      => $form['estado']      ?? null,
                'principal'   => $principal,
                'criado_em'   => $this->now(),
            ], ['principal' => ParameterType::BOOLEAN]);

            $addressId = (int) DB::connection()->lastInsertId();

            return $this->json($response, ['status' => true, 'msg' => 'Endereço adicionado!', 'id' => $addressId], 201);
        } catch (Exception $e) {
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
            DB::connection()->delete('addresses', ['id' => $addressId, 'entidade' => 'supplier']);

            return $this->json($response, ['status' => true, 'msg' => 'Endereço removido!', 'id' => (int) $addressId]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function now(): string
    {
        return (new DateTime())->format('Y-m-d H:i:s');
    }
}
