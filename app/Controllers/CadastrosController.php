<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\Auditoria;
use App\Services\Auth;
use App\Services\Calculo\DiasExibicao;
use App\Validators\DocumentoValidator;

/**
 * Manutenção do catálogo comercial que, no Gerador original, ficava fixo no JavaScript:
 * empresas emissoras, veículos (blocos e cláusulas 6/7), tabelas/praças e preços por ano, programas (dias, faixa,
 * índice do 15", esquema de patrocínio, GRP/impactos), formatos digitais e de publicidade legal, índices de duração,
 * cláusulas fixas (RN-47), feriados e listas de opções.
 */
final class CadastrosController extends Controller
{
    public function index(Request $r): Response
    {
        $cont = [
            'empresas' => (int) Database::value('SELECT COUNT(*) FROM empresas'),
            'pendentes' => (int) Database::value('SELECT COUNT(*) FROM empresas WHERE razao_social IS NULL OR cnpj IS NULL'),
            'veiculos' => (int) Database::value('SELECT COUNT(*) FROM veiculos'),
            'tabelas' => (int) Database::value('SELECT COUNT(*) FROM tabelas_precos WHERE ativo = 1'),
            'programas' => (int) Database::value('SELECT COUNT(*) FROM programas WHERE ativo = 1'),
            'formatos' => (int) Database::value('SELECT COUNT(*) FROM formatos_midia WHERE ativo = 1'),
            'precos' => (int) Database::value('SELECT COUNT(*) FROM precos_midia'),
            'feriados' => (int) Database::value('SELECT COUNT(*) FROM feriados WHERE data >= CURDATE()'),
            'anos' => array_column(Database::all('SELECT DISTINCT ano_vigencia FROM tabelas_precos ORDER BY ano_vigencia'), 'ano_vigencia'),
        ];
        return $this->view('cadastros/index', ['titulo' => 'Tabelas e veículos', 'c' => $cont]);
    }

    // ---------------------------------------------------------------- empresas emissoras
    public function empresas(Request $r): Response
    {
        return $this->view('cadastros/empresas', ['titulo' => 'Empresas emissoras', 'empresas' => Database::all(
            'SELECT e.*, GROUP_CONCAT(v.nome ORDER BY v.ordem SEPARATOR \', \') AS veiculos FROM empresas e LEFT JOIN veiculos v ON v.empresa_id = e.id GROUP BY e.id ORDER BY e.id'
        )]);
    }

    public function salvarEmpresa(Request $r): Response
    {
        $id = $this->id($r);
        $antes = Database::first('SELECT * FROM empresas WHERE id = ?', [$id]) ?? throw new HttpException(404);
        $cnpj = trim($r->str('cnpj'));
        $e = [];
        $d = [
            'nome_curto' => $r->str('nome_curto'),
            'razao_social' => $r->str('razao_social') ?: null,
            'cnpj' => null,
            'endereco' => $r->str('endereco') ?: null,
            'telefone' => $r->str('telefone') ?: null,
            'inscricao_municipal' => $r->str('inscricao_municipal') ?: null,
            'dados_bancarios' => $r->str('dados_bancarios') ?: null,
            'ativo' => $r->str('ativo') === '1' ? 1 : 0,
        ];
        if ($d['nome_curto'] === '') {
            $e["empresa_{$id}_nome_curto"] = 'Informe o nome.';
        }
        if ($cnpj !== '') {
            $doc = DocumentoValidator::verificar($cnpj);
            if (!$doc['ok'] || $doc['tipo'] !== 'CNPJ') {
                $e["empresa_{$id}_cnpj"] = 'CNPJ inválido.';
            } else {
                $d['cnpj'] = $doc['formatado'];
            }
        }
        if ($e) {
            throw new ValidationException($e, 'Confira o CNPJ e o nome da empresa.');
        }
        Database::update('empresas', $d, ['id' => $id]);
        Auditoria::registrar('empresa_alterada', 'empresa', $id, 'Empresa ' . $d['nome_curto'], $antes, $d);
        return $this->voltar('/cadastros/empresas', 'Empresa ' . $d['nome_curto'] . ' salva. Os dados saem no cabeçalho das próximas PIs.');
    }

    // ---------------------------------------------------------------- veículos
    public function veiculos(Request $r): Response
    {
        return $this->view('cadastros/veiculos', ['titulo' => 'Veículos', 'veiculos' => Database::all(
            'SELECT v.*, e.nome_curto AS empresa, (SELECT COUNT(*) FROM programas p WHERE p.veiculo_id = v.id AND p.ativo = 1) AS programas,
                    (SELECT COUNT(*) FROM formatos_midia f WHERE f.veiculo_id = v.id AND f.ativo = 1) AS formatos
               FROM veiculos v JOIN empresas e ON e.id = v.empresa_id ORDER BY v.ordem'
        )]);
    }

    public function veiculo(Request $r): Response
    {
        $v = Database::first('SELECT * FROM veiculos WHERE id = ?', [$this->id($r)]) ?? throw new HttpException(404);
        $empresas = [];
        foreach (Database::all('SELECT id, nome_curto FROM empresas ORDER BY nome_curto') as $e) {
            $empresas[(int) $e['id']] = $e['nome_curto'];
        }
        return $this->view('cadastros/veiculo', ['titulo' => 'Veículo ' . $v['nome'], 'v' => $v, 'empresas' => $empresas]);
    }

    public function salvarVeiculo(Request $r): Response
    {
        $id = $this->id($r);
        $antes = Database::first('SELECT * FROM veiculos WHERE id = ?', [$id]) ?? throw new HttpException(404);
        $d = [
            'nome' => $r->str('nome'), 'empresa_id' => $r->int('empresa_id'), 'titulo_pi' => $r->str('titulo_pi'),
            'bloco_insercoes' => $r->str('bloco_insercoes') === '1' ? 1 : 0, 'bloco_patrocinio' => $r->str('bloco_patrocinio') === '1' ? 1 : 0,
            'bloco_digital' => $r->str('bloco_digital') === '1' ? 1 : 0, 'bloco_publicidade_legal' => $r->str('bloco_publicidade_legal') === '1' ? 1 : 0,
            'clausula_6' => $r->str('clausula_6') ?: null, 'clausula_7' => $r->str('clausula_7') ?: null, 'texto_atendimento' => $r->str('texto_atendimento') ?: null,
            'ativo' => $r->str('ativo') === '1' ? 1 : 0,
        ];
        $e = [];
        if ($d['nome'] === '') {
            $e['nome'] = 'Informe o nome do veículo.';
        } elseif (Database::value('SELECT id FROM veiculos WHERE nome = ? AND id <> ?', [$d['nome'], $id])) {
            $e['nome'] = 'Já existe veículo com este nome.';
        }
        if (!Database::value('SELECT id FROM empresas WHERE id = ?', [$d['empresa_id']])) {
            $e['empresa_id'] = 'Escolha a empresa emissora.';
        }
        if ($d['titulo_pi'] === '') {
            $e['titulo_pi'] = 'Informe o título que sai na PI.';
        }
        if (!$d['bloco_insercoes'] && !$d['bloco_patrocinio'] && !$d['bloco_digital'] && !$d['bloco_publicidade_legal']) {
            $e['bloco_insercoes'] = 'Marque ao menos um bloco de mídia (RN-09).';
        }
        if ($d['bloco_publicidade_legal'] && $antes['prefixo'] !== 'IMI') {
            $e['bloco_publicidade_legal'] = 'Publicidade legal é exclusiva do Imirante.';
        }
        if ($e) {
            throw new ValidationException($e);
        }
        Database::update('veiculos', $d, ['id' => $id]);
        Auditoria::registrar('veiculo_alterado', 'veiculo', $id, 'Veículo ' . $d['nome'], $antes, $d);
        return $this->voltar('/cadastros/veiculos', 'Veículo salvo. O prefixo da numeração (' . $antes['prefixo'] . ') não muda, para não repetir números.');
    }

    // ---------------------------------------------------------------- tabelas / praças e preços
    public function tabelas(Request $r): Response
    {
        $ano = $r->int('ano') ?: (int) (Database::value('SELECT MAX(ano_vigencia) FROM tabelas_precos WHERE ano_vigencia <= YEAR(CURDATE())') ?: date('Y'));
        $tabelas = Database::all(
            'SELECT t.*, v.nome AS veiculo, v.tipo, (SELECT COUNT(*) FROM precos_midia pm WHERE pm.tabela_preco_id = t.id) AS qtd_precos
               FROM tabelas_precos t JOIN veiculos v ON v.id = t.veiculo_id WHERE t.ano_vigencia = ? ORDER BY v.ordem, t.ordem',
            [$ano]
        );
        $anos = array_map('intval', array_column(Database::all('SELECT DISTINCT ano_vigencia FROM tabelas_precos ORDER BY ano_vigencia DESC'), 'ano_vigencia'));
        $veiculos = [];
        foreach (Database::all('SELECT id, nome FROM veiculos ORDER BY ordem') as $v) {
            $veiculos[(int) $v['id']] = $v['nome'];
        }
        return $this->view('cadastros/tabelas', ['titulo' => 'Tabelas de preços', 'ano' => $ano, 'anos' => $anos, 'tabelas' => $tabelas, 'veiculos' => $veiculos]);
    }

    public function salvarTabela(Request $r): Response
    {
        $id = $r->int('id');
        $d = [
            'veiculo_id' => $r->int('veiculo_id'), 'codigo' => mb_strtoupper($r->str('codigo')), 'descricao' => $r->str('descricao'),
            'cidade' => $r->str('cidade') ?: 'São Luís - MA', 'ano_vigencia' => $r->int('ano_vigencia'), 'sem_tabela' => $r->str('sem_tabela') === '1' ? 1 : 0,
            'ativo' => $r->str('ativo', '1') === '1' ? 1 : 0,
        ];
        $e = [];
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $d['codigo'])) {
            $e['codigo'] = 'Código com 2 a 10 letras ou números (ex.: MA1, LOC).';
        }
        if ($d['descricao'] === '') {
            $e['descricao'] = 'Informe a descrição (ex.: São Luís, Verba local).';
        }
        if ($d['ano_vigencia'] < 2000 || $d['ano_vigencia'] > 2100) {
            $e['ano_vigencia'] = 'Ano inválido.';
        }
        if (!Database::value('SELECT id FROM veiculos WHERE id = ?', [$d['veiculo_id']])) {
            $e['veiculo_id'] = 'Escolha o veículo.';
        }
        if (Database::value('SELECT id FROM tabelas_precos WHERE veiculo_id = ? AND codigo = ? AND ano_vigencia = ? AND id <> ?', [$d['veiculo_id'], $d['codigo'], $d['ano_vigencia'], $id])) {
            $e['codigo'] = 'Este veículo já tem a tabela ' . $d['codigo'] . ' em ' . $d['ano_vigencia'] . '.';
        }
        if ($e) {
            throw new ValidationException($e);
        }
        if ($id) {
            $antes = Database::first('SELECT * FROM tabelas_precos WHERE id = ?', [$id]) ?? throw new HttpException(404);
            Database::update('tabelas_precos', $d, ['id' => $id]);
            Auditoria::registrar('tabela_alterada', 'tabela_preco', $id, "Tabela {$d['codigo']}/{$d['ano_vigencia']}", $antes, $d);
        } else {
            $id = Database::insert('tabelas_precos', $d + ['ordem' => 99]);
            Auditoria::registrar('tabela_criada', 'tabela_preco', $id, "Tabela {$d['codigo']}/{$d['ano_vigencia']}", null, $d);
        }
        return $this->voltar('/cadastros/tabelas?ano=' . $d['ano_vigencia'], 'Tabela salva.');
    }

    /** Cria as tabelas de um ano novo copiando as de outro ano (para depois atualizar os preços). */
    public function copiarAno(Request $r): Response
    {
        $de = $r->int('de');
        $para = $r->int('para');
        if ($para <= $de || $para > 2100) {
            throw new BusinessException('Escolha um ano de destino maior que o de origem.');
        }
        if (Database::value('SELECT COUNT(*) FROM tabelas_precos WHERE ano_vigencia = ?', [$para])) {
            throw new BusinessException("Já existem tabelas de {$para}. Edite os preços diretamente.");
        }
        $n = Database::transaction(function () use ($de, $para) {
            $n = 0;
            foreach (Database::all('SELECT * FROM tabelas_precos WHERE ano_vigencia = ?', [$de]) as $t) {
                $novo = Database::insert('tabelas_precos', ['veiculo_id' => $t['veiculo_id'], 'codigo' => $t['codigo'], 'descricao' => $t['descricao'], 'cidade' => $t['cidade'],
                    'ano_vigencia' => $para, 'sem_tabela' => $t['sem_tabela'], 'ordem' => $t['ordem'], 'ativo' => $t['ativo']]);
                Database::query('INSERT INTO precos_midia (tabela_preco_id, programa_id, formato_id, tipo, duracao_seg, valor) SELECT ?, programa_id, formato_id, tipo, duracao_seg, valor FROM precos_midia WHERE tabela_preco_id = ?', [$novo, (int) $t['id']]);
                Database::query('INSERT INTO programa_audiencia (programa_id, tabela_preco_id, grp, impactos) SELECT programa_id, ?, grp, impactos FROM programa_audiencia WHERE tabela_preco_id = ?', [$novo, (int) $t['id']]);
                $n++;
            }
            return $n;
        });
        Auditoria::registrar('tabelas_copiadas', 'tabela_preco', null, "{$n} tabelas copiadas de {$de} para {$para}");
        return $this->voltar('/cadastros/tabelas?ano=' . $para, "{$n} tabelas de {$de} copiadas para {$para}. Atualize os preços de cada tabela antes de começar a vender com elas.");
    }

    public function precos(Request $r): Response
    {
        $t = $this->tabela($this->id($r));
        $linhas = [];
        $duracoes = [];
        if ($t['tipo'] === 'Digital') {
            foreach (Database::all('SELECT f.id, f.sigla, f.nome, f.modelo, f.publicidade_legal, pm.valor FROM formatos_midia f LEFT JOIN precos_midia pm ON pm.formato_id = f.id AND pm.tabela_preco_id = ? AND pm.tipo = \'unitario\' WHERE f.veiculo_id = ? AND f.ativo = 1 ORDER BY f.publicidade_legal, f.modelo, f.nome', [(int) $t['id'], (int) $t['veiculo_id']]) as $f) {
                $linhas[] = $f;
            }
        } else {
            $duracoes = $t['tipo'] === 'Rádio'
                ? array_map('intval', array_column(Database::all('SELECT DISTINCT duracao_seg FROM precos_midia pm JOIN tabelas_precos t ON t.id = pm.tabela_preco_id WHERE t.veiculo_id = ? AND pm.tipo = \'duracao\' ORDER BY duracao_seg', [(int) $t['veiculo_id']]), 'duracao_seg'))
                : [];
            if ($t['tipo'] === 'Rádio' && !$duracoes) {
                $duracoes = [15, 30, 45, 60, 90];
            }
            $precos = [];
            foreach (Database::all('SELECT programa_id, tipo, duracao_seg, valor FROM precos_midia WHERE tabela_preco_id = ? AND programa_id IS NOT NULL', [(int) $t['id']]) as $p) {
                $precos[(int) $p['programa_id']][$p['tipo'] . ':' . (int) $p['duracao_seg']] = $p['valor'];
            }
            $aud = [];
            foreach (Database::all('SELECT programa_id, grp, impactos FROM programa_audiencia WHERE tabela_preco_id = ?', [(int) $t['id']]) as $a) {
                $aud[(int) $a['programa_id']] = $a;
            }
            foreach (Database::all('SELECT id, sigla, nome, dias_exibicao, faixa_horaria FROM programas WHERE veiculo_id = ? AND ativo = 1 ORDER BY nome', [(int) $t['veiculo_id']]) as $p) {
                $p['precos'] = $precos[(int) $p['id']] ?? [];
                $p['aud'] = $aud[(int) $p['id']] ?? null;
                $linhas[] = $p;
            }
        }
        return $this->view('cadastros/precos', ['titulo' => 'Preços · ' . $t['veiculo'] . ' · ' . $t['codigo'] . ' ' . $t['ano_vigencia'], 't' => $t, 'linhas' => $linhas, 'duracoes' => $duracoes]);
    }

    public function salvarPrecos(Request $r): Response
    {
        $t = $this->tabela($this->id($r));
        $entrada = (array) $r->input('preco', []);
        $audiencia = (array) $r->input('aud', []);
        $mudancas = 0;
        $log = [];
        Database::transaction(function () use ($t, $entrada, $audiencia, &$mudancas, &$log) {
            $atuais = [];
            foreach (Database::all('SELECT id, programa_id, formato_id, tipo, duracao_seg, valor FROM precos_midia WHERE tabela_preco_id = ?', [(int) $t['id']]) as $p) {
                $k = ($p['programa_id'] ? 'p' . $p['programa_id'] : 'f' . $p['formato_id']) . '|' . $p['tipo'] . '|' . (int) $p['duracao_seg'];
                $atuais[$k] = $p;
            }
            foreach ($entrada as $chave => $valor) {
                if (!preg_match('/^([pf])(\d+)\|(base_30s|duracao|cota_patrocinio|unitario)\|(\d+)$/', (string) $chave, $m)) {
                    continue;
                }
                $item = (int) $m[2];
                $tabelaItem = $m[1] === 'p' ? 'programas' : 'formatos_midia';
                if (!Database::value("SELECT id FROM {$tabelaItem} WHERE id = ? AND veiculo_id = ?", [$item, (int) $t['veiculo_id']])) {
                    continue;
                }
                $txt = trim((string) $valor);
                $novo = $txt === '' ? null : round(valor_decimal($txt), 2);
                $atual = $atuais[$chave] ?? null;
                if ($novo === null && $atual) {
                    Database::query('DELETE FROM precos_midia WHERE id = ?', [(int) $atual['id']]);
                    $log[$chave] = [$atual['valor'], null];
                    $mudancas++;
                } elseif ($novo !== null && $novo < 0) {
                    throw new ValidationException(['preco' => 'Preço não pode ser negativo.']);
                } elseif ($novo !== null && (!$atual || abs((float) $atual['valor'] - $novo) >= 0.005)) {
                    if ($atual) {
                        Database::query('UPDATE precos_midia SET valor = ? WHERE id = ?', [$novo, (int) $atual['id']]);
                    } else {
                        Database::insert('precos_midia', ['tabela_preco_id' => (int) $t['id'], 'programa_id' => $m[1] === 'p' ? $item : null, 'formato_id' => $m[1] === 'f' ? $item : null,
                            'tipo' => $m[3], 'duracao_seg' => (int) $m[4] ?: null, 'valor' => $novo]);
                    }
                    $log[$chave] = [$atual['valor'] ?? null, $novo];
                    $mudancas++;
                }
            }
            foreach ($audiencia as $pid => $a) {
                if (!is_array($a) || !Database::value('SELECT id FROM programas WHERE id = ? AND veiculo_id = ?', [(int) $pid, (int) $t['veiculo_id']])) {
                    continue;
                }
                $grp = trim((string) ($a['grp'] ?? '')) === '' ? null : valor_decimal($a['grp']);
                $imp = trim((string) ($a['impactos'] ?? '')) === '' ? null : (int) preg_replace('/\D/', '', (string) $a['impactos']);
                $atual = Database::first('SELECT grp, impactos FROM programa_audiencia WHERE programa_id = ? AND tabela_preco_id = ?', [(int) $pid, (int) $t['id']]);
                if ($grp === null && $imp === null) {
                    if ($atual) {
                        Database::query('DELETE FROM programa_audiencia WHERE programa_id = ? AND tabela_preco_id = ?', [(int) $pid, (int) $t['id']]);
                        $mudancas++;
                    }
                    continue;
                }
                if (!$atual || abs((float) $atual['grp'] - (float) $grp) > 0.000001 || (int) $atual['impactos'] !== (int) $imp) {
                    Database::query('INSERT INTO programa_audiencia (programa_id, tabela_preco_id, grp, impactos) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE grp = VALUES(grp), impactos = VALUES(impactos)',
                        [(int) $pid, (int) $t['id'], $grp ?? 0, $imp ?? 0]);
                    $log['aud' . $pid] = [$atual, ['grp' => $grp, 'impactos' => $imp]];
                    $mudancas++;
                }
            }
        });
        if ($mudancas) {
            Auditoria::registrar('precos_alterados', 'tabela_preco', (int) $t['id'], "{$mudancas} preço(s)/audiência(s) alterados na tabela {$t['codigo']}/{$t['ano_vigencia']} de {$t['veiculo']}", null, ['alteracoes' => $log]);
        }
        return $this->voltar('/cadastros/tabelas/' . $t['id'] . '/precos', $mudancas ? "{$mudancas} alteração(ões) salvas. PIs já geradas não mudam; as próximas usam os novos valores." : 'Nenhuma alteração.', $mudancas ? 'success' : 'info');
    }

    // ---------------------------------------------------------------- programas
    public function programas(Request $r): Response
    {
        $vid = $r->int('veiculo');
        $q = $r->str('q');
        $w = ["v.tipo <> 'Digital'"];
        $p = [];
        if ($vid) {
            $w[] = 'p.veiculo_id = :v';
            $p['v'] = $vid;
        }
        if ($q !== '') {
            $w[] = '(p.sigla LIKE :q OR p.nome LIKE :q2)';
            $p += ['q' => "%{$q}%", 'q2' => "%{$q}%"];
        }
        $sql = 'SELECT p.*, v.nome AS veiculo, v.tipo FROM programas p JOIN veiculos v ON v.id = p.veiculo_id WHERE ' . implode(' AND ', $w);
        return $this->view('cadastros/programas', ['titulo' => 'Programas', 'p' => $this->paginar($sql, $p, 'v.ordem, p.nome', $r, 50), 'vid' => $vid, 'q' => $q,
            'veiculos' => Database::all("SELECT id, nome FROM veiculos WHERE tipo <> 'Digital' ORDER BY ordem")]);
    }

    public function programa(Request $r): Response
    {
        $pr = ['ativo' => 1, 'veiculo_id' => $r->int('veiculo')];
        $esq = null;
        if ($r->param('id') !== '') {
            $pr = Database::first('SELECT * FROM programas WHERE id = ?', [$this->id($r)]) ?? throw new HttpException(404);
            $esq = Database::first('SELECT * FROM programa_esquemas WHERE programa_id = ?', [(int) $pr['id']]);
        }
        $veiculos = [];
        foreach (Database::all("SELECT id, nome, tipo FROM veiculos WHERE tipo <> 'Digital' ORDER BY ordem") as $v) {
            $veiculos[(int) $v['id']] = $v['nome'];
        }
        return $this->view('cadastros/programa', ['titulo' => empty($pr['id']) ? 'Novo programa' : 'Programa ' . $pr['sigla'], 'pr' => $pr, 'esq' => $esq, 'veiculos' => $veiculos]);
    }

    public function salvarPrograma(Request $r): Response
    {
        $id = $r->param('id') !== '' ? $this->id($r) : null;
        $antes = $id ? (Database::first('SELECT * FROM programas WHERE id = ?', [$id]) ?? throw new HttpException(404)) : null;
        $vid = $r->int('veiculo_id');
        $v = Database::first('SELECT id, tipo FROM veiculos WHERE id = ?', [$vid]);
        $dias = $r->str('dias_exibicao');
        $d = [
            'veiculo_id' => $vid, 'sigla' => mb_strtoupper($r->str('sigla')), 'nome' => $r->str('nome'), 'dias_exibicao' => $dias ?: null,
            'dias_mascara' => DiasExibicao::mascara($dias), 'faixa_horaria' => $r->str('faixa_horaria') ?: null,
            'indice_15s' => ($v && $v['tipo'] === 'TV' && $r->str('indice_15s') !== '') ? valor_decimal($r->str('indice_15s')) : null,
            'ativo' => $r->str('ativo') === '1' ? 1 : 0,
        ];
        $e = [];
        if (!$v || $v['tipo'] === 'Digital') {
            $e['veiculo_id'] = 'Escolha a TV ou uma rádio.';
        }
        if (!preg_match('/^[A-Z0-9]{2,12}$/', $d['sigla'])) {
            $e['sigla'] = 'Sigla com 2 a 12 letras ou números, sem espaço.';
        } elseif (Database::value('SELECT id FROM programas WHERE veiculo_id = ? AND sigla = ? AND id <> ?', [$vid, $d['sigla'], $id ?? 0])) {
            $e['sigla'] = 'Esta sigla já existe neste veículo.';
        }
        if ($d['nome'] === '') {
            $e['nome'] = 'Informe o nome do programa.';
        }
        if ($dias !== '' && $d['dias_mascara'] === null && !in_array(normaliza($dias), ['variavel'], true)) {
            $e['dias_exibicao'] = 'Use o padrão do cadastro: SEG/SEX, SEG/SÁB, SEG/DOM, SÁB/DOM, TER/QUI, QUA, DIÁRIO ou "Variável".';
        }
        if ($d['indice_15s'] !== null && ($d['indice_15s'] <= 0 || $d['indice_15s'] > 4)) {
            $e['indice_15s'] = 'Índice entre 0 e 4 (ex.: 0,5 ou 0,75).';
        }
        if ($e) {
            throw new ValidationException($e);
        }
        $id = Database::transaction(function () use ($id, $d, $r, $v, $antes) {
            if ($id) {
                Database::update('programas', $d, ['id' => $id]);
            } else {
                $id = Database::insert('programas', $d);
            }
            if ($v['tipo'] === 'Rádio') {
                $esq = array_map(fn ($k) => max(0, (int) $r->input('esq_' . $k, 0)), ['ins_30', 'ins_60', 'vinheta', 'flash', 'testemunhal', 'entrevista']);
                if (array_sum($esq)) {
                    Database::query('INSERT INTO programa_esquemas (programa_id, ins_30, ins_60, vinheta, flash, testemunhal, entrevista) VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE ins_30 = VALUES(ins_30), ins_60 = VALUES(ins_60), vinheta = VALUES(vinheta), flash = VALUES(flash), testemunhal = VALUES(testemunhal), entrevista = VALUES(entrevista)', [$id, ...$esq]);
                } else {
                    Database::query('DELETE FROM programa_esquemas WHERE programa_id = ?', [$id]);
                }
            }
            Auditoria::registrar($antes ? 'programa_alterado' : 'programa_criado', 'programa', $id, 'Programa ' . $d['sigla'] . ' — ' . $d['nome'], $antes, $d);
            return $id;
        });
        return $this->voltar('/cadastros/programas?veiculo=' . $vid, 'Programa ' . $d['sigla'] . ' salvo. Lembre de conferir os preços dele em Tabelas de preços.');
    }

    // ---------------------------------------------------------------- formatos digitais
    public function formatos(Request $r): Response
    {
        $vid = $r->int('veiculo');
        $q = $r->str('q');
        $w = ["v.tipo = 'Digital'"];
        $p = [];
        if ($vid) {
            $w[] = 'f.veiculo_id = :v';
            $p['v'] = $vid;
        }
        if ($q !== '') {
            $w[] = '(f.sigla LIKE :q OR f.nome LIKE :q2)';
            $p += ['q' => "%{$q}%", 'q2' => "%{$q}%"];
        }
        $sql = 'SELECT f.*, v.nome AS veiculo FROM formatos_midia f JOIN veiculos v ON v.id = f.veiculo_id WHERE ' . implode(' AND ', $w);
        return $this->view('cadastros/formatos', ['titulo' => 'Formatos digitais', 'p' => $this->paginar($sql, $p, 'v.ordem, f.publicidade_legal, f.nome', $r, 50), 'vid' => $vid, 'q' => $q,
            'veiculos' => Database::all("SELECT id, nome FROM veiculos WHERE tipo = 'Digital' ORDER BY ordem")]);
    }

    public function formato(Request $r): Response
    {
        $f = ['ativo' => 1, 'veiculo_id' => $r->int('veiculo'), 'modelo' => 'Mensal'];
        if ($r->param('id') !== '') {
            $f = Database::first('SELECT * FROM formatos_midia WHERE id = ?', [$this->id($r)]) ?? throw new HttpException(404);
        }
        $veiculos = [];
        foreach (Database::all("SELECT id, nome FROM veiculos WHERE tipo = 'Digital' ORDER BY ordem") as $v) {
            $veiculos[(int) $v['id']] = $v['nome'];
        }
        return $this->view('cadastros/formato', ['titulo' => empty($f['id']) ? 'Novo formato' : 'Formato ' . $f['sigla'], 'f' => $f, 'veiculos' => $veiculos]);
    }

    public function salvarFormato(Request $r): Response
    {
        $id = $r->param('id') !== '' ? $this->id($r) : null;
        $antes = $id ? (Database::first('SELECT * FROM formatos_midia WHERE id = ?', [$id]) ?? throw new HttpException(404)) : null;
        $vid = $r->int('veiculo_id');
        $v = Database::first('SELECT id, tipo, prefixo FROM veiculos WHERE id = ?', [$vid]);
        $modelo = $r->str('modelo');
        $d = [
            'veiculo_id' => $vid, 'sigla' => mb_strtoupper($r->str('sigla')), 'nome' => $r->str('nome'), 'modelo' => $modelo, 'categoria' => $r->str('categoria') ?: null,
            'entrega_estimada' => $r->str('entrega_estimada') !== '' ? (int) preg_replace('/\D/', '', $r->str('entrega_estimada')) : null,
            'visibilidade_pct' => $r->str('visibilidade_pct') !== '' ? valor_decimal($r->str('visibilidade_pct')) : null,
            'dispositivo' => $r->str('dispositivo') ?: null, 'local_pagina' => $r->str('local_pagina') ?: null, 'observacao' => $r->str('observacao') ?: null,
            'publicidade_legal' => $modelo === 'cm/col' ? 1 : 0, 'ativo' => $r->str('ativo') === '1' ? 1 : 0,
        ];
        $e = [];
        if (!$v || $v['tipo'] !== 'Digital') {
            $e['veiculo_id'] = 'Escolha G1 Maranhão ou Imirante.';
        }
        if (!in_array($modelo, ['Mensal', 'Diária', 'Unidade', 'Pacote', 'CPM', 'cm/col'], true)) {
            $e['modelo'] = 'Escolha o modelo de cobrança.';
        } elseif ($modelo === 'cm/col' && $v && $v['prefixo'] !== 'IMI') {
            $e['modelo'] = 'Publicidade legal (cm/col) é exclusiva do Imirante.';
        }
        if (!preg_match('/^[A-Z0-9]{2,12}$/', $d['sigla'])) {
            $e['sigla'] = 'Sigla com 2 a 12 letras ou números.';
        } elseif (Database::value('SELECT id FROM formatos_midia WHERE veiculo_id = ? AND sigla = ? AND id <> ?', [$vid, $d['sigla'], $id ?? 0])) {
            $e['sigla'] = 'Esta sigla já existe neste veículo.';
        }
        if ($d['nome'] === '') {
            $e['nome'] = 'Informe o nome do formato.';
        }
        if ($e) {
            throw new ValidationException($e);
        }
        if ($id) {
            Database::update('formatos_midia', $d, ['id' => $id]);
        } else {
            $id = Database::insert('formatos_midia', $d);
        }
        Auditoria::registrar($antes ? 'formato_alterado' : 'formato_criado', 'formato', $id, 'Formato ' . $d['sigla'], $antes, $d);
        return $this->voltar('/cadastros/formatos?veiculo=' . $vid, 'Formato ' . $d['sigla'] . ' salvo. Sem preço na tabela, ele aparece como "sob consulta" (preço manual com aprovação).');
    }

    // ---------------------------------------------------------------- índices de duração (RN-12)
    public function indices(Request $r): Response
    {
        return $this->view('cadastros/indices', ['titulo' => 'Índices de duração', 'indices' => Database::all('SELECT * FROM indices_duracao ORDER BY duracao_seg')]);
    }

    public function salvarIndices(Request $r): Response
    {
        $antes = Database::all('SELECT * FROM indices_duracao ORDER BY duracao_seg');
        $novos = [];
        foreach ((array) $r->input('indice', []) as $dur => $ind) {
            if (trim((string) $ind) !== '') {
                $novos[(int) $dur] = valor_decimal($ind);
            }
        }
        $nd = $r->int('nova_duracao');
        if ($nd > 0 && $r->str('novo_indice') !== '') {
            $novos[$nd] = valor_decimal($r->str('novo_indice'));
        }
        foreach ($novos as $dur => $ind) {
            if ($dur < 1 || $dur > 600 || $ind <= 0 || $ind > 40) {
                throw new ValidationException(['indice' => "Valor inválido para {$dur}\"."]);
            }
        }
        if (!isset($novos[30]) || abs($novos[30] - 1) > 0.00001) {
            throw new ValidationException(['indice' => 'O índice do 30" é a base e precisa ser 1.']);
        }
        Database::transaction(function () use ($novos) {
            Database::query('DELETE FROM indices_duracao');
            foreach ($novos as $dur => $ind) {
                Database::insert('indices_duracao', ['duracao_seg' => $dur, 'indice' => $ind]);
            }
        });
        Auditoria::registrar('indices_alterados', 'indices_duracao', null, 'Índices de duração da TV alterados', ['indices' => $antes], ['indices' => $novos]);
        return $this->voltar('/cadastros/indices', 'Índices salvos.');
    }

    // ---------------------------------------------------------------- cláusulas fixas (RN-47)
    public function clausulas(Request $r): Response
    {
        return $this->view('cadastros/clausulas', ['titulo' => 'Cláusulas da PI', 'clausulas' => Database::all('SELECT * FROM clausulas ORDER BY ordem'),
            'multa' => \App\Services\Configuracao::texto('multa_rescisao_texto')]);
    }

    public function salvarClausulas(Request $r): Response
    {
        if (!Auth::isAdmin()) {
            throw new HttpException(403, 'Só o administrador altera as cláusulas fixas (RN-47).');
        }
        $textos = (array) $r->input('texto', []);
        $n = 0;
        foreach (Database::all('SELECT * FROM clausulas') as $c) {
            $novo = trim((string) ($textos[$c['id']] ?? ''));
            if ($novo === '' || $novo === $c['texto']) {
                continue;
            }
            Database::query('UPDATE clausulas SET texto = ?, versao = versao + 1, updated_by = ? WHERE id = ?', [$novo, Auth::id(), (int) $c['id']]);
            Auditoria::registrar('clausula_alterada', 'clausula', (int) $c['id'], 'Cláusula ' . $c['codigo'] . ' alterada', ['texto' => $c['texto']], ['texto' => $novo]);
            $n++;
        }
        $multa = $r->str('multa_rescisao_texto');
        if ($multa !== \App\Services\Configuracao::texto('multa_rescisao_texto')) {
            \App\Services\Configuracao::definir('multa_rescisao_texto', $multa !== '' ? $multa : null, Auth::id());
            Auditoria::registrar('configuracao_alterada', 'configuracao', 'multa_rescisao_texto', 'Texto da multa por rescisão alterado');
            $n++;
        }
        return $this->voltar('/cadastros/clausulas', $n ? 'Cláusulas salvas. Valem para as próximas PIs geradas; as já geradas mantêm o texto da época.' : 'Nenhuma alteração.', $n ? 'success' : 'info');
    }

    // ---------------------------------------------------------------- feriados
    public function feriados(Request $r): Response
    {
        $ano = $r->int('ano') ?: (int) date('Y');
        return $this->view('cadastros/feriados', ['titulo' => 'Feriados', 'ano' => $ano, 'feriados' => Database::all('SELECT * FROM feriados WHERE YEAR(data) = ? ORDER BY data', [$ano])]);
    }

    public function salvarFeriado(Request $r): Response
    {
        $data = data_iso($r->str('data'));
        $desc = $r->str('descricao');
        $abr = $r->str('abrangencia');
        $e = [];
        if ($data === '') {
            $e['data'] = 'Informe a data.';
        }
        if ($desc === '') {
            $e['descricao'] = 'Informe a descrição.';
        }
        if (!in_array($abr, ['Nacional', 'Estadual', 'Municipal'], true)) {
            $e['abrangencia'] = 'Escolha a abrangência.';
        }
        if ($e) {
            throw new ValidationException($e);
        }
        Database::query('INSERT INTO feriados (data, descricao, abrangencia) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), abrangencia = VALUES(abrangencia)', [$data, $desc, $abr]);
        Auditoria::registrar('feriado_salvo', 'feriado', $data, "Feriado {$desc} em " . data_br($data));
        return $this->voltar('/cadastros/feriados?ano=' . substr($data, 0, 4), 'Feriado salvo. Ele passa a contar nos prazos em dias úteis.');
    }

    public function excluirFeriado(Request $r): Response
    {
        $data = data_iso($r->str('data'));
        Database::query('DELETE FROM feriados WHERE data = ?', [$data]);
        Auditoria::registrar('feriado_excluido', 'feriado', $data, 'Feriado excluído: ' . data_br($data));
        return $this->voltar('/cadastros/feriados?ano=' . substr($data, 0, 4), 'Feriado excluído.');
    }

    // ---------------------------------------------------------------- listas de opções
    public function listas(Request $r): Response
    {
        $listas = [];
        foreach (Database::all('SELECT * FROM listas_valores ORDER BY lista, ordem') as $l) {
            $listas[$l['lista']][] = $l;
        }
        return $this->view('cadastros/listas', ['titulo' => 'Listas de opções', 'listas' => $listas]);
    }

    public function salvarLista(Request $r): Response
    {
        $id = $r->int('id');
        if ($id) {
            $l = Database::first('SELECT * FROM listas_valores WHERE id = ?', [$id]) ?? throw new HttpException(404);
            $ativo = $r->str('ativo') === '1' ? 1 : 0;
            Database::query('UPDATE listas_valores SET ativo = ? WHERE id = ?', [$ativo, $id]);
            Auditoria::registrar('lista_alterada', 'lista', $id, ($ativo ? 'Reativou ' : 'Desativou ') . "\"{$l['valor']}\" em {$l['lista']}");
            return $this->voltar('/cadastros/listas', 'Opção ' . ($ativo ? 'reativada.' : 'desativada (não aparece mais nos formulários; PIs antigas não mudam).'));
        }
        $lista = $r->str('lista');
        $valor = $r->str('valor');
        if (!Database::value('SELECT 1 FROM listas_valores WHERE lista = ? LIMIT 1', [$lista]) || $valor === '' || mb_strlen($valor) > 80) {
            throw new ValidationException(['valor' => 'Informe a opção (até 80 caracteres).']);
        }
        if (Database::value('SELECT id FROM listas_valores WHERE lista = ? AND valor = ?', [$lista, $valor])) {
            throw new ValidationException(['valor' => 'Esta opção já existe.']);
        }
        $ordem = (int) Database::value('SELECT COALESCE(MAX(ordem), 0) + 1 FROM listas_valores WHERE lista = ?', [$lista]);
        $nid = Database::insert('listas_valores', ['lista' => $lista, 'valor' => $valor, 'ordem' => $ordem]);
        Auditoria::registrar('lista_incluida', 'lista', $nid, "Incluiu \"{$valor}\" em {$lista}");
        return $this->voltar('/cadastros/listas', 'Opção incluída.');
    }

    /** @return array<string,mixed> */
    private function tabela(int $id): array
    {
        return Database::first('SELECT t.*, v.nome AS veiculo, v.tipo FROM tabelas_precos t JOIN veiculos v ON v.id = t.veiculo_id WHERE t.id = ?', [$id]) ?? throw new HttpException(404, 'Tabela não encontrada.');
    }
}
