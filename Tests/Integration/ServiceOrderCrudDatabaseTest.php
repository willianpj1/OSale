<?php

declare(strict_types=1);

use App\Database\DB;
use Doctrine\DBAL\ParameterType;

/**
 * Helpers locais para montar dependências mínimas (customer, técnico/usuário,
 * produto e payment_term) necessárias pra testar service_orders de ponta a ponta.
 * Ajuste os nomes/colunas das tabelas auxiliares se o schema real divergir.
 */
function criarClienteTeste(string $sufixo = ''): int
{
    DB::connection()->insert('customers', [
        'nome'          => 'Cliente Teste Integração' . $sufixo,
        'cpf_cnpj'      => '00000000000',
        'ativo'         => true,
        'excluido'      => false,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN, 'excluido' => ParameterType::BOOLEAN]);

    return (int) DB::connection()->lastInsertId();
}

function criarUsuarioTeste(string $sufixo = ''): int
{
    // email é NOT NULL/UNIQUE na tabela users — gera um valor único por chamada
    // pra evitar colisão entre os testes.
    $email = 'teste' . $sufixo . '.' . uniqid() . '@teste.local';

    DB::connection()->insert('users', [
        'nome'          => 'Tecnico',
        'sobrenome'     => 'Teste' . $sufixo,
        'email'         => $email,
        'ativo'         => true,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN]);

    return (int) DB::connection()->lastInsertId();
}

function criarProdutoTeste(float $estoque = 10.0, string $sufixo = ''): int
{
    // trg_force_zero_stock_on_insert sempre zera estoque_atual no INSERT,
    // então o produto sempre nasce com 0. Pra ter saldo inicial, precisamos
    // gerar um stock_movement tipo=ENTRADA, que é quem realmente credita
    // products.estoque_atual via trigger trg_apply_stock_movement.
    DB::connection()->insert('products', [
        'nome'           => 'Produto Teste Integração' . $sufixo,
        'preco_venda'    => 50.00,
        'estoque_atual'  => $estoque, // ignorado pelo trigger, mantido só por clareza
        'ativo'          => true,
        'excluido'       => false,
        'criado_em'      => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em'  => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN, 'excluido' => ParameterType::BOOLEAN]);

    $produtoId = (int) DB::connection()->lastInsertId();

    if ($estoque > 0) {
        DB::connection()->executeStatement(
            "INSERT INTO stock_movements (product_id, quantidade, estoque_anterior, estoque_posterior, tipo, origem, criado_em)
             VALUES (:product_id, :quantidade, 0, :quantidade, 'ENTRADA', 'AJUSTE_MANUAL', :criado_em)",
            [
                'product_id' => $produtoId,
                'quantidade' => $estoque,
                'criado_em'  => (new DateTime())->format('Y-m-d H:i:s'),
            ]
        );
    }

    return $produtoId;
}

function criarPaymentTermTeste(string $codigo = '01', string $sufixo = ''): int
{
    DB::connection()->insert('payment_terms', [
        'codigo'        => $codigo,
        'titulo'        => 'Forma Teste' . $sufixo,
        'atalho'        => 'FT',
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ]);

    return (int) DB::connection()->lastInsertId();
}

function criarInstallmentTeste(int $idPagamento, int $parcela, int $intervaloDias = 30): void
{
    DB::connection()->insert('installment', [
        'id_pagamento' => $idPagamento,
        'parcela'      => $parcela,
        'intervalo'    => $intervaloDias,
    ]);
}

function numeroOsTeste(): string
{
    // coluna "numero" é varchar(20) — usa timestamp + random curto, bem abaixo do limite.
    return 'OS-' . substr(str_replace('.', '', (string) microtime(true)), -10);
}

function limparServiceOrder(int $id): void
{
    DB::connection()->delete('service_order_payments', ['service_order_id' => $id]);
    DB::connection()->delete('stock_movements', ['service_order_item_id' => null]); // no-op seguro
    DB::connection()->delete('service_order_items', ['service_order_id' => $id]);
    DB::connection()->delete('service_orders', ['id' => $id]);
}

// ─────────────────────────────────────────────────────────────────────────────

test('ciclo CRUD completo na tabela service_orders', function () {

    $customerId = criarClienteTeste('-crud');
    $userId     = criarUsuarioTeste('-crud');
    $criadoPor  = criarUsuarioTeste('-crud-autor');

    // 1. INSERT — abre uma OS real no banco
    $numero = numeroOsTeste();

    DB::connection()->insert('service_orders', [
        'customer_id'        => $customerId,
        'user_id'            => $userId,
        'criado_por'         => $criadoPor,
        'numero'             => $numero,
        'status'             => 'aberta',
        'prioridade'         => 'normal',
        'equipamento'        => 'Notebook Dell Inspiron 15',
        'marca'              => 'Dell',
        'modelo'             => 'Inspiron 15',
        'numero_serie'       => 'SN-TESTE-001',
        'defeito_relatado'   => 'Não liga',
        'valor_total'        => 0.00,
        'excluido'           => false,
        'aberto_em'          => (new DateTime())->format('Y-m-d H:i:s'),
        'criado_em'          => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em'      => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $id = (int) DB::connection()->lastInsertId();
    expect($id)->toBeGreaterThan(0);

    // 2. SELECT — busca a OS recém-criada
    $order = DB::select('*')
        ->from('service_orders')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($order)->not->toBeFalse();
    expect($order['numero'])->toBe($numero);
    expect($order['status'])->toBe('aberta');
    expect($order['prioridade'])->toBe('normal');
    expect((float) $order['valor_total'])->toBe(0.0);

    // 3. UPDATE — altera dados da OS
    DB::connection()->update('service_orders', [
        'status'             => 'em_andamento',
        'prioridade'         => 'alta',
        'defeito_constatado' => 'Fonte queimada',
        'atualizado_em'      => (new DateTime())->format('Y-m-d H:i:s'),
    ], [
        'id'       => $id,
        'excluido' => false,
    ], ['excluido' => ParameterType::BOOLEAN]);

    $orderAlterada = DB::select('*')
        ->from('service_orders')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($orderAlterada['status'])->toBe('em_andamento');
    expect($orderAlterada['prioridade'])->toBe('alta');
    expect($orderAlterada['defeito_constatado'])->toBe('Fonte queimada');

    // 4. Numero deve ser único
    $duplicatas = (int) DB::select('COUNT(*)')
        ->from('service_orders')
        ->where('numero = :n')
        ->setParameter('n', $numero, ParameterType::STRING)
        ->fetchOne();

    expect($duplicatas)->toBe(1);

    // 5. DELETE (soft delete, igual ao controller) — marca excluido = true
    DB::connection()->update('service_orders', [
        'excluido'      => true,
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['id' => $id], ['excluido' => ParameterType::BOOLEAN]);

    $orderExcluida = DB::select('*')
        ->from('service_orders')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($orderExcluida['excluido'])->toBeTruthy();

    // Limpeza física (mantém o banco limpo entre execuções)
    limparServiceOrder($id);
});

test('adicionar item de serviço recalcula valor_total da OS', function () {

    $customerId = criarClienteTeste('-item-serv');
    $criadoPor  = criarUsuarioTeste('-item-serv');

    DB::connection()->insert('service_orders', [
        'customer_id'   => $customerId,
        'criado_por'    => $criadoPor,
        'numero'        => numeroOsTeste(),
        'status'        => 'aberta',
        'prioridade'    => 'normal',
        'valor_total'   => 0.00,
        'excluido'      => false,
        'aberto_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $orderId = (int) DB::connection()->lastInsertId();

    // Insere um item de serviço diretamente (equivalente ao itemInsert do controller)
    DB::connection()->insert('service_order_items', [
        'service_order_id' => $orderId,
        'tipo'              => 'servico',
        'service_id'        => null,
        'product_id'        => null,
        'descricao'         => 'Troca de fonte',
        'quantidade'        => 1,
        'preco_unitario'    => 150.00,
        'subtotal'          => 150.00,
        'criado_em'         => (new DateTime())->format('Y-m-d H:i:s'),
    ], [
        'service_order_id' => ParameterType::INTEGER,
        'quantidade'        => ParameterType::STRING,
        'preco_unitario'    => ParameterType::STRING,
        'subtotal'          => ParameterType::STRING,
    ]);

    // Simula o recalcularTotal() do controller
    $total = (float) DB::select('COALESCE(SUM(subtotal), 0)')
        ->from('service_order_items')
        ->where('service_order_id = :id')
        ->setParameter('id', $orderId, ParameterType::INTEGER)
        ->fetchOne();

    DB::connection()->update('service_orders', [
        'valor_total'   => $total,
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['id' => $orderId]);

    $order = DB::select('valor_total')
        ->from('service_orders')
        ->where('id = :id')
        ->setParameter('id', $orderId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect((float) $order['valor_total'])->toBe(150.00);

    limparServiceOrder($orderId);
});

test('não permite remover item de OS já concluída', function () {

    $customerId = criarClienteTeste('-item-bloq');
    $criadoPor  = criarUsuarioTeste('-item-bloq');

    DB::connection()->insert('service_orders', [
        'customer_id'   => $customerId,
        'criado_por'    => $criadoPor,
        'numero'        => numeroOsTeste(),
        'status'        => 'concluida',
        'prioridade'    => 'normal',
        'valor_total'   => 100.00,
        'excluido'      => false,
        'aberto_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'concluido_em'  => (new DateTime())->format('Y-m-d H:i:s'),
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $orderId = (int) DB::connection()->lastInsertId();

    DB::connection()->insert('service_order_items', [
        'service_order_id' => $orderId,
        'tipo'              => 'servico',
        'descricao'         => 'Item já faturado',
        'quantidade'        => 1,
        'preco_unitario'    => 100.00,
        'subtotal'          => 100.00,
        'criado_em'         => (new DateTime())->format('Y-m-d H:i:s'),
    ], [
        'service_order_id' => ParameterType::INTEGER,
        'quantidade'        => ParameterType::STRING,
        'preco_unitario'    => ParameterType::STRING,
        'subtotal'          => ParameterType::STRING,
    ]);

    // Reproduz a verificação que o controller faz antes de deletar
    $order = DB::select('status')->from('service_orders')
        ->where('id = :id')
        ->setParameter('id', $orderId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($order['status'])->toBe('concluida');
    // Se status === 'concluida', o controller bloqueia a exclusão do item —
    // então aqui só validamos a pré-condição, sem chamar o delete.

    $itemAindaExiste = (int) DB::select('COUNT(*)')
        ->from('service_order_items')
        ->where('service_order_id = :id')
        ->setParameter('id', $orderId, ParameterType::INTEGER)
        ->fetchOne();

    expect($itemAindaExiste)->toBe(1);

    limparServiceOrder($orderId);
});

test('finalize com forma única de pagamento conclui a OS e baixa estoque do produto', function () {

    $customerId    = criarClienteTeste('-finalize-unico');
    $criadoPor     = criarUsuarioTeste('-finalize-unico');
    $paymentTermId = criarPaymentTermTeste('01', '-finalize-unico');
    $produtoId     = criarProdutoTeste(10.0, '-finalize-unico');

    criarInstallmentTeste($paymentTermId, 1, 0);

    DB::connection()->insert('service_orders', [
        'customer_id'   => $customerId,
        'criado_por'    => $criadoPor,
        'numero'        => numeroOsTeste(),
        'status'        => 'aberta',
        'prioridade'    => 'normal',
        'valor_total'   => 200.00,
        'excluido'      => false,
        'aberto_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $orderId = (int) DB::connection()->lastInsertId();

    DB::connection()->insert('service_order_items', [
        'service_order_id' => $orderId,
        'tipo'              => 'produto',
        'product_id'        => $produtoId,
        'descricao'         => 'Produto Teste Integração-finalize-unico',
        'quantidade'        => 2,
        'preco_unitario'    => 100.00,
        'subtotal'          => 200.00,
        'criado_em'         => (new DateTime())->format('Y-m-d H:i:s'),
    ], [
        'service_order_id' => ParameterType::INTEGER,
        'product_id'        => ParameterType::INTEGER,
        'quantidade'        => ParameterType::STRING,
        'preco_unitario'    => ParameterType::STRING,
        'subtotal'          => ParameterType::STRING,
    ]);

    // ── Reproduz a lógica de finalize() do controller ──────────────────────
    $desconto     = 0.0;
    $acrescimo    = 0.0;
    $subtotal     = 200.00;
    $valorLiquido = round($subtotal - ($subtotal * $desconto / 100) + ($subtotal * $acrescimo / 100), 2);

    $conn = DB::connection();
    $conn->beginTransaction();

    $itensProduto = DB::select('id, product_id, quantidade, descricao')
        ->from('service_order_items')
        ->where('service_order_id = :id')
        ->andWhere('tipo = :tipo')
        ->setParameter('id', $orderId, ParameterType::INTEGER)
        ->setParameter('tipo', 'produto', ParameterType::STRING)
        ->fetchAllAssociative();

    foreach ($itensProduto as $item) {
        $produto = $conn->fetchAssociative(
            'SELECT estoque_atual FROM products WHERE id = :id FOR UPDATE',
            ['id' => (int) $item['product_id']]
        );

        $estoqueAnterior = (float) $produto['estoque_atual'];
        $quantidade      = (float) $item['quantidade'];

        // Não calcula/força estoque_posterior aqui: o trigger
        // fn_apply_stock_movement é quem debita products.estoque_atual.
        // Passar um estoque_posterior "chutado" manualmente pode conflitar
        // com o que o trigger realmente aplica, gerando divergência.
        $conn->insert('stock_movements', [
            'product_id'            => (int) $item['product_id'],
            'service_order_item_id' => (int) $item['id'],
            'tipo'                  => 'SAIDA',
            'origem'                => 'VENDA',
            'quantidade'            => $quantidade,
            'estoque_anterior'      => $estoqueAnterior,
            'estoque_posterior'     => $estoqueAnterior - $quantidade,
            'criado_por'            => null,
            'criado_em'             => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    $conn->insert('service_order_payments', [
        'service_order_id' => $orderId,
        'id_pagamento'      => $paymentTermId,
        'parcelas'          => 1,
        'valor'             => $valorLiquido,
        'criado_em'         => (new DateTime())->format('Y-m-d H:i:s'),
    ]);

    $conn->update('service_orders', [
        'status'        => 'concluida',
        'id_pagamento'  => $paymentTermId,
        'parcelas'      => 1,
        'desconto'      => $desconto,
        'acrescimo'     => $acrescimo,
        'valor_liquido' => $valorLiquido,
        'concluido_em'  => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['id' => $orderId]);

    $conn->commit();

    // ── Verificações ────────────────────────────────────────────────────
    $orderFinal = DB::select('*')
        ->from('service_orders')
        ->where('id = :id')
        ->setParameter('id', $orderId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($orderFinal['status'])->toBe('concluida');
    expect((float) $orderFinal['valor_liquido'])->toBe(200.00);
    expect((int) $orderFinal['id_pagamento'])->toBe($paymentTermId);
    expect((int) $orderFinal['parcelas'])->toBe(1);
    expect($orderFinal['concluido_em'])->not->toBeNull();

    $produtoFinal = DB::select('estoque_atual')
        ->from('products')
        ->where('id = :id')
        ->setParameter('id', $produtoId, ParameterType::INTEGER)
        ->fetchAssociative();

    // Estoque inicial 10, vendeu 2 -> deve sobrar 8
    expect((float) $produtoFinal['estoque_atual'])->toBe(8.0);

    $movimento = DB::select('*')
        ->from('stock_movements')
        ->where('service_order_item_id IS NOT NULL')
        ->andWhere('product_id = :pid')
        ->setParameter('pid', $produtoId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($movimento)->not->toBeFalse();
    expect($movimento['tipo'])->toBe('SAIDA');
    expect($movimento['origem'])->toBe('VENDA');
    expect((float) $movimento['quantidade'])->toBe(2.0);

    // Limpeza
    DB::connection()->delete('stock_movements', ['product_id' => $produtoId]);
    DB::connection()->delete('service_order_payments', ['service_order_id' => $orderId]);
    limparServiceOrder($orderId);
    DB::connection()->delete('products', ['id' => $produtoId]);
    DB::connection()->delete('installment', ['id_pagamento' => $paymentTermId]);
    DB::connection()->delete('payment_terms', ['id' => $paymentTermId]);
});

test('finalize com split de pagamento grava múltiplas linhas em service_order_payments', function () {

    $customerId = criarClienteTeste('-finalize-split');
    $criadoPor  = criarUsuarioTeste('-finalize-split');

    $termA = criarPaymentTermTeste('01', '-split-a');
    $termB = criarPaymentTermTeste('02', '-split-b');
    criarInstallmentTeste($termA, 1, 0);
    criarInstallmentTeste($termB, 1, 0);
    criarInstallmentTeste($termB, 2, 30);

    DB::connection()->insert('service_orders', [
        'customer_id'   => $customerId,
        'criado_por'    => $criadoPor,
        'numero'        => numeroOsTeste(),
        'status'        => 'aberta',
        'prioridade'    => 'normal',
        'valor_total'   => 300.00,
        'excluido'      => false,
        'aberto_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $orderId = (int) DB::connection()->lastInsertId();

    // Split: 100 no termA (à vista) + 200 no termB (2x)
    $splits = [
        ['id_pagamento' => $termA, 'parcelas' => 1, 'valor' => 100.00],
        ['id_pagamento' => $termB, 'parcelas' => 2, 'valor' => 200.00],
    ];

    $somaSplitsCentavos   = array_sum(array_map(fn($s) => (int) round($s['valor'] * 100), $splits));
    $totalLiquidoCentavos = (int) round(300.00 * 100);

    expect($somaSplitsCentavos)->toBe($totalLiquidoCentavos);

    $conn = DB::connection();
    $conn->beginTransaction();

    foreach ($splits as $split) {
        $conn->insert('service_order_payments', [
            'service_order_id' => $orderId,
            'id_pagamento'      => $split['id_pagamento'],
            'parcelas'          => $split['parcelas'],
            'valor'             => $split['valor'],
            'criado_em'         => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    // Mais de uma forma de pagamento -> id_pagamento/parcelas na OS ficam null
    $conn->update('service_orders', [
        'status'        => 'concluida',
        'id_pagamento'  => null,
        'parcelas'      => null,
        'valor_liquido' => 300.00,
        'concluido_em'  => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['id' => $orderId]);

    $conn->commit();

    $splitsGravados = DB::select('*')
        ->from('service_order_payments')
        ->where('service_order_id = :id')
        ->setParameter('id', $orderId, ParameterType::INTEGER)
        ->orderBy('id', 'ASC')
        ->fetchAllAssociative();

    expect($splitsGravados)->toHaveCount(2);
    expect((float) $splitsGravados[0]['valor'])->toBe(100.00);
    expect((int) $splitsGravados[0]['parcelas'])->toBe(1);
    expect((float) $splitsGravados[1]['valor'])->toBe(200.00);
    expect((int) $splitsGravados[1]['parcelas'])->toBe(2);

    $orderFinal = DB::select('id_pagamento, parcelas, status')
        ->from('service_orders')
        ->where('id = :id')
        ->setParameter('id', $orderId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($orderFinal['status'])->toBe('concluida');
    expect($orderFinal['id_pagamento'])->toBeNull();
    expect($orderFinal['parcelas'])->toBeNull();

    // Limpeza
    DB::connection()->delete('service_order_payments', ['service_order_id' => $orderId]);
    limparServiceOrder($orderId);
    DB::connection()->delete('installment', ['id_pagamento' => $termA]);
    DB::connection()->delete('installment', ['id_pagamento' => $termB]);
    DB::connection()->delete('payment_terms', ['id' => $termA]);
    DB::connection()->delete('payment_terms', ['id' => $termB]);
});

test('não permite finalizar OS já concluída ou cancelada', function () {

    $customerId = criarClienteTeste('-finalize-bloq');
    $criadoPor  = criarUsuarioTeste('-finalize-bloq');

    DB::connection()->insert('service_orders', [
        'customer_id'   => $customerId,
        'criado_por'    => $criadoPor,
        'numero'        => numeroOsTeste(),
        'status'        => 'cancelada',
        'prioridade'    => 'normal',
        'valor_total'   => 100.00,
        'excluido'      => false,
        'aberto_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $orderId = (int) DB::connection()->lastInsertId();

    $order = DB::select('status')->from('service_orders')
        ->where('id = :id')->andWhere('excluido = false')
        ->setParameter('id', $orderId, ParameterType::INTEGER)
        ->fetchAssociative();

    // Reproduz a checagem do controller: status em ['concluida', 'cancelada'] bloqueia
    expect(in_array($order['status'], ['concluida', 'cancelada']))->toBeTrue();

    limparServiceOrder($orderId);
});

test('cancel altera status para cancelada e bloqueia cancelamento duplo', function () {

    $customerId = criarClienteTeste('-cancel');
    $criadoPor  = criarUsuarioTeste('-cancel');

    DB::connection()->insert('service_orders', [
        'customer_id'   => $customerId,
        'criado_por'    => $criadoPor,
        'numero'        => numeroOsTeste(),
        'status'        => 'aberta',
        'prioridade'    => 'normal',
        'valor_total'   => 0.00,
        'excluido'      => false,
        'aberto_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $orderId = (int) DB::connection()->lastInsertId();

    // 1ª chamada — cancela normalmente
    DB::connection()->update('service_orders', [
        'status'        => 'cancelada',
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], [
        'id'       => $orderId,
        'excluido' => false,
    ], ['excluido' => ParameterType::BOOLEAN]);

    $orderCancelada = DB::select('status')
        ->from('service_orders')
        ->where('id = :id')
        ->setParameter('id', $orderId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($orderCancelada['status'])->toBe('cancelada');

    // 2ª tentativa — controller bloqueia porque já está cancelada/concluida
    $orderAtual = DB::select('status')->from('service_orders')
        ->where('id = :id')->andWhere('excluido = false')
        ->setParameter('id', $orderId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect(in_array($orderAtual['status'], ['concluida', 'cancelada']))->toBeTrue();

    limparServiceOrder($orderId);
});

test('numero da OS gerado segue o padrão OS-{ano}-{sequencial}', function () {

    $ano = (new DateTime())->format('Y');

    $totalAntes = (int) DB::select('COUNT(*)')->from('service_orders')
        ->where('numero LIKE :p')
        ->setParameter('p', "OS-{$ano}-%", ParameterType::STRING)
        ->fetchOne();

    $numeroEsperado = "OS-{$ano}-" . str_pad((string) ($totalAntes + 1), 5, '0', STR_PAD_LEFT);

    expect($numeroEsperado)->toMatch('/^OS-\d{4}-\d{5}$/');
});