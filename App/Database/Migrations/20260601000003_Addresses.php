<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

final class Addresses extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('addresses');

        $table
            ->addColumn('entidade',    'string',    ['limit' => 10,  'null' => false])
            ->addColumn('entidade_id', 'integer',   ['null' => false])
            ->addColumn('nome',        'string',    ['limit' => 100, 'null' => true,  'default' => null])
            ->addColumn('cep',         'string',    ['limit' => 9,   'null' => true,  'default' => null])
            ->addColumn('logradouro',  'string',    ['limit' => 255, 'null' => true,  'default' => null])
            ->addColumn('numero',      'string',    ['limit' => 10,  'null' => true,  'default' => null])
            ->addColumn('complemento', 'string',    ['limit' => 100, 'null' => true,  'default' => null])
            ->addColumn('bairro',      'string',    ['limit' => 100, 'null' => true,  'default' => null])
            ->addColumn('cidade',      'string',    ['limit' => 100, 'null' => true,  'default' => null])
            ->addColumn('estado',      'string',    ['limit' => 2,   'null' => true,  'default' => null])
            ->addColumn('principal',   'boolean',   ['null' => false, 'default' => false])
            ->addColumn('criado_em',   'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['entidade', 'entidade_id'], ['name' => 'addresses_entidade_id_idx'])
            ->addIndex(['cep'],                     ['name' => 'addresses_cep_idx'])

            ->create();
    }
}