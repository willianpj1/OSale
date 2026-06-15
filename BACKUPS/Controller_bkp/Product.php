<?php

declare(strict_types=1);

namespace app\controller;

use DateTime;
use Doctrine\DBAL\ParameterType;
use Exception;
use App\database\DB;

final class Product extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-product'), [
                'titulo' => 'Lista de produtos',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id      = $args['id'] ?? null;
        $action  = ($id === null) ? 'c' : 'e';
        $product = [];

        if (!is_null($id)) {
            $qb = DB::select('*')->from('product');
            $product = $qb
                ->where('id = ' . $qb->createPositionalParameter($id, ParameterType::INTEGER))
                ->fetchAssociative();
        }

        return $this->getTwig()
            ->render($response, $this->setView('product'), [
                'titulo'  => 'Detalhes do produto',
                'id'      => $id,
                'action'  => $action,
                'product' => $product,
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
            'nome'              => $nome,
            'codigo_barra'      => $form['codigo_barra']      ?? null,
            'unidade'           => $form['unidade']           ?? null,
            'preco_compra'      => (float) ($form['preco_compra']     ?? 0),
            'total_imposto'     => (float) ($form['total_imposto']    ?? 0),
            'margem_lucro'      => (float) ($form['margem_lucro']     ?? 0),
            'custo_operacional' => (float) ($form['custo_operacional'] ?? 0),
            'preco_venda'       => (float) ($form['preco_venda']       ?? 0),
            'descricao'         => $form['descricao']         ?? null,
            'ativo'             => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
            'excluido'          => false,
        ];

        try {
            $conn = DB::connection();
            $conn->insert('product', $fieldsAndValues, [
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
            'nome'              => $nome,
            'codigo_barra'      => $form['codigo_barra']      ?? null,
            'unidade'           => $form['unidade']           ?? null,
            'preco_compra'      => (float) ($form['preco_compra']     ?? 0),
            'total_imposto'     => (float) ($form['total_imposto']    ?? 0),
            'margem_lucro'      => (float) ($form['margem_lucro']     ?? 0),
            'custo_operacional' => (float) ($form['custo_operacional'] ?? 0),
            'preco_venda'       => (float) ($form['preco_venda']       ?? 0),
            'descricao'         => $form['descricao']         ?? null,
            'ativo'             => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
            'atualizado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        ];

        try {
            $conn = DB::connection();
            $isUpdated = $conn->update('product', $fieldsAndValues, ['id' => $id, 'excluido' => false], [
                'ativo' => ParameterType::BOOLEAN,
                'id'    => ParameterType::INTEGER,
                'excluido' => ParameterType::BOOLEAN
            ]);

            if (!$isUpdated) {
                return $this->json($response, ['status' => false, 'msg' => 'Produto não encontrado', 'id' => 0], 403);
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
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do produto', 'id' => 0], 403);
        }

        try {
            $isDeleted = DB::connection()->update(
                'product',
                ['excluido' => true, 'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s')],
                ['id' => $id],
                ['excluido' => ParameterType::BOOLEAN, 'id' => ParameterType::INTEGER]
            );

            if (!$isDeleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Produto não encontrado', 'id' => $id], 403);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Excluído com sucesso!', 'id' => (int) $id]);
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

        $columns = [0 => 'id', 1 => 'nome', 2 => 'codigo_barra', 3 => 'unidade', 4 => 'preco_venda', 5 => 'ativo', 6 => 'criado_em', 7 => 'atualizado_em'];

        $posField   = (isset($form['order'][0]['column']) && isset($columns[(int) $form['order'][0]['column']])) ? (int) $form['order'][0]['column'] : 0;
        $orderType  = strtoupper($form['order'][0]['dir'] ?? 'DESC');
        $orderType  = in_array($orderType, ['ASC', 'DESC'], true) ? $orderType : 'DESC';
        $orderField = $columns[$posField];

        try {
            $totalRecords = (int) DB::select('COUNT(*)')->from('product')->where('excluido = false')->fetchOne();
            $query = DB::select('*')->from('product')->where('excluido = false');

            if (!is_null($term) && $term !== '') {
                $query->setParameter('term', '%' . $term . '%');
                $query->andWhere($query->expr()->or('CAST(id AS TEXT) ILIKE :term', 'nome ILIKE :term', 'codigo_barra ILIKE :term'));
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();
            $products = $query->orderBy($orderField, $orderType)->setFirstResult($start)->setMaxResults($length)->fetchAllAssociative();

            $rows = [];
            foreach ($products as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $value['nome'],
                    $value['codigo_barra'] ?? '',
                    $value['unidade']      ?? '',
                    number_format((float) ($value['preco_venda'] ?? 0), 2, ',', '.'),
                    ($value['ativo'] === true) ? 'Ativo' : 'Inativo',
                    (new DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    (new DateTime($value['atualizado_em']))->format('d/m/Y H:i:s'),
                    "<td><a class='btn btn-sm btn-warning' href='/produto/detalhes/{$value['id']}'><i class='fa-solid fa-pen-to-square'></i> Editar</a> <button class='btn btn-sm btn-danger' onclick='ShowModal({$value['id']});'><i class='fa-solid fa-trash'></i> Excluir</button></td>",
                ];
            }

            return $this->json($response, ['recordsTotal' => $totalRecords, 'recordsFiltered' => $filteredRecords, 'data' => $rows], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
}