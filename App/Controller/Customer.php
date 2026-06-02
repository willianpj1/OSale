<?php

declare(strict_types=1);

namespace app\controller;

use DateTime;
use Doctrine\DBAL\ParameterType;
use Exception;
use App\database\DB;

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
        $id = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $customer = [];

        if (!is_null($id)) {
            $qb = DB::select('*')->from('customer');
            $customer = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, ParameterType::INTEGER))
                ->fetchAssociative();
        }
        return $this->getTwig()
            ->render($response, $this->setView('customer'), [
                'titulo'   => 'Detalhes do cliente',
                'id'       => $id,
                'action'   => $action,
                'customer' => $customer
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
    public function insert($request, $response)
    {
        $form = $request->getParsedBody();
        $fieldsAndValues = [
            'nome'     => $form['nomeExibicao'] ?? '',
            'cpf'      => $form['numeroDocumento'] ?? null,
            'rg'       => $form['registroSecundario'] ?? null,
            'ativo'    => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
            'excluido' => false
        ];
        try {
            $conn = DB::connection();
            $conn->insert('customer', $fieldsAndValues, [
                'ativo'    => ParameterType::BOOLEAN,
                'excluido' => ParameterType::BOOLEAN,
                'nome'     => ParameterType::STRING,
                'cpf'      => ParameterType::STRING,
                'rg'       => ParameterType::STRING,
            ]);
            $id = $conn->lastInsertId();
            return $this->json($response, [
                'status' => true, 
                'msg'    => 'Salvo com sucesso!', 
                'id'     => (int)$id
            ], 201);
        } catch (Exception $e) {
            return $this->json($response, [
                'status' => false, 
                'msg'    => 'Erro ao inserir: ' . $e->getMessage(), 
                'id'     => 0
            ], 500);
        }
    }
    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id = $form['id'] ?? null;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID não informado', 'id' => 0], 403);
        }
        $fieldsAndValues = [
            'nome'          => $form['nomeExibicao'] ?? '',
            'cpf'           => $form['numeroDocumento'] ?? null,
            'rg'            => $form['registroSecundario'] ?? null,
            'ativo'         => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
            'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s')
        ];
        try {
            $conn = DB::connection();
            $conn->update('customer', $fieldsAndValues, ['id' => $id], [
                'ativo' => ParameterType::BOOLEAN,
                'id'    => ParameterType::INTEGER
            ]);
            return $this->json($response, ['status' => true, 'msg' => 'Alterado com sucesso!', 'id' => (int)$id], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao atualizar: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id = $form['id'] ?? null;
        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID não informado', 'id' => 0], 403);
        }
        try {
            $isDeleted = DB::connection()->update(
                'customer',
                ['excluido' => true, 'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s')],
                ['id' => $id],
                ['excluido' => ParameterType::BOOLEAN, 'id' => ParameterType::INTEGER]
            );
            return $this->json($response, ['status' => true, 'msg' => 'Removido com sucesso!', 'id' => (int)$id]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao excluir: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function listingdata($request, $response)
    {
        $form = $request->getParsedBody();
        $term = $form['search']['value'] ?? null;
        $start = (int)($form['start'] ?? 0);
        $length = (int)($form['length'] ?? 10);
        
        $columns = [0 => 'id', 1 => 'nome', 2 => 'cpf', 3 => 'rg', 4 => 'ativo', 5 => 'criado_em'];
        $posField = (isset($form['order'][0]['column'])) ? (int)$form['order'][0]['column'] : 0;
        $orderType = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderField = $columns[$posField] ?? 'id';

        try {
            $totalRecords = (int) DB::select('COUNT(*)')->from('customer')->where('excluido = false')->fetchOne();
            $query = DB::select('*')->from('customer')->where('excluido = false');

            if ($term) {
                $query->setParameter('term', '%' . $term . '%');
                $query->andWhere($query->expr()->or('nome ILIKE :term', 'cpf ILIKE :term'));
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();
            $customers = $query->orderBy($orderField, $orderType)->setFirstResult($start)->setMaxResults($length)->fetchAllAssociative();

            $rows = [];
            foreach ($customers as $key => $value) {
                $rows[$key] = [
                    $value['id'], 
                    $value['nome'], 
                    $value['cpf'] ?? '',
                    $value['rg'] ?? '',
                    ($value['ativo']) ? 'Ativo' : 'Inativo',
                    (new DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    "<td><button class='btn btn-sm btn-danger' onclick='ShowModal({$value['id']});'>Excluir</button></td>"
                ];
            }
            return $this->json($response, ['recordsTotal' => $totalRecords, 'recordsFiltered' => $filteredRecords, 'data' => $rows], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }
}