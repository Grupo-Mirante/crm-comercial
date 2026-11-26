-- =====================================================================
-- Motivos de devolução (D01–D15), checklist da Conferência e cláusulas fixas
-- Textos conforme o Projeto de Processos Comerciais e o Gerador de PI.
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO motivos_devolucao (codigo, descricao, exemplo, ordem) VALUES
 ('D01', 'Dados do cliente incompletos ou incorretos (razão social, CNPJ, endereço, e-mail de faturamento)', 'E-mail de cobrança pessoal', 1),
 ('D02', 'Período ou datas inválidas', '"01 a 31/04"', 2),
 ('D03', 'Grade de inserções por dia não preenchida', 'Grade de 01 a 31 vazia', 3),
 ('D04', 'Quantidades não batem com o total', '140 inserções somadas, 176 declaradas', 4),
 ('D05', 'Valor unitário, total ou desconto ausente ou divergente', 'Valores por linha em branco', 5),
 ('D06', 'Desconto ou bonificação sem aprovação da alçada', '66 chamadas bonificadas sem aprovação', 6),
 ('D07', 'Forma de pagamento, parcelas ou vencimento indefinidos', 'Vencimento "obs abaixo"', 7),
 ('D08', 'Agência ou comissão não informada', 'Campo agência em branco', 8),
 ('D09', 'Entrega especial sem briefing ou sem área responsável', 'Posts, app e sorteio em texto livre', 9),
 ('D10', 'Assinatura ou aceite do cliente ausente', 'Assinaturas em branco', 10),
 ('D11', 'Programa, horário ou formato inexistente ou sem disponibilidade', NULL, 11),
 ('D12', 'Material ou duração não especificados', NULL, 12),
 ('D13', 'Promoção ou sorteio sem aval do jurídico', 'Sorteio de iPhone 12', 13),
 ('D14', 'PI fora do modelo padrão ou sem número do registro', 'Código 14625 repetido', 14),
 ('D15', 'Vendedor não identificado', 'Dois contatos, nenhum vendedor', 15);

INSERT INTO checklist_conferencia (ordem, descricao, codigos) VALUES
 (1,  'Modelo padrão e número do registro', 'D14'),
 (2,  'Vendedor e gestor identificados', 'D15'),
 (3,  'Dados do cliente completos e iguais ao cadastro', 'D01'),
 (4,  'Datas válidas e dentro do prazo de envio', 'D02'),
 (5,  'Uma linha por programa e formato, com grade diária preenchida', 'D03,D11,D12'),
 (6,  'Soma das linhas igual ao total de inserções e ao valor bruto', 'D04,D05'),
 (7,  'Descontos e bonificações com aprovação registrada', 'D06'),
 (8,  'Pagamento, parcelas e vencimentos definidos; agência e comissão informadas', 'D07,D08'),
 (9,  'Entregas especiais com briefing e, se houver sorteio, aval do jurídico', 'D09,D13'),
 (10, 'Assinatura ou aceite do cliente anexado', 'D10');

-- RN-47: cláusulas fixas que saem em toda PI. {MULTA_RESCISAO} é substituído pela configuração
-- "multa_rescisao_texto" (enquanto o jurídico não definir, sai "[texto a definir pelo jurídico]").
INSERT INTO clausulas (codigo, titulo, texto, ordem) VALUES
 ('C1', 'Venda incancelável',
  '1. Toda compra de mídia é INCANCELÁVEL, IRREVOGÁVEL e IRRETRATÁVEL, não cabendo direito de arrependimento após a confirmação.', 1),
 ('C2', 'Alteração com 72 horas úteis',
  '2. Alteração ou suspensão de programação somente por PI de alteração, com no mínimo 72 horas úteis de antecedência.', 2),
 ('C3', 'Vencimento e atraso',
  '3. Vencimento: se não houver data definida, é o último dia útil do mês seguinte a cada mês de veiculação. Atraso: multa de 2% sobre o valor vencido e não pago; a CONTRATADA pode suspender a veiculação até a regularização.', 3),
 ('C4', 'Responsabilidade pelo conteúdo',
  '4. Anunciante e agência são solidariamente responsáveis pelo conteúdo da publicidade. A agência precisa de carta de credenciamento do anunciante para assinar esta PI.', 4),
 ('C5', 'Contrato, foro e rescisão',
  '5. Esta PI integra o Contrato de Veiculação Publicitária do Grupo Mirante. Foro: comarca de São Luís - MA. Multa por rescisão antecipada: {MULTA_RESCISAO}.', 5);
