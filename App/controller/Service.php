<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use DateTime;
use Exception;

final class Service extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-service'), [
                'titulo' => 'Lista de serviços',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id      = $args['id'] ?? null;
        $action  = ($id === null) ? 'c' : 'e';
        $service = [];

        if ($id !== null) {
            $service = DB::queryOne(
                'SELECT * FROM services WHERE id = :id AND excluido = false',
                ['id' => $id]
            );
        }

        return $this->getTwig()
            ->render($response, $this->setView('service'), [
                'titulo'  => 'Detalhes do serviço',
                'id'      => $id,
                'action'  => $action,
                'service' => $service,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();
        $nome = trim($form['nome'] ?? '');

        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo nome é obrigatório', 'id' => 0], 400);
        }

        try {
            $now = (new DateTime())->format('Y-m-d H:i:s');

            DB::execute(
                'INSERT INTO services
                    (nome, descricao, preco, tempo_estimado, ativo, excluido, criado_em, atualizado_em)
                 VALUES
                    (:nome, :descricao, :preco, :tempo_estimado, :ativo, false, :criado_em, :atualizado_em)',
                [
                    'nome'           => $nome,
                    'descricao'      => $form['descricao']      ?? null,
                    'preco'          => (float) ($form['preco'] ?? 0),
                    'tempo_estimado' => $form['tempo_estimado'] ?? null,
                    'ativo'          => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
                    'criado_em'      => $now,
                    'atualizado_em'  => $now,
                ]
            );

            $id = (int) DB::lastInsertId('services_id_seq');

            return $this->json($response, ['status' => true, 'msg' => 'Serviço salvo com sucesso!', 'id' => $id], 201);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao inserir: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe o ID do registro', 'id' => 0], 403);
        }

        $nome = trim($form['nome'] ?? '');
        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo nome é obrigatório', 'id' => 0], 400);
        }

        try {
            DB::execute(
                'UPDATE services SET
                    nome = :nome, descricao = :descricao, preco = :preco,
                    tempo_estimado = :tempo_estimado, ativo = :ativo, atualizado_em = :atualizado_em
                 WHERE id = :id AND excluido = false',
                [
                    'nome'           => $nome,
                    'descricao'      => $form['descricao']      ?? null,
                    'preco'          => (float) ($form['preco'] ?? 0),
                    'tempo_estimado' => $form['tempo_estimado'] ?? null,
                    'ativo'          => isset($form['ativo']) ? filter_var($form['ativo'], FILTER_VALIDATE_BOOLEAN) : true,
                    'atualizado_em'  => (new DateTime())->format('Y-m-d H:i:s'),
                    'id'             => $id,
                ]
            );

            return $this->json($response, ['status' => true, 'msg' => 'Serviço atualizado com sucesso!', 'id' => (int) $id], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao atualizar: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do serviço', 'id' => 0], 403);
        }

        try {
            DB::execute(
                'UPDATE services SET excluido = true, atualizado_em = :now WHERE id = :id',
                ['now' => (new DateTime())->format('Y-m-d H:i:s'), 'id' => $id]
            );

            return $this->json($response, ['status' => true, 'msg' => 'Serviço removido com sucesso!', 'id' => (int) $id]);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao excluir: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function listingdata($request, $response)
    {
        $form   = $request->getParsedBody();
        $term   = $form['search']['value'] ?? null;
        $start  = (int) ($form['start']  ?? 0);
        $length = (int) ($form['length'] ?? 10);

        $columns = [
            0 => 'id',
            1 => 'nome',
            2 => 'preco',
            3 => 'tempo_estimado',
            4 => 'ativo',
            5 => 'criado_em',
        ];

        $posField   = isset($columns[(int) ($form['order'][0]['column'] ?? 0)]) ? (int) $form['order'][0]['column'] : 0;
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? 'DESC'), ['ASC', 'DESC']) ? strtoupper($form['order'][0]['dir']) : 'DESC';
        $orderField = $columns[$posField];

        try {
            $where  = 'WHERE excluido = false';
            $params = [];

            if (!empty($term)) {
                $where          .= ' AND (nome ILIKE :term OR descricao ILIKE :term)';
                $params['term']  = '%' . $term . '%';
            }

            $totalRecords    = (int) DB::queryOne('SELECT COUNT(*) as total FROM services WHERE excluido = false')['total'];
            $filteredRecords = (int) DB::queryOne("SELECT COUNT(*) as total FROM services {$where}", $params)['total'];

            $params['limit']  = $length;
            $params['offset'] = $start;

            $services = DB::query(
                "SELECT * FROM services {$where} ORDER BY {$orderField} {$orderType} LIMIT :limit OFFSET :offset",
                $params
            );

            $rows = [];
            foreach ($services as $key => $value) {
                $descricaoBtn = $value['descricao']
                    ? "<button type='button' class='btn btn-sm btn-secondary' onclick='ShowDescricao(" . json_encode($value['descricao'], JSON_HEX_APOS | JSON_HEX_QUOT) . ")'><i class='bi bi-card-text'></i> Ver</button>"
                    : '—';

                $rows[$key] = [
                    $value['id'],
                    $value['nome'],
                    'R$ ' . number_format((float) $value['preco'], 2, ',', '.'),
                    $value['tempo_estimado'] ?? '—',
                    $value['ativo'] ? 'Ativo' : 'Inativo',
                    (new DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    $descricaoBtn,
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/servico/detalhes/{$value['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
                        <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$value['id']});'><i class='bi bi-trash'></i> Excluir</button>
                    </td>",
                ];
            }

            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }
}