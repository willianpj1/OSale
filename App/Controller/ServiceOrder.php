<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use DateTime;
use Doctrine\DBAL\ParameterType;
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
        $id     = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $order  = [];
        $items  = [];

        if ($id !== null) {
            $order = DB::select('so.*, c.nome as customer_nome, u.nome as tecnico_nome, u.sobrenome as tecnico_sobrenome')
                ->from('service_orders', 'so')
                ->leftJoin('so', 'customers', 'c', 'c.id = so.customer_id')
                ->leftJoin('so', 'users',     'u', 'u.id = so.user_id')
                ->where('so.excluido = false')
                ->andWhere('so.id = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();

            $items = DB::select('soi.*, s.nome as service_nome, p.nome as product_nome')
                ->from('service_order_items', 'soi')
                ->leftJoin('soi', 'services', 's', 's.id = soi.service_id')
                ->leftJoin('soi', 'products', 'p', 'p.id = soi.product_id')
                ->where('soi.service_order_id = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->orderBy('soi.id', 'ASC')
                ->fetchAllAssociative();
        }

        $customers = DB::select('id, nome, cpf_cnpj')
            ->from('customers')
            ->where('excluido = false')
            ->andWhere('ativo = true')
            ->orderBy('nome', 'ASC')
            ->fetchAllAssociative();

        $technicians = DB::select('id, nome, sobrenome')
            ->from('users')
            ->where('ativo = true')
            ->orderBy('nome', 'ASC')
            ->fetchAllAssociative();

        // Se a OS já tem uma condição de pagamento vinculada, busca ela + as parcelas
        $payment      = null;
        $installments = [];

        if (!empty($order['id_pagamento'])) {
            $payment = DB::select('*')->from('payment_terms')
                ->where('id = :id')
                ->setParameter('id', $order['id_pagamento'], ParameterType::INTEGER)
                ->fetchAssociative();

            if ($payment) {
                // Usa só a quantidade de parcelas que foi de fato escolhida ao
                // finalizar (ex: crédito em 7x) — não as 12 linhas inteiras
                // cadastradas na condição.
                $qtdParcelas = (int) ($order['parcelas'] ?? 1);

                $rows = DB::select('parcela, intervalo')->from('installment')
                    ->where('id_pagamento = :id')
                    ->setParameter('id', $order['id_pagamento'], ParameterType::INTEGER)
                    ->orderBy('parcela', 'ASC')
                    ->setMaxResults($qtdParcelas)
                    ->fetchAllAssociative();

                // Usa o valor já negociado (com desconto/acréscimo) quando a OS foi
                // finalizada; cai pro valor_total (subtotal) caso não exista ainda.
                $totalOS      = (float) ($order['valor_liquido'] ?? $order['valor_total'] ?? 0);
                $installments = $this->calcularParcelas($rows, $totalOS);
            }
        }

        return $this->getTwig()
            ->render($response, $this->setView('service-order'), [
                'titulo'       => 'Ordem de Serviço',
                'id'           => $id,
                'action'       => $action,
                'order'        => $order,
                'items'        => $items,
                'customers'    => $customers,
                'technicians'  => $technicians,
                'payment'      => $payment,
                'installments' => $installments,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form      = $request->getParsedBody();
        $criadoPor = $_SESSION['user']['id'] ?? null;

        if (empty($form['customer_id'])) {
            return $this->json($response, ['status' => false, 'msg' => 'Selecione um cliente', 'id' => 0], 400);
        }

        if (!$criadoPor) {
            return $this->json($response, ['status' => false, 'msg' => 'Sessão inválida', 'id' => 0], 401);
        }

        try {
            $numero = $this->gerarNumeroOS();

            DB::connection()->insert('service_orders', [
                'customer_id'        => (int) $form['customer_id'],
                'user_id'            => !empty($form['user_id']) ? (int) $form['user_id'] : null,
                'criado_por'         => (int) $criadoPor,
                'numero'             => $numero,
                'status'             => $form['status']             ?? 'aberta',
                'prioridade'         => $form['prioridade']         ?? 'normal',
                'equipamento'        => $form['equipamento']        ?? null,
                'marca'              => $form['marca']              ?? null,
                'modelo'             => $form['modelo']             ?? null,
                'numero_serie'       => $form['numero_serie']       ?? null,
                'defeito_relatado'   => $form['defeito_relatado']   ?? null,
                'defeito_constatado' => $form['defeito_constatado'] ?? null,
                'observacoes'        => $form['observacoes']        ?? null,
                'valor_total'        => 0.00,
                'excluido'           => false,
                'aberto_em'          => $this->now(),
                'criado_em'          => $this->now(),
                'atualizado_em'      => $this->now(),
            ], ['excluido' => ParameterType::BOOLEAN]);

            $id = (int) DB::connection()->lastInsertId();

            if ($id === 0) {
                $id = (int) (DB::select('id')->from('service_orders')
                    ->where('numero = :numero')->setParameter('numero', $numero)
                    ->fetchOne() ?? 0);
            }

            return $this->json($response, ['status' => true, 'msg' => 'OS aberta com sucesso!', 'id' => $id, 'numero' => $numero], 201);
        } catch (Exception $e) {
            error_log('[ServiceOrder::insert] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
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
            DB::connection()->update('service_orders', [
                'user_id'            => !empty($form['user_id']) ? (int) $form['user_id'] : null,
                'status'             => $form['status']             ?? 'aberta',
                'prioridade'         => $form['prioridade']         ?? 'normal',
                'equipamento'        => $form['equipamento']        ?? null,
                'marca'              => $form['marca']              ?? null,
                'modelo'             => $form['modelo']             ?? null,
                'numero_serie'       => $form['numero_serie']       ?? null,
                'defeito_relatado'   => $form['defeito_relatado']   ?? null,
                'defeito_constatado' => $form['defeito_constatado'] ?? null,
                'observacoes'        => $form['observacoes']        ?? null,
                'atualizado_em'      => $this->now(),
            ], [
                'id'       => (int) $id,
                'excluido' => false,
            ], [
                'excluido' => ParameterType::BOOLEAN,
            ]);

            return $this->json($response, ['status' => true, 'msg' => 'OS atualizada com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            error_log('[ServiceOrder::update] ' . $e->getMessage());
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
            DB::connection()->update('service_orders', [
                'excluido'      => true,
                'atualizado_em' => $this->now(),
            ], ['id' => $id], ['excluido' => ParameterType::BOOLEAN]);

            return $this->json($response, ['status' => true, 'msg' => 'OS removida com sucesso!', 'id' => (int) $id]);
        } catch (Exception $e) {
            error_log('[ServiceOrder::delete] ' . $e->getMessage());
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

        $orderField = $columns[(int) ($form['order'][0]['column'] ?? 0)] ?? 'so.id';
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? ''), ['ASC', 'DESC']) ? strtoupper($form['order'][0]['dir']) : 'DESC';

        $statusLabels = [
            'aberta'          => '<span class="badge bg-primary">Aberta</span>',
            'em_andamento'    => '<span class="badge bg-warning text-dark">Em andamento</span>',
            'aguardando_peca' => '<span class="badge bg-secondary">Aguardando peça</span>',
            'concluida'       => '<span class="badge bg-success">Concluída</span>',
            'cancelada'       => '<span class="badge bg-danger">Cancelada</span>',
        ];

        $prioridadeLabels = [
            'baixa'   => '<span class="badge bg-secondary">Baixa</span>',
            'normal'  => '<span class="badge bg-info text-dark">Normal</span>',
            'alta'    => '<span class="badge bg-warning text-dark">Alta</span>',
            'urgente' => '<span class="badge bg-danger">Urgente</span>',
        ];

        try {
            $totalRecords = (int) DB::select('COUNT(*)')->from('service_orders')->where('excluido = false')->fetchOne();

            $query = DB::select('so.*, c.nome as customer_nome, u.nome as tecnico_nome, u.sobrenome as tecnico_sobrenome')
                ->from('service_orders', 'so')
                ->leftJoin('so', 'customers', 'c', 'c.id = so.customer_id')
                ->leftJoin('so', 'users',     'u', 'u.id = so.user_id')
                ->where('so.excluido = false');

            if (!empty($term)) {
                $query->andWhere($query->expr()->or(
                    'so.numero ILIKE :term',
                    'c.nome ILIKE :term',
                    'so.equipamento ILIKE :term',
                    'so.status ILIKE :term'
                ))->setParameter('term', '%' . $term . '%');
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $orders = $query->orderBy($orderField, $orderType)
                ->setFirstResult($start)->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = array_map(fn($v) => [
                $v['id'],
                $v['numero'],
                $v['customer_nome'],
                $v['equipamento'] ?? '—',
                $statusLabels[$v['status']]         ?? $v['status'],
                $prioridadeLabels[$v['prioridade']] ?? $v['prioridade'],
                $v['tecnico_nome'] ? $v['tecnico_nome'] . ' ' . $v['tecnico_sobrenome'] : '—',
                'R$ ' . number_format((float) $v['valor_total'], 2, ',', '.'),
                (new DateTime($v['aberto_em']))->format('d/m/Y H:i:s'),
                "<td>
                    <a class='btn btn-sm btn-warning' href='/os/detalhes/{$v['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
                    <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$v['id']});'><i class='bi bi-trash'></i> Excluir</button>
                </td>",
            ], $orders);

            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (Exception $e) {
            error_log('[ServiceOrder::listingdata] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    // ── Items ─────────────────────────────────────────────────────────────────

    public function itemInsert($request, $response, $args)
    {
        $orderId    = $args['id'] ?? null;
        $form       = $request->getParsedBody();
        $tipo       = $form['item-tipo'] ?? null;
        $descricao  = trim($form['item-descricao'] ?? '');
        $quantidade = trim($form['item-quantidade'] ?? '');
        $preco_unit = trim($form['item-preco'] ?? '0');

        if (!$orderId) {
            return $this->json($response, ['status' => false, 'msg' => 'ID da OS não informado', 'id' => 0], 403);
        }
        if (!in_array($tipo, ['servico', 'produto'])) {
            return $this->json($response, ['status' => false, 'msg' => 'Tipo inválido — use servico ou produto', 'id' => 0], 400);
        }
        if ($descricao === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo descrição é obrigatório', 'id' => 0], 400);
        }
        if ($quantidade === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo quantidade é obrigatório', 'id' => 0], 400);
        }
        if ($preco_unit === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo preço unitário é obrigatório', 'id' => 0], 400);
        }

        $subtotal = round($quantidade * $preco_unit, 2);

        try {
            DB::connection()->insert('service_order_items', [
                'service_order_id' => (int) $orderId,
                'tipo'             => $tipo,
                'service_id'       => $tipo === 'servico' && !empty($form['service_id']) ? (int) $form['service_id'] : null,
                'product_id'       => $tipo === 'produto' && !empty($form['product_id']) ? (int) $form['product_id'] : null,
                'descricao'        => $descricao,
                'quantidade'       => $quantidade,
                'preco_unitario'   => $preco_unit,
                'subtotal'         => $subtotal,
                'criado_em'        => $this->now(),
            ]);

            $itemId = (int) DB::connection()->lastInsertId();
            $this->recalcularTotal((int) $orderId);

            return $this->json($response, ['status' => true, 'msg' => 'Item adicionado!', 'id' => $itemId, 'subtotal' => $subtotal], 201);
        } catch (Exception $e) {
            error_log('[ServiceOrder::itemInsert] ' . $e->getMessage());
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
            DB::connection()->delete('service_order_items', ['id' => $itemId, 'service_order_id' => $orderId]);
            $this->recalcularTotal((int) $orderId);

            return $this->json($response, ['status' => true, 'msg' => 'Item removido!', 'id' => (int) $itemId]);
        } catch (Exception $e) {
            error_log('[ServiceOrder::itemDelete] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ── Search ────────────────────────────────────────────────────────────────
    // Usados pela DataTable do modal "Adicionar item" (service-order.js),
    // que espera um array de objetos com as chaves 'id', 'nome' e 'preco'.

    public function searchProducts($request, $response)
    {
        $term  = $request->getQueryParams()['q'] ?? null;
        $query = DB::select('id, nome, preco_venda as preco, unidade')->from('products');

        if (!empty($term)) {
            $query->where('CAST(id AS TEXT) ILIKE :term')->orWhere('nome ILIKE :term')
                ->setParameter('term', '%' . $term . '%');
        }

        $produtos = $query->orderBy('nome', 'ASC')->setMaxResults(20)->fetchAllAssociative();

        $results = array_map(fn($p) => [
            'id'    => $p['id'],
            'nome'  => $p['nome'],
            'preco' => (float) $p['preco'],
        ], $produtos);

        return $this->json($response, ['results' => $results], 200);
    }

    public function searchServices($request, $response)
    {
        $form = $request->getQueryParams();
        $term = $form['q'] ?? '';

        $query = DB::select('id, nome, preco')->from('services');

        if ($term !== '') {
            $query->where('CAST(id AS TEXT) ILIKE :term')
                ->orWhere('nome ILIKE :term')
                ->setParameter('term', '%' . $term . '%');
        }

        $servicos = $query->orderBy('nome', 'ASC')->setFirstResult(0)->setMaxResults(20)->fetchAllAssociative();

        $results = array_map(fn($s) => [
            'id'    => $s['id'],
            'nome'  => $s['nome'],
            'preco' => (float) $s['preco'],
        ], $servicos);

        return $this->json($response, ['results' => $results], 200);
    }

    // ── Payment terms (modal de finalizar) ──────────────────────────────────────
    // Lista as condições de pagamento disponíveis para popular o #payment-terms-list.
    // Cada condição já vem com 'max_parcelas' (quantidade de linhas cadastradas
    // em installment) — o front usa isso pra decidir se mostra o seletor 1x..Nx.
    // Aceita desconto/acrescimo (%) via query string para recalcular o preview das
    // parcelas em tempo real, sem persistir nada — a persistência só acontece em finalize().

    public function paymentTerms($request, $response, $args)
    {
        $orderId = $args['id'] ?? null;
        $query   = $request->getQueryParams();

        $desconto  = isset($query['desconto'])  ? max(0, min(100, (float) $query['desconto']))  : 0.0;
        $acrescimo = isset($query['acrescimo']) ? max(0, min(100, (float) $query['acrescimo'])) : 0.0;

        try {
            $terms = DB::select('id, codigo, titulo, atalho')
                ->from('payment_terms')
                ->orderBy('titulo', 'ASC')
                ->fetchAllAssociative();

            $order = null;
            if ($orderId) {
                $order = DB::select('valor_total')->from('service_orders')
                    ->where('id = :id')
                    ->setParameter('id', $orderId, ParameterType::INTEGER)
                    ->fetchAssociative();
            }

            $subtotal = (float) ($order['valor_total'] ?? 0);
            $totalOS  = round($subtotal - ($subtotal * $desconto / 100) + ($subtotal * $acrescimo / 100), 2);

            $result = [];
            foreach ($terms as $t) {
                $rows = DB::select('parcela, intervalo')->from('installment')
                    ->where('id_pagamento = :id')
                    ->setParameter('id', $t['id'], ParameterType::INTEGER)
                    ->orderBy('parcela', 'ASC')
                    ->fetchAllAssociative();

                $maxParcelas = count($rows);

                // Preview inicial (1x) — o front recalcula via installmentPreview()
                // sempre que o usuário mudar a quantidade de parcelas escolhida.
                $rowsUmaParcela = array_slice($rows, 0, 1);

                $result[] = [
                    'id'           => $t['id'],
                    'codigo'       => $t['codigo'],
                    'titulo'       => $t['titulo'],
                    'atalho'       => $t['atalho'],
                    'max_parcelas' => $maxParcelas,
                    'parcelas'     => $this->calcularParcelas($rowsUmaParcela, $totalOS),
                ];
            }

            return $this->json($response, [
                'status'        => true,
                'data'          => $result,
                'subtotal'      => $subtotal,
                'desconto'      => $desconto,
                'acrescimo'     => $acrescimo,
                'total_liquido' => $totalOS,
            ], 200);
        } catch (Exception $e) {
            error_log('[ServiceOrder::paymentTerms] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // Devolve o preview de parcelas pra uma quantidade específica escolhida
    // pelo usuário (ex: crédito em 7x). Usa só as N primeiras linhas de
    // installment daquela condição — não persiste nada, é só preview.

    public function installmentPreview($request, $response, $args)
    {
        $orderId = $args['id'] ?? null;
        $query   = $request->getQueryParams();

        $idPaymentTerms = $query['id_payment_terms'] ?? null;
        $qtdParcelas    = max(1, (int) ($query['parcelas'] ?? 1));
        $desconto       = isset($query['desconto'])  ? max(0, min(100, (float) $query['desconto']))  : 0.0;
        $acrescimo      = isset($query['acrescimo']) ? max(0, min(100, (float) $query['acrescimo'])) : 0.0;

        if (!$orderId || !$idPaymentTerms) {
            return $this->json($response, ['status' => false, 'msg' => 'Parâmetros inválidos', 'data' => []], 400);
        }

        try {
            $order = DB::select('valor_total')->from('service_orders')
                ->where('id = :id')
                ->setParameter('id', $orderId, ParameterType::INTEGER)
                ->fetchAssociative();

            $subtotal = (float) ($order['valor_total'] ?? 0);
            $totalOS  = round($subtotal - ($subtotal * $desconto / 100) + ($subtotal * $acrescimo / 100), 2);

            $rows = DB::select('parcela, intervalo')->from('installment')
                ->where('id_pagamento = :id')
                ->setParameter('id', $idPaymentTerms, ParameterType::INTEGER)
                ->orderBy('parcela', 'ASC')
                ->setMaxResults($qtdParcelas)
                ->fetchAllAssociative();

            if (empty($rows)) {
                return $this->json($response, ['status' => false, 'msg' => 'Condição sem parcelas cadastradas', 'data' => []], 422);
            }

            $parcelas = $this->calcularParcelas($rows, $totalOS);

            return $this->json($response, [
                'status' => true,
                'data'   => $parcelas,
                'total'  => $totalOS,
            ], 200);
        } catch (Exception $e) {
            error_log('[ServiceOrder::installmentPreview] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ── Finalize ──────────────────────────────────────────────────────────────
    // Aqui é o único lugar onde a negociação (desconto/acréscimo) e a quantidade
    // de parcelas escolhida são de fato validadas e persistidas — junto com o
    // valor_liquido calculado sobre valor_total.

    public function finalize($request, $response)
    {
        $form           = $request->getParsedBody();
        $id             = $form['id'] ?? null;
        $idPaymentTerms = $form['id_payment_terms'] ?? null;
        $qtdParcelas    = max(1, (int) ($form['parcelas'] ?? 1));
        $desconto       = isset($form['desconto'])  ? max(0, min(100, (float) $form['desconto']))  : 0.0;
        $acrescimo      = isset($form['acrescimo']) ? max(0, min(100, (float) $form['acrescimo'])) : 0.0;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID não informado', 'id' => 0], 403);
        }

        if (!$idPaymentTerms) {
            return $this->json($response, ['status' => false, 'msg' => 'Selecione uma condição de pagamento', 'id' => 0], 400);
        }

        try {
            $order = DB::select('status, valor_total')->from('service_orders')
                ->where('id = :id')->andWhere('excluido = false')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();

            if (!$order || in_array($order['status'], ['concluida', 'cancelada'])) {
                return $this->json($response, ['status' => false, 'msg' => 'OS não pode ser concluída', 'id' => 0], 422);
            }

            $paymentTerm = DB::select('id')->from('payment_terms')
                ->where('id = :id')
                ->setParameter('id', $idPaymentTerms, ParameterType::INTEGER)
                ->fetchAssociative();

            if (!$paymentTerm) {
                return $this->json($response, ['status' => false, 'msg' => 'Condição de pagamento inválida', 'id' => 0], 422);
            }

            // Garante que a quantidade de parcelas escolhida realmente existe
            // pra essa condição (não deixa mandar "20x" numa condição de 12x).
            $maxParcelas = (int) DB::select('COUNT(*)')->from('installment')
                ->where('id_pagamento = :id')
                ->setParameter('id', $idPaymentTerms, ParameterType::INTEGER)
                ->fetchOne();

            if ($qtdParcelas > $maxParcelas) {
                return $this->json($response, ['status' => false, 'msg' => 'Quantidade de parcelas inválida para essa condição', 'id' => 0], 422);
            }

            $subtotal     = (float) $order['valor_total'];
            $valorLiquido = round($subtotal - ($subtotal * $desconto / 100) + ($subtotal * $acrescimo / 100), 2);

            DB::connection()->update('service_orders', [
                'status'        => 'concluida',
                'id_pagamento'  => (int) $idPaymentTerms,
                'parcelas'      => $qtdParcelas,
                'desconto'      => $desconto,
                'acrescimo'     => $acrescimo,
                'valor_liquido' => $valorLiquido,
                'concluido_em'  => $this->now(),
                'atualizado_em' => $this->now(),
            ], ['id' => $id]);

            return $this->json($response, ['status' => true, 'msg' => 'OS concluída com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            error_log('[ServiceOrder::finalize] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Divide $total em parcelas iguais (uma por linha de $rows), arredondando
     * cada uma para 2 casas decimais e jogando a sobra de centavos (resultante
     * do arredondamento) na última parcela — garante que soma(parcelas) === $total
     * sempre, mesmo quando a divisão não é exata (ex: 47.04 / 3).
     *
     * @param array $rows Lista de linhas com 'parcela' e 'intervalo'
     * @return array Lista de parcelas com 'parcela', 'intervalo' e 'valor'
     */
    private function calcularParcelas(array $rows, float $total): array
    {
        $qtd = count($rows);

        if ($qtd === 0) {
            return [];
        }

        $valorBase   = round($total / $qtd, 2);
        $parcelas    = [];
        $somaParcial = 0.0;

        foreach ($rows as $i => $r) {
            $isUltima = ($i === $qtd - 1);
            // a última parcela recebe o que sobrar, não o valor base —
            // isso absorve os centavos perdidos no arredondamento das anteriores
            $valor = $isUltima ? round($total - $somaParcial, 2) : $valorBase;

            $parcelas[] = [
                'parcela'   => $r['parcela'],
                'intervalo' => $r['intervalo'],
                'valor'     => $valor,
            ];

            $somaParcial += $valor;
        }

        return $parcelas;
    }

    private function recalcularTotal(int $orderId): void
    {
        $total = (float) DB::select('COALESCE(SUM(subtotal), 0)')
            ->from('service_order_items')
            ->where('service_order_id = :id')
            ->setParameter('id', $orderId, ParameterType::INTEGER)
            ->fetchOne();

        DB::connection()->update('service_orders', ['valor_total' => $total, 'atualizado_em' => $this->now()], ['id' => $orderId]);
    }

    private function gerarNumeroOS(): string
    {
        $ano   = (new DateTime())->format('Y');
        $total = (int) DB::select('COUNT(*)')->from('service_orders')
            ->where('numero LIKE :p')->setParameter('p', "OS-{$ano}-%")->fetchOne();

        do {
            $numero = "OS-{$ano}-" . str_pad((string) ++$total, 5, '0', STR_PAD_LEFT);
            $existe = DB::select('id')->from('service_orders')
                ->where('numero = :n')->setParameter('n', $numero)->fetchOne();
        } while ($existe);

        return $numero;
    }

    private function now(): string
    {
        return (new DateTime())->format('Y-m-d H:i:s');
    }
}