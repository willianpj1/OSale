<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MvwStockMovement extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
           
        CREATE MATERIALIZED VIEW mvw_estoque AS
                SELECT 
                    id_produto,
                    product.nome,
                    SUM(COALESCE(stock_movement.quantidade_entrada, 0)) AS total_entradas,
                    SUM(COALESCE(stock_movement.quantidade_saida, 0)) AS total_saidas,
                    (SUM(COALESCE(stock_movement.quantidade_entrada, 0)) - SUM(COALESCE(stock_movement.quantidade_saida, 0))) AS estoque_atual,
                        MAX(data_cadastro) AS ultima_movimentacao
                            FROM 
                                stock_movement
                            LEFT JOIN product ON product.id = stock_movement.id_produto
                            WHERE product.excluido != true
                            GROUP BY 
                                stock_movement.id_produto,product.nome;
        ");

        $this->execute("
            CREATE INDEX product_id_hash ON product USING HASH (id);
            CREATE INDEX product_nome_hash ON product USING HASH (nome);
            CREATE INDEX stock_movement_idprd_hash ON stock_movement USING HASH (id_produto);
        ");
    }

    public function down(): void
    {
        $this->execute("DROP MATERIALIZED VIEW IF EXISTS mvw_stock_movement CASCADE;");
    }
}
