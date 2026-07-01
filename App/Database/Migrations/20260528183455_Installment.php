<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Installment extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('installment', ['id' => 'id', 'signed' => false]);
        $table
            ->addColumn('id_pagamento', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('parcela', 'integer', ['null' => true])
            ->addColumn('intervalo', 'integer', ['null' => true])
            ->addColumn('alterar_vencimento_conta', 'integer', ['null' => true, 'default' => 0])
            ->addColumn('criado_em', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('atualizado_em', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('id_pagamento', 'payment_terms', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_installment_payment_terms',
            ])
            ->addIndex(['id_pagamento'], ['name' => 'idx_installment_id_pagamento'])
            ->create();
    }
}