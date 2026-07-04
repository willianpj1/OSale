<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ProductsEstoqueAtual extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
            -- Aplica o movimento no saldo denormalizado de products.estoque_atual
            CREATE OR REPLACE FUNCTION fn_apply_stock_movement()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.tipo = 'ENTRADA' THEN
                    UPDATE products
                    SET estoque_atual = estoque_atual + NEW.quantidade,
                        atualizado_em = NOW()
                    WHERE id = NEW.product_id;
                ELSIF NEW.tipo = 'SAIDA' THEN
                    UPDATE products
                    SET estoque_atual = estoque_atual - NEW.quantidade,
                        atualizado_em = NOW()
                    WHERE id = NEW.product_id;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_apply_stock_movement
            AFTER INSERT ON stock_movements
            FOR EACH ROW
            EXECUTE FUNCTION fn_apply_stock_movement();

            -- Garante que todo produto nasce com estoque 0, independente do
            -- que vier no INSERT. Isso fecha a porta que o controller antigo
            -- deixava aberta (o insert() do Product aceitava estoque_atual do form).
            CREATE OR REPLACE FUNCTION fn_force_zero_stock_on_insert()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.estoque_atual := 0;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_force_zero_stock_on_insert
            BEFORE INSERT ON products
            FOR EACH ROW
            EXECUTE FUNCTION fn_force_zero_stock_on_insert();
        ");
    }

    public function down(): void
    {
        $this->execute("
            DROP TRIGGER IF EXISTS trg_apply_stock_movement ON stock_movements;
            DROP FUNCTION IF EXISTS fn_apply_stock_movement();
            DROP TRIGGER IF EXISTS trg_force_zero_stock_on_insert ON products;
            DROP FUNCTION IF EXISTS fn_force_zero_stock_on_insert();
        ");
    }
}
