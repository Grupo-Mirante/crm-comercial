<?php

declare(strict_types=1);

namespace App\Services\Instalacao;

use App\Core\Config;
use App\Core\Database;
use App\Services\AuthService;
use RuntimeException;

/**
 * Dados FICTÍCIOS para homologação e testes automatizados.
 * Proteção: só roda com APP_ENV diferente de "production" E em banco cujo nome termina em "_teste".
 * Assim é impossível misturar dados de teste com a base real.
 */
final class SeedTeste
{
    /** Senha comum de todos os usuários de teste (somente no banco _teste). */
    public const SENHA = 'Teste2026';

    public static function permitido(): bool
    {
        $env = (string) Config::get('app.env', 'production');
        $db = (string) Config::get('database.database', '');
        return $env !== 'production' && str_ends_with($db, '_teste');
    }

    /** @return array<string,int> ids dos usuários por login */
    public static function executar(): array
    {
        if (!self::permitido()) {
            throw new RuntimeException('Dados de teste bloqueados: use APP_ENV diferente de production e um banco com nome terminado em "_teste".');
        }
        if ((int) Database::value("SELECT COUNT(*) FROM usuarios WHERE login = 'teste.admin'")) {
            return self::usuarios();
        }
        $hash = AuthService::hash(self::SENHA);
        $perfil = fn (string $c) => (int) Database::value('SELECT id FROM perfis WHERE codigo = ?', [$c]);
        $u = [];
        $add = function (string $login, string $nome, string $perf, ?string $gestor = null, ?string $mat = null) use (&$u, $hash, $perfil) {
            $u[$login] = Database::insert('usuarios', [
                'nome' => $nome, 'login' => $login, 'email' => $login . '@teste.invalid', 'matricula' => $mat, 'telefone' => '(98) 0000-0000',
                'perfil_id' => $perfil($perf), 'gestor_id' => $gestor ? $u[$gestor] : null, 'senha_hash' => $hash, 'trocar_senha' => 0, 'ativo' => 1,
                'senha_alterada_em' => date('Y-m-d H:i:s', time() - 60),
            ]);
        };
        $add('teste.admin', 'Administrador de Teste', 'admin');
        $add('teste.diretor', 'Diretora de Teste', 'diretor');
        $add('teste.gerente', 'Gerente de Teste', 'gerente', 'teste.diretor', 'G001');
        $add('teste.gerente2', 'Gerente Dois de Teste', 'gerente', 'teste.diretor', 'G002');
        $add('teste.conf', 'Conferência de Teste', 'conf');
        $add('teste.fin', 'Financeiro de Teste', 'financeiro');
        $add('teste.exec', 'Executivo de Teste', 'exec', 'teste.gerente', 'E001');
        $add('teste.exec2', 'Executiva Dois de Teste', 'exec', 'teste.gerente', 'E002');
        $add('teste.exec3', 'Executivo de Outra Equipe', 'exec', 'teste.gerente2', 'E003');
        $add('teste.vend', 'Vendedor de Teste', 'vend', 'teste.gerente', 'V001');

        // Clientes e agências fictícios (documentos com dígitos verificadores válidos)
        Database::insert('clientes', ['razao_social' => 'Cliente Teste Comércio Ltda.', 'nome_fantasia' => 'Cliente Teste', 'tipo_documento' => 'CNPJ', 'documento' => '11222333000181',
            'documento_formatado' => '11.222.333/0001-81', 'endereco' => 'Rua de Teste, 100', 'bairro' => 'Centro', 'cidade' => 'São Luís', 'uf' => 'MA', 'cep' => '65000-000',
            'contato' => 'Contato Teste', 'telefone' => '(98) 0000-0001', 'email_faturamento' => 'financeiro@cliente.teste.invalid', 'codigo_interno' => 'T001']);
        Database::insert('clientes', ['razao_social' => 'Empresa Alfanumérica Teste S.A.', 'tipo_documento' => 'CNPJ', 'documento' => '12ABC34501DE35',
            'documento_formatado' => '12.ABC.345/01DE-35', 'endereco' => 'Av. Teste, 200', 'cidade' => 'Imperatriz', 'uf' => 'MA', 'contato' => 'Fulana', 'telefone' => '(99) 0000-0002',
            'email_faturamento' => 'nf@alfa.teste.invalid']);
        Database::insert('agencias', ['razao_social' => 'Agência Teste Propaganda Ltda.', 'cnpj' => '11444777000161', 'cnpj_formatado' => '11.444.777/0001-61', 'comissao_padrao' => 20,
            'carta_numero' => 'CC-001/2026', 'carta_validade' => date('Y') . '-12-31', 'cidade' => 'São Luís', 'uf' => 'MA', 'contato' => 'Atendimento', 'email' => 'midia@agencia.teste.invalid']);

        // Projetos do documento (cadastrados em 24/11/2026) — usados como EXEMPLO apenas na base de teste (decisão Q6)
        foreach ([['PROJ01', 'Projeto 01', '2026-12-31', [5000, 3000, 2000], [1, 2, 2]], ['PROJ02', 'Projeto 02', '2026-11-30', [6000, 4000, 3000], [1, 2, 2]]] as [$cod, $nome, $ate, $vals, $qtds]) {
            $pid = Database::insert('projetos_especiais', ['codigo' => $cod, 'nome' => $nome, 'descricao' => 'Exemplo do documento de processos (dados de teste).', 'data_limite_venda' => $ate]);
            foreach (Database::all('SELECT id FROM veiculos') as $v) {
                Database::insert('projetos_empresas', ['projeto_id' => $pid, 'veiculo_id' => (int) $v['id']]);
            }
            foreach (['Ouro', 'Prata', 'Bronze'] as $i => $cota) {
                Database::insert('projetos_cotas', ['projeto_id' => $pid, 'nome' => $cota, 'valor' => $vals[$i], 'quantidade_total' => $qtds[$i], 'ordem' => $i]);
            }
        }
        return $u;
    }

    /** @return array<string,int> */
    public static function usuarios(): array
    {
        $out = [];
        foreach (Database::all("SELECT id, login FROM usuarios WHERE login LIKE 'teste.%'") as $r) {
            $out[(string) $r['login']] = (int) $r['id'];
        }
        return $out;
    }
}
