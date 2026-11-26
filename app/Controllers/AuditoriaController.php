<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/** Consulta do log de auditoria (somente leitura): quem fez o quê, quando, de onde, com os dados antes e depois. */
final class AuditoriaController extends Controller
{
    public function index(Request $r): Response
    {
        $f = [
            'de' => data_iso($r->str('de')) ?: date('Y-m-d', strtotime('-30 days')),
            'ate' => data_iso($r->str('ate')) ?: date('Y-m-d'),
            'usuario' => $r->int('usuario'),
            'entidade' => $r->str('entidade'),
            'acao' => $r->str('acao'),
            'q' => $r->str('q'),
        ];
        $w = ['l.criado_em BETWEEN :de AND :ate'];
        $p = ['de' => $f['de'] . ' 00:00:00', 'ate' => $f['ate'] . ' 23:59:59'];
        if ($f['usuario']) {
            $w[] = 'l.usuario_id = :u';
            $p['u'] = $f['usuario'];
        }
        if ($f['entidade'] !== '') {
            $w[] = 'l.entidade = :e';
            $p['e'] = $f['entidade'];
        }
        if ($f['acao'] !== '') {
            $w[] = 'l.acao = :a';
            $p['a'] = $f['acao'];
        }
        if ($f['q'] !== '') {
            $w[] = '(l.descricao LIKE :q OR l.entidade_id = :q2)';
            $p += ['q' => '%' . $f['q'] . '%', 'q2' => $f['q']];
        }
        $sql = 'SELECT l.id, l.usuario_nome, l.acao, l.entidade, l.entidade_id, l.descricao, l.ip, l.criado_em FROM logs_auditoria l WHERE ' . implode(' AND ', $w);
        return $this->view('auditoria/index', [
            'titulo' => 'Auditoria', 'p' => $this->paginar($sql, $p, 'l.id DESC', $r, 50), 'f' => $f,
            'usuarios' => Database::all('SELECT id, nome FROM usuarios ORDER BY nome'),
            'entidades' => array_column(Database::all('SELECT DISTINCT entidade FROM logs_auditoria WHERE entidade IS NOT NULL ORDER BY entidade'), 'entidade'),
            'acoes' => array_column(Database::all('SELECT DISTINCT acao FROM logs_auditoria ORDER BY acao'), 'acao'),
        ]);
    }

    public function ver(Request $r): Response
    {
        $l = Database::first('SELECT * FROM logs_auditoria WHERE id = ?', [$this->id($r)]) ?? throw new HttpException(404);
        return $this->view('auditoria/ver', ['titulo' => 'Registro de auditoria #' . $l['id'], 'l' => $l]);
    }
}
