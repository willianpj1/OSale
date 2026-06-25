<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;
use DateTime;
use Doctrine\DBAL\ParameterType;
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
            $service = DB::select('*')
                ->from('services')
                ->where('id = :id')
                ->andWhere('excluido = false')
                ->setParameter('id', $id, ParameterType::INTEGER)
                ->fetchAssociative();
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
            DB::connection()->insert('services', [
                'nome'           => $nome,
                'descricao'      => $form['descricao']      ?? null,
                'preco'          => (float) ($form['preco'] ?? 0),
                'tempo_estimado' => $form['tempo_estimado'] ?? null,
                'ativo'          => filter_var($form['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'excluido'       => false,
                'criado_em'      => $this->now(),
                'atualizado_em'  => $this->now(),
            ], [
                'ativo'    => ParameterType::BOOLEAN,
                'excluido' => ParameterType::BOOLEAN,
            ]);

            $id = (int) DB::connection()->lastInsertId();

            return $this->json($response, ['status' => true, 'msg' => 'Serviço salvo com sucesso!', 'id' => $id], 201);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Erro ao inserir: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id   = $form['id'] ?? null;
        $nome = trim($form['nome'] ?? '');

        if (!$id) {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe o ID do registro', 'id' => 0], 403);
        }
        if ($nome === '') {
            return $this->json($response, ['status' => false, 'msg' => 'O campo nome é obrigatório', 'id' => 0], 400);
        }

        try {
            DB::connection()->update('services', [
                'nome'           => $nome,
                'descricao'      => $form['descricao']      ?? null,
                'preco'          => (float) ($form['preco'] ?? 0),
                'tempo_estimado' => $form['tempo_estimado'] ?? null,
                'ativo'          => filter_var($form['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'atualizado_em'  => $this->now(),
            ], ['id' => $id], ['ativo' => ParameterType::BOOLEAN]);

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
            DB::connection()->update('services', [
                'excluido'      => true,
                'atualizado_em' => $this->now(),
            ], ['id' => $id], ['excluido' => ParameterType::BOOLEAN]);

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
            0 => 'id', 1 => 'nome', 2 => 'preco',
            3 => 'tempo_estimado', 4 => 'ativo', 5 => 'criado_em',
        ];

        $orderField = $columns[(int) ($form['order'][0]['column'] ?? 0)] ?? 'id';
        $orderType  = in_array(strtoupper($form['order'][0]['dir'] ?? ''), ['ASC', 'DESC']) ? strtoupper($form['order'][0]['dir']) : 'DESC';

        try {
            $totalRecords = (int) DB::select('COUNT(*)')
                ->from('services')
                ->where('excluido = false')
                ->fetchOne();

            $query = DB::select('*')
                ->from('services')
                ->where('excluido = false');

            if (!empty($term)) {
                $query->andWhere($query->expr()->or('nome ILIKE :term', 'descricao ILIKE :term'))
                    ->setParameter('term', '%' . $term . '%');
            }

            $filteredRecords = (int) (clone $query)->select('COUNT(*)')->fetchOne();

            $services = $query->orderBy($orderField, $orderType)
                ->setFirstResult($start)->setMaxResults($length)
                ->fetchAllAssociative();

            $rows = array_map(fn($v) => [
                $v['id'],
                $v['nome'],
                'R$ ' . number_format((float) $v['preco'], 2, ',', '.'),
                $v['tempo_estimado'] ?? '—',
                $v['ativo'] ? 'Ativo' : 'Inativo',
                (new DateTime($v['criado_em']))->format('d/m/Y H:i:s'),
                $v['descricao']
                    ? "<button type='button' class='btn btn-sm btn-secondary' onclick='ShowDescricao(" . json_encode($v['descricao'], JSON_HEX_APOS | JSON_HEX_QUOT) . ")'><i class='bi bi-card-text'></i> Ver</button>"
                    : '—',
                "<td>
                    <a class='btn btn-sm btn-warning' href='/servico/detalhes/{$v['id']}'><i class='bi bi-pencil-square'></i> Editar</a>
                    <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal({$v['id']});'><i class='bi bi-trash'></i> Excluir</button>
                </td>",
            ], $services);

            return $this->json($response, [
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $rows,
            ], 200);
        } catch (Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function now(): string
    {
        return (new DateTime())->format('Y-m-d H:i:s');
    }
}