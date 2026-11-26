# CRM Comercial · Grupo Mirante de Comunicação

Sistema de gestão comercial do Grupo Mirante: montagem e numeração das **PIs** (Rede Mirante TV, Mirante FM, Mirante News, G1 Maranhão e Imirante), conferência, aprovações, operação até o faturamento, projetos especiais, reservas de 24 h e relatórios.

**Versão 1.0.0** · PHP 8.5 · MySQL 8 · Bootstrap 5 · sem Node em produção

## Instalação
Siga o **[Manual de instalação](docs/MANUAL-DE-INSTALACAO.md)** (9 etapas, para não desenvolvedores). Resumo:

1. Envie os arquivos e aponte o domínio para a pasta **`public`** (com HTTPS).
2. Crie um banco MySQL vazio e um usuário exclusivo.
3. Abra `https://SEU-ENDERECO/instalar` (código em `storage/codigo-instalacao.txt`) **ou** rode `php instalar.php` no terminal.
4. Agende o cron: `*/5 * * * * cd /caminho/do/crm && php bin/console.php rotinas`.
5. Entre com o administrador criado (troca de senha obrigatória) e cadastre a equipe.

Não há usuário nem senha padrão, e a instalação de produção começa sem dados fictícios.

## Documentação
| Documento | Para quem |
|---|---|
| [Manual de instalação](docs/MANUAL-DE-INSTALACAO.md) | TI / hospedagem |
| [Checklist de implantação](docs/CHECKLIST-DE-IMPLANTACAO.md) | TI e comercial, antes de liberar |
| [Manual de uso](docs/MANUAL-DE-USO.md) | Equipe comercial, Conferência, Financeiro, administração |
| [Documentação técnica](docs/DOCUMENTACAO-TECNICA.md) | Desenvolvedores e suporte |
| [Matriz de regras](docs/MATRIZ-REGRAS.md) | RN-01 a RN-80 → código → teste |

## Comandos
```bash
php instalar.php                         # instalação pelo terminal
php bin/console.php migrar               # aplica atualizações de estrutura
php bin/console.php rotinas              # rotinas agendadas (cron a cada 5 min)
php bin/console.php backup --anexos      # backup do banco e dos anexos
php bin/console.php restaurar ARQUIVO    # restaura um backup (pede confirmação)
php bin/console.php criar-admin          # cria outro administrador
```

## Testes (desenvolvimento)
```bash
composer install
TEST_DB_HOST=127.0.0.1 TEST_DB_USERNAME=root TEST_DB_PASSWORD=SENHA vendor/bin/phpunit
```
O banco de teste `crm_grupo_mirante_teste` é recriado a cada execução; os testes recusam qualquer banco que não termine em `_teste`.

## Segurança
Argon2id · CSRF em todo envio · escape de saída · prepared statements · CSP sem scripts inline · sessão em banco com expiração · bloqueio por tentativas · autorização no servidor em toda rota e ação · anexos fora da pasta pública · auditoria com antes/depois · instalador com código de servidor e trava. Detalhes na [Documentação técnica](docs/DOCUMENTACAO-TECNICA.md#9-segurança).

© 2026 Grupo Mirante de Comunicação. Uso interno.
