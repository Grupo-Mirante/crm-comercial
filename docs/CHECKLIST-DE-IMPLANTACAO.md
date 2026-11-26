# Checklist de implantação — CRM Comercial · Grupo Mirante

Marque cada item antes de liberar o CRM para a equipe. Os números entre parênteses indicam a etapa do **Manual de instalação**.

## A. Servidor e instalação
- [ ] PHP 8.5 com todas as extensões em verde no instalador (2)
- [ ] MySQL 8.x; banco `crm_grupo_mirante` **vazio** e usuário **exclusivo** do CRM, sem uso do `root` (4)
- [ ] O endereço do CRM aponta para a pasta **`public`** — e não para a pasta do projeto (3)
- [ ] **HTTPS** ativo (cadeado) e redirecionamento de `http://` para `https://` (3)
- [ ] Instalação concluída pela web ou pelo terminal; `/instalar` agora responde "Página não encontrada" (5)
- [ ] O arquivo `storage/codigo-instalacao.txt` **não existe** mais (é apagado ao instalar)
- [ ] `.env` com `APP_ENV=production`, `APP_DEBUG=false` e `APP_URL` correto (https, sem barra final)
- [ ] `.env` com permissão 640 e dono = usuário do servidor web; pasta `storage` gravável só por ele (3)

## B. Segurança (testes rápidos no navegador)
- [ ] `https://ENDERECO/.env` → **bloqueado** (403 ou 404)
- [ ] `https://ENDERECO/storage/logs/` e `https://ENDERECO/storage/backups/` → **não abrem**
- [ ] `https://ENDERECO/vendor/` e `https://ENDERECO/app/` → **não abrem**
- [ ] Abrir `https://ENDERECO/pis` sem login → vai para a tela de **login**
- [ ] 5 senhas erradas seguidas → mensagem de **bloqueio temporário**
- [ ] O administrador já trocou a senha provisória no primeiro acesso (6)
- [ ] Não existe nenhum usuário com login genérico (`admin`, `teste`) nem senha compartilhada

## C. Dados iniciais
- [ ] Usuários cadastrados: diretor, gerentes, Conferência/OPEC, Financeiro e executivos (cada executivo com **gestor** e **matrícula**) (6)
- [ ] Cada pessoa recebeu a própria senha provisória **pessoalmente** e já criou a senha pessoal
- [ ] **Empresas emissoras**: razão social, CNPJ e endereço conferidos (saem na PI) — *pendência do comercial*
- [ ] **Feriados** estaduais e municipais de 2026/2027 cadastrados (afetam prazos e vencimentos)
- [ ] **Alçadas** de desconto e bonificação definidas pela diretoria (ou deixadas vazias de propósito)
- [ ] **Cláusulas** da PI (inclusive o texto da multa) revisadas pelo jurídico
- [ ] Tabela de preços 2026 conferida por amostragem (3 programas de TV, 3 de rádio, 3 formatos digitais)
- [ ] Projetos especiais reais cadastrados (os projetos de exemplo existem **somente** no banco de testes)

## D. Rotinas e backup
- [ ] Cron a cada 5 minutos configurado; `storage/logs/cron.log` com execuções recentes (7)
- [ ] **Gestão → Backup** mostra "Último backup" de hoje (7, 8)
- [ ] Um backup foi **baixado** e guardado fora do servidor (8)
- [ ] Teste de restauração feito **num banco de homologação** (não no de produção) (8)
- [ ] E-mail (SMTP) configurado e testado com "Esqueci minha senha" — ou decidido que a recuperação será pelo administrador (6)

## E. Teste de aceite com a equipe (homologação)
Fazer numa instalação de **homologação** (`APP_ENV=staging`, banco separado) ou, em produção, com dados reais que serão usados de verdade:
- [ ] Executivo monta e **gera** uma PI de cada veículo (TV, FM, News, G1, Imirante) e baixa o **Excel**
- [ ] PI de TV atravessando meses gera **uma PI por mês** com números corretos
- [ ] PI com desconto → **Solicitar aprovação** → gerente aprova pela tela **Aprovações**
- [ ] Conferência **devolve** uma PI com código; executivo **corrige**; Conferência **aprova**
- [ ] OPEC libera, inicia e finaliza a veiculação; Financeiro **fatura** e **encerra**
- [ ] **Anexo** (aceite do cliente em PDF) enviado e baixado pela ficha da PI
- [ ] **Reserva de 24 h** feita e convertida em PI
- [ ] Dois vendedores disputando a **última cota** de um projeto: só um consegue
- [ ] Relatórios do mês conferidos com os números do painel; exportação Excel abre corretamente
- [ ] Ajuste **A-01** (produção/taxas e entregas especiais só na 1ª PI mensal da TV) **confirmado pelo comercial**

## F. Liberação
- [ ] Equipe treinada com o **Manual de uso** (`docs/MANUAL-DE-USO.md`)
- [ ] Responsável técnico definido para backup, atualizações e suporte
- [ ] Data de corte: a partir dela, **toda venda passa pelo CRM** (sem PI no CRM, não há veiculação)

| Responsável | Data | Assinatura |
|---|---|---|
| TI / hospedagem | | |
| Comercial (diretoria) | | |
| Conferência / OPEC | | |
| Financeiro | | |
