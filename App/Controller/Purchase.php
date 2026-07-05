<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use DateTime;
use Doctrine\DBAL\ParameterType;
use Exception;

final class Purchase extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-purchase'), [
                'titulo' => 'Lista de compras',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
    public function details($request, $response, $args)
    {
        $id       = $args['id'] ?? null;
        $action   = ($id === null) ? 'c' : 'e';
        $purchase = [];
        $items    = [];

        if ($id !== null) {
            $purchase = DB::select('p.*, s.nome as supplier_nome, s.cnpj as supplier_cnpj')
                ->from('purchases', 'p')
                ->leftJoin('p', 'suppliers', 's', 's.id = p.supplier_id')
                ->where('p.excluido = false')
                ->andWhere('p.id = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();

            $items = DB::select('pi.*, pr.nome as product_nome')
                ->from('purchase_items', 'pi')
                ->leftJoin('pi', 'products', 'pr', 'pr.id = pi.product_id')
                ->where('pi.purchase_id = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->orderBy('pi.id', 'ASC')
                ->fetchAllAssociative();
        }

        $suppliers = DB::select('id, nome, cnpj')
            ->from('suppliers')
            ->where('excluido = false')
            ->andWhere('ativo = true')
            ->orderBy('nome', 'ASC')
            ->fetchAllAssociative();

        return $this->getTwig()
            ->render($response, $this->setView('purchase'), [
                'titulo'    => 'Compra',
                'id'        => $id,
                'action'    => $action,
                'purchase'  => $purchase,
                'items'     => $items,
                'suppliers' => $suppliers,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
    public function insert($request, $response)
    {
        $form      = $request->getParsedBody();
        $criadoPor = $_SESSION['user']['id'] ?? null;
        if (empty($form['supplier_id'])) {
            return $this->json($response, ['status' => false, 'msg' => 'Selecione um fornecedor', 'id' => 0], 400);
        }
        if (!$criadoPor) {
            return $this->json($response, ['status' => false, 'msg' => 'Sessão inválida', 'id' => 0], 401);
        }
        try {
            $numero = $this->gerarNumeroCompra();
            DB::connection()->insert(
                'purchases',
                [
                    'supplier_id'   => (int) $form['supplier_id'],
                    'criado_por'    => (int) $criadoPor,
                    'numero'        => $numero,
                    'nota_pedido'   => $form['nota_pedido'] ?? null,
                    'status'        => 'pendente',
                    'observacoes'   => $form['observacoes'] ?? null,
                    'valor_total'   => 0.00,
                    'excluido'      => false,
                    'criado_em'     => $this->now(),
                    'atualizado_em' => $this->now(),
                ],
                ['excluido' => ParameterType::BOOLEAN]
            );
            $id = (int) DB::connection()->lastInsertId();
            if ($id === 0) {
                $id = (int) (DB::select('id')->from('purchases')
                    ->where('numero = :numero')->setParameter('numero', $numero)
                    ->fetchOne() ?? 0);
            }
            return $this->json($response, ['status' => true, 'msg' => 'Compra criada com sucesso!', 'id' => $id, 'numero' => $numero], 201);
        } catch (Exception $e) {
            error_log('[Purchase::insert] ' . $e->getMessage());
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
        try {
            $purchase = DB::select('status')->from('purchases')
                ->where('id = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();
            if ($purchase && $purchase['status'] !== 'pendente') {
                return $this->json($response, ['status' => false, 'msg' => 'Não é possível editar uma compra já recebida/cancelada', 'id' => 0], 422);
            }
            DB::connection()->update(
                'purchases',
                [
                    'supplier_id'   => !empty($form['supplier_id']) ? (int) $form['supplier_id'] : null,
                    'nota_pedido'   => $form['nota_pedido'] ?? null,
                    'observacoes'   => $form['observacoes'] ?? null,
                    'atualizado_em' => $this->now(),
                ],
                ['id'       => (int) $id, 'excluido' => false,],
                ['excluido' => ParameterType::BOOLEAN,]
            );
            return $this->json($response, ['status' => true, 'msg' => 'Compra atualizada com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            error_log('[Purchase::update] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao atualizar: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;
        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código da compra', 'id' => 0], 403);
        }
        try {
            $purchase = DB::select('status')->from('purchases')
                ->where('id = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();
            if ($purchase && $purchase['status'] === 'recebida') {
                return $this->json($response, ['status' => false, 'msg' => 'Não é possível excluir uma compra já recebida', 'id' => 0], 422);
            }
            DB::connection()->update(
                'purchases',
                [
                    'excluido'      => true,
                    'atualizado_em' => $this->now(),
                ],
                ['id' => $id],
                ['excluido' => ParameterType::BOOLEAN]
            );
            return $this->json($response, ['status' => true, 'msg' => 'Compra removida com sucesso!', 'id' => (int) $id]);
        } catch (Exception $e) {
            error_log('[Purchase::delete] ' . $e->getMessage());
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
            1 => 'p.numero',
            2 => 's.nome',
            3 => 'p.nota_pedido',
            4 => 'p.status',
            5 => 'p.valor_total',
            6 => 'p.criado_em',
        ];
        $orderField = $columns[(int) ($form['order'][0]['column'] ?? 0)] ?? 'p.id';
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? ''), ['ASC', 'DESC']) ? strtoupper($form['order'][0]['dir']) : 'DESC';
        $statusLabels = [
            'pendente'  => '<span class="badge bg-primary">Pendente</span>',
            'recebida'  => '<span class="badge bg-success">Recebida</span>',
            'cancelada' => '<span class="badge bg-danger">Cancelada</span>',
        ];
        try {
            $totalRecords = (int) DB::select('COUNT(*)')->from('purchases')->where('excluido = false')->fetchOne();
            $query = DB::select('p.*, s.nome as supplier_nome')
                ->from('purchases', 'p')
                ->leftJoin('p', 'suppliers', 's', 's.id = p.supplier_id')
                ->where('p.excluido = false');
            if (!empty($term)) {
                $query->andWhere($query->expr()->or(
                    'p.numero ILIKE :term',
                    's.nome ILIKE :term',
                    'p.nota_pedido ILIKE :term',
                    'p.status ILIKE :term'
                ))->setParameter('term', '%' . $term . '%');
            }
            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();
            $purchases = $query->orderBy($orderField, $orderType)
                ->setFirstResult($start)->setMaxResults($length)
                ->fetchAllAssociative();
            $rows = array_map(fn($v) => [
                $v['id'],
                $v['numero'],
                $v['supplier_nome'],
                $v['nota_pedido'] ?? '—',
                $statusLabels[$v['status']] ?? $v['status'],
                'R$ ' . number_format((float) $v['valor_total'], 2, ',', '.'),
                (new DateTime($v['criado_em']))->format('d/m/Y H:i:s'),
                $v['status'] === 'pendente'
                    ? "<td>
                            <a class='btn btn-sm btn-warning' href='/compras/detalhes/{$v['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
                            <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$v['id']});'><i class='bi bi-trash'></i> Excluir</button>
                        </td>"
                    : "<td>
                            <a class='btn btn-sm btn-warning' href='/compras/detalhes/{$v['id']}'><i class='bi bi-eye'></i> Visualizar</a>
                            <a class='btn btn-sm btn-warning' href='/relatorio/detalhes/{$v['id']}'><i class='bi bi-eye'></i> Imprimir</a>
                        </td>",
            ],
            $purchases);
            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (Exception $e) {
            error_log('[Purchase::listingdata] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    // ── Items ─────────────────────────────────────────────────────────────────
    public function itemInsert($request, $response, $args)
    {
        $purchaseId = $args['id'] ?? null;
        $form       = $request->getParsedBody();
        $productId  = $form['product_id'] ?? null;
        $quantidade = trim($form['quantidade'] ?? '');
        $precoUnit  = trim($form['preco_unitario'] ?? '');
        if (!$purchaseId) {
            return $this->json($response, ['status' => false, 'msg' => 'ID da compra não informado', 'id' => 0], 403);
        }
        if (!$productId) {
            return $this->json($response, ['status' => false, 'msg' => 'Selecione um produto', 'id' => 0], 400);
        }
        if ($quantidade === '' || (float) $quantidade <= 0) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe uma quantidade válida', 'id' => 0], 400);
        }
        if ($precoUnit === '' || (float) $precoUnit <= 0) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe um preço unitário válido', 'id' => 0], 400);
        }
        $purchase = DB::select('status')->from('purchases')
            ->where('id = :id')
            ->setParameter('id', $purchaseId, ParameterType::INTEGER)
            ->fetchAssociative();
        if ($purchase && $purchase['status'] !== 'pendente') {
            return $this->json($response, ['status' => false, 'msg' => 'Não é possível adicionar itens a uma compra já recebida/cancelada', 'id' => 0], 422);
        }
        $subtotal = round((float) $quantidade * (float) $precoUnit, 2);
        try {
            DB::connection()->insert('purchase_items', [
                'purchase_id'    => (int) $purchaseId,
                'product_id'     => (int) $productId,
                'quantidade'     => $quantidade,
                'preco_unitario' => $precoUnit,
                'subtotal'       => $subtotal,
                'criado_em'      => $this->now(),
            ], [
                'purchase_id'    => ParameterType::INTEGER,
                'product_id'     => ParameterType::INTEGER,
                'quantidade'     => ParameterType::STRING,
                'preco_unitario' => ParameterType::STRING,
                'subtotal'       => ParameterType::STRING,
            ]);
            $itemId = (int) DB::connection()->lastInsertId();
            $this->recalcularTotal((int) $purchaseId);
            return $this->json($response, ['status' => true, 'msg' => 'Item adicionado!', 'id' => $itemId, 'subtotal' => $subtotal], 201);
        } catch (Exception $e) {
            error_log('[Purchase::itemInsert] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function itemDelete($request, $response, $args)
    {
        $itemId     = $args['itemId'] ?? null;
        $purchaseId = $args['id']     ?? null;
        if (!$itemId) {
            return $this->json($response, ['status' => false, 'msg' => 'ID do item não informado', 'id' => 0], 403);
        }
        $purchase = DB::select('status')->from('purchases')
            ->where('id = :id')
            ->setParameter('id', $purchaseId, ParameterType::INTEGER)
            ->fetchAssociative();
        if ($purchase && $purchase['status'] !== 'pendente') {
            return $this->json($response, ['status' => false, 'msg' => 'Não é possível remover itens de uma compra já recebida/cancelada', 'id' => 0], 422);
        }
        try {
            DB::connection()->delete('purchase_items', ['id' => $itemId, 'purchase_id' => $purchaseId]);
            $this->recalcularTotal((int) $purchaseId);
            return $this->json($response, ['status' => true, 'msg' => 'Item removido!', 'id' => (int) $itemId]);
        } catch (Exception $e) {
            error_log('[Purchase::itemDelete] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ── Search ────────────────────────────────────────────────────────────────
    public function searchProducts($request, $response)
    {
        $term  = $request->getQueryParams()['q'] ?? null;
        $query = DB::select('id, nome, preco_compra, unidade, estoque_atual')->from('products')
            ->where('excluido = false')
            ->andWhere('ativo = true');
        if (!empty($term)) {
            $query->andWhere($query->expr()->or('CAST(id AS TEXT) ILIKE :term', 'nome ILIKE :term'))
                ->setParameter('term', '%' . $term . '%');
        }
        $produtos = $query->orderBy('nome', 'ASC')->setMaxResults(20)->fetchAllAssociative();
        $results = array_map(fn($p) => [
            'id'            => $p['id'],
            'nome'          => $p['nome'],
            'preco_compra'  => (float) $p['preco_compra'],
            'estoque_atual' => (float) $p['estoque_atual'],
        ], 
        $produtos);
        return $this->json($response, ['results' => $results], 200);
    }

    // ── Receber compra (entrada de estoque) ─────────────────────────────────
    public function receive($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;
        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID não informado', 'id' => 0], 403);
        }
        try {
            $purchase = DB::select('status')->from('purchases')
                ->where('id = :id')->andWhere('excluido = false')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();
            if (!$purchase || $purchase['status'] !== 'pendente') {
                return $this->json($response, ['status' => false, 'msg' => 'Compra não pode ser recebida', 'id' => 0], 422);
            }
            $itens = DB::select('id, product_id, quantidade')
                ->from('purchase_items')
                ->where('purchase_id = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAllAssociative();
            if (empty($itens)) {
                return $this->json($response, ['status' => false, 'msg' => 'Compra sem itens não pode ser recebida', 'id' => 0], 422);
            }
            $conn = DB::connection();
            $conn->beginTransaction();
            try {
                foreach ($itens as $item) {
                    $produto = $conn->fetchAssociative(
                        'SELECT estoque_atual FROM products WHERE id = :id FOR UPDATE',
                        ['id' => (int) $item['product_id']]
                    );
                    if (!$produto) {
                        throw new Exception("Produto id {$item['product_id']} não encontrado");
                    }
                    $estoqueAnterior  = (float) $produto['estoque_atual'];
                    $quantidade       = (float) $item['quantidade'];
                    $estoquePosterior = $estoqueAnterior + $quantidade;
                    $conn->insert('stock_movements', [
                        'product_id'            => (int) $item['product_id'],
                        'service_order_item_id' => null,
                        'tipo'                  => 'ENTRADA',
                        'origem'                => 'COMPRA',
                        'quantidade'            => $quantidade,
                        'estoque_anterior'      => $estoqueAnterior,
                        'estoque_posterior'     => $estoquePosterior,
                        'observacao'            => "Compra #{$id} — item #{$item['id']}",
                        'criado_por'            => $_SESSION['user']['id'] ?? null,
                        'criado_em'             => $this->now(),
                    ]);
                }
                $conn->update('purchases', [
                    'status'        => 'recebida',
                    'recebido_em'   => $this->now(),
                    'atualizado_em' => $this->now(),
                ], ['id' => $id]);
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            return $this->json($response, ['status' => true, 'msg' => 'Compra recebida e estoque atualizado!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            error_log('[Purchase::receive] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function cancel($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;
        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID não informado', 'id' => 0], 403);
        }
        try {
            $purchase = DB::select('status')->from('purchases')
                ->where('id = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();

            if (!$purchase || $purchase['status'] === 'recebida') {
                return $this->json($response, ['status' => false, 'msg' => 'Compra já recebida não pode ser cancelada por aqui', 'id' => 0], 422);
            }
            DB::connection()->update('purchases', [
                'status'        => 'cancelada',
                'atualizado_em' => $this->now(),
            ], ['id' => $id]);
            return $this->json($response, ['status' => true, 'msg' => 'Compra cancelada!', 'id' => (int) $id]);
        } catch (Exception $e) {
            error_log('[Purchase::cancel] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function recalcularTotal(int $purchaseId): void
    {
        $total = (float) DB::select('COALESCE(SUM(subtotal), 0)')
            ->from('purchase_items')
            ->where('purchase_id = :id')
            ->setParameter('id', $purchaseId, ParameterType::INTEGER)
            ->fetchOne();
        DB::connection()->update('purchases', ['valor_total' => $total, 'atualizado_em' => $this->now()], ['id' => $purchaseId]);
    }    
    private function gerarNumeroCompra(): string
    {
        $ano   = (new DateTime())->format('Y');
        $total = (int) DB::select('COUNT(*)')->from('purchases')
            ->where('numero LIKE :p')->setParameter('p', "COMPRA-{$ano}-%")->fetchOne();
        do {
            $numero = "COMPRA-{$ano}-" . str_pad((string) ++$total, 5, '0', STR_PAD_LEFT);
            $existe = DB::select('id')->from('purchases')
                ->where('numero = :n')->setParameter('n', $numero)->fetchOne();
        } while ($existe);
        return $numero;
    }
    private function now(): string
    {
        return (new DateTime())->format('Y-m-d H:i:s');
    }
}
