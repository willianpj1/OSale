<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CustomerContacts extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('customer_contacts');

        $table
            ->addColumn('customer_id', 'integer',   ['null' => false])
            ->addColumn('tipo',        'string',    ['limit' => 20, 'null' => false, 'default' => 'telefone'])
            // tipo: 'telefone' | 'whatsapp' | 'email' | 'outro'
            ->addColumn('descricao',   'string',    ['limit' => 100, 'null' => true,  'default' => null])
            // label amigável: "Celular pessoal", "WhatsApp do trabalho"
            ->addColumn('valor',       'string',    ['limit' => 255, 'null' => false])
            // o número/e-mail em si
            ->addColumn('principal',   'boolean',   ['null' => false, 'default' => false])
            ->addColumn('criado_em',   'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addForeignKey('customer_id', 'customers', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'CASCADE',
                'constraint' => 'fk_customer_contacts_customer_id',
            ])

            ->addIndex(['customer_id'], ['name' => 'customer_contacts_customer_id_idx'])

            ->create();
    }
}
