# Manual de uso — CRM Comercial · Grupo Mirante

O CRM é o único caminho para uma venda virar veiculação: **sem PI gerada no CRM, não há veiculação**. Este manual mostra o que cada perfil faz no dia a dia.

## Sumário
1. [Primeiros passos (todos)](#1-primeiros-passos-todos)
2. [O caminho de uma PI](#2-o-caminho-de-uma-pi)
3. [Executivo e Vendedor](#3-executivo-e-vendedor)
4. [Gerente comercial](#4-gerente-comercial)
5. [Diretor](#5-diretor)
6. [Conferência / OPEC](#6-conferência--opec)
7. [Financeiro](#7-financeiro)
8. [Administrador](#8-administrador)
9. [Relatórios](#9-relatórios)
10. [Códigos de devolução D01–D15](#10-códigos-de-devolução-d01d15)
11. [Perguntas frequentes](#11-perguntas-frequentes)

---

## 1. Primeiros passos (todos)

**Entrar.** Abra o endereço do CRM, informe **usuário** e **senha**. No primeiro acesso, o sistema pede uma **senha pessoal** (letras e números, mínimo de 8 caracteres; 12 para administradores). Depois de 5 senhas erradas seguidas, o acesso fica bloqueado por alguns segundos.

**Esqueci a senha.** Clique em **Esqueci minha senha**. Se o e-mail da empresa estiver configurado, chega um link válido por pouco tempo. Se não estiver, peça ao administrador uma senha provisória.

**Sair.** Menu com o seu nome (canto superior direito) → **Sair**. A sessão também termina sozinha depois de um período sem uso e, no máximo, após 12 horas.

**Avisos.** O **sino** no topo mostra PIs devolvidas, aprovações pendentes, prazos vencendo e reservas expirando. Clique no aviso para abrir o que ele indica.

**Painel.** A primeira tela mostra os números do mês (PIs, negociado, líquido, ticket médio, devoluções, fora do prazo), gráficos por veículo e a lista **"O que precisa da sua atenção"**. Use os filtros de período, empresa, veículo, executivo e situação.

**O que cada pessoa vê:** o executivo vê as **próprias** PIs; o gerente, as da **equipe**; Conferência, Financeiro, Diretor e Administrador veem **todas**.

---

## 2. O caminho de uma PI

```
Rascunho ──(sem pendências)──► Enviada ──► Aprovada ──► Em programação ──► Em veiculação
   ▲                              │  ▲                                          │
   │                       Devolvida │                                          ▼
   └── corrigir ──► Corrigida ───────┘                         Pronta para faturamento ──► Faturada ──► Encerrada
Em qualquer ponto antes do faturamento: Cancelada (com motivo) ou Substituída (pela PI de cancela/substitui)
```

| Situação | Quem age | O que acontece |
|---|---|---|
| **Rascunho** | Executivo | Gerada com pendência (ex.: falta aceite). Recebe número, mas ainda não vai para a Conferência. |
| **Enviada / Corrigida** | Conferência | Na fila da Conferência, com prazo: mesmo dia se chegou até 15h; senão, até 12h do dia útil seguinte. |
| **Devolvida** | Executivo | Corrigir em até 1 dia útil. O número é mantido. |
| **Aprovada** | OPEC | Liberar para programação (grade e mapa). |
| **Em programação → Em veiculação** | OPEC | Material e início da veiculação. |
| **Pronta para faturamento** | Financeiro | Depois de "Veiculação finalizada" com a data de fim. |
| **Faturada → Encerrada** | Financeiro | Nota fiscal registrada e encerramento. |

PIs **Canceladas, Substituídas, Prontas para faturamento, Faturadas e Encerradas** ficam somente para consulta.

---

## 3. Executivo e Vendedor

### 3.1 Montar uma PI — menu **Nova PI**
A tela tem **8 etapas** (barra no topo). A etapa fica **verde** quando está completa e **vermelha** quando falta algo. O painel lateral mostra, ao vivo, valor de tabela, desconto, negociado, comissão, líquido, inserções (e GRP/CPM na TV) e a **lista de pendências**: clique numa pendência para ir direto ao campo.

1. **Identificação** — escolha o **veículo** (Rede Mirante TV, Mirante FM, Mirante News, G1 Maranhão ou Imirante). O veículo define a empresa emissora, as tabelas, os programas e os blocos de mídia. Escolha o tipo: **Nova, Renovação, Alteração, Cancelamento ou Cancela/substitui** (os três últimos pedem a PI de origem).
2. **Comercial** — seus dados (nome, matrícula, gestor) vêm do cadastro e não podem ser trocados. Informe se a venda é **direta** ou **por agência**.
3. **Cliente e agência** — digite parte do nome ou o CNPJ/CPF: o CRM sugere clientes já cadastrados e preenche o resto. CNPJ alfanumérico é aceito. Na venda por agência: nome, CNPJ, % de comissão e carta de credenciamento válida.
4. **Campanha** — título, produto, período (início e fim) e praça de cobrança.
5. **Mídia** — escolha a **tabela/praça** e inclua as linhas:
   - **Inserções (TV, rádio):** digite a sigla ou o nome do programa, a duração e a quantidade **por dia** na grade. Dias em cinza: o programa não vai ao ar. **Preencher** marca só os dias válidos.
   - **Patrocínio (rádio):** esquema × cotas e o mês da linha.
   - **Digital (G1, Imirante):** formato, modelo de cobrança (mensal proporcional, diária, unidade, pacote, CPM com impressões).
   - **Publicidade legal (só Imirante):** colunas × cm × publicações e o texto do anúncio.
   - **Projetos especiais:** projeto e cota; o CRM mostra quantas ainda estão disponíveis.
   - **Cobrança** de cada linha: Avulsa, Bonificado, Reaplicação ou Permuta (Bonificado e Reaplicação valem zero e somam como bonificado).
6. **Entregas especiais** — área responsável e nº do briefing; sorteio exige aval do jurídico.
7. **Financeiro** — forma de pagamento, produção/taxas e parcelas. **Gerar parcelas** divide o líquido em até 12, com os centavos na última. A soma das parcelas precisa ser igual ao líquido.
8. **Aprovações** — se houver **desconto, bonificação ou preço manual**, clique em **Solicitar aprovação** e escreva a justificativa. O pedido vai ao seu **gestor** (ou à diretoria, acima da alçada). Aceite do cliente: nome de quem aceitou e data.

**Salvamento automático.** Tudo é salvo sozinho como "Em montagem" (sem número). Para voltar depois: **Nova PI → Em montagem**.

**Gerar PI.** Clique em **Gerar PI**. O CRM confere tudo de novo no servidor:
- sem pendências → a PI sai **Enviada** e vai para a Conferência;
- com pendências → sai **Rascunho** (com número), e o Excel leva `_RASCUNHO` no nome;
- **TV que atravessa meses** gera **uma PI por mês**, cada uma com o próprio número, ligadas entre si.

Depois de gerar, baixe o **Excel** (A4 paisagem, uma página) ou use **Imprimir**.

### 3.2 Reservar por 24 horas
Na Nova PI, **Reservar por 24h** segura as cotas de projeto e marca o espaço (programas, patrocínios, formatos) por 24 horas. Outros vendedores veem um **aviso** se tentarem vender o mesmo espaço. A reserva **não se renova**: gere a PI antes de vencer. Em **Reservas 24h** você vê as suas, **Abre e gera a PI** ou **Libera**.

### 3.3 Corrigir uma PI devolvida
O aviso mostra os **códigos** (D01–D15) e o que corrigir. Abra a PI → **Corrigir**. Ao gerar de novo, ela volta para a Conferência como **Corrigida**, com o mesmo número. Prazo: 1 dia útil.

### 3.4 Alterar, renovar, cancelar/substituir
Na ficha da PI (**PIs** → número):
- **Abrir e alterar:** gera de novo com o **mesmo número**. Se já estava Aprovada e houve alteração, volta para a Conferência.
- **Renovar:** cria PI nova (número novo) com o mês seguinte e a grade vazia.
- **Cancela/substitui:** monta a PI substituta; a antiga só vira **Substituída** quando a nova for gerada.

### 3.5 Anexos
Na ficha da PI → **Anexos**: aceite do cliente, briefing, parecer jurídico, comprovante. Tipos aceitos: PDF, imagens, e-mail, Word, Excel e texto.

---

## 4. Gerente comercial
- **Aprovações** (menu) → pedidos da sua equipe: veja desconto, bonificado e preços manuais e clique em **Aprovar** ou **Recusar** (recusa exige motivo). Prazo: 1 dia útil. Você **não aprova o próprio pedido**.
- **PIs** → filtre pela equipe; você pode **cancelar** (com motivo, irreversível) e **devolver**.
- **Relatórios** → ranking e números da equipe.
- A **segunda devolução** de uma PI avisa o gestor.

## 5. Diretor
- Vê todas as PIs e todos os relatórios.
- Aprova os pedidos **acima da alçada** do gestor (**Aprovações**).
- Pode cadastrar e editar **Projetos especiais** (código, nome, data-limite de venda, empresas e cotas). Não é possível reduzir cotas abaixo do que já foi vendido; excluir um projeto não apaga PIs.

## 6. Conferência / OPEC

### 6.1 Conferir — menu **Conferência**
1. A fila mostra cada PI com o **prazo**; atrasadas em vermelho. Clique em **Conferir a primeira da fila**.
2. Compare a **PI com o cadastro do cliente** (diferenças aparecem com ≠), os dados comerciais, a mídia e os totais (calculados pelo sistema) e os anexos.
3. Marque os **10 itens do checklist**. Só é possível **aprovar** com todos marcados.
4. Se algo estiver errado, **devolva com código**: marque os códigos (D01–D15) e escreva o que o executivo precisa corrigir. Itens do checklist desmarcados **sugerem** os códigos.
5. Depois de aprovar ou devolver, a próxima PI da fila abre sozinha.

> A Conferência **nunca corrige** a PI pelo vendedor: aprova ou devolve. "Enviada fora do prazo" **não** é motivo de devolução; conta no indicador do executivo.

### 6.2 Operação — menu **Faturamento**
Painel por etapa. Na ficha de cada PI:
- **Liberar** para programação (Aprovada → Em programação);
- **Início da veiculação** (→ Em veiculação);
- **Veiculação finalizada**, com a data de fim (→ Pronta para faturamento).

## 7. Financeiro
- **Faturamento** → coluna **Prontas para faturamento**. Na ficha: **Faturar** com o número e o valor da nota fiscal. Se o valor da NF for diferente do líquido da PI, o CRM avisa e registra.
- **Encerrar** a PI depois do faturamento.
- Cadastro e situação cadastral de **clientes** (Ativo, Em análise, Bloqueado).

## 8. Administrador
| Menu | Para quê |
|---|---|
| **Usuários** | Cadastrar, editar, desativar, redefinir senha (senha provisória mostrada uma vez). Não é possível desativar o único administrador. |
| **Permissões** | Ajustar o que cada perfil pode fazer. |
| **Tabelas e veículos** | Empresas emissoras, veículos, tabelas de preço por ano (com cópia de um ano para o outro), preços, programas (dias de exibição, audiência), formatos digitais, cláusulas fixas da PI e feriados. |
| **Configurações** | Prazos (SLA), alçadas, segurança (tentativas, tamanho de senha), reservas, parcelas. |
| **Auditoria** | Quem fez o quê e quando (login, PIs, valores, permissões, cadastros), com antes e depois. |
| **Backup** | Backup na hora e download dos arquivos (veja o Manual de instalação, etapa 8). |

## 9. Relatórios
Menu **Relatórios**: 19 relatórios em 5 grupos — **Vendas** (período, veículo, empresa, executivo, gestor), **Valores** (negociados, líquidos, comissões de agências), **PIs** (geradas, aprovadas, devolvidas, motivos de devolução, ranking de devoluções, fora do prazo, tempo de conferência, prontas para faturamento) e **Projetos e reservas** (projetos, cotas, reservas ativas).

Em cada relatório: escolha a visão **Diária, Semanal ou Mensal**, o período e os filtros e clique em **Atualizar**. **Exportar Excel** gera a planilha com totais por fórmula; **Imprimir** gera a versão em papel. Canceladas e substituídas não entram nos totais de venda.

> Rankings com nomes: o executivo vê só os próprios números; o gerente, a equipe; Conferência, Diretor e Administrador, todos.

## 10. Códigos de devolução D01–D15
| Código | Motivo |
|---|---|
| D01 | Dados do cliente incompletos ou incorretos (razão social, CNPJ, endereço, e-mail de faturamento) |
| D02 | Período ou datas inválidas |
| D03 | Grade de inserções por dia não preenchida |
| D04 | Quantidades não batem com o total |
| D05 | Valor unitário, total ou desconto ausente ou divergente |
| D06 | Desconto ou bonificação sem aprovação da alçada |
| D07 | Forma de pagamento, parcelas ou vencimento indefinidos |
| D08 | Agência ou comissão não informada (ou carta de credenciamento vencida) |
| D09 | Entrega especial sem briefing ou sem área responsável |
| D10 | Assinatura ou aceite do cliente ausente |
| D11 | Programa, horário ou formato inexistente ou sem disponibilidade |
| D12 | Material ou duração não especificados |
| D13 | Promoção ou sorteio sem aval do jurídico |
| D14 | PI fora do modelo padrão ou sem número do registro |
| D15 | Vendedor não identificado |

## 11. Perguntas frequentes
**Posso editar uma PI depois de gerada?** Sim, enquanto não estiver Pronta para faturamento, Faturada, Encerrada, Cancelada ou Substituída: **Abrir e alterar** (mesmo número).

**Por que minha PI saiu como Rascunho?** Ficou alguma pendência (a lista aparece na ficha). Corrija e gere de novo: o número é mantido.

**Dois vendedores podem pegar a última cota de um projeto?** Não. O CRM trava a cota no momento de gerar ou reservar: o segundo recebe a mensagem de que não há mais cota disponível.

**O preço não aparece para um formato.** Ele é "sob consulta": informe o preço manual e solicite a aprovação do gestor.

**A TV de dezembro a janeiro saiu com dois números.** É a regra: uma PI por mês, cada uma com o número do ano do mês (ex.: TV-2026-0012 e TV-2027-0001).
