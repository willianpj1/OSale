<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use DateTime;
use Exception;

final class Sale extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-sale'), ['titulo' => 'Lista de vendas'])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id    = $args['id'] ?? null;
        $sale  = [];
        $items = [];

        if ($id !== null) {
            $sale = DB::queryOne(
                'SELECT s.*, c.nome AS customer_nome, u.nome AS vendedor_nome, u.sobrenome AS vendedor_sobrenome, so.numero AS os_numero
                 FROM sales s
                 LEFT JOIN customers c ON c.id = s.customer_id
                 LEFT JOIN users u ON u.id = s.user_id
                 LEFT JOIN service_orders so ON so.id = s.service_order_id
                 WHERE s.id = :id AND s.excluido = false',
                ['id' => $id]
            );

            $items = DB::query(
                'SELECT si.*, sv.nome AS service_nome, p.nome AS product_nome
                 FROM sale_items si
                 LEFT JOIN services sv ON sv.id = si.service_id
                 LEFT JOIN products p ON p.id = si.product_id
                 WHERE si.sale_id = :id ORDER BY si.id ASC',
                ['id' => $id]
            );
        }

        return $this->getTwig()
            ->render($response, $this->setView('sale'), [
                'titulo'        => 'Detalhes da venda',
                'id'            => $id,
                'action'        => ($id === null) ? 'c' : 'e',
                'sale'          => $sale,
                'items'         => $items
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form   = $request->getParsedBody();
        $userId = $_SESSION['user']['id'] ?? null;

        if (!$userId) {
            return $this->json($response, ['status' => false, 'msg' => 'Sessão inválida', 'id' => 0], 401);
        }

        try {
            $now    = (new DateTime())->format('Y-m-d H:i:s');
            $numero = $this->gerarNumeroVenda();

            DB::execute(
                'INSERT INTO sales
                    (customer_id, user_id, service_order_id, numero, status, forma_pagamento,
                     desconto, total_servicos, total_produtos, total_geral, observacoes, excluido, criado_em, atualizado_em)
                 VALUES
                    (:customer_id, :user_id, :service_order_id, :numero, :status, :forma_pagamento,
                     :desconto, 0.00, 0.00, 0.00, :observacoes, false, :criado_em, :atualizado_em)',
                [
                    'customer_id'      => !empty($form['customer_id'])      ? (int) $form['customer_id']      : null,
                    'user_id'          => (int) $userId,
                    'service_order_id' => !empty($form['service_order_id']) ? (int) $form['service_order_id'] : null,
                    'numero'           => $numero,
                    'status'           => 'aberta',
                    'forma_pagamento'  => $form['forma_pagamento'] ?? null,
                    'desconto'         => (float) ($form['desconto'] ?? 0),
                    'observacoes'      => $form['observacoes'] ?? null,
                    'criado_em'        => $now,
                    'atualizado_em'    => $now,
                ]
            );

            $id = (int) DB::lastInsertId('sales_id_seq');

            return $this->json($response, ['status' => true, 'msg' => 'Venda criada com sucesso!', 'id' => $id, 'numero' => $numero], 201);
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

        try {
            DB::execute(
                'UPDATE sales SET
                    customer_id = :customer_id, service_order_id = :service_order_id,
                    forma_pagamento = :forma_pagamento, desconto = :desconto,
                    observacoes = :observacoes, atualizado_em = :atualizado_em
                 WHERE id = :id AND excluido = false AND status = :status_aberta',
                [
                    'customer_id'      => !empty($form['customer_id'])      ? (int) $form['customer_id']      : null,
                    'service_order_id' => !empty($form['service_order_id']) ? (int) $form['service_order_id'] : null,
                    'forma_pagamento'  => $form['forma_pagamento'] ?? null,
                    'desconto'         => (float) ($form['desconto'] ?? 0),
                    'observacoes'      => $form['observacoes'] ?? null,
                    'atualizado_em'    => (new DateTime())->format('Y-m-d H:i:s'),
                    'id'               => $id,
                    'status_aberta'    => 'aberta',
                ]
            );

            $this->recalcularTotal((int) $id);

            return $this->json($response, ['status' => true, 'msg' => 'Venda atualizada com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao atualizar: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function finalize($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID não informado', 'id' => 0], 403);
        }

        try {
            $sale = DB::queryOne('SELECT * FROM sales WHERE id = :id AND excluido = false', ['id' => $id]);

            if (!$sale || $sale['status'] !== 'aberta') {
                return $this->json($response, ['status' => false, 'msg' => 'Apenas vendas abertas podem ser finalizadas', 'id' => 0], 422);
            }

            $formaPagamento = $form['forma_pagamento'] ?? $sale['forma_pagamento'];
            if (empty($formaPagamento)) {
                return $this->json($response, ['status' => false, 'msg' => 'Informe a forma de pagamento para finalizar', 'id' => 0], 400);
            }

            DB::execute(
                'UPDATE sales SET status = :status, forma_pagamento = :forma_pagamento, atualizado_em = :now WHERE id = :id',
                ['status' => 'finalizada', 'forma_pagamento' => $formaPagamento, 'now' => (new DateTime())->format('Y-m-d H:i:s'), 'id' => $id]
            );

            return $this->json($response, ['status' => true, 'msg' => 'Venda finalizada com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao finalizar: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código da venda', 'id' => 0], 403);
        }

        try {
            DB::execute(
                'UPDATE sales SET excluido = true, atualizado_em = :now WHERE id = :id AND status != :finalizada',
                ['now' => (new DateTime())->format('Y-m-d H:i:s'), 'id' => $id, 'finalizada' => 'finalizada']
            );

            return $this->json($response, ['status' => true, 'msg' => 'Venda removida com sucesso!', 'id' => (int) $id]);
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
            0 => 's.id',
            1 => 's.numero',
            2 => 'c.nome',
            3 => 's.status',
            4 => 's.forma_pagamento',
            5 => 's.total_geral',
            6 => 's.criado_em',
        ];

        $posField   = isset($columns[(int) ($form['order'][0]['column'] ?? 0)]) ? (int) $form['order'][0]['column'] : 0;
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? 'DESC'), ['ASC', 'DESC']) ? strtoupper($form['order'][0]['dir']) : 'DESC';
        $orderField = $columns[$posField];

        try {
            $where  = 'WHERE s.excluido = false';
            $params = [];

            if (!empty($term)) {
                $where         .= ' AND (s.numero ILIKE :term OR c.nome ILIKE :term OR s.status ILIKE :term)';
                $params['term'] = '%' . $term . '%';
            }

            $totalRecords    = (int) DB::queryOne('SELECT COUNT(*) as total FROM sales s WHERE s.excluido = false')['total'];
            $filteredRecords = (int) DB::queryOne(
                "SELECT COUNT(*) as total FROM sales s LEFT JOIN customers c ON c.id = s.customer_id {$where}",
                $params
            )['total'];

            $params['limit']  = $length;
            $params['offset'] = $start;

            $sales = DB::query(
                "SELECT s.*, c.nome AS customer_nome, u.nome AS vendedor_nome, u.sobrenome AS vendedor_sobrenome
                 FROM sales s
                 LEFT JOIN customers c ON c.id = s.customer_id
                 LEFT JOIN users u ON u.id = s.user_id
                 {$where} ORDER BY {$orderField} {$orderType} LIMIT :limit OFFSET :offset",
                $params
            );

            $statusLabels = [
                'aberta'     => '<span class="badge bg-primary">Aberta</span>',
                'finalizada' => '<span class="badge bg-success">Finalizada</span>',
                'cancelada'  => '<span class="badge bg-danger">Cancelada</span>',
            ];

            $pagamentoLabels = [
                'dinheiro'       => 'Dinheiro',
                'pix'            => 'PIX',
                'cartao_credito' => 'Cartão de Crédito',
                'cartao_debito'  => 'Cartão de Débito',
                'transferencia'  => 'Transferência',
            ];

            $rows = [];
            foreach ($sales as $key => $value) {
                $rows[$key] = [
                    $value['id'],
                    $value['numero'],
                    $value['customer_nome'] ?? 'Balcão',
                    $value['vendedor_nome'] . ' ' . $value['vendedor_sobrenome'],
                    $statusLabels[$value['status']] ?? $value['status'],
                    $pagamentoLabels[$value['forma_pagamento']] ?? ($value['forma_pagamento'] ?? '—'),
                    'R$ ' . number_format((float) $value['total_geral'], 2, ',', '.'),
                    (new DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/venda/detalhes/{$value['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
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

    // ── Items ────────────────────────────────────────────────────────────────

    public function itemInsert($request, $response, $args)
    {
        $saleId = $args['id'] ?? null;
        $form   = $request->getParsedBody();

        if (!$saleId) {
            return $this->json($response, ['status' => false, 'msg' => 'ID da venda não informado', 'id' => 0], 403);
        }

        $sale = DB::queryOne('SELECT status FROM sales WHERE id = :id AND excluido = false', ['id' => $saleId]);
        if (!$sale || $sale['status'] !== 'aberta') {
            return $this->json($response, ['status' => false, 'msg' => 'Só é possível adicionar itens a vendas abertas', 'id' => 0], 422);
        }

        $tipo = $form['tipo'] ?? null;
        if (!in_array($tipo, ['servico', 'produto'])) {
            return $this->json($response, ['status' => false, 'msg' => 'Tipo inválido — use servico ou produto', 'id' => 0], 400);
        }

        $descricao = trim($form['descricao'] ?? '');
        if ($descricao === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo descrição é obrigatório', 'id' => 0], 400);
        }

        try {
            $quantidade    = (float) ($form['quantidade']    ?? 1);
            $precoUnitario = (float) ($form['preco_unitario'] ?? 0);
            $descontoItem  = (float) ($form['desconto_item']  ?? 0);
            $subtotal      = round(($quantidade * $precoUnitario) - $descontoItem, 2);

            DB::execute(
                'INSERT INTO sale_items
                    (sale_id, tipo, service_id, product_id, descricao, quantidade, preco_unitario, desconto_item, subtotal, criado_em)
                 VALUES
                    (:sale_id, :tipo, :service_id, :product_id, :descricao, :quantidade, :preco_unitario, :desconto_item, :subtotal, :criado_em)',
                [
                    'sale_id'        => (int) $saleId,
                    'tipo'           => $tipo,
                    'service_id'     => $tipo === 'servico' && !empty($form['service_id']) ? (int) $form['service_id'] : null,
                    'product_id'     => $tipo === 'produto' && !empty($form['product_id']) ? (int) $form['product_id'] : null,
                    'descricao'      => $descricao,
                    'quantidade'     => $quantidade,
                    'preco_unitario' => $precoUnitario,
                    'desconto_item'  => $descontoItem,
                    'subtotal'       => $subtotal,
                    'criado_em'      => (new DateTime())->format('Y-m-d H:i:s'),
                ]
            );

            $itemId = (int) DB::lastInsertId('sale_items_id_seq');
            $this->recalcularTotal((int) $saleId);

            return $this->json($response, ['status' => true, 'msg' => 'Item adicionado!', 'id' => $itemId, 'subtotal' => $subtotal], 201);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function itemDelete($request, $response, $args)
    {
        $itemId = $args['itemId'] ?? null;
        $saleId = $args['id']     ?? null;

        if (!$itemId) {
            return $this->json($response, ['status' => false, 'msg' => 'ID do item não informado', 'id' => 0], 403);
        }

        $sale = DB::queryOne('SELECT status FROM sales WHERE id = :id AND excluido = false', ['id' => $saleId]);
        if (!$sale || $sale['status'] !== 'aberta') {
            return $this->json($response, ['status' => false, 'msg' => 'Só é possível remover itens de vendas abertas', 'id' => 0], 422);
        }

        try {
            DB::execute('DELETE FROM sale_items WHERE id = :id AND sale_id = :sale_id', ['id' => $itemId, 'sale_id' => $saleId]);
            $this->recalcularTotal((int) $saleId);

            return $this->json($response, ['status' => true, 'msg' => 'Item removido!', 'id' => (int) $itemId]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ── Search ───────────────────────────────────────────────────────────────

    public function searchProducts($request, $response)
    {
        $term     = $request->getQueryParams()['q'] ?? '';
        $products = DB::query(
            'SELECT id, nome, preco_venda AS preco, unidade FROM products WHERE excluido = false AND ativo = true AND nome ILIKE :term ORDER BY nome ASC LIMIT 20',
            ['term' => '%' . $term . '%']
        );

        return $this->json($response, ['results' => $products], 200);
    }

    public function searchServices($request, $response)
    {
        $term     = $request->getQueryParams()['q'] ?? '';
        $services = DB::query(
            'SELECT id, nome, preco FROM services WHERE excluido = false AND ativo = true AND nome ILIKE :term ORDER BY nome ASC LIMIT 20',
            ['term' => '%' . $term . '%']
        );

        return $this->json($response, ['results' => $services], 200);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function recalcularTotal(int $saleId): void
    {
        $sale    = DB::queryOne('SELECT desconto FROM sales WHERE id = :id', ['id' => $saleId]);
        $desconto = (float) ($sale['desconto'] ?? 0);

        $totalServicos = (float) DB::queryOne("SELECT COALESCE(SUM(subtotal), 0) AS t FROM sale_items WHERE sale_id = :id AND tipo = 'servico'", ['id' => $saleId])['t'];
        $totalProdutos = (float) DB::queryOne("SELECT COALESCE(SUM(subtotal), 0) AS t FROM sale_items WHERE sale_id = :id AND tipo = 'produto'", ['id' => $saleId])['t'];
        $totalGeral    = max(0, round($totalServicos + $totalProdutos - $desconto, 2));

        DB::execute(
            'UPDATE sales SET total_servicos = :ts, total_produtos = :tp, total_geral = :tg, atualizado_em = :now WHERE id = :id',
            ['ts' => $totalServicos, 'tp' => $totalProdutos, 'tg' => $totalGeral, 'now' => (new DateTime())->format('Y-m-d H:i:s'), 'id' => $saleId]
        );
    }

    private function gerarNumeroVenda(): string
    {
        $ano       = (new DateTime())->format('Y');
        $result    = DB::queryOne('SELECT COUNT(*) AS total FROM sales WHERE numero LIKE :pattern', ['pattern' => "VND-{$ano}-%"]);
        $sequencia = str_pad((string) ((int) $result['total'] + 1), 5, '0', STR_PAD_LEFT);
        return "VND-{$ano}-{$sequencia}";
    }
}
