<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Suppliers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('suppliers');

        $table
            ->addColumn('nome',          'string',    ['limit' => 200, 'null' => false])
            ->addColumn('cnpj',          'string',    ['limit' => 18,  'null' => true,  'default' => null])
            ->addColumn('observacoes',   'text',      ['null' => true,  'default' => null])
            ->addColumn('ativo',         'boolean',   ['null' => false, 'default' => true])
            ->addColumn('excluido',      'boolean',   ['null' => false, 'default' => false])
            ->addColumn('criado_em',     'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('atualizado_em', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['cnpj'],     ['unique' => true, 'name' => 'suppliers_cnpj_unique'])
            ->addIndex(['nome'],     ['name' => 'suppliers_nome_idx'])
            ->addIndex(['excluido'], ['name' => 'suppliers_excluido_idx'])

            ->create();
    }
}