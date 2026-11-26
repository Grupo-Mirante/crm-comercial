<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BusinessException;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Instalacao\Instalador;
use Throwable;

/**
 * Assistente web de instalação (/instalar). Roda antes de existir banco e sessão:
 *  - só abre enquanto NÃO houver storage/instalado.lock (depois responde 404);
 *  - exige o código de instalação gravado em storage/codigo-instalacao.txt (prova de acesso ao servidor);
 *  - o código vai em todo POST, servindo também de proteção contra CSRF.
 */
final class InstaladorController
{
    public static function handle(Request $r): Response
    {
        if (Instalador::instalado()) {
            return Response::html(View::render('erros/erro', ['status' => 404, 'mensagem' => 'Página não encontrada.', 'titulo' => 'Não encontrada'], 'simples'), 404);
        }
        $detectada = self::urlDetectada($r);
        Config::set('app.url', $detectada);
        Instalador::garantirCodigo();

        $codigo = (string) $r->input('codigo', '');
        $GLOBALS['__old'] = [];
        $GLOBALS['__errors'] = [];
        $dados = ['titulo' => 'Instalação', 'requisitos' => Instalador::requisitos($r->isSecure(), self::local($r)), 'detectada' => $detectada,
            'arquivoCodigo' => 'storage/codigo-instalacao.txt', 'mensagem' => null, 'tipo' => 'info', 'resultado' => null];

        if ($r->method !== 'POST') {
            return self::tela('codigo', $dados);
        }
        if (!Instalador::codigoValido($codigo)) {
            usleep(800000); // atrasa tentativas por força bruta
            $GLOBALS['__errors'] = ['codigo' => 'Código incorreto. Copie o código do arquivo ' . $dados['arquivoCodigo'] . ' no servidor.'];
            return self::tela('codigo', $dados, 422);
        }
        $dados['codigo'] = $codigo;
        $acao = $r->str('acao');
        if ($acao === 'entrar') {
            $GLOBALS['__old'] = self::padroes($detectada);
            return self::tela('formulario', $dados);
        }

        $GLOBALS['__old'] = array_diff_key($r->post, array_flip(['adm_senha', 'adm_confirmacao', 'codigo']));
        $db = ['host' => $r->str('db_host'), 'port' => $r->str('db_port', '3306'), 'socket' => $r->str('db_socket'), 'database' => $r->str('db_database'),
            'username' => $r->str('db_username'), 'password' => (string) $r->input('db_password', ''), 'criar' => (bool) $r->input('db_criar')];
        try {
            if ($acao === 'testar') {
                $c = Instalador::conectar($db, (bool) $r->input('db_criar'));
                Instalador::conferirBancoVazio($c['pdo']);
                $dados['mensagem'] = 'Conexão funcionando: MySQL ' . $c['versao'] . '. ' . ($c['criado'] ? 'O banco ' . $db['database'] . ' foi criado. ' : 'O banco existe ') . 'e está pronto para o CRM.';
                $dados['tipo'] = 'success';
                return self::tela('formulario', $dados);
            }
            $res = Instalador::instalar($db, ['url' => $r->str('app_url'), 'env' => $r->str('app_env', 'production')],
                ['nome' => $r->str('adm_nome'), 'login' => $r->str('adm_login'), 'email' => $r->str('adm_email'),
                    'senha' => (string) $r->input('adm_senha', ''), 'confirmacao' => (string) $r->input('adm_confirmacao', '')], 'instalador web');
            Config::set('app.url', rtrim($r->str('app_url'), '/'));
            $dados['resultado'] = $res;
            return self::tela('concluido', $dados);
        } catch (ValidationException $e) {
            $GLOBALS['__errors'] = $e->errors;
            $dados['mensagem'] = $e->getMessage();
            $dados['tipo'] = 'danger';
        } catch (BusinessException $e) {
            $dados['mensagem'] = $e->getMessage();
            $dados['tipo'] = 'danger';
        } catch (Throwable $e) {
            \App\Core\Logger::error('Instalação falhou: ' . $e->getMessage(), ['arquivo' => $e->getFile() . ':' . $e->getLine()]);
            $dados['mensagem'] = 'A instalação parou com um erro: ' . $e->getMessage() . ' — corrija e clique em Instalar de novo (o que já foi aplicado não é repetido).';
            $dados['tipo'] = 'danger';
        }
        return self::tela('formulario', $dados, 422);
    }

    private static function tela(string $etapa, array $dados, int $status = 200): Response
    {
        return Response::html(View::render('instalar/' . $etapa, $dados, 'instalador'), $status);
    }

    /** Valores iniciais: o que já estiver no .env (se o técnico preencheu) ou padrões seguros. */
    private static function padroes(string $detectada): array
    {
        $env = fn (string $k, string $p = '') => (string) (\App\Core\Env::get($k) ?? $p);
        $url = $env('APP_URL');
        return ['db_host' => $env('DB_HOST', '127.0.0.1'), 'db_port' => $env('DB_PORT', '3306'), 'db_socket' => $env('DB_SOCKET'),
            'db_database' => $env('DB_DATABASE', 'crm_grupo_mirante'), 'db_username' => $env('DB_USERNAME'),
            'app_url' => $url === '' || str_contains($url, 'seudominio') ? $detectada : $url, 'app_env' => 'production', 'db_criar' => ''];
    }

    private static function urlDetectada(Request $r): string
    {
        $host = (string) ($r->server['HTTP_HOST'] ?? 'localhost');
        $host = preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host) ? $host : 'localhost';
        $dir = rtrim(str_replace('\\', '/', dirname((string) ($r->server['SCRIPT_NAME'] ?? '/'))), '/.');
        return ($r->isSecure() ? 'https' : 'http') . '://' . $host . $dir;
    }

    private static function local(Request $r): bool
    {
        return in_array((string) ($r->server['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);
    }
}
