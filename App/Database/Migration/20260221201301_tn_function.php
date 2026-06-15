<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TnFunction extends AbstractMigration
{
    public function change(): void
    {
        $this->execute("
            CREATE OR REPLACE FUNCTION refresh_mvw_estoque()
            RETURNS TRIGGER AS $$
            BEGIN
                REFRESH MATERIALIZED VIEW mvw_estoque;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION fn_trigger_purchase_to_stock_movement()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN   
                IF (NEW.estado_compra = 'RECEBIDO') AND (OLD.estado_compra IS DISTINCT FROM 'RECEBIDO') THEN
                    INSERT INTO stock_movement (id_produto, quantidade_entrada, tipo, origem_movimento)
                    SELECT id_produto, COALESCE(SUM(quantidade), 0), 'ENTRADA', 'COMPRA' 
                    FROM item_purchase 
                    WHERE id_compra = NEW.id 
                    GROUP BY id_produto;
                    
                    IF NOT FOUND THEN 
                        RAISE WARNING 'Trigger fn_trigger_purchase_to_stock_movement: Nenhum item encontrado para a compra ID = %', NEW.id;
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE OR REPLACE FUNCTION fn_trigger_inicializar_estoque()
            RETURNS TRIGGER AS $$
            BEGIN
                INSERT INTO stock_movement (
                    id_produto, 
                    quantidade_entrada, 
                    quantidade_saida, 
                    tipo, 
                    origem_movimento, 
                    observacao
                )
                VALUES (
                    NEW.id, 
                    0, 
                    0, 
                    'ENTRADA', 
                    'AJUSTE_MANUAL', 
                    'INICIALIZAÇÃO DE CADASTRO' -- Faltava esse valor aqui para fechar os 6!
                );
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");
    }
}
