<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CustomTypes extends AbstractMigration
{
    public function change(): void
    {
        $this->execute("
            CREATE TYPE stock_movement_direction AS ENUM ('ENTRADA','SAIDA');
            CREATE TYPE stock_movement_origin AS ENUM (
                'VENDA',
                'CANCELAMENTO_VENDA',
                'COMPRA',
                'CANCELAMENTO_COMPRA',
                'AJUSTE_MANUAL'
            );
        ");
    }

    public function down(): void
    {
        $this->execute("
            DROP TYPE IF EXISTS stock_movement_direction;
            DROP TYPE IF EXISTS stock_movement_origin;
        ");
    }
}