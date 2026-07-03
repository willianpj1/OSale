<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use DateTime;
use Doctrine\DBAL\ParameterType;
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
            $product = DB::select('p.*, s.nome as supplier_nome')
                ->from('products', 'p')
                ->leftJoin('p', 'suppliers', 's', 's.id = p.supplier_id')
                ->where('p.excluido = false')
                ->andWhere('p.id = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();
        }

        $suppliers = DB::select('id, nome')
            ->from('suppliers')
            ->where('excluido = false')
            ->andWhere('ativo = true')
            ->orderBy('nome', 'ASC')
            ->fetchAllAssociative();

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
            // estoque_atual NÃO é aceito do form de propósito — produto sempre
            // nasce com estoque 0. O único jeito de ganhar saldo é via ajuste
            // manual (StockMovement::ajustar) ou compra (fase futura).
            // A trigger trg_force_zero_stock_on_insert garante isso também a
            // nível de banco, mesmo que alguém tente burlar isso aqui.
            DB::connection()->insert('products', [
                'supplier_id'    => !empty($form['supplier_id']) ? (int) $form['supplier_id'] : null,
                'nome'           => $nome,
                'codigo_barra'   => $form['codigo_barra'] ?? null,
                'unidade'        => $form['unidade']      ?? 'un',
                'descricao'      => $form['descricao']    ?? null,
                'preco_compra'   => (float) ($form['preco_compra']   ?? 0),
                'margem_lucro'   => (float) ($form['margem_lucro']   ?? 0),
                'preco_venda'    => (float) ($form['preco_venda']    ?? 0),
                'estoque_minimo' => (float) ($form['estoque_minimo'] ?? 0),
                'ativo'          => filter_var($form['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'excluido'       => false,
                'criado_em'      => $this->now(),
                'atualizado_em'  => $this->now(),
            ], [
                'ativo'    => ParameterType::BOOLEAN,
                'excluido' => ParameterType::BOOLEAN,
            ]);

            $id = (int) DB::connection()->lastInsertId();

            return $this->json($response, ['status' => true, 'msg' => 'Produto salvo com sucesso!', 'id' => $id], 201);
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
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe o ID do registro', 'id' => 0], 403);
        }
        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo nome é obrigatório', 'id' => 0], 400);
        }

        try {
            // estoque_atual também não é editável aqui — só via stock_movements.
            DB::connection()->update('products', [
                'supplier_id'    => !empty($form['supplier_id']) ? (int) $form['supplier_id'] : null,
                'nome'           => $nome,
                'codigo_barra'   => $form['codigo_barra'] ?? null,
                'unidade'        => $form['unidade']      ?? 'un',
                'descricao'      => $form['descricao']    ?? null,
                'preco_compra'   => (float) ($form['preco_compra']   ?? 0),
                'margem_lucro'   => (float) ($form['margem_lucro']   ?? 0),
                'preco_venda'    => (float) ($form['preco_venda']    ?? 0),
                'estoque_minimo' => (float) ($form['estoque_minimo'] ?? 0),
                'ativo'          => filter_var($form['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'atualizado_em'  => $this->now(),
            ], ['id' => $id], ['ativo' => ParameterType::BOOLEAN]);

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
            DB::connection()->update('products', [
                'excluido'      => true,
                'atualizado_em' => $this->now(),
            ], ['id' => $id], ['excluido' => ParameterType::BOOLEAN]);

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
            5 => 'p.ativo',
            6 => 'p.criado_em',
        ];

        $orderField = $columns[(int) ($form['order'][0]['column'] ?? 0)] ?? 'p.id';
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? ''), ['ASC', 'DESC']) ? strtoupper($form['order'][0]['dir']) : 'DESC';

        try {
            $totalRecords = (int) DB::select('COUNT(*)')
                ->from('products', 'p')
                ->where('p.excluido = false')
                ->fetchOne();

            $query = DB::select('p.*, s.nome as supplier_nome')
                ->from('products', 'p')
                ->leftJoin('p', 'suppliers', 's', 's.id = p.supplier_id')
                ->where('p.excluido = false');

            if (!empty($term)) {
                $query->andWhere($query->expr()->or('p.nome ILIKE :term', 'p.codigo_barra ILIKE :term'))
                    ->setParameter('term', '%' . $term . '%');
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $products = $query->orderBy($orderField, $orderType)
                ->setFirstResult($start)->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = array_map(fn($v) => [
                $v['id'],
                $v['nome'],
                'R$ ' . number_format((float) $v['preco_venda'], 2, ',', '.'),
                "<span class='" . ((float) $v['estoque_atual'] <= (float) $v['estoque_minimo'] ? 'text-danger fw-bold' : '') . "'>"
                    . $this->formatarEstoque((float) $v['estoque_atual']) . " {$v['unidade']}</span>
    <button type='button' class='btn btn-sm btn-outline-primary ms-1 btn-ajustar-estoque'
        data-product-id='{$v['id']}'
        data-product-nome='" . htmlspecialchars($v['nome'], ENT_QUOTES) . "'
        data-estoque-atual='{$v['estoque_atual']}'>
        <i class='bi bi-plus-slash-minus'></i>
    </button>",
                $v['codigo_barra'] ?? '',
                $v['ativo'] ? 'Ativo' : 'Inativo',
                (new DateTime($v['criado_em']))->format('d/m/Y H:i:s'),
                "<td>
<a class='btn btn-sm btn-warning' href='/produto/detalhes/{$v['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
<button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$v['id']});'><i class='bi bi-trash'></i> Excluir</button>
</td>",
            ], $products);

            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatarEstoque(float $valor): string
    {
        // Remove zeros e ponto decimal desnecessários (9.000 -> 9, 9.500 -> 9,5)
        $formatado = rtrim(rtrim(number_format($valor, 3, '.', ''), '0'), '.');
        return str_replace('.', ',', $formatado);
    }

    private function now(): string
    {
        return (new DateTime())->format('Y-m-d H:i:s');
    }
}
