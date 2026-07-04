<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ServiceOrderPayments extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('service_order_payments');

        $table
            ->addColumn('service_order_id', 'integer',    ['null' => false])
            ->addColumn('id_pagamento',     'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('parcelas',         'integer',    ['null' => false, 'default' => 1])
            // fatia (em R$) desse método de pagamento dentro do valor_liquido total da OS
            ->addColumn('valor',            'decimal',    ['precision' => 10, 'scale' => 2, 'null' => false])
            ->addColumn('criado_em',        'timestamp',  ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addForeignKey('service_order_id', 'service_orders', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'CASCADE',
                'constraint' => 'fk_sop_service_order_id',
            ])
            ->addForeignKey('id_pagamento', 'payment_terms', 'id', [
                'delete'     => 'RESTRICT',
                'update'     => 'CASCADE',
                'constraint' => 'fk_sop_id_pagamento',
            ])

            ->addIndex(['service_order_id'], ['name' => 'service_order_payments_order_idx'])
            ->addIndex(['id_pagamento'],     ['name' => 'service_order_payments_pagamento_idx'])

            ->create();

        // service_orders.id_pagamento / parcelas continuam existindo (legado / atalho
        // pra quando a OS usa uma única forma de pagamento), mas passam a ser
        // preenchidos a partir de service_order_payments quando há split.
    }
}