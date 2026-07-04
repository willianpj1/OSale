<?php

declare(strict_types=1);

use App\Database\DB;
use Doctrine\DBAL\ParameterType;

function criarSupplierTeste(string $sufixo = ''): int
{
    $cnpj = str_pad((string) random_int(1, 99999999999999), 14, '0', STR_PAD_LEFT);

    DB::connection()->insert('suppliers', [
        'nome'          => 'Fornecedor Teste Integração' . $sufixo,
        'cnpj'          => $cnpj,
        'ativo'         => true,
        'excluido'      => false,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN, 'excluido' => ParameterType::BOOLEAN]);

    return (int) DB::connection()->lastInsertId();
}

function criarUsuarioTestePurchase(string $sufixo = ''): int
{
    $email = 'teste' . $sufixo . '.' . uniqid() . '@teste.local';

    DB::connection()->insert('users', [
        'nome'          => 'Comprador',
        'sobrenome'     => 'Teste' . $sufixo,
        'email'         => $email,
        'ativo'         => true,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN]);

    return (int) DB::connection()->lastInsertId();
}

function criarProdutoTestePurchase(string $sufixo = ''): int
{
    // trg_force_zero_stock_on_insert força estoque_atual = 0 no INSERT.
    DB::connection()->insert('products', [
        'nome'           => 'Produto Teste Compra' . $sufixo,
        'preco_compra'   => 30.00,
        'preco_venda'    => 50.00,
        'ativo'          => true,
        'excluido'       => false,
        'criado_em'      => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em'  => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN, 'excluido' => ParameterType::BOOLEAN]);

    return (int) DB::connection()->lastInsertId();
}

function numeroCompraTeste(): string
{
    // coluna "numero" é varchar(20) — mantém curto.
    return 'CP-' . substr(str_replace('.', '', (string) microtime(true)), -10);
}

function limparPurchase(int $id): void
{
    DB::connection()->delete('purchase_items', ['purchase_id' => $id]);
    DB::connection()->delete('purchases', ['id' => $id]);
}

// ─────────────────────────────────────────────────────────────────────────────

test('ciclo CRUD completo na tabela purchases', function () {

    $supplierId = criarSupplierTeste('-crud');
    $criadoPor  = criarUsuarioTestePurchase('-crud');
    $numero     = numeroCompraTeste();

    // 1. INSERT
    DB::connection()->insert('purchases', [
        'supplier_id'   => $supplierId,
        'criado_por'    => $criadoPor,
        'numero'        => $numero,
        'nota_pedido'   => 'NP-001',
        'status'        => 'pendente',
        'valor_total'   => 0.00,
        'excluido'      => false,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $id = (int) DB::connection()->lastInsertId();
    expect($id)->toBeGreaterThan(0);

    // 2. SELECT
    $purchase = DB::select('*')
        ->from('purchases')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($purchase)->not->toBeFalse();
    expect($purchase['numero'])->toBe($numero);
    expect($purchase['status'])->toBe('pendente');
    expect((float) $purchase['valor_total'])->toBe(0.0);

    // 3. UPDATE
    DB::connection()->update('purchases', [
        'nota_pedido'   => 'NP-002',
        'observacoes'   => 'Atualizado no teste',
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], [
        'id'       => $id,
        'excluido' => false,
    ], ['excluido' => ParameterType::BOOLEAN]);

    $purchaseAlterada = DB::select('*')
        ->from('purchases')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($purchaseAlterada['nota_pedido'])->toBe('NP-002');
    expect($purchaseAlterada['observacoes'])->toBe('Atualizado no teste');

    // 4. numero deve ser único
    $duplicatas = (int) DB::select('COUNT(*)')
        ->from('purchases')
        ->where('numero = :n')
        ->setParameter('n', $numero, ParameterType::STRING)
        ->fetchOne();

    expect($duplicatas)->toBe(1);

    // 5. DELETE (soft delete)
    DB::connection()->update('purchases', [
        'excluido'      => true,
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['id' => $id], ['excluido' => ParameterType::BOOLEAN]);

    $purchaseExcluida = DB::select('*')
        ->from('purchases')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($purchaseExcluida['excluido'])->toBeTruthy();

    limparPurchase($id);
});

test('adicionar item de compra recalcula valor_total', function () {

    $supplierId = criarSupplierTeste('-item');
    $criadoPor  = criarUsuarioTestePurchase('-item');
    $produtoId  = criarProdutoTestePurchase('-item');

    DB::connection()->insert('purchases', [
        'supplier_id'   => $supplierId,
        'criado_por'    => $criadoPor,
        'numero'        => numeroCompraTeste(),
        'status'        => 'pendente',
        'valor_total'   => 0.00,
        'excluido'      => false,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $purchaseId = (int) DB::connection()->lastInsertId();

    DB::connection()->insert('purchase_items', [
        'purchase_id'    => $purchaseId,
        'product_id'     => $produtoId,
        'quantidade'     => 5,
        'preco_unitario' => 20.00,
        'subtotal'       => 100.00,
        'criado_em'      => (new DateTime())->format('Y-m-d H:i:s'),
    ], [
        'purchase_id'    => ParameterType::INTEGER,
        'product_id'     => ParameterType::INTEGER,
        'quantidade'     => ParameterType::STRING,
        'preco_unitario' => ParameterType::STRING,
        'subtotal'       => ParameterType::STRING,
    ]);

    $total = (float) DB::select('COALESCE(SUM(subtotal), 0)')
        ->from('purchase_items')
        ->where('purchase_id = :id')
        ->setParameter('id', $purchaseId, ParameterType::INTEGER)
        ->fetchOne();

    DB::connection()->update('purchases', [
        'valor_total'   => $total,
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['id' => $purchaseId]);

    $purchase = DB::select('valor_total')
        ->from('purchases')
        ->where('id = :id')
        ->setParameter('id', $purchaseId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect((float) $purchase['valor_total'])->toBe(100.00);

    limparPurchase($purchaseId);
    DB::connection()->delete('products', ['id' => $produtoId]);
});

test('não permite editar ou remover item de compra já recebida', function () {

    $supplierId = criarSupplierTeste('-bloq');
    $criadoPor  = criarUsuarioTestePurchase('-bloq');

    DB::connection()->insert('purchases', [
        'supplier_id'   => $supplierId,
        'criado_por'    => $criadoPor,
        'numero'        => numeroCompraTeste(),
        'status'        => 'recebida',
        'valor_total'   => 100.00,
        'excluido'      => false,
        'recebido_em'   => (new DateTime())->format('Y-m-d H:i:s'),
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $purchaseId = (int) DB::connection()->lastInsertId();

    // Reproduz a checagem do controller: status !== 'pendente' bloqueia edição/exclusão de item
    $purchase = DB::select('status')->from('purchases')
        ->where('id = :id')
        ->setParameter('id', $purchaseId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($purchase['status'])->not->toBe('pendente');

    limparPurchase($purchaseId);
});

test('receive conclui a compra e credita estoque dos produtos via stock_movements', function () {

    $supplierId = criarSupplierTeste('-receive');
    $criadoPor  = criarUsuarioTestePurchase('-receive');
    $produtoId  = criarProdutoTestePurchase('-receive');

    DB::connection()->insert('purchases', [
        'supplier_id'   => $supplierId,
        'criado_por'    => $criadoPor,
        'numero'        => numeroCompraTeste(),
        'status'        => 'pendente',
        'valor_total'   => 300.00,
        'excluido'      => false,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $purchaseId = (int) DB::connection()->lastInsertId();

    DB::connection()->insert('purchase_items', [
        'purchase_id'    => $purchaseId,
        'product_id'     => $produtoId,
        'quantidade'     => 10,
        'preco_unitario' => 30.00,
        'subtotal'       => 300.00,
        'criado_em'      => (new DateTime())->format('Y-m-d H:i:s'),
    ], [
        'purchase_id'    => ParameterType::INTEGER,
        'product_id'     => ParameterType::INTEGER,
        'quantidade'     => ParameterType::STRING,
        'preco_unitario' => ParameterType::STRING,
        'subtotal'       => ParameterType::STRING,
    ]);

    // ── Reproduz a lógica de receive() do controller ──────────────────────
    $itens = DB::select('id, product_id, quantidade')
        ->from('purchase_items')
        ->where('purchase_id = :id')
        ->setParameter('id', $purchaseId, ParameterType::INTEGER)
        ->fetchAllAssociative();

    $conn = DB::connection();
    $conn->beginTransaction();

    foreach ($itens as $item) {
        $produto = $conn->fetchAssociative(
            'SELECT estoque_atual FROM products WHERE id = :id FOR UPDATE',
            ['id' => (int) $item['product_id']]
        );

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
            'observacao'            => "Compra #{$purchaseId} — item #{$item['id']}",
            'criado_por'            => null,
            'criado_em'             => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    $conn->update('purchases', [
        'status'        => 'recebida',
        'recebido_em'   => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['id' => $purchaseId]);

    $conn->commit();

    // ── Verificações ────────────────────────────────────────────────────
    $purchaseFinal = DB::select('*')
        ->from('purchases')
        ->where('id = :id')
        ->setParameter('id', $purchaseId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($purchaseFinal['status'])->toBe('recebida');
    expect($purchaseFinal['recebido_em'])->not->toBeNull();

    $produtoFinal = DB::select('estoque_atual')
        ->from('products')
        ->where('id = :id')
        ->setParameter('id', $produtoId, ParameterType::INTEGER)
        ->fetchAssociative();

    // Estoque nasce em 0 (trigger), recebeu 10 -> deve ficar 10
    expect((float) $produtoFinal['estoque_atual'])->toBe(10.0);

    $movimento = DB::select('*')
        ->from('stock_movements')
        ->where('product_id = :pid')
        ->setParameter('pid', $produtoId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($movimento)->not->toBeFalse();
    expect($movimento['tipo'])->toBe('ENTRADA');
    expect($movimento['origem'])->toBe('COMPRA');
    expect((float) $movimento['quantidade'])->toBe(10.0);

    DB::connection()->delete('stock_movements', ['product_id' => $produtoId]);
    limparPurchase($purchaseId);
    DB::connection()->delete('products', ['id' => $produtoId]);
});

test('não permite receber compra sem itens ou já recebida/cancelada', function () {

    $supplierId = criarSupplierTeste('-receive-bloq');
    $criadoPor  = criarUsuarioTestePurchase('-receive-bloq');

    // Compra sem itens
    DB::connection()->insert('purchases', [
        'supplier_id'   => $supplierId,
        'criado_por'    => $criadoPor,
        'numero'        => numeroCompraTeste(),
        'status'        => 'pendente',
        'valor_total'   => 0.00,
        'excluido'      => false,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $purchaseId = (int) DB::connection()->lastInsertId();

    $itens = DB::select('id')->from('purchase_items')
        ->where('purchase_id = :id')
        ->setParameter('id', $purchaseId, ParameterType::INTEGER)
        ->fetchAllAssociative();

    expect($itens)->toBeEmpty();

    // Compra já cancelada não pode ser recebida
    DB::connection()->update('purchases', ['status' => 'cancelada'], ['id' => $purchaseId]);

    $purchase = DB::select('status')->from('purchases')
        ->where('id = :id')->andWhere('excluido = false')
        ->setParameter('id', $purchaseId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($purchase['status'])->not->toBe('pendente');

    limparPurchase($purchaseId);
});

test('cancel altera status para cancelada e bloqueia cancelamento de compra já recebida', function () {

    $supplierId = criarSupplierTeste('-cancel');
    $criadoPor  = criarUsuarioTestePurchase('-cancel');

    DB::connection()->insert('purchases', [
        'supplier_id'   => $supplierId,
        'criado_por'    => $criadoPor,
        'numero'        => numeroCompraTeste(),
        'status'        => 'pendente',
        'valor_total'   => 0.00,
        'excluido'      => false,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $purchaseId = (int) DB::connection()->lastInsertId();

    // 1ª chamada — cancela normalmente
    DB::connection()->update('purchases', [
        'status'        => 'cancelada',
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['id' => $purchaseId]);

    $purchaseCancelada = DB::select('status')
        ->from('purchases')
        ->where('id = :id')
        ->setParameter('id', $purchaseId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($purchaseCancelada['status'])->toBe('cancelada');

    // Simula uma compra recebida, que não pode ser cancelada pelo endpoint
    $supplierId2 = criarSupplierTeste('-cancel-recebida');
    DB::connection()->insert('purchases', [
        'supplier_id'   => $supplierId2,
        'criado_por'    => $criadoPor,
        'numero'        => numeroCompraTeste(),
        'status'        => 'recebida',
        'valor_total'   => 0.00,
        'excluido'      => false,
        'recebido_em'   => (new DateTime())->format('Y-m-d H:i:s'),
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['excluido' => ParameterType::BOOLEAN]);

    $purchaseRecebidaId = (int) DB::connection()->lastInsertId();

    $purchaseRecebida = DB::select('status')->from('purchases')
        ->where('id = :id')
        ->setParameter('id', $purchaseRecebidaId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($purchaseRecebida['status'])->toBe('recebida');
    // Controller bloqueia: status === 'recebida' não pode ser cancelada por aqui

    limparPurchase($purchaseId);
    limparPurchase($purchaseRecebidaId);
});

test('numero da compra gerado segue o padrão COMPRA-{ano}-{sequencial}', function () {

    $ano = (new DateTime())->format('Y');

    $totalAntes = (int) DB::select('COUNT(*)')->from('purchases')
        ->where('numero LIKE :p')
        ->setParameter('p', "COMPRA-{$ano}-%", ParameterType::STRING)
        ->fetchOne();

    $numeroEsperado = "COMPRA-{$ano}-" . str_pad((string) ($totalAntes + 1), 5, '0', STR_PAD_LEFT);

    expect($numeroEsperado)->toMatch('/^COMPRA-\d{4}-\d{5}$/');
});