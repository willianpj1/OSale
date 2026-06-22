<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use DateTime;
use Exception;

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

        if ($id !== null) {
            $product = DB::queryOne(
                'SELECT p.*, s.nome as supplier_nome 
                 FROM products p
                 LEFT JOIN suppliers s ON s.id = p.supplier_id
                 WHERE p.id = :id AND p.excluido = false',
                ['id' => $id]
            );
        }

        $suppliers = DB::query(
            'SELECT id, nome FROM suppliers WHERE excluido = false AND ativo = true ORDER BY nome ASC'
        );

        return $this->getTwig()
            ->render($response, $this->setView('product'), [
                'titulo'    => 'Detalhes do produto',
                'id'        => $id,
                'action'    => $action,
                'product'   => $product,
                'suppliers' => $suppliers,
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
                'INSERT INTO products
                    (supplier_id, nome, codigo_barra, unidade, descricao, preco_compra, margem_lucro, preco_venda, estoque_atual, estoque_minimo, ativo, excluido, criado_em, atualizado_em)
                 VALUES
                    (:supplier_id, :nome, :codigo_barra, :unidade, :descricao, :preco_compra, :margem_lucro, :preco_venda, :estoque_atual, :estoque_minimo, :ativo, false, :criado_em, :atualizado_em)',
                [
                    'supplier_id'    => !empty($form['supplier_id']) ? (int) $form['supplier_id'] : null,
                    'nome'           => $nome,
                    'codigo_barra'   => $form['codigo_barra']  ?? null,
                    'unidade'        => $form['unidade']       ?? 'un',
                    'descricao'      => $form['descricao']     ?? null,
                    'preco_compra'   => (float) ($form['preco_compra']   ?? 0),
                    'margem_lucro'   => (float) ($form['margem_lucro']   ?? 0),
                    'preco_venda'    => (float) ($form['preco_venda']    ?? 0),
                    'estoque_atual'  => (float) ($form['estoque_atual']  ?? 0),
                    'estoque_minimo' => (float) ($form['estoque_minimo'] ?? 0),
                    'ativo'          => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
                    'criado_em'      => $now,
                    'atualizado_em'  => $now,
                ]
            );

            $id = (int) DB::lastInsertId('products_id_seq');

            return $this->json($response, ['status' => true, 'msg' => 'Produto salvo com sucesso!', 'id' => $id], 201);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao inserir: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe o ID do registro', 'id' => 0], 403);
        }

        $nome = trim($form['nome'] ?? '');
        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo nome é obrigatório', 'id' => 0], 400);
        }

        try {
            DB::execute(
                'UPDATE products SET
                    supplier_id = :supplier_id, nome = :nome, codigo_barra = :codigo_barra,
                    unidade = :unidade, descricao = :descricao, preco_compra = :preco_compra,
                    margem_lucro = :margem_lucro, preco_venda = :preco_venda,
                    estoque_minimo = :estoque_minimo, ativo = :ativo, atualizado_em = :atualizado_em
                 WHERE id = :id AND excluido = false',
                [
                    'supplier_id'    => !empty($form['supplier_id']) ? (int) $form['supplier_id'] : null,
                    'nome'           => $nome,
                    'codigo_barra'   => $form['codigo_barra'] ?? null,
                    'unidade'        => $form['unidade']      ?? 'un',
                    'descricao'      => $form['descricao']    ?? null,
                    'preco_compra'   => (float) ($form['preco_compra']   ?? 0),
                    'margem_lucro'   => (float) ($form['margem_lucro']   ?? 0),
                    'preco_venda'    => (float) ($form['preco_venda']    ?? 0),
                    'estoque_minimo' => (float) ($form['estoque_minimo'] ?? 0),
                    'ativo'          => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
                    'atualizado_em'  => (new DateTime())->format('Y-m-d H:i:s'),
                    'id'             => $id,
                ]
            );

            return $this->json($response, ['status' => true, 'msg' => 'Produto atualizado com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao atualizar: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do produto', 'id' => 0], 403);
        }

        try {
            DB::execute(
                'UPDATE products SET excluido = true, atualizado_em = :now WHERE id = :id',
                ['now' => (new DateTime())->format('Y-m-d H:i:s'), 'id' => $id]
            );

            return $this->json($response, ['status' => true, 'msg' => 'Produto removido com sucesso!', 'id' => (int) $id]);
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
            0 => 'p.id',
            1 => 'p.nome',
            2 => 'p.preco_venda',
            3 => 'p.estoque_atual',
            4 => 'p.codigo_barra',
            6 => 'p.ativo',
            7 => 'p.criado_em',
        ];

        $posField   = isset($columns[(int) ($form['order'][0]['column'] ?? 0)]) ? (int) $form['order'][0]['column'] : 0;
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? 'DESC'), ['ASC', 'DESC']) ? strtoupper($form['order'][0]['dir']) : 'DESC';
        $orderField = $columns[$posField];

        try {
            $where  = 'WHERE p.excluido = false';
            $params = [];

            if (!empty($term)) {
                $where         .= ' AND (p.nome ILIKE :term OR p.codigo_barra ILIKE :term)';
                $params['term'] = '%' . $term . '%';
            }

            $totalRecords    = (int) DB::queryOne('SELECT COUNT(*) as total FROM products p WHERE p.excluido = false')['total'];
            $filteredRecords = (int) DB::queryOne("SELECT COUNT(*) as total FROM products p {$where}", $params)['total'];

            $params['limit']  = $length;
            $params['offset'] = $start;

            $products = DB::query(
                "SELECT p.*, s.nome as supplier_nome
                 FROM products p
                 LEFT JOIN suppliers s ON s.id = p.supplier_id
                 {$where} ORDER BY {$orderField} {$orderType} LIMIT :limit OFFSET :offset",
                $params
            );

            $rows = [];
            foreach ($products as $key => $value) {
                $estoqueClass = (float) $value['estoque_atual'] <= (float) $value['estoque_minimo']
                    ? 'text-danger fw-bold'
                    : '';

                $rows[$key] = [
                    $value['id'],
                    $value['nome'],
                    $value['codigo_barra']  ?? '',
                    $value['unidade']       ?? '',
                    'R$ ' . number_format((float) $value['preco_venda'], 2, ',', '.'),
                    "<span class='{$estoqueClass}'>" . number_format((float) $value['estoque_atual'], 3, ',', '.') . "</span>",
                    $value['supplier_nome'] ?? '—',
                    $value['ativo'] ? 'Ativo' : 'Inativo',
                    (new DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/produto/detalhes/{$value['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
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
}