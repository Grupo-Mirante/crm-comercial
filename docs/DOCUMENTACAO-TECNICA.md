# Documentação técnica — CRM Comercial · Grupo Mirante

Versão 1.0.0 · PHP 8.5 · MySQL 8 · MVC próprio, sem framework

## 1. Visão geral

| Item | Escolha |
|---|---|
| Linguagem | PHP 8.5 (`declare(strict_types=1)` em todo arquivo) |
| Banco | MySQL 8, `utf8mb4_0900_ai_ci`, InnoDB, fuso da sessão = America/Sao_Paulo |
| Arquitetura | MVC leve: `Router` → middlewares → `Controller` → `Service` → `Database` (PDO) → `View` (PHP puro) |
| Front-end | Bootstrap 5, Bootstrap Icons, Chart.js, fontes Inter e Inter Tight, tudo servido localmente (sem CDN e sem Node em produção) |
| Dependências (Composer) | `phpoffice/phpspreadsheet` (Excel), `phpmailer/phpmailer` (e-mail). Dev: `phpunit/phpunit` |
| Idioma e formatos | pt-BR, R$ 1.234,56, DD/MM/AAAA |

**Princípio central:** o servidor é a única fonte da verdade. O navegador só monta a tela; todo cálculo, validação, numeração e autorização são refeitos no servidor (`CalculadoraPi` é chamada pela tela via `/api/pis/calcular` e de novo ao gerar).

## 2. Estrutura de pastas

```
crm-grupo-mirante/
├── app/
│   ├── Core/          App (boot, erros, cabeçalhos), Router, Request, Response, View, Database, Session (em banco),
│   │                  Csrf, Config, Env, Logger, SqlScript, exceções (Http, Validation, Business)
│   ├── Middleware/    Autenticado, Convidado, Permissao (perm:x|y), VerificaCsrf
│   ├── Controllers/   uma classe por área (Pi, Conferencia, Operacao, ReservasProjetos, Relatorios, Backup, Instalador…)
│   ├── Services/      regras de negócio (PiService, PiWorkflow, NumeracaoService, ProjetoService, ReservaService,
│   │   │              AprovacaoService, ParcelamentoService, RelatorioService, SlaService, RotinasService,
│   │   │              BackupService, AnexoService, ExcelPiService, AuthService, Auditoria, Notificacoes…)
│   │   ├── Calculo/   CalculadoraPi (motor), Catalogo, ContextoCalculo, DiasExibicao, DivisaoMensalTv
│   │   └── Instalacao/ Instalador, Migrador, SeedTeste
│   ├── Validators/    DocumentoValidator (CPF, CNPJ numérico e alfanumérico)
│   └── Helpers/       functions.php (e(), url(), moeda()…), Calendario, Form, Menu
├── bin/console.php    comandos de linha (migrar, rotinas, backup, restaurar, criar-admin, instalar…)
├── config/            app.php, database.php, mail.php, menu.php (lêem o .env)
├── database/
│   ├── migrations/    001_estrutura.sql (49 tabelas)
│   ├── seeds/producao/ perfis e permissões, catálogo 2026, checklist/cláusulas, configurações
│   ├── schema.sql     estrutura completa para consulta
│   └── tools/         extrator do catálogo a partir do Gerador original
├── public/            ÚNICA pasta exposta: index.php, .htaccess, assets (css, js, img, fonts, vendor)
├── resources/         layouts/ e views/ (PHP com escape e())
├── routes/web.php     todas as rotas com seus middlewares
├── storage/           logs, sessions, cache, exports, uploads (anexos), backups, instalado.lock
├── tests/             Unit, Integration, Concurrency (PHPUnit 12)
├── instalar.php       instalador pelo terminal
└── .env.example       modelo de configuração
```

## 3. Ciclo de uma requisição

1. `public/index.php` → `App::boot()` (lê `.env`, fuso, tratamento de erros; em produção `APP_DEBUG` é sempre desligado).
2. `App::handle()`:
   - `/instalar` → `InstaladorController` (só enquanto não existir `storage/instalado.lock`);
   - sem trava → tela "Instalação pendente";
   - `Session::start()` — sessão gravada na tabela `sessoes` (`DatabaseSessionHandler`), cookie `HttpOnly`, `SameSite=Lax`, `Secure` em produção, expiração ociosa e absoluta (≤ 12 h).
3. `Router::dispatch()` — encontra a rota; aplica `VerificaCsrf` em todo método que altera dados e os middlewares da rota (`auth`, `guest`, `perm:modulo.acao|…`).
4. O controller chama os services, que validam e gravam (transações quando há mais de uma tabela).
5. Erros viram respostas amigáveis: `ValidationException` (campos), `BusinessException` (regra), `HttpException` (403/404/419); qualquer outro erro é registrado em `storage/logs/app-AAAA-MM-DD.log` com um **código de referência** mostrado ao usuário.
6. `App::secure()` aplica os cabeçalhos: CSP (`script-src 'self'`, sem JS inline), `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cache-Control: no-store` e HSTS em HTTPS.

## 4. Banco de dados (49 tabelas)

| Grupo | Tabelas |
|---|---|
| Acesso | `perfis`, `permissoes`, `perfil_permissoes`, `usuarios`, `login_tentativas`, `senha_tokens`, `sessoes`, `logs_auditoria` |
| Catálogo | `empresas`, `veiculos`, `tabelas_precos`, `indices_duracao`, `programas`, `programa_esquemas`, `programa_audiencia`, `formatos_midia`, `precos_midia`, `listas_valores`, `clausulas`, `feriados`, `configuracoes` |
| Cadastros | `clientes`, `agencias` |
| Projetos e reservas | `projetos_especiais`, `projetos_empresas`, `projetos_cotas`, `reservas`, `reservas_itens` |
| PI | `sequencias_pi`, `pi_grupos`, `pi_rascunhos`, `pis`, `pi_itens`, `pi_insercoes_diarias`, `pi_patrocinios`, `pi_entregas_especiais`, `pi_parcelas`, `pi_aprovacoes`, `pi_versoes`, `pi_historico`, `anexos` |
| Conferência | `motivos_devolucao` (D01–D15), `checklist_conferencia`, `pi_conferencias`, `pi_devolucoes`, `pi_devolucao_codigos` |
| Sistema | `notificacoes`, `rotinas_execucoes`, `migrations` |

Pontos importantes:
- **`pi_versoes`** guarda o resultado completo do cálculo (JSON) de cada geração. O Excel e a impressão são montados a partir dela, então o documento sempre corresponde ao que foi gerado. O hash usa `PiService::canonico` porque o MySQL reordena chaves de JSON.
- A PI guarda uma **cópia** dos dados do cliente, agência e executivo no momento da geração: excluir ou editar cadastros não altera PIs antigas (RN-64).
- Cotas **vendidas** são calculadas por consulta (`pi_itens` de PIs vivas) e não por contador, o que evita divergências (RN-36).

## 5. Motor de cálculo

`App\Services\Calculo\CalculadoraPi::calcular(array $estado)` é a tradução fiel da função `calc()` do Gerador original (veja `database/tools/fonte/`). Recebe o estado da tela (`f` campos, `ins` inserções, `pat` patrocínios, `dig` digital, `avi` publicidade legal, `prj` projetos, `ent` entregas, `parc` parcelas) e devolve as linhas calculadas, os totais `T`, a conferência automática `C` (D01–D15, P01–P04), `needGs` (precisa de aprovação) e `manuais` (preços manuais).

- `Catalogo` lê programas, formatos, preços e índices do banco (por veículo e ano da tabela).
- `ContextoCalculo` fornece o que depende do banco ou do usuário: cotas disponíveis, reservas de outros, aprovações, cliente/agência cadastrados.
- `DivisaoMensalTv` divide a TV por mês (uma PI por mês, número do ano de cada mês, cota de projeto e produção só no 1º mês — ajuste A-01).
- `DiasExibicao` interpreta a máscara de dias de cada programa.

## 6. Numeração e concorrência

- Número `PREFIXO-ANO-SEQUÊNCIA` (TV, FM, NEWS, G1, IMI), anual por veículo, atribuído ao **Gerar PI**.
- `NumeracaoService`: `INSERT IGNORE` da linha de sequência **antes** da transação; dentro dela, `SELECT … FOR UPDATE` e incremento. `UNIQUE(numero)` garante no banco.
- Cotas de projeto: `ProjetoService::travarCotas` faz `SELECT … FOR UPDATE` ordenado por id (evita deadlock) e recalcula o saldo dentro da transação da geração ou da reserva.
- `Database::transaction($fn, 4)` repete a transação em caso de deadlock (1213) ou timeout de lock (1205).
- Testes com processos paralelos reais (`tests/Concurrency`): numeração simultânea, disputa pela última cota, gerações simultâneas.

## 7. Fluxo da PI (máquina de estados)

`PiWorkflow` concentra as transições e as permissões de cada uma:

`Rascunho → Enviada → (Devolvida → Corrigida →)* Aprovada → Em programação → Em veiculação → Pronta para faturamento → Faturada → Encerrada`, com `Cancelada` e `Substituída` como saídas. Cada mudança grava `pi_historico` (data, evento, responsável) e auditoria. A Conferência aprova com o checklist completo e devolve só com código; a segunda devolução notifica o gestor.

## 8. Rotinas, prazos e notificações

`php bin/console.php rotinas` (cron a cada 5 min) → `RotinasService::executar()`:
- expira reservas de 24 h vencidas;
- avisa conferência atrasada, correção atrasada (1 dia útil), início em ≤ 5 dias sem aprovação e aprovações paradas — **uma vez por dia por PI** (chave única na tabela `notificacoes`);
- monta a fila diária às 9h (`fila_diaria_hora`);
- faz o backup diário depois das 2h.

Cada execução fica em `rotinas_execucoes`. Os prazos ficam em `SlaService` (dias úteis com a tabela `feriados`).

## 9. Segurança

| Tema | Como é feito |
|---|---|
| SQL Injection | 100% prepared statements (`Database::query`, emulação desligada). Fragmentos dinâmicos de SQL vêm apenas de constantes do código (listas `IN` de situações, agrupamentos fixos dos relatórios). |
| XSS | Toda saída nas views passa por `e()`; o JS usa `esc()` antes de `innerHTML`; dados iniciais vão em `<script type="application/json">` com `JSON_HEX_*`; CSP sem `unsafe-inline` para scripts. |
| CSRF | Token por sessão em todo POST/PUT/DELETE (campo `_token` ou cabeçalho `X-CSRF-Token`). |
| Autenticação | Argon2id; bloqueio por usuário (5 erros → segundos) e por IP (30 em 15 min); regeneração do ID de sessão no login; recuperação por token de uso único (hash no banco, validade curta); senha mínima de 8 (12 para administradores). |
| Autorização | Sempre no servidor: middleware `perm:` por rota + verificação por objeto nos services (escopo própria/equipe/todas, dono da reserva, quem pede não aprova o próprio pedido). |
| Arquivos | Anexos fora de `public/`, nome aleatório, tipo conferido pelo conteúdo (`finfo`), tamanho limitado, download só para quem vê a PI. |
| Excel | Texto digitado é gravado como texto explícito: nada que comece com `=`, `+`, `-` ou `@` vira fórmula. |
| Instalação | Sem credencial padrão; administrador com senha forte e troca obrigatória no 1º acesso; assistente web exige código lido no servidor; trava `storage/instalado.lock`; recusa banco com dados. |
| Configuração | Segredos só no `.env` (fora da pasta pública, permissão 640); `.htaccess` nega a raiz do projeto e arquivos ocultos. |
| Auditoria | `logs_auditoria` com antes/depois (senhas e tokens mascarados), IP e navegador. |

## 10. Configuração (`.env`)

| Chave | Uso |
|---|---|
| `APP_ENV` | `production`, `staging` ou `local` (`testing` só nos testes) |
| `APP_DEBUG` | detalhes de erro na tela — ignorado em produção |
| `APP_URL` | endereço completo, sem barra no final |
| `APP_TRUST_PROXY` | `true` só atrás de proxy que termina o HTTPS |
| `DB_HOST`, `DB_PORT`, `DB_SOCKET`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | conexão MySQL |
| `SESSION_IDLE_MINUTES`, `SESSION_ABSOLUTE_MINUTES` | expiração da sessão (máximo 720) |
| `MAIL_*` | SMTP opcional |
| `UPLOAD_MAX_MB` | tamanho máximo dos anexos |
| `BACKUP_DIR`, `BACKUP_RETENCAO_DIAS`, `MYSQLDUMP_PATH`, `MYSQL_PATH` | backup |

As regras de negócio ajustáveis (prazos, alçadas, tentativas de login, validade de reserva) ficam na tabela `configuracoes`, editável em **Gestão → Configurações**.

## 11. Comandos de linha

```
php instalar.php                        instalação pelo terminal
php bin/console.php migrar              aplica estrutura e dados iniciais pendentes (atualizações)
php bin/console.php rotinas             rotinas agendadas (cron a cada 5 min) [--sem-backup]
php bin/console.php backup [--anexos]   backup em storage/backups
php bin/console.php restaurar ARQUIVO   restaura o banco (pede confirmação e faz backup antes)
php bin/console.php criar-admin         cria outro administrador
php bin/console.php expirar-reservas    só a expiração de reservas
php bin/console.php schema              regera database/schema.sql
php bin/console.php seed-teste          dados fictícios — bloqueado em produção e fora de banco *_teste
```

## 12. Testes automatizados

Pré-requisito: dependências de desenvolvimento (`composer install`) e um MySQL de teste.

```bash
export TEST_DB_HOST=127.0.0.1 TEST_DB_USERNAME=root TEST_DB_PASSWORD=SENHA
vendor/bin/phpunit
```

O bootstrap **apaga e recria** o banco `crm_grupo_mirante_teste` (recusa qualquer banco que não termine em `_teste`), aplica a estrutura, os dados de produção e os dados fictícios. Cada teste roda numa transação desfeita no fim. Suítes:

| Suíte | Cobre |
|---|---|
| Unit | CPF/CNPJ (inclusive alfanumérico), calendário e dias de exibição, separador de SQL |
| CalculadoraPrecosTest | preços de TV, rádio, digital, publicidade legal, patrocínio, totais, comissão, parcelas, conferência automática |
| PiFluxoTest | numeração, ciclo completo, devolução/correção, cancelamento, renovação, substituição, TV dividida, permissões |
| ProjetosReservasAprovacoesTest | cotas, bloqueios, reservas 24 h, aprovações e alçadas |
| GeradorExcelTest | geração pela API dos 5 veículos e Excel com fórmulas |
| GestaoComercialTest | Conferência, aprovações, faturamento, reservas, projetos, 19 relatórios, anexos, rotinas/SLA, backup |
| AutenticacaoPermissoesTest, TelasTest | login, bloqueio, recuperação, permissões, todas as telas |
| InstaladorTest | instalação do zero, retomada, recusas, código do assistente, `.env` |
| SegurancaTest | rotas sem login, CSRF, XSS, SQLi, fórmula no Excel, cabeçalhos, arquivos fora da pasta pública, redirecionamento |
| RegrasComplementaresTest | RN-14, RN-48/RN-60, RN-50 |
| ConcorrenciaTest | processos paralelos disputando número e cota |

A relação regra → código → teste está em `docs/MATRIZ-REGRAS.md`.

## 13. Como estender

- **Nova tela:** rota em `routes/web.php` com `perm:` → método no controller → view em `resources/views` usando `e()` → item em `config/menu.php` (o menu só mostra rotas existentes e permitidas).
- **Nova permissão:** `INSERT` em `permissoes` por um novo arquivo em `database/seeds/producao/` (ou migration) e `php bin/console.php migrar`; depois, marque os perfis em **Gestão → Permissões**.
- **Mudança de estrutura:** novo arquivo `database/migrations/00N_descricao.sql` (nunca edite um já aplicado) e `migrar`.
- **Novo relatório:** entrada em `RelatorioService::catalogo()` e um `case` em `executar()`; a tela, os filtros e o Excel são genéricos.
- **Tabela de preços de um novo ano:** **Gestão → Tabelas e veículos → Tabelas → Copiar ano** e ajuste os valores.
