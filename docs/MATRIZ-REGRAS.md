# Matriz de regras — RN → código → teste

As 80 regras do documento **"Projeto de Processos Comerciais — Grupo Mirante de Comunicação"** e onde cada uma está implementada e testada. Os testes ficam em `tests/` e rodam com `vendor/bin/phpunit` (102+ testes). No código, as regras são citadas como `RN-xx` nos comentários; para achar todos os pontos de uma regra: `grep -rn "RN-27" app resources`.

Legenda dos testes: `Calc` = `Integration/CalculadoraPrecosTest`, `Fluxo` = `Integration/PiFluxoTest`, `PRA` = `Integration/ProjetosReservasAprovacoesTest`, `Excel` = `Integration/GeradorExcelTest`, `Gestao` = `Integration/GestaoComercialTest`, `Auth` = `Integration/AutenticacaoPermissoesTest`, `Telas` = `Integration/TelasTest`, `Seg` = `Integration/SegurancaTest`, `Inst` = `Integration/InstaladorTest`, `Compl` = `Integration/RegrasComplementaresTest`, `Conc` = `Concurrency/ConcorrenciaTest`, `Doc` = `Unit/DocumentoValidatorTest`, `Cal` = `Unit/CalendarioEDiasTest`.

## Gerais, numeração e registro
| RN | Regra | Código | Teste |
|---|---|---|---|
| 01 | Toda venda gera PI pelo gerador | `PiService::gerar`, `PiController` | Fluxo::testRn03Rn51GeraNumeroSequencialEEnviada · Excel::testFluxoPelaApiRascunhoCalculoGeracaoEExcelDosCincoVeiculos |
| 02 | Modelo único para os 5 veículos; o veículo define empresa, tabelas e blocos | tabelas `veiculos`/`empresas`, `Calculo\Catalogo`, `pis/gerador.php` | Excel::testCatalogoDoVeiculo · Excel::testFluxoPelaApi… (5 veículos) |
| 03 | Número PREFIXO-ANO-SEQ, anual por veículo | `NumeracaoService`, `sequencias_pi` | Fluxo::testRn03Rn51… · Excel::testTvDivididaPelaApiENumeracaoPorAnoDoMes |
| 04 | Dois vendedores nunca recebem o mesmo número | `NumeracaoService` (`FOR UPDATE`), `UNIQUE(numero)`, `Database::transaction` | Conc::testNumeracaoSimultaneaNuncaRepete · Conc::testGeracoesSimultaneasDeVendedoresDiferentes |
| 05 | Gerar de novo a PI aberta mantém o número | `PiService::gerar` (modo edição/correção) | Fluxo::testRn54AprovadaAlteradaVoltaParaConferenciaSemAlteracaoNao · Excel::testAbrirParaCorrigirRenovarESubstituirPelaTela |
| 06 | Tipos de PI; origem obrigatória em Alteração, Cancelamento e Cancela/substitui | `CalculadoraPi` (conferência), `PiService` | Fluxo::testNumeroDigitadoNaoExisteEBloqueado · Fluxo::testRn56… |
| 07 | Toda PI gerada fica no registro (ajuste: no banco, não no navegador) | `pis`, `pi_versoes`, `PiController::index` | Fluxo::testFluxoCompletoDevolucaoCorrecaoAprovacaoAteEncerrar · Telas::testAdministradorAbreTodasAsTelas |
| 08 | Dados do executivo entram sozinhos (do cadastro) | `PiService::aplicarExecutivo` | Fluxo::testRn76DadosDoExecutivoNaoPodemSerTrocados |

## Veículos, tabelas e preços
| RN | Regra | Código | Teste |
|---|---|---|---|
| 09 | Blocos de mídia por veículo | `veiculos.bloco_*`, `Catalogo`, `CadastrosController` | Excel::testCatalogoDoVeiculo · Calc::testRn31PublicidadeLegalSomenteImirante |
| 10 | Tabela/praça obrigatória; sem tabela (SAT) → preço manual | `CalculadoraPi` (D05), `tabelas_precos` | Calc::testRn15SatSemTabelaExigePrecoManualComAprovacao |
| 11 | Sigla aceita código ou nome; fora do cadastro bloqueia | `Catalogo`, `CalculadoraPi` (D11) | Calc::testRn11SiglaPorCodigoOuNome |
| 12 | TV = 30" da praça × índice da duração | `CalculadoraPi`, `indices_duracao` | Calc::testRn12TvPrecoDo30VezesIndice · Calc::testTv15SegundosUsaIndiceProprioDoPrograma |
| 13 | Rádio = tabela por duração e verba | `CalculadoraPi` | Calc::testRn13RadioPorDuracaoEVerba |
| 14 | Digital pelo mídia kit; "sob consulta" exige preço manual | `CalculadoraPi` (bloco digital) | Compl::testRn14DigitalSobConsultaExigePrecoManual · Calc::testRn27… |
| 15 | Preço manual só com aprovação do gestor | `CalculadoraPi` (`needGs`, `manuais`), `AprovacaoService` | Calc::testRn15Sat… · PRA::testRn41AprovacaoDoGestorNoSistema |

## Período e divisão por mês
| RN | Regra | Código | Teste |
|---|---|---|---|
| 16 | Início e fim obrigatórios, fim ≥ início | `CalculadoraPi` (D02) | Calc::testConferenciaAutomaticaApontaPendenciasComCodigos |
| 17 | TV que atravessa meses → 1 PI por mês, número próprio, grupo | `DivisaoMensalTv`, `pi_grupos`, `PiService` | Fluxo::testRn17TvDivididaGeraUmaPiPorMesComGrupo · Calc::testRn17Rn20Rn38TvDivididaPorMes |
| 18 | Rádio: 1 PI com linha por mês; patrocínio indica o mês | `CalculadoraPi` | Calc::testRn18RadioUmaPiComLinhasPorMes |
| 19 | G1/Imirante: 1 PI, linha por formato, período próprio | `CalculadoraPi` | Calc::testRn27… · Calc::testRn28Rn29Rn30… · Excel::testFluxoPelaApi… |
| 20 | TV dividida: vencimento na data do mês ou último dia útil do mês seguinte | `ParcelamentoService`, `DivisaoMensalTv`, `Helpers\Calendario` | Cal::testRn20UltimoDiaUtilDoMesSeguinte · Calc::testRn17Rn20Rn38… |

## Inserções e patrocínio
| RN | Regra | Código | Teste |
|---|---|---|---|
| 21 | Linha = programa, duração e quantidade por dia; totais calculados | `CalculadoraPi`, `pi_insercoes_diarias` | Calc::testRn12… · Calc::testRn22eRn23DiasInvalidos |
| 22 | Inserção fora do período = erro | `CalculadoraPi` (D02) | Calc::testRn22eRn23DiasInvalidos |
| 23 | Dia sem exibição = erro; "Preencher" só dias válidos | `DiasExibicao`, `CalculadoraPi` (D03), `gerador.js` | Calc::testRn22eRn23… · Cal::testDiasDeExibicaoComoNoGerador |
| 24 | Avulsa/Bonificado/Reaplicação/Permuta | `CalculadoraPi` | Calc::testRn24BonificadoEReaplicacaoValemZero |
| 25 | TV mostra GRP, impactos e CPM | `CalculadoraPi`, `programa_audiencia` | Calc::testRn25TvGrpImpactosCpm |
| 26 | Patrocínio = esquema × cotas | `CalculadoraPi`, `programa_esquemas` | Calc::testRn26PatrocinioEsquemaVezesCotas |

## Digital e publicidade legal
| RN | Regra | Código | Teste |
|---|---|---|---|
| 27 | Mensal proporcional aos dias usados | `CalculadoraPi` | Calc::testRn27DigitalMensalProporcional |
| 28 | Diária/unidade/pacote = preço × quantidade | `CalculadoraPi` | Calc::testRn28Rn29Rn30DigitalDiariaCpmECobranca |
| 29 | CPM = impressões ÷ 1000 × CPM; impressões obrigatórias | `CalculadoraPi` (D05) | Calc::testRn28Rn29Rn30… |
| 30 | Pago / Incluso no pacote / Bonificado / Permuta | `CalculadoraPi` | Calc::testRn28Rn29Rn30… |
| 31 | Publicidade legal (só Imirante) = col × cm × publicações × R$ | `CalculadoraPi` | Calc::testRn31PublicidadeLegalSomenteImirante |

## Projetos especiais
| RN | Regra | Código | Teste |
|---|---|---|---|
| 32 | Projeto com código automático (PROJ-ANO-0001), nome, prazo, empresas e cotas | `ProjetoService::salvar`, `NumeracaoService::proximoProjeto`, `ReservasProjetosController` | PRA::testRn37… · Gestao::testReservasEProjetosPelasTelas · CodigoProjetoTest · Conc::testCadastroSimultaneoDeProjetosNuncaRepeteCodigo |
| 33 | Bloqueio após a data-limite (comparando com hoje) | `ProjetoService`, `CalculadoraPi` (P02) | PRA::testRn33Rn35Rn34BloqueiosImpedemGerar · Compl::testRn50SituacaoDoProjeto |
| 34 | Bloqueio acima do saldo; revalidação ao gerar | `ProjetoService::travarCotas` (`FOR UPDATE`) | PRA::testRn33Rn35Rn34… · Conc::testDuasPessoasDisputandoAUltimaCota |
| 35 | Projeto só nas empresas marcadas | `CalculadoraPi` (P04) | PRA::testRn33Rn35Rn34… |
| 36 | Rascunho consome; cancelada devolve; reserva de outro desconta; regerar não duplica | `ProjetoService::vendidas/reservadas` | PRA::testRn36RascunhoConsomeCanceladaDevolveERegerarNaoDuplica |
| 37 | Valor = cota × qtd − desconto; bonificada consome | `CalculadoraPi` | PRA::testRn37ValorDaCotaEFechamento |
| 38 | TV dividida: cota só no 1º mês | `DivisaoMensalTv` | PRA::testRn38CotaSoNaPrimeiraPiMensalDaTv · Calc::testRn17Rn20Rn38… |
| 39 | Não reduzir cotas abaixo do vendido; excluir não apaga PIs | `ProjetoService::salvar/excluir` | PRA::testRn39NaoReduzAbaixoDoVendidoEExcluirMantemPis |

## Valores, aprovações e pagamento
| RN | Regra | Código | Teste |
|---|---|---|---|
| 40 | Tabela, negociado e desconto nominal (+ produção) | `CalculadoraPi` (totais) | Calc::testRn40Rn42TotaisComissaoELiquido |
| 41 | Desconto, bonificação e preço manual exigem aprovação do gestor | `AprovacaoService`, `OperacaoController::aprovacoes`, `CalculadoraPi` (D06) | PRA::testRn41… · PRA::testAlcadaAcimaDoGestorVaiParaDiretoria · Gestao::testAprovacoesPelaTela · Gestao::testQuemPedeNaoAprovaNemSendoAdministrador · Excel::testSolicitarAprovacaoVaiAoGestorDoExecutivo |
| 42 | Agência: nome, CNPJ, %, carta; comissão e líquido | `CalculadoraPi` (D08), `AgenciaService` | Calc::testRn40Rn42… |
| 43 | Σ parcelas = líquido; até 12; centavos na última | `ParcelamentoService`, `CalculadoraPi` (D07) | Calc::testRn43ParcelasCentavosNaUltima |
| 44 | Entrega especial com área e briefing; sorteio com jurídico | `CalculadoraPi` (D09, D13) | Calc::testConferenciaAutomaticaApontaPendenciasComCodigos |
| 45 | Aceite do cliente (nome e data) | `CalculadoraPi` (D10) | Calc::testConferenciaAutomatica… · Gestao::testConferenciaPelaTelaAprovaEDevolve |

## Excel e painel
| RN | Regra | Código | Teste |
|---|---|---|---|
| 46 | `PI_NÚMERO_CLIENTE.xlsx` / `_RASCUNHO`; ordem das faixas | `ExcelPiService` | Excel::testFluxoPelaApi… · Excel::testRascunhoComPendenciaGeraArquivoRascunho · Seg::testExcelNaoExecutaFormulaDigitadaPeloUsuario |
| 47 | Cláusulas fixas, não editáveis pelo vendedor | `clausulas`, `ExcelPiService`, `CadastrosController` (só admin) | Excel::testFluxoPelaApi… |
| 48 | Painel com PIs, negociado, ticket, rascunhos, por veículo, filtros | `DashboardService`, `DashboardController` | Compl::testRn48Rn60PainelNaoSomaCanceladas · Telas::testPainelAbreParaTodosOsPerfis |
| 49 | Abrir mantém número; Renovar cria nova com mês seguinte | `PiService::abrir`, `PiController` | Fluxo::testRn49RenovacaoGeraNumeroNovoComMesSeguinte · Excel::testAbrirParaCorrigir… |
| 50 | Acompanhamento de projetos: Aberto / Esgotado / Encerrado | `ProjetoService::situacao`, `projetos/index.php` | Compl::testRn50SituacaoDoProjeto |

## Ciclo de vida
| RN | Regra | Código | Teste |
|---|---|---|---|
| 51 | Sem pendência → Enviada; com pendência → Rascunho | `PiService::gerar` | Fluxo::testRn03Rn51… · Fluxo::testRn51ComPendenciaSaiComoRascunho |
| 52 | Devolver exige código e descrição; alimenta ranking | `PiWorkflow::devolver`, `ConferenciaController` | Fluxo::testFluxoCompleto… · Gestao::testConferenciaPelaTelaAprovaEDevolve · Gestao::testDezenoveRelatoriosEPrivacidadeDoRanking |
| 53 | Corrigir mantém número; volta como Corrigida | `PiService::gerar` (correção) | Fluxo::testFluxoCompleto… |
| 54 | Aprovar é da Conferência; Aprovada alterada volta para Enviada | `PiWorkflow::aprovar`, `PiService` (hash da versão) | Fluxo::testRn54… · Fluxo::testRn74PermissoesDoFluxo |
| 55 | Cancelar exige motivo, irreversível, devolve cotas | `PiWorkflow::cancelar` | Fluxo::testRn55CancelarExigeMotivoEIrreversivel |
| 56 | Cancela/substitui: antiga vira Substituída só quando a nova é gerada | `PiService::gerar` (transação única) | Fluxo::testRn56CancelaSubstituiTrocaAntigaSoQuandoANovaEGerada |
| 57 | Veiculação finalizada exige data de fim → Pronta para faturamento | `PiWorkflow::finalizarVeiculacao` | Gestao::testOperacaoFaturamentoPelaFicha |
| 58 | Cancelada, Substituída, Pronta para faturamento: só consulta | `PiService::podeAlterar` | Fluxo::testFluxoCompleto… · Fluxo::testRn55… |
| 59 | Histórico com data, evento e responsável | `pi_historico`, `PiService::historico`, `Auditoria` | Fluxo::testFluxoCompleto… |
| 60 | Painel sem canceladas/substituídas; devolvidas; prontas para faturar | `DashboardService` | Compl::testRn48Rn60PainelNaoSomaCanceladas |

## Clientes e CNPJ/CPF
| RN | Regra | Código | Teste |
|---|---|---|---|
| 61 | DV de CPF, CNPJ numérico e alfanumérico | `Validators\DocumentoValidator` | Doc::testExemplosDoDocumento · Doc::testCpf · Doc::testInvalidosEVazio |
| 62 | Formatação automática | `DocumentoValidator::formatar`, máscara em `app.js` | Doc::testFormataAutomaticamente |
| 63 | Cliente único por documento; cadastro ao gerar a PI | `ClienteService`, `UNIQUE(documento)` | Auth::testRn63ClienteUnicoPorDocumentoEExclusaoLogica · Fluxo::testRn63ClienteEAgenciaEntramNoCadastro |
| 64 | Busca por nome/documento; excluir cliente não altera PIs | `ClientesController`, cópia dos dados na PI | Auth::testRn63… · Seg::testXssEEscapadoNasTelas (busca) |

## Reservas de 24 horas
| RN | Regra | Código | Teste |
|---|---|---|---|
| 65 | Reserva tudo o que está na PI; exige veículo, cliente e ≥ 1 linha | `ReservaService::reservar` | PRA::testRn65Rn66Rn67Rn68ReservaSeguraCotaEAvisaEspaco |
| 66 | Cota reservada sai da disponibilidade na hora | `ReservaService`, `ProjetoService::travarCotas` | PRA::testRn65Rn66Rn67Rn68… |
| 67 | Vale 24 h; atualizar não renova | `reservas.expira_em`, `ReservaService::expirarVencidas` | PRA::testRn65Rn66Rn67Rn68… · PRA::testExpiracaoAutomaticaDasReservas |
| 68 | Conflito de espaço = aviso (não bloqueia) | `ReservaService::conflitos` | PRA::testRn65Rn66Rn67Rn68… |
| 69 | Gerar a PI converte a reserva | `ReservaService::converter` (na geração) | PRA::testRn65Rn66Rn67Rn68… · Gestao::testReservasEProjetosPelasTelas |
| 70 | Lista de reservas; só o dono (ou gestor/admin) abre e libera | `ReservasProjetosController`, `ReservaService::liberar` | Gestao::testReservasEProjetosPelasTelas |

## Acesso, usuários e agências
| RN | Regra | Código | Teste |
|---|---|---|---|
| 71 | Login obrigatório; primeiro acesso força senha pessoal (ajuste: sem `admin/admin`, admin criado no instalador) | `Middleware\Autenticado`, `AuthController`, `Instalacao\Instalador` | Auth::testLoginELogout · Telas::testPrimeiroAcessoObrigaCriarSenha · Inst::testInstalacaoCompletaDoZeroSemDadosFicticios |
| 72 | Perfis e tabela de permissões | `perfis`, `permissoes`, `Middleware\Permissao`, `PermissoesController` | Auth::testPermissoesPorPerfil · Telas::testPermissoesNasRotas · Seg::testTodaRotaInternaExigeLoginEPermissaoNoServidor |
| 73 | Visibilidade: própria / equipe / todas | `Auth`, `PiService` (escopo em SQL) | Fluxo::testRn74… · Excel::testPermissoesDoGerador · Gestao::testAnexoProtegidoPorPermissao |
| 74 | Quem aprova, devolve, finaliza, cancela; Conferência não gera PI | permissões iniciais, `PiWorkflow` | Fluxo::testRn74PermissoesDoFluxo · Excel::testPermissoesDoGerador |
| 75 | Só admin cadastra; senha provisória exibida uma vez; senha mínima | `UsuarioService`, `AuthService::validarSenha/minimoPara` | Auth::testRn75SenhaProvisoriaEPrimeiroAcesso · Inst::testValidacoesDoAdministrador |
| 76 | Nome, matrícula e gestor vêm do cadastro | `PiService::aplicarExecutivo` | Fluxo::testRn76… |
| 77 | Desativado não entra; não remove o único admin; bloqueio por erros; sessão ≤ 12 h | `AuthService`, `Session`, `UsuarioService` | Auth::testUsuarioDesativadoNaoEntra · Auth::testSenhaErradaEBloqueioTemporario · Auth::testRn77UnicoAdministradorNaoPodeSerDesativadoNemRebaixado |
| 78 | Senhas cifradas (ajuste: Argon2id no servidor) | `AuthService::hash` | Auth::testLoginELogout · Inst::testInstalacaoCompletaDoZero… |
| 79 | Agência única por CNPJ; cadastro ao gerar | `AgenciaService`, `UNIQUE(cnpj)` | Fluxo::testRn63ClienteEAgenciaEntramNoCadastro |
| 80 | Carta vencida na emissão = D08 → Rascunho | `CalculadoraPi` (D08) | Calc::testConferenciaAutomaticaApontaPendenciasComCodigos |

## Regras gerais do documento (além das RN)
| Regra | Código | Teste |
|---|---|---|
| Sem PI não há veiculação | fluxo obrigatório `PiWorkflow` (operação só a partir de Aprovada) | Gestao::testOperacaoFaturamentoPelaFicha |
| PI fora do prazo não é recusada: é marcada "Fora do prazo" | `PiService::foraDoPrazo`, Conferência | Gestao::testConferenciaPelaTela… |
| Conferência: mesmo dia até 15h, senão 12h do dia útil seguinte; correção em 1 dia útil | `SlaService` | Gestao::testRotinasEPrazos |
| Segunda devolução aciona o gestor | `PiWorkflow::devolver` | Fluxo::testFluxoCompleto… |
| Conferência nunca corrige a PI do vendedor | `ConferenciaController` (só aprova/devolve) | Excel::testPermissoesDoGerador |
| Quem pede não aprova o próprio pedido | `AprovacaoService::responder` | Gestao::testQuemPedeNaoAprovaNemSendoAdministrador |

## Requisitos de segurança do projeto
| Requisito | Código | Teste |
|---|---|---|
| Sem credencial padrão; 1º admin pelo instalador com senha forte e troca obrigatória | `Instalador`, `InstaladorController`, `instalar.php` | Inst::* · Seg::testInstaladorTravadoNaoAbre |
| Sem dados fictícios em produção | `SeedTeste::permitido`, `Instalador` (só seeds de produção) | Inst::testInstalacaoCompletaDoZeroSemDadosFicticios |
| Sem armazenamento local do navegador como banco | tudo no MySQL (`pi_rascunhos`, `pis`…) | Excel::testFluxoPelaApi… |
| Autorização no backend | `Middleware\Permissao` + verificações nos services | Seg::testTodaRotaInterna… · Telas::testPermissoesNasRotas |
| CSRF, XSS, SQL Injection | `VerificaCsrf`, `e()`/`esc()`, prepared statements | Seg::testPostSemTokenCsrfERecusado · Seg::testXssEEscapadoNasTelas · Seg::testSqlInjectionNaoAfetaBuscasNemLogin |
| Concorrência (numeração e cotas) | `NumeracaoService`, `ProjetoService::travarCotas` | Conc::* |
