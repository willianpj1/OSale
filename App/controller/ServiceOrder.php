<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use DateTime;
use Exception;

final class ServiceOrder extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-service-order'), [
                'titulo' => 'Lista de ordens de serviço',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id           = $args['id'] ?? null;
        $action       = ($id === null) ? 'c' : 'e';
        $order        = [];
        $items        = [];

        if ($id !== null) {
            $order = DB::queryOne(
                'SELECT so.*, 
                        c.nome as customer_nome,
                        u.nome as tecnico_nome,
                        u.sobrenome as tecnico_sobrenome
                 FROM service_orders so
                 LEFT JOIN customers c ON c.id = so.customer_id
                 LEFT JOIN users u ON u.id = so.user_id
                 WHERE so.id = :id AND so.excluido = false',
                ['id' => $id]
            );

            $items = DB::query(
                'SELECT soi.*,
                        s.nome as service_nome,
                        p.nome as product_nome
                 FROM service_order_items soi
                 LEFT JOIN services s ON s.id = soi.service_id
                 LEFT JOIN products p ON p.id = soi.product_id
                 WHERE soi.service_order_id = :id
                 ORDER BY soi.id ASC',
                ['id' => $id]
            );
        }

        $customers = DB::query(
            'SELECT id, nome, cpf_cnpj FROM customers WHERE excluido = false AND ativo = true ORDER BY nome ASC'
        );

        $technicians = DB::query(
            'SELECT id, nome, sobrenome FROM users WHERE ativo = true ORDER BY nome ASC'
        );

        return $this->getTwig()
            ->render($response, $this->setView('service-order'), [
                'titulo'      => 'Ordem de Serviço',
                'id'          => $id,
                'action'      => $action,
                'order'       => $order,
                'items'       => $items,
                'customers'   => $customers,
                'technicians' => $technicians,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form        = $request->getParsedBody();
        $customerId  = $form['customer_id'] ?? null;
        $criadoPor = $_SESSION['user']['id'] ?? 1;

        if (!$customerId) {
            return $this->json($response, ['status' => false, 'msg' => 'Selecione um cliente', 'id' => 0], 400);
        }

        if (!$criadoPor) {
            return $this->json($response, ['status' => false, 'msg' => 'Sessão inválida', 'id' => 0], 401);
        }

        try {
            $now    = (new DateTime())->format('Y-m-d H:i:s');
            $numero = $this->gerarNumeroOS();

            DB::execute(
                'INSERT INTO service_orders
                    (customer_id, user_id, criado_por, numero, status, prioridade,
                     equipamento, marca, modelo, numero_serie,
                     defeito_relatado, defeito_constatado, observacoes,
                     valor_total, excluido, aberto_em, criado_em, atualizado_em)
                 VALUES
                    (:customer_id, :user_id, :criado_por, :numero, :status, :prioridade,
                     :equipamento, :marca, :modelo, :numero_serie,
                     :defeito_relatado, :defeito_constatado, :observacoes,
                     0.00, false, :aberto_em, :criado_em, :atualizado_em)',
                [
                    'customer_id'       => (int) $customerId,
                    'user_id'           => $form['user_id']           ? (int) $form['user_id'] : null,
                    'criado_por'        => (int) $criadoPor,
                    'numero'            => $numero,
                    'status'            => $form['status']            ?? 'aberta',
                    'prioridade'        => $form['prioridade']        ?? 'normal',
                    'equipamento'       => $form['equipamento']       ?? null,
                    'marca'             => $form['marca']             ?? null,
                    'modelo'            => $form['modelo']            ?? null,
                    'numero_serie'      => $form['numero_serie']      ?? null,
                    'defeito_relatado'  => $form['defeito_relatado']  ?? null,
                    'defeito_constatado'=> $form['defeito_constatado'] ?? null,
                    'observacoes'       => $form['observacoes']       ?? null,
                    'aberto_em'         => $now,
                    'criado_em'         => $now,
                    'atualizado_em'     => $now,
                ]
            );

            $id = (int) DB::lastInsertId('service_orders_id_seq');

            return $this->json($response, ['status' => true, 'msg' => 'OS aberta com sucesso!', 'id' => $id, 'numero' => $numero], 201);
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
                'UPDATE service_orders SET
                    user_id = :user_id, status = :status, prioridade = :prioridade,
                    equipamento = :equipamento, marca = :marca, modelo = :modelo,
                    numero_serie = :numero_serie, defeito_relatado = :defeito_relatado,
                    defeito_constatado = :defeito_constatado, observacoes = :observacoes,
                    atualizado_em = :atualizado_em
                 WHERE id = :id AND excluido = false',
                [
                    'user_id'            => $form['user_id']            ? (int) $form['user_id'] : null,
                    'status'             => $form['status']             ?? 'aberta',
                    'prioridade'         => $form['prioridade']         ?? 'normal',
                    'equipamento'        => $form['equipamento']        ?? null,
                    'marca'              => $form['marca']              ?? null,
                    'modelo'             => $form['modelo']             ?? null,
                    'numero_serie'       => $form['numero_serie']       ?? null,
                    'defeito_relatado'   => $form['defeito_relatado']   ?? null,
                    'defeito_constatado' => $form['defeito_constatado'] ?? null,
                    'observacoes'        => $form['observacoes']        ?? null,
                    'atualizado_em'      => (new DateTime())->format('Y-m-d H:i:s'),
                    'id'                 => $id,
                ]
            );

            return $this->json($response, ['status' => true, 'msg' => 'OS atualizada com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao atualizar: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código da OS', 'id' => 0], 403);
        }

        try {
            DB::execute(
                'UPDATE service_orders SET excluido = true, atualizado_em = :now WHERE id = :id',
                ['now' => (new DateTime())->format('Y-m-d H:i:s'), 'id' => $id]
            );

            return $this->json($response, ['status' => true, 'msg' => 'OS removida com sucesso!', 'id' => (int) $id]);
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
            0 => 'so.id',
            1 => 'so.numero',
            2 => 'c.nome',
            3 => 'so.equipamento',
            4 => 'so.status',
            5 => 'so.prioridade',
            6 => 'so.valor_total',
            7 => 'so.aberto_em',
        ];

        $posField   = isset($columns[(int) ($form['order'][0]['column'] ?? 0)]) ? (int) $form['order'][0]['column'] : 0;
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? 'DESC'), ['ASC', 'DESC']) ? strtoupper($form['order'][0]['dir']) : 'DESC';
        $orderField = $columns[$posField];

        try {
            $where  = 'WHERE so.excluido = false';
            $params = [];

            if (!empty($term)) {
                $where          .= ' AND (so.numero ILIKE :term OR c.nome ILIKE :term OR so.equipamento ILIKE :term OR so.status ILIKE :term)';
                $params['term']  = '%' . $term . '%';
            }

            $totalRecords    = (int) DB::queryOne('SELECT COUNT(*) as total FROM service_orders so WHERE so.excluido = false')['total'];
            $filteredRecords = (int) DB::queryOne(
                "SELECT COUNT(*) as total FROM service_orders so 
                 LEFT JOIN customers c ON c.id = so.customer_id {$where}",
                $params
            )['total'];

            $params['limit']  = $length;
            $params['offset'] = $start;

            $orders = DB::query(
                "SELECT so.*, c.nome as customer_nome,
                        u.nome as tecnico_nome, u.sobrenome as tecnico_sobrenome
                 FROM service_orders so
                 LEFT JOIN customers c ON c.id = so.customer_id
                 LEFT JOIN users u ON u.id = so.user_id
                 {$where} ORDER BY {$orderField} {$orderType} LIMIT :limit OFFSET :offset",
                $params
            );

            $statusLabels = [
                'aberta'           => '<span class="badge bg-primary">Aberta</span>',
                'em_andamento'     => '<span class="badge bg-warning text-dark">Em andamento</span>',
                'aguardando_peca'  => '<span class="badge bg-secondary">Aguardando peça</span>',
                'concluida'        => '<span class="badge bg-success">Concluída</span>',
                'cancelada'        => '<span class="badge bg-danger">Cancelada</span>',
            ];

            $prioridadeLabels = [
                'baixa'   => '<span class="badge bg-secondary">Baixa</span>',
                'normal'  => '<span class="badge bg-info text-dark">Normal</span>',
                'alta'    => '<span class="badge bg-warning text-dark">Alta</span>',
                'urgente' => '<span class="badge bg-danger">Urgente</span>',
            ];

            $rows = [];
            foreach ($orders as $key => $value) {
                $tecnico = $value['tecnico_nome']
                    ? $value['tecnico_nome'] . ' ' . $value['tecnico_sobrenome']
                    : '—';

                $rows[$key] = [
                    $value['id'],
                    $value['numero'],
                    $value['customer_nome'],
                    $value['equipamento'] ?? '—',
                    $statusLabels[$value['status']]       ?? $value['status'],
                    $prioridadeLabels[$value['prioridade']] ?? $value['prioridade'],
                    $tecnico,
                    'R$ ' . number_format((float) $value['valor_total'], 2, ',', '.'),
                    (new DateTime($value['aberto_em']))->format('d/m/Y H:i:s'),
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/os/detalhes/{$value['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
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

    // ── Items ─────────────────────────────────────────────────────────────────

    public function itemInsert($request, $response, $args)
    {
        $orderId = $args['id'] ?? null;
        $form    = $request->getParsedBody();

        if (!$orderId) {
            return $this->json($response, ['status' => false, 'msg' => 'ID da OS não informado', 'id' => 0], 403);
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
            $quantidade    = (float) ($form['quantidade']     ?? 1);
            $precoUnitario = (float) ($form['preco_unitario'] ?? 0);
            $subtotal      = round($quantidade * $precoUnitario, 2);

            DB::execute(
                'INSERT INTO service_order_items
                    (service_order_id, tipo, service_id, product_id, descricao, quantidade, preco_unitario, subtotal, criado_em)
                 VALUES
                    (:service_order_id, :tipo, :service_id, :product_id, :descricao, :quantidade, :preco_unitario, :subtotal, :criado_em)',
                [
                    'service_order_id' => (int) $orderId,
                    'tipo'             => $tipo,
                    'service_id'       => $tipo === 'servico' && !empty($form['service_id']) ? (int) $form['service_id'] : null,
                    'product_id'       => $tipo === 'produto' && !empty($form['product_id']) ? (int) $form['product_id'] : null,
                    'descricao'        => $descricao,
                    'quantidade'       => $quantidade,
                    'preco_unitario'   => $precoUnitario,
                    'subtotal'         => $subtotal,
                    'criado_em'        => (new DateTime())->format('Y-m-d H:i:s'),
                ]
            );

            $itemId = (int) DB::lastInsertId('service_order_items_id_seq');

            // Recalcula e atualiza o valor_total da OS
            $this->recalcularTotal((int) $orderId);

            return $this->json($response, [
                'status'   => true,
                'msg'      => 'Item adicionado!',
                'id'       => $itemId,
                'subtotal' => $subtotal,
            ], 201);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function itemDelete($request, $response, $args)
    {
        $itemId  = $args['itemId'] ?? null;
        $orderId = $args['id']     ?? null;

        if (!$itemId) {
            return $this->json($response, ['status' => false, 'msg' => 'ID do item não informado', 'id' => 0], 403);
        }

        try {
            DB::execute(
                'DELETE FROM service_order_items WHERE id = :id AND service_order_id = :order_id',
                ['id' => $itemId, 'order_id' => $orderId]
            );

            // Recalcula e atualiza o valor_total da OS
            $this->recalcularTotal((int) $orderId);

            return $this->json($response, ['status' => true, 'msg' => 'Item removido!', 'id' => (int) $itemId]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ── Search (para select2 no formulário) ───────────────────────────────────

    public function searchProducts($request, $response)
    {
        $term = $request->getQueryParams()['q'] ?? '';

        $products = DB::query(
            'SELECT id, nome, preco_venda as preco, unidade
             FROM products
             WHERE excluido = false AND ativo = true AND nome ILIKE :term
             ORDER BY nome ASC LIMIT 20',
            ['term' => '%' . $term . '%']
        );

        return $this->json($response, ['results' => $products], 200);
    }

    public function searchServices($request, $response)
    {
        $term = $request->getQueryParams()['q'] ?? '';

        $services = DB::query(
            'SELECT id, nome, preco
             FROM services
             WHERE excluido = false AND ativo = true AND nome ILIKE :term
             ORDER BY nome ASC LIMIT 20',
            ['term' => '%' . $term . '%']
        );

        return $this->json($response, ['results' => $services], 200);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function recalcularTotal(int $orderId): void
    {
        $result = DB::queryOne(
            'SELECT COALESCE(SUM(subtotal), 0) as total FROM service_order_items WHERE service_order_id = :id',
            ['id' => $orderId]
        );

        DB::execute(
            'UPDATE service_orders SET valor_total = :total, atualizado_em = :now WHERE id = :id',
            [
                'total' => (float) $result['total'],
                'now'   => (new DateTime())->format('Y-m-d H:i:s'),
                'id'    => $orderId,
            ]
        );
    }

    private function gerarNumeroOS(): string
    {
        $ano     = (new DateTime())->format('Y');
        $result  = DB::queryOne(
            "SELECT COUNT(*) as total FROM service_orders WHERE numero LIKE :pattern",
            ['pattern' => "OS-{$ano}-%"]
        );
        $sequencia = str_pad((string) ((int) $result['total'] + 1), 5, '0', STR_PAD_LEFT);
        return "OS-{$ano}-{$sequencia}";
    }
}