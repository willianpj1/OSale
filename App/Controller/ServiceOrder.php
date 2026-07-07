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

        // Agora uma OS finalizada pode ter N formas de pagamento (split).
        // Cada linha de service_order_payments vira um "bloco" com título +
        // suas próprias parcelas calculadas sobre a fatia (valor) daquele split.
        $paymentSplits = [];

        if ($id) {
            $splits = DB::select('sop.*, pt.titulo, pt.codigo, pt.atalho')
                ->from('service_order_payments', 'sop')
                ->leftJoin('sop', 'payment_terms', 'pt', 'pt.id = sop.id_pagamento')
                ->where('sop.service_order_id = :id')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->orderBy('sop.id', 'ASC')
                ->fetchAllAssociative();

            foreach ($splits as $split) {
                $rows = DB::select('parcela, intervalo')->from('installment')
                    ->where('id_pagamento = :id')
                    ->setParameter('id', $split['id_pagamento'], ParameterType::INTEGER)
                    ->orderBy('parcela', 'ASC')
                    ->setMaxResults((int) $split['parcelas'])
                    ->fetchAllAssociative();

                $paymentSplits[] = [
                    'titulo'       => $split['titulo'] ?: $split['codigo'],
                    'parcelas_qtd' => (int) $split['parcelas'],
                    'valor'        => (float) $split['valor'],
                    'installments' => $this->calcularParcelas($rows, (float) $split['valor']),
                ];
            }
        }

        return $this->getTwig()
            ->render($response, $this->setView('service-order'), [
                'titulo'         => 'Ordem de Serviço',
                'id'             => $id,
                'action'         => $action,
                'order'          => $order,
                'items'          => $items,
                'customers'      => $customers,
                'technicians'    => $technicians,
                'paymentSplits'  => $paymentSplits,
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
            'orcamento'       => '<span class="badge bg-secondary">Orçamento</span>',
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
                $v['status'] === 'orcamento'
                    ? "<td>
                        <a class='btn btn-sm btn-warning' href='/os/detalhes/{$v['id']}'><i class='bi bi-eye'></i> Visualizar</a>
                        <a class='btn btn-sm btn-outline-info' href='/relatorio/os/{$v['id']}'><i class='bi bi-printer-fill'></i> Imprimir</a>
                        <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$v['id']});'><i class='bi bi-trash'></i> Excluir</button>
                    </td>"
                    : (in_array($v['status'], ['em_andamento', 'concluida', 'cancelada'])
                        ? "<td>
                            <a class='btn btn-sm btn-warning' href='/os/detalhes/{$v['id']}'><i class='bi bi-eye'></i> Visualizar</a>
                            <a class='btn btn-sm btn-outline-info' href='/relatorio/os/{$v['id']}'><i class='bi bi-printer-fill'></i> Imprimir</a>
                        </td>"
                        : "<td>
                            <a class='btn btn-sm btn-warning' href='/os/detalhes/{$v['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
                            <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$v['id']});'><i class='bi bi-trash'></i> Excluir</button>
                        </td>")
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

        // Ao adicionar produto na OS, valida contra o estoque disponível.
        // Não reserva/baixa nada aqui ainda — a baixa real só acontece no
        // finalize(), pra não travar estoque de orçamentos que não viram venda.
        // Mas bloquear o item aqui evita montar uma OS com item que já
        // sabemos que não vai poder ser vendido.
        if ($tipo === 'produto' && !empty($form['product_id'])) {
            $produto = DB::select('estoque_atual, nome')
                ->from('products')
                ->where('id = :id')
                ->setParameter('id', (int) $form['product_id'], ParameterType::INTEGER)
                ->fetchAssociative();

            if ($produto && (float) $quantidade > (float) $produto['estoque_atual']) {
                return $this->json($response, [
                    'status' => false,
                    'msg' => "Estoque insuficiente de \"{$produto['nome']}\" (disponível: " . number_format((float)$produto['estoque_atual'], 1, '.', '') . ")",
                    'id'     => 0,
                ], 422);
            }
        }

        $subtotal = round($quantidade * $preco_unit, 2);

        try {
            $FieldAndValues = [
                'service_order_id' => (int) $orderId,
                'tipo'             => $tipo,
                #'service_id'       => $tipo === 'servico' && !empty($form['service_id']) ? (int) $form['service_id'] : null,
                #'product_id'       => $tipo === 'produto' && !empty($form['product_id']) ? (int) $form['product_id'] : null,
                'descricao'        => $descricao,
                'quantidade'       => $quantidade,
                'preco_unitario'   => $preco_unit,
                'subtotal'         => $subtotal,
                'criado_em'        => $this->now(),
            ];
            if ($tipo === 'servico') {
                $FieldAndValues['product_id'] = null;
                $FieldAndValues['service_id'] = $form['service_id'] ?? null;
            } elseif ($tipo === 'produto') {
                $FieldAndValues['service_id'] = null;
                $FieldAndValues['product_id'] = $form['product_id'] ?? null;
            }
            DB::connection()->insert('service_order_items', $FieldAndValues, [
                'service_order_id' => ParameterType::INTEGER,
                'service_id'       => ParameterType::INTEGER,
                'product_id'       => ParameterType::INTEGER,
                'quantidade'       => ParameterType::STRING,
                'preco_unitario'   => ParameterType::STRING,
                'subtotal'         => ParameterType::STRING,
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

        // Não permite excluir item de OS já concluída — a baixa de estoque já
        // foi feita e removê-lo aqui deixaria stock_movements dessincronizado
        // do que realmente está na OS.
        $order = DB::select('status')->from('service_orders')
            ->where('id = :id')
            ->setParameter('id', $orderId, ParameterType::INTEGER)
            ->fetchAssociative();

        if ($order && $order['status'] === 'concluida') {
            return $this->json($response, ['status' => false, 'msg' => 'Não é possível remover itens de uma OS já concluída', 'id' => 0], 422);
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

    public function searchProducts($request, $response)
    {
        $term  = $request->getQueryParams()['q'] ?? null;
        $query = DB::select('id, nome, preco_venda as preco, unidade, estoque_atual')->from('products')
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
            'preco'         => (float) $p['preco'],
            'estoque_atual' => (float) $p['estoque_atual'],
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

                $result[] = [
                    'id'           => $t['id'],
                    'codigo'       => $t['codigo'],
                    'titulo'       => $t['titulo'],
                    'atalho'       => $t['atalho'],
                    'max_parcelas' => $maxParcelas,
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

    public function installmentPreview($request, $response, $args)
    {
        $query = $request->getQueryParams();

        $idPaymentTerms = $query['id_payment_terms'] ?? null;
        $qtdParcelas    = max(1, (int) ($query['parcelas'] ?? 1));
        $valor          = (float) ($query['valor'] ?? 0);

        if (!$idPaymentTerms || $valor <= 0) {
            return $this->json($response, ['status' => false, 'msg' => 'Parâmetros inválidos', 'data' => []], 400);
        }

        try {
            $rows = DB::select('parcela, intervalo')->from('installment')
                ->where('id_pagamento = :id')
                ->setParameter('id', $idPaymentTerms, ParameterType::INTEGER)
                ->orderBy('parcela', 'ASC')
                ->setMaxResults($qtdParcelas)
                ->fetchAllAssociative();

            if (empty($rows)) {
                return $this->json($response, ['status' => false, 'msg' => 'Condição sem parcelas cadastradas', 'data' => []], 422);
            }

            $parcelas = $this->calcularParcelas($rows, $valor);

            return $this->json($response, [
                'status' => true,
                'data'   => $parcelas,
                'total'  => $valor,
            ], 200);
        } catch (Exception $e) {
            error_log('[ServiceOrder::installmentPreview] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ── Finalize ──────────────────────────────────────────────────────────────
    // Agora recebe uma LISTA de formas de pagamento (split). Cada item cobre uma
    // fatia em R$ do valor_liquido total; a soma das fatias precisa bater
    // exatamente com o valor_liquido calculado a partir de desconto/acréscimo.
    //
    // Além disso, a finalização agora também é o ponto único de baixa de
    // estoque: todo item tipo='produto' da OS gera um stock_movement SAIDA/VENDA
    // dentro da MESMA transação — ou a OS conclui E dá baixa, ou nenhuma das duas.

    public function finalize($request, $response)
    {
        $form      = $request->getParsedBody();
        $id        = $form['id'] ?? null;
        $desconto  = isset($form['desconto'])  ? max(0, min(100, (float) $form['desconto']))  : 0.0;
        $acrescimo = isset($form['acrescimo']) ? max(0, min(100, (float) $form['acrescimo'])) : 0.0;

        $payments = $form['payments'] ?? null;
        if (is_string($payments)) {
            $payments = json_decode($payments, true);
        }

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID não informado', 'id' => 0], 403);
        }

        if (empty($payments) || !is_array($payments)) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe ao menos uma forma de pagamento', 'id' => 0], 400);
        }

        try {
            $order = DB::select('status, valor_total')->from('service_orders')
                ->where('id = :id')->andWhere('excluido = false')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();

            if (!$order || in_array($order['status'], ['concluida', 'cancelada'])) {
                return $this->json($response, ['status' => false, 'msg' => 'OS não pode ser concluída', 'id' => 0], 422);
            }

            $subtotal     = (float) $order['valor_total'];
            $valorLiquido = round($subtotal - ($subtotal * $desconto / 100) + ($subtotal * $acrescimo / 100), 2);

            // Valida cada split e soma os valores em centavos pra evitar erro de
            // ponto flutuante na comparação com valorLiquido.
            $somaSplitsCentavos = 0;
            $splitsValidados    = [];

            foreach ($payments as $p) {
                $idPaymentTerms = $p['id_payment_terms'] ?? null;
                $qtdParcelas    = max(1, (int) ($p['parcelas'] ?? 1));
                $valor          = round((float) ($p['valor'] ?? 0), 2);

                if (!$idPaymentTerms || $valor <= 0) {
                    return $this->json($response, ['status' => false, 'msg' => 'Forma de pagamento inválida no split', 'id' => 0], 422);
                }

                $paymentTerm = DB::select('id')->from('payment_terms')
                    ->where('id = :id')
                    ->setParameter('id', $idPaymentTerms, ParameterType::INTEGER)
                    ->fetchAssociative();

                if (!$paymentTerm) {
                    return $this->json($response, ['status' => false, 'msg' => 'Condição de pagamento inválida', 'id' => 0], 422);
                }

                $maxParcelas = (int) DB::select('COUNT(*)')->from('installment')
                    ->where('id_pagamento = :id')
                    ->setParameter('id', $idPaymentTerms, ParameterType::INTEGER)
                    ->fetchOne();

                if ($qtdParcelas > $maxParcelas) {
                    return $this->json($response, ['status' => false, 'msg' => 'Quantidade de parcelas inválida para uma das formas selecionadas', 'id' => 0], 422);
                }

                $somaSplitsCentavos += (int) round($valor * 100);
                $splitsValidados[]   = [
                    'id_pagamento' => (int) $idPaymentTerms,
                    'parcelas'     => $qtdParcelas,
                    'valor'        => $valor,
                ];
            }

            $totalLiquidoCentavos = (int) round($valorLiquido * 100);

            if ($somaSplitsCentavos !== $totalLiquidoCentavos) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'A soma das formas de pagamento (R$ ' . number_format($somaSplitsCentavos / 100, 2, ',', '.')
                        . ') precisa ser igual ao total da OS (R$ ' . number_format($valorLiquido, 2, ',', '.') . ')',
                    'id'     => 0,
                ], 422);
            }

            // Carrega os itens tipo='produto' ANTES de abrir a transação, só
            // pra validar rápido se algum já ficou sem estoque suficiente
            // entre o momento em que foi adicionado na OS e agora.
            $itensProduto = DB::select('id, product_id, quantidade, descricao')
                ->from('service_order_items')
                ->where('service_order_id = :id')
                ->andWhere('tipo = :tipo')
                ->andWhere('product_id IS NOT NULL')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->setParameter('tipo', 'produto')
                ->fetchAllAssociative();

            $conn = DB::connection();
            $conn->beginTransaction();

            try {
                // ── Baixa de estoque (venda) ─────────────────────────────
                // Lock pessimista linha a linha evita duas OSs concorrentes
                // vendendo o mesmo saldo de estoque ao mesmo tempo.
                foreach ($itensProduto as $item) {
                    $produto = $conn->fetchAssociative(
                        'SELECT estoque_atual FROM products WHERE id = :id FOR UPDATE',
                        ['id' => (int) $item['product_id']]
                    );

                    if (!$produto) {
                        throw new Exception("Produto \"{$item['descricao']}\" não encontrado");
                    }

                    $estoqueAnterior = (float) $produto['estoque_atual'];
                    $quantidade      = (float) $item['quantidade'];

                    if ($quantidade > $estoqueAnterior) {
                        throw new Exception("Estoque insuficiente de \"{$item['descricao']}\" (disponível: {$estoqueAnterior})");
                    }

                    $estoquePosterior = $estoqueAnterior - $quantidade;

                    $conn->insert('stock_movements', [
                        'product_id'            => (int) $item['product_id'],
                        'service_order_item_id' => (int) $item['id'],
                        'tipo'                  => 'SAIDA',
                        'origem'                => 'VENDA',
                        'quantidade'            => $quantidade,
                        'estoque_anterior'      => $estoqueAnterior,
                        'estoque_posterior'     => $estoquePosterior,
                        'criado_por'            => $_SESSION['user']['id'] ?? null,
                        'criado_em'             => $this->now(),
                    ]);
                    // trigger fn_apply_stock_movement já atualiza products.estoque_atual
                }

                // ── Formas de pagamento (split) ──────────────────────────
                // Remove splits antigos, se essa OS já teve uma tentativa de
                // finalização interrompida (mantém idempotente reenviar o finalize).
                $conn->delete('service_order_payments', ['service_order_id' => (int) $id]);

                foreach ($splitsValidados as $split) {
                    $conn->insert('service_order_payments', [
                        'service_order_id' => (int) $id,
                        'id_pagamento'      => $split['id_pagamento'],
                        'parcelas'          => $split['parcelas'],
                        'valor'             => $split['valor'],
                        'criado_em'         => $this->now(),
                    ]);
                }

                $unicaForma = count($splitsValidados) === 1;

                $conn->update('service_orders', [
                    'status'        => 'concluida',
                    'id_pagamento'  => $unicaForma ? $splitsValidados[0]['id_pagamento'] : null,
                    'parcelas'      => $unicaForma ? $splitsValidados[0]['parcelas']     : null,
                    'desconto'      => $desconto,
                    'acrescimo'     => $acrescimo,
                    'valor_liquido' => $valorLiquido,
                    'concluido_em'  => $this->now(),
                    'atualizado_em' => $this->now(),
                ], ['id' => $id]);

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }

            return $this->json($response, ['status' => true, 'msg' => 'OS concluída com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            error_log('[ServiceOrder::finalize] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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
    public function cancel($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'ID não informado', 'id' => 0], 403);
        }

        try {
            $order = DB::select('status')->from('service_orders')
                ->where('id = :id')->andWhere('excluido = false')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();

            if (!$order) {
                return $this->json($response, ['status' => false, 'msg' => 'OS não encontrada', 'id' => 0], 404);
            }

            if (in_array($order['status'], ['concluida', 'cancelada'])) {
                return $this->json($response, ['status' => false, 'msg' => 'OS já está concluída ou cancelada', 'id' => 0], 422);
            }

            DB::connection()->update('service_orders', [
                'status'        => 'cancelada',
                'atualizado_em' => $this->now(),
            ], [
                'id'       => (int) $id,
                'excluido' => false,
            ], [
                'excluido' => ParameterType::BOOLEAN,
            ]);

            return $this->json($response, ['status' => true, 'msg' => 'OS cancelada com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            error_log('[ServiceOrder::cancel] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao cancelar: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
}
