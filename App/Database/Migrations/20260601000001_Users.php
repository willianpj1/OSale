<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Users extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');

        $table
            ->addColumn('nome',          'string',    ['limit' => 150, 'null' => false])
            ->addColumn('sobrenome',     'string',    ['limit' => 150, 'null' => true,  'default' => null])
            ->addColumn('email',         'string',    ['limit' => 255, 'null' => true,  'default' => null])
            ->addColumn('senha',         'text',      ['null' => false])
            ->addColumn('perfil',        'string',    ['limit' => 30,  'null' => false, 'default' => 'tecnico'])
            // perfil: 'admin' | 'tecnico' | 'atendente'
            ->addColumn('telefone',      'string',    ['limit' => 20,  'null' => true,  'default' => null])
            ->addColumn('whatsapp',      'string',    ['limit' => 20,  'null' => true,  'default' => null])
            ->addColumn('cpf',           'string',    ['limit' => 14,  'null' => true,  'default' => null])
            ->addColumn('rg',            'string',    ['limit' => 20,  'null' => true,  'default' => null])
            ->addColumn('google_id',     'string',    ['limit' => 255, 'null' => true,  'default' => null])
            ->addColumn('ativo',         'boolean',   ['null' => false, 'default' => true])
            ->addColumn('administrador', 'boolean',   ['null' => false, 'default' => false])
            ->addColumn('criado_em',     'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('atualizado_em', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['email'],     ['unique' => true, 'name' => 'users_email_unique'])
            ->addIndex(['cpf'],       ['unique' => true, 'name' => 'users_cpf_unique'])
            ->addIndex(['google_id'], ['unique' => true, 'name' => 'users_google_id_unique'])
            ->addIndex(['nome'],      ['name' => 'users_nome_idx'])

            ->create();
    }
}
