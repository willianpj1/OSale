<?php

declare(strict_types=1);

namespace app\controller;

use DateTime;
use Doctrine\DBAL\ParameterType;
use Exception;
use App\database\DB;

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

        if (!is_null($id)) {
            $qb = DB::select('*')->from('supplier');
            $supplier = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, ParameterType::INTEGER))
                ->fetchAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('supplier'), [
                'titulo'   => 'Detalhes do fornecedor',
                'id'       => $id,
                'action'   => $action,
                'supplier' => $supplier,
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
        $fieldsAndValues = [
            'nome'     => $nome,
            'cnpj'     => $form['cnpj']     ?? null,
            'email'    => $form['email']    ?? null,
            'telefone' => $form['telefone'] ?? null,
            'ativo'    => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
            'excluido' => false,
        ];
        try {
            $conn = DB::connection();
            $conn->insert('supplier', $fieldsAndValues, [
                'ativo'    => ParameterType::BOOLEAN,
                'excluido' => ParameterType::BOOLEAN
            ]);
            $id = $conn->lastInsertId();
            return $this->json($response, ['status' => true, 'msg' => 'Salvo com sucesso!', 'id' => (int) $id], 201);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id)) {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe o ID do registro', 'id' => 0], 403);
        }

        $nome = trim($form['nome'] ?? '');
        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo nome é obrigatório', 'id' => 0], 400);
        }

        $fieldsAndValues = [
            'nome'          => $nome,
            'cnpj'          => $form['cnpj']     ?? null,
            'email'         => $form['email']    ?? null,
            'telefone'      => $form['telefone'] ?? null,
            'ativo'         => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
            'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
        ];

        try {
            $conn = DB::connection();
            $isUpdated = $conn->update('supplier', $fieldsAndValues, ['id' => $id, 'excluido' => false], [
                'ativo' => ParameterType::BOOLEAN,
                'id'    => ParameterType::INTEGER,
                'excluido' => ParameterType::BOOLEAN
            ]);

            if (!$isUpdated) {
                return $this->json($response, ['status' => false, 'msg' => 'Fornecedor não encontrado', 'id' => 0], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Atualizado com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do fornecedor', 'id' => 0], 403);
        }

        try {
            // Agora usando Soft Delete para padronizar
            $isDeleted = DB::connection()->update(
                'supplier',
                ['excluido' => true, 'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s')],
                ['id' => $id],
                ['excluido' => ParameterType::BOOLEAN, 'id' => ParameterType::INTEGER]
            );

            if (!$isDeleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Fornecedor não encontrado', 'id' => $id], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Excluído com sucesso!', 'id' => (int) $id]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function listingdata($request, $response)
    {
        $form = $request->getParsedBody();
        $term = $form['search']['value'] ?? null;
        $start = (int)($form['start'] ?? 0);
        $length = (int)($form['length'] ?? 10);

        $columns = [0 => 'id', 1 => 'nome', 2 => 'cnpj', 3 => 'email', 4 => 'telefone', 5 => 'criado_em', 6 => 'atualizado_em'];
        $posField = (isset($form['order'][0]['column']) && isset($columns[(int)$form['order'][0]['column']])) ? (int)$form['order'][0]['column'] : 0;
        $orderType = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderField = $columns[$posField];

        try {
            $totalRecords = (int) DB::select('COUNT(*)')->from('supplier')->where('excluido = false')->fetchOne();
            $query = DB::select('*')->from('supplier')->where('excluido = false');

            if ($term) {
                $query->setParameter('term', '%' . $term . '%');
                $query->andWhere($query->expr()->or('nome ILIKE :term', 'cnpj ILIKE :term'));
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();
            $suppliers = $query->orderBy($orderField, $orderType)->setFirstResult($start)->setMaxResults($length)->fetchAllAssociative();

            $rows = [];
            foreach ($suppliers as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $value['nome'],
                    $value['cnpj'] ?? '',
                    $value['email'] ?? '',
                    $value['telefone'] ?? '',
                    (new DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    (new DateTime($value['atualizado_em']))->format('d/m/Y H:i:s'),
                    "<td><a class='btn btn-sm btn-warning' href='/fornecedor/detalhes/{$value['id']}'><i class='fa-solid fa-pen-to-square'></i></a></td>",
                ];
            }

            return $this->json($response, ['recordsTotal' => $totalRecords, 'recordsFiltered' => $filteredRecords, 'data' => $rows], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }
}
