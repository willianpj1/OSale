<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PaymentTerms extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('payment_terms', ['id' => 'id', 'signed' => false]);
        $table
            ->addColumn('codigo', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('titulo', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('atalho', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('criado_em', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('atualizado_em', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['titulo'], ['name' => 'idx_payment_terms_titulo'])
            ->addIndex(['codigo'], ['name' => 'idx_payment_terms_codigo'])
            ->create();
    }
}