<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

final class Contacts extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('contacts');

        $table
            ->addColumn('entidade',    'string',  ['limit' => 10,  'null' => false])
            // entidade: 'customer' | 'supplier'
            ->addColumn('entidade_id', 'integer', ['null' => false])
            // id do customer ou supplier — sem FK pois serve pra duas tabelas
            ->addColumn('tipo',        'string',  ['limit' => 20,  'null' => false, 'default' => 'telefone'])
            // tipo: 'telefone' | 'whatsapp' | 'email' | 'outro'
            ->addColumn('nome',        'string',  ['limit' => 100, 'null' => true,  'default' => null])
            // label amigável: "Celular pessoal", "WhatsApp comercial"
            ->addColumn('contato',     'string',  ['limit' => 255, 'null' => false])
            // o número ou e-mail em si
            ->addColumn('principal',   'boolean', ['null' => false, 'default' => false])
            ->addColumn('criado_em',   'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['entidade', 'entidade_id'], ['name' => 'contacts_entidade_id_idx'])
            ->addIndex(['tipo'],                    ['name' => 'contacts_tipo_idx'])

            ->create();
    }
}