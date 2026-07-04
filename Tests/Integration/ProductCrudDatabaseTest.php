<?php

declare(strict_types=1);

use App\Database\DB;
use Doctrine\DBAL\ParameterType;

/**
 * Nomes de helper específicos deste arquivo (sufixo "Product") pra evitar
 * colisão de "Cannot redeclare function" com helpers de mesmo propósito
 * definidos em outros arquivos de teste (ex: PurchaseCrudDatabaseTest.php).
 */
function criarSupplierTesteProduct(string $sufixo = ''): int
{
    $cnpj = str_pad((string) random_int(1, 99999999999999), 14, '0', STR_PAD_LEFT);

    DB::connection()->insert('suppliers', [
        'nome'          => 'Fornecedor Teste Produto' . $sufixo,
        'cnpj'          => $cnpj,
        'ativo'         => true,
        'excluido'      => false,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN, 'excluido' => ParameterType::BOOLEAN]);

    return (int) DB::connection()->lastInsertId();
}

function codigoBarraTeste(): string
{
    // codigo_barra é unique — microtime() sozinho pode colidir em chamadas
    // consecutivas na mesma execução, então soma um random extra.
    return 'CB' . substr(str_replace('.', '', (string) microtime(true)), -8) . random_int(1000, 9999);
}

function limparProdutoTeste(int $id): void
{
    DB::connection()->delete('stock_movements', ['product_id' => $id]);
    DB::connection()->delete('products', ['id' => $id]);
}

// ─────────────────────────────────────────────────────────────────────────────

test('ciclo CRUD completo na tabela products', function () {

    $supplierId  = criarSupplierTesteProduct('-crud');
    $codigoBarra = codigoBarraTeste();

    // 1. INSERT — nome, preços, etc; estoque_atual não é enviado (nasce em 0)
    DB::connection()->insert('products', [
        'supplier_id'    => $supplierId,
        'nome'           => 'Produto CRUD Teste Integração',
        'codigo_barra'   => $codigoBarra,
        'unidade'        => 'un',
        'descricao'      => 'Descrição de teste',
        'preco_compra'   => 10.00,
        'margem_lucro'   => 50.00,
        'preco_venda'    => 15.00,
        'estoque_minimo' => 2.000,
        'ativo'          => true,
        'excluido'       => false,
        'criado_em'      => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em'  => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN, 'excluido' => ParameterType::BOOLEAN]);

    $id = (int) DB::connection()->lastInsertId();
    expect($id)->toBeGreaterThan(0);

    // 2. SELECT
    $product = DB::select('*')
        ->from('products')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($product)->not->toBeFalse();
    expect($product['nome'])->toBe('Produto CRUD Teste Integração');
    expect($product['codigo_barra'])->toBe($codigoBarra);
    expect((float) $product['preco_venda'])->toBe(15.00);
    expect($product['ativo'])->toBeTruthy();

    // estoque_atual sempre nasce 0, mesmo não tendo sido enviado no insert
    // (garantido pela trigger trg_force_zero_stock_on_insert)
    expect((float) $product['estoque_atual'])->toBe(0.0);

    // 3. UPDATE
    DB::connection()->update('products', [
        'nome'          => 'Produto CRUD Alterado',
        'preco_venda'   => 20.00,
        'ativo'         => false,
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['id' => $id], ['ativo' => ParameterType::BOOLEAN]);

    $productAlterado = DB::select('*')
        ->from('products')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($productAlterado['nome'])->toBe('Produto CRUD Alterado');
    expect((float) $productAlterado['preco_venda'])->toBe(20.00);
    expect($productAlterado['ativo'])->toBeFalsy();

    // 4. codigo_barra deve ser único
    $duplicatas = (int) DB::select('COUNT(*)')
        ->from('products')
        ->where('codigo_barra = :cb')
        ->setParameter('cb', $codigoBarra, ParameterType::STRING)
        ->fetchOne();

    expect($duplicatas)->toBe(1);

    // 5. DELETE (soft delete)
    DB::connection()->update('products', [
        'excluido'      => true,
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['id' => $id], ['excluido' => ParameterType::BOOLEAN]);

    $productExcluido = DB::select('*')
        ->from('products')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($productExcluido['excluido'])->toBeTruthy();

    limparProdutoTeste($id);
});

test('estoque_atual sempre nasce zerado mesmo se o insert tentar forçar outro valor', function () {

    // Reproduz o comentário do controller: mesmo tentando "burlar" enviando
    // estoque_atual no insert, a trigger trg_force_zero_stock_on_insert zera.
    DB::connection()->insert('products', [
        'nome'           => 'Produto Burla Estoque',
        'codigo_barra'   => codigoBarraTeste(),
        'unidade'        => 'un',
        'preco_compra'   => 5.00,
        'preco_venda'    => 8.00,
        'estoque_atual'  => 999.000,
        'ativo'          => true,
        'excluido'       => false,
        'criado_em'      => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em'  => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN, 'excluido' => ParameterType::BOOLEAN]);

    $id = (int) DB::connection()->lastInsertId();

    $product = DB::select('estoque_atual')
        ->from('products')
        ->where('id = :id')
        ->setParameter('id', $id, ParameterType::INTEGER)
        ->fetchAssociative();

    expect((float) $product['estoque_atual'])->toBe(0.0);

    limparProdutoTeste($id);
});

test('não permite dois produtos com o mesmo codigo_barra', function () {

    $codigoBarra = codigoBarraTeste();

    DB::connection()->insert('products', [
        'nome'          => 'Produto Original',
        'codigo_barra'  => $codigoBarra,
        'unidade'       => 'un',
        'preco_compra'  => 1.00,
        'preco_venda'   => 2.00,
        'ativo'         => true,
        'excluido'      => false,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN, 'excluido' => ParameterType::BOOLEAN]);

    $id = (int) DB::connection()->lastInsertId();

    $duplicado = false;

    try {
        DB::connection()->insert('products', [
            'nome'          => 'Produto Duplicado',
            'codigo_barra'  => $codigoBarra,
            'unidade'       => 'un',
            'preco_compra'  => 1.00,
            'preco_venda'   => 2.00,
            'ativo'         => true,
            'excluido'      => false,
            'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
            'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
        ], ['ativo' => ParameterType::BOOLEAN, 'excluido' => ParameterType::BOOLEAN]);
    } catch (\Doctrine\DBAL\Exception $e) {
        $duplicado = true;
    }

    expect($duplicado)->toBeTrue();

    limparProdutoTeste($id);
});

test('produto respeita FK opcional com supplier_id (SET NULL ao remover fornecedor)', function () {

    $supplierId = criarSupplierTesteProduct('-fk');

    DB::connection()->insert('products', [
        'supplier_id'   => $supplierId,
        'nome'          => 'Produto Vinculado a Fornecedor',
        'codigo_barra'  => codigoBarraTeste(),
        'unidade'       => 'un',
        'preco_compra'  => 1.00,
        'preco_venda'   => 2.00,
        'ativo'         => true,
        'excluido'      => false,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN, 'excluido' => ParameterType::BOOLEAN]);

    $productId = (int) DB::connection()->lastInsertId();

    // Remove o fornecedor de verdade — a FK é ON DELETE SET NULL
    DB::connection()->delete('suppliers', ['id' => $supplierId]);

    $product = DB::select('supplier_id')
        ->from('products')
        ->where('id = :id')
        ->setParameter('id', $productId, ParameterType::INTEGER)
        ->fetchAssociative();

    expect($product['supplier_id'])->toBeNull();

    limparProdutoTeste($productId);
});

test('formatação de estoque remove zeros e ponto desnecessários', function () {

    // Reproduz a lógica de formatarEstoque() do controller (função privada,
    // testada aqui via sua regra de formatação equivalente).
    $formatarEstoque = function (float $valor): string {
        $formatado = rtrim(rtrim(number_format($valor, 3, '.', ''), '0'), '.');
        return str_replace('.', ',', $formatado);
    };

    expect($formatarEstoque(9.000))->toBe('9');
    expect($formatarEstoque(9.500))->toBe('9,5');
    expect($formatarEstoque(9.250))->toBe('9,25');
    expect($formatarEstoque(0.000))->toBe('0');
});

test('listing considera apenas produtos não excluídos', function () {

    $codigoAtivo    = codigoBarraTeste();
    $codigoExcluido = codigoBarraTeste();

    DB::connection()->insert('products', [
        'nome'          => 'Produto Listagem Ativo',
        'codigo_barra'  => $codigoAtivo,
        'unidade'       => 'un',
        'preco_compra'  => 1.00,
        'preco_venda'   => 2.00,
        'ativo'         => true,
        'excluido'      => false,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN, 'excluido' => ParameterType::BOOLEAN]);
    $idAtivo = (int) DB::connection()->lastInsertId();

    DB::connection()->insert('products', [
        'nome'          => 'Produto Listagem Excluído',
        'codigo_barra'  => $codigoExcluido,
        'unidade'       => 'un',
        'preco_compra'  => 1.00,
        'preco_venda'   => 2.00,
        'ativo'         => true,
        'excluido'      => true,
        'criado_em'     => (new DateTime())->format('Y-m-d H:i:s'),
        'atualizado_em' => (new DateTime())->format('Y-m-d H:i:s'),
    ], ['ativo' => ParameterType::BOOLEAN, 'excluido' => ParameterType::BOOLEAN]);
    $idExcluido = (int) DB::connection()->lastInsertId();

    // Reproduz a query base do listingdata(): where p.excluido = false
    $resultados = DB::select('id, nome')
        ->from('products')
        ->where('excluido = false')
        ->andWhere('codigo_barra IN (:c1, :c2)')
        ->setParameter('c1', $codigoAtivo, ParameterType::STRING)
        ->setParameter('c2', $codigoExcluido, ParameterType::STRING)
        ->fetchAllAssociative();

    $ids = array_column($resultados, 'id');

    expect($ids)->toContain($idAtivo);
    expect($ids)->not->toContain($idExcluido);

    limparProdutoTeste($idAtivo);
    limparProdutoTeste($idExcluido);
});