-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 30-Jun-2026 às 11:51
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `crm_albinet`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `crm_opcoes`
--

CREATE TABLE `crm_opcoes` (
  `id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `valor` varchar(100) NOT NULL,
  `ordem` int(11) DEFAULT 999
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `crm_opcoes`
--

INSERT INTO `crm_opcoes` (`id`, `tipo`, `valor`, `ordem`) VALUES
(1, 'origem', 'Website', 2),
(3, 'servicos', 'Implementação de CRM', 2),
(4, 'servicos', 'Consultoria Comercial', 1),
(5, 'estado', 'Nova', 1),
(7, 'prioridade', 'Baixa', 2),
(11, 'estado', 'Reunião Marcada', 4),
(12, 'estado', 'Proposta Enviada', 3),
(13, 'estado', 'Em negociação', 5),
(16, 'origem', 'Evento / Feira', 3),
(17, 'origem', 'Referência de conhecido', 4),
(18, 'origem', 'E-mail', 5),
(20, 'prioridade', 'Média', 3),
(21, 'prioridade', 'Alta', 4),
(23, 'estado', 'Ganha', 6),
(31, 'prioridade', 'Nenhuma', 1),
(33, 'servicos', 'Licenciamento de Software', 3),
(34, 'prioridade', 'Urgente', 5),
(35, 'servicos', 'Formação de Equipas', 4),
(42, 'interacao', 'Chamadas', 1),
(43, 'interacao', 'E-mail', 2),
(44, 'interacao', 'WhatsApp', 3),
(45, 'interacao', 'Reuniões', 4),
(46, 'interacao', 'Notas', 5),
(49, 'estado', 'Perdida', 7),
(50, 'origem', 'Linkedin', 999),
(54, 'estado', 'Contactada', 2);

-- --------------------------------------------------------

--
-- Estrutura da tabela `interacoes`
--

CREATE TABLE `interacoes` (
  `id_interacao` int(11) NOT NULL,
  `id_lead` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL,
  `tipo` varchar(30) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `data_registo` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `interacoes`
--

INSERT INTO `interacoes` (`id_interacao`, `id_lead`, `id_utilizador`, `tipo`, `descricao`, `data_registo`) VALUES
(200, 200, 202, 'Chamadas', 'Contacto telefónico inicial. Demonstrou muito interesse no CRM.', '2026-06-01 10:30:00'),
(201, 201, 203, 'E-mail', 'Enviado e-mail com a apresentação institucional e portfólio.', '2026-06-02 16:00:00'),
(202, 202, 205, 'WhatsApp', 'Enviado link do Teams para a reunião de amanhã via WhatsApp.', '2026-06-03 11:15:00'),
(203, 203, 206, 'E-mail', 'Proposta comercial enviada formalmente por e-mail.', '2026-06-04 14:20:00'),
(204, 204, 207, 'Reuniões', 'Reunião presencial para negociar os valores da implementação.', '2026-06-05 10:00:00'),
(205, 205, 209, 'Notas', 'Contrato assinado recebido fisicamente. Mover para ganha.', '2026-06-06 17:45:00'),
(206, 206, 210, 'Chamadas', 'Cliente informou que não tem orçamento este ano. Negócio perdido.', '2026-06-07 11:30:00'),
(207, 207, 211, 'Chamadas', 'Tentativa de contacto sem sucesso, deixou mensagem no atendedor.', '2026-06-08 15:00:00'),
(208, 208, 213, 'WhatsApp', 'Cliente pediu os detalhes da API por WhatsApp para avançar mais rápido.', '2026-06-09 09:10:00'),
(209, 209, 214, 'Reuniões', 'Reunião de alinhamento na feira. Mostrou interesse na consultoria.', '2026-06-10 14:00:00'),
(210, 210, 216, 'E-mail', 'Enviada cotação para as 20 licenças solicitadas.', '2026-06-11 16:30:00'),
(211, 211, 217, 'Chamadas', 'Ligou a dizer que a proposta está na mesa do Diretor Geral.', '2026-06-12 12:00:00'),
(212, 212, 218, 'Notas', 'Indicação direta do parceiro de negócios. Cliente muito recetivo.', '2026-06-13 09:00:00'),
(213, 213, 220, 'E-mail', 'Recebido e-mail de rejeição. Optaram por uma ferramenta local.', '2026-06-14 15:20:00'),
(214, 214, 221, 'Chamadas', 'Primeira abordagem para perceber o tamanho da equipa deles.', '2026-06-15 10:45:00');

-- --------------------------------------------------------

--
-- Estrutura da tabela `leads`
--

CREATE TABLE `leads` (
  `id_lead` int(11) NOT NULL,
  `nome_contacto` varchar(100) NOT NULL,
  `empresa` varchar(100) NOT NULL,
  `telefone` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `origem` varchar(30) NOT NULL,
  `servicos` varchar(255) DEFAULT NULL,
  `estado` varchar(30) NOT NULL,
  `prioridade` varchar(15) NOT NULL,
  `id_responsavel` int(11) DEFAULT NULL,
  `observacoes` varchar(255) NOT NULL,
  `rgpd_data_consentimento` date DEFAULT NULL,
  `rgpd_origem_consentimento` varchar(100) DEFAULT NULL,
  `valor_potencial` decimal(10,2) DEFAULT NULL,
  `data_criacao` date NOT NULL DEFAULT curdate(),
  `token_rgpd` varchar(64) DEFAULT NULL,
  `estado_rgpd` enum('Pendente','Aceite','Removido') DEFAULT 'Pendente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `leads`
--

INSERT INTO `leads` (`id_lead`, `nome_contacto`, `empresa`, `telefone`, `email`, `origem`, `servicos`, `estado`, `prioridade`, `id_responsavel`, `observacoes`, `rgpd_data_consentimento`, `rgpd_origem_consentimento`, `valor_potencial`, `data_criacao`, `token_rgpd`, `estado_rgpd`) VALUES
(200, 'Manuel Pereira', 'TecnoNorte Lda', '911000001', 'manuel@tecnonorte.pt', 'Website', 'Implementação de CRM', 'Nova', 'Alta', 202, 'Interessado em migrar sistema antigo.', '2026-06-01', 'Formulário Web', 2500.00, '2026-06-01', 'token_lead_200', 'Aceite'),
(201, 'Maria Rodrigues', 'Sabor&Arte', '922000002', 'maria@saborarte.pt', 'Referência de conhecido', 'Consultoria Comercial', 'Contactada', 'Média', 203, 'Pediu contacto após as 18h.', '2026-06-02', 'Chamada Telefónica', 1200.00, '2026-06-02', 'token_lead_201', 'Aceite'),
(202, 'Jose Carvalho', 'InovaModa', '933000003', 'jose@inovamoda.pt', 'Linkedin', 'Licenciamento de Software', 'Reunião Marcada', 'Urgente', 205, 'Reunião agendada via Teams.', '2026-06-03', 'Linkedin Msg', 4500.00, '2026-06-03', 'token_lead_202', 'Aceite'),
(203, 'António Lopes', 'ConstroiBem', '966000004', 'antonio@constroibem.pt', 'Evento / Feira', 'Formação de Equipas', 'Proposta Enviada', 'Baixa', 206, 'Proposta enviada por email.', NULL, NULL, 800.00, '2026-06-04', 'token_lead_203', 'Pendente'),
(204, 'Isabel Magalhães', 'Clinica Viva', '911000005', 'isabel@clinicaviva.pt', 'E-mail', 'Implementação de CRM', 'Em negociação', 'Alta', 207, 'A discutir termos de pagamento.', '2026-06-05', 'E-mail Resposta', 3100.00, '2026-06-05', 'token_lead_204', 'Aceite'),
(205, 'Francisco Jesus', 'AutoStand Rio', '922000006', 'francisco@autostandrio.pt', 'Website', 'Consultoria Comercial', 'Ganha', 'Média', 209, 'Contrato assinado hoje.', '2026-06-06', 'Formulário Web', 1500.00, '2026-06-06', 'token_lead_205', 'Aceite'),
(206, 'Paula Henriques', 'Logistica Global', '933000007', 'paula@logisticaglobal.pt', 'Linkedin', 'Licenciamento de Software', 'Perdida', 'Nenhuma', 210, 'Sem orçamento para este ano.', NULL, NULL, 6000.00, '2026-06-07', 'token_lead_206', 'Removido'),
(207, 'Ricardo Sousa', 'Padaria Central', '966000008', 'ricardo@padariacentral.pt', 'Referência de conhecido', 'Formação de Equipas', 'Nova', 'Baixa', 211, 'Lead fria, apenas sondagem.', '2026-06-08', 'Conversa Direta', 500.00, '2026-06-08', 'token_lead_207', 'Aceite'),
(208, 'Luísa Fontes', 'EducaMais', '911000009', 'luisa@educamais.pt', 'E-mail', 'Implementação de CRM', 'Contactada', 'Alta', 213, 'Necessita de mais informações das APIS.', '2026-06-09', 'E-mail Resposta', 2900.00, '2026-06-09', 'token_lead_208', 'Aceite'),
(209, 'Carlos Antunes', 'FrioNorte S.A.', '922000010', 'carlos@frionorte.pt', 'Evento / Feira', 'Consultoria Comercial', 'Reunião Marcada', 'Média', 214, 'Conhecido na feira internacional.', '2026-06-10', 'Inscrição Feira', 1800.00, '2026-06-10', 'token_lead_209', 'Aceite'),
(210, 'Soraia Abreu', 'Têxtil Ave', '933000011', 'soraia@textilave.pt', 'Website', 'Licenciamento de Software', 'Proposta Enviada', 'Urgente', 216, 'Proposta de 20 licenças.', '2026-06-11', 'Formulário Web', 4000.00, '2026-06-11', 'token_lead_210', 'Aceite'),
(211, 'Jorge Barreto', 'Imobiliária Sol', '966000012', 'jorge@imobsol.pt', 'Linkedin', 'Formação de Equipas', 'Em negociação', 'Baixa', 217, 'Falta aprovação do Diretor Geral.', NULL, NULL, 1250.00, '2026-06-12', 'token_lead_211', 'Pendente'),
(212, 'Catarina Reis', 'EcoEnergia', '911000013', 'catarina@ecoenergia.pt', 'Referência de conhecido', 'Implementação de CRM', 'Ganha', 'Alta', 218, 'Cliente recomendou a plataforma.', '2026-06-13', 'Indicação', 5500.00, '2026-06-13', 'token_lead_212', 'Aceite'),
(213, 'Miguel Oliveira', 'Vinhos do Douro', '922000014', 'miguel@vinhosdouro.pt', 'E-mail', 'Consultoria Comercial', 'Perdida', 'Média', 220, 'Escolheram solução concorrente.', NULL, NULL, 2200.00, '2026-06-14', 'token_lead_213', 'Pendente'),
(214, 'Beatriz Cunha', 'Geral Design', '933000015', 'beatriz@geraldesign.pt', 'Website', 'Licenciamento de Software', 'Nova', 'Nenhuma', 221, 'Submeteu formulário fora de horas.', '2026-06-15', 'Formulário Web', 900.00, '2026-06-15', 'token_lead_214', 'Aceite'),
(215, 'Fernando Neto', 'Metalúrgica Faria', '966000016', 'fernando@metalfaria.pt', 'Evento / Feira', 'Formação de Equipas', 'Contactada', 'Baixa', 222, 'Pediu portfólio de formação.', '2026-06-16', 'Cartão de Visita', 1100.00, '2026-06-16', 'token_lead_215', 'Aceite'),
(216, 'Diana Marques', 'Consultores Associados', '911000017', 'diana@consultores.pt', 'Linkedin', 'Implementação de CRM', 'Reunião Marcada', 'Alta', 224, 'Quer demonstração ao vivo.', '2026-06-17', 'Linkedin Msg', 3800.00, '2026-06-17', 'token_lead_216', 'Aceite'),
(217, 'Daniel Cardoso', 'Farmácia Ideal', '922000018', 'daniel@farmaciaideal.pt', 'Referência de conhecido', 'Consultoria Comercial', 'Proposta Enviada', 'Média', 225, 'A aguardar feedback da proposta.', '2026-06-18', 'E-mail', 1700.00, '2026-06-18', 'token_lead_217', 'Aceite'),
(218, 'Sara Pintor', 'Moda Jovem', '933000019', 'sara@modajovem.pt', 'E-mail', 'Licenciamento de Software', 'Em negociação', 'Urgente', 226, 'Urgência devido ao fecho do trimestre.', NULL, NULL, 5000.00, '2026-06-19', 'token_lead_218', 'Pendente'),
(219, 'Nuno Valentim', 'Seguros Aliança', '966000020', 'nuno@segurosalianca.pt', 'Website', 'Formação de Equipas', 'Ganha', 'Baixa', 228, 'Formação agendada para Julho.', '2026-06-20', 'Formulário Web', 1600.00, '2026-06-20', 'token_lead_219', 'Aceite'),
(220, 'Cláudia Cruz', 'Braga Prata', '911000021', 'claudia@bragaprata.pt', 'Linkedin', 'Implementação de CRM', 'Perdida', 'Alta', 229, 'Orçamento muito reduzido.', NULL, NULL, 3000.00, '2026-06-21', 'token_lead_220', 'Pendente'),
(221, 'Vítor Gaspar', 'Peças Auto Coimbra', '922000022', 'vitor@pecasauto.pt', 'Evento / Feira', 'Consultoria Comercial', 'Nova', 'Média', 202, 'Contacto inicial no Stand.', '2026-06-22', 'Inscrição Feira', 2000.00, '2026-06-22', 'token_lead_221', 'Aceite'),
(222, 'Rita Pires', 'Soluções LED', '933000023', 'rita@solucoesled.pt', 'Referência de conhecido', 'Licenciamento de Software', 'Contactada', 'Urgente', 203, 'Trocar emails sobre compatibilidade.', '2026-06-23', 'Chamada', 4200.00, '2026-06-23', 'token_lead_222', 'Aceite'),
(223, 'Alexandre Lima', 'Turismo Douro', '966000024', 'alexandre@turismodouro.pt', 'E-mail', 'Formação de Equipas', 'Reunião Marcada', 'Baixa', 205, 'Reunião presencial no Porto.', '2026-06-24', 'E-mail Directo', 1300.00, '2026-06-24', 'token_lead_223', 'Aceite'),
(224, 'Margarida Pinto', 'Contas Certas', '911000025', 'margarida@contascertas.pt', 'Website', 'Implementação de CRM', 'Proposta Enviada', 'Alta', 206, 'Proposta enviada com desconto 10%.', '2026-06-25', 'Formulário Web', 2700.00, '2026-06-25', 'token_lead_224', 'Aceite'),
(225, 'Rui Pedro', 'Fruta Fresca S.A.', '922000026', 'rui@frutafresca.pt', 'Linkedin', 'Consultoria Comercial', 'Em negociação', 'Média', 207, 'A analisar concorrência.', NULL, NULL, 1900.00, '2026-06-26', 'token_lead_225', 'Pendente'),
(226, 'Inês Vieira', 'Gás e Luz', '933000027', 'ines@gaseluz.pt', 'Evento / Feira', 'Licenciamento de Software', 'Ganha', 'Urgente', 209, 'Fechado com sucesso rápida adesão.', '2026-06-27', 'Feira Contacto', 4800.00, '2026-06-27', 'token_lead_226', 'Aceite'),
(227, 'Gonçalo Silva', 'Construções Vouga', '966000028', 'goncalo@constroivouga.pt', 'Referência de conhecido', 'Formação de Equipas', 'Perdida', 'Baixa', 210, 'Decidiram adiar a formação.', NULL, NULL, 1400.00, '2026-06-28', 'token_lead_227', 'Removido'),
(228, 'Bárbara Ramos', 'Estética Plena', '911000029', 'barbara@esteticaplena.pt', 'E-mail', 'Implementação de CRM', 'Nova', 'Alta', 211, 'Sondagem de mercado rápida.', '2026-06-29', 'E-mail', 3300.00, '2026-06-29', 'token_lead_228', 'Aceite'),
(229, 'Tomé Alvares', 'Peixe do Dia', '922000030', 'tome@peixedodia.pt', 'Website', 'Consultoria Comercial', 'Contactada', 'Média', 213, 'Ligou de volta interessado.', '2026-06-29', 'Formulário Web', 1150.00, '2026-06-29', 'token_lead_229', 'Aceite');

-- --------------------------------------------------------

--
-- Estrutura da tabela `tarefas`
--

CREATE TABLE `tarefas` (
  `id_tarefa` int(11) NOT NULL,
  `id_lead` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `id_utilizador` int(11) DEFAULT NULL,
  `descricao` varchar(200) NOT NULL,
  `data_limite` datetime NOT NULL,
  `estado` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `tarefas`
--

INSERT INTO `tarefas` (`id_tarefa`, `id_lead`, `titulo`, `id_utilizador`, `descricao`, `data_limite`, `estado`) VALUES
(200, 200, 'Primeiro Contacto', 202, 'Ligar para validar os dados do website.', '2026-07-02 10:00:00', 'Pendente'),
(201, 201, 'Enviar Portfólio', 203, 'Enviar e-mail com casos de sucesso de consultoria.', '2026-07-03 15:30:00', 'Concluída'),
(202, 202, 'Preparar Reunião Teams', 205, 'Estudar o perfil da InovaModa para a demonstração.', '2026-07-04 09:15:00', 'Pendente'),
(203, 203, 'Follow-up Proposta', 206, 'Verificar se receberam a proposta comercial.', '2026-07-06 14:00:00', 'Pendente'),
(204, 204, 'Negociar Desconto', 207, 'Falar com o gestor sobre margem de manobra.', '2026-07-05 11:00:00', 'Concluída'),
(205, 205, 'Reunião de Integração', 209, 'Reunião de kickoff do projeto ganho.', '2026-07-07 16:00:00', 'Pendente'),
(206, 206, 'Arquivar Processo', 210, 'Mudar estado para perdida no sistema global.', '2026-07-01 12:00:00', 'Concluída'),
(207, 207, 'Ligar para Qualificar', 211, 'Perceber real interesse na formação.', '2026-07-10 10:30:00', 'Pendente'),
(208, 208, 'Enviar Documento API', 213, 'Enviar PDF com especificações técnicas do CRM.', '2026-07-04 17:00:00', 'Pendente'),
(209, 209, 'Confirmar Hora Reunião', 214, 'Mandar SMS a confirmar a sala e hora da reunião.', '2026-07-03 11:45:00', 'Concluída'),
(210, 210, 'Ajustar Preço Licenças', 216, 'Refazer proposta com 15 licenças em vez de 20.', '2026-07-08 14:30:00', 'Pendente'),
(211, 211, 'Ligar Diretor Geral', 217, 'Tentar falar diretamente com quem decide.', '2026-07-12 09:30:00', 'Pendente'),
(212, 212, 'Enviar Fatura Inicial', 218, 'Emitir e enviar fatura de adjudicação.', '2026-07-02 18:00:00', 'Concluída'),
(213, 213, 'Análise de Concorrência', 220, 'Registar internamente o porquê de terem preferido o concorrente.', '2026-07-05 10:00:00', 'Concluída'),
(214, 214, 'Contacto Inicial', 221, 'Ligar para agendar chamada de qualificação.', '2026-07-06 11:15:00', 'Pendente'),
(215, 215, 'Validar Agenda Formador', 222, 'Verificar disponibilidade de datas para a formação.', '2026-07-09 15:00:00', 'Pendente'),
(216, 216, 'Configurar Demo CRM', 224, 'Criar conta teste limpa para mostrar ao cliente.', '2026-07-05 14:00:00', 'Concluída'),
(217, 217, 'Verificar E-mail', 225, 'Confirmar se responderam à proposta enviada.', '2026-07-07 10:00:00', 'Pendente'),
(218, 218, 'Telefonema Urgente', 226, 'Tentar fechar o negócio ainda hoje.', '2026-07-01 16:30:00', 'Pendente'),
(219, 219, 'Preparar Contrato', 228, 'Inserir dados contratuais na minuta padrão.', '2026-07-04 12:00:00', 'Concluída'),
(220, 220, 'Reavaliar Requisitos', 229, 'Perceber se podemos cortar funcionalidades para baixar preço.', '2026-07-09 09:00:00', 'Pendente'),
(221, 221, 'Mandar Notas de Feira', 202, 'Adicionar observações do stand à ficha da lead.', '2026-07-02 14:15:00', 'Concluída'),
(222, 222, 'Esclarecer Dúvida Técnica', 203, 'Falar com engenharia sobre compatibilidade LED.', '2026-07-06 15:45:00', 'Pendente'),
(223, 223, 'Reservar Sala Hotel', 205, 'Reservar espaço para a reunião presencial.', '2026-07-08 11:00:00', 'Pendente'),
(224, 224, 'Ligar a cobrar Resposta', 206, 'Insistir educadamente sobre o estado da proposta.', '2026-07-11 10:00:00', 'Pendente'),
(225, 225, 'Pesquisa da Empresa', 207, 'Ver faturação pública da empresa para ajustar proposta.', '2026-07-03 16:00:00', 'Concluída'),
(226, 226, 'Ativar Licenças', 209, 'Dar ordem ao suporte para criar os acessos definitivos.', '2026-07-02 09:00:00', 'Concluída'),
(227, 227, 'Email de Agradecimento', 210, 'Agradecer o tempo despendido apesar de não fecharem.', '2026-07-04 14:00:00', 'Pendente'),
(228, 228, 'Tentar Primeiro Contacto', 211, 'Ligar para o número fixo deixado no e-mail.', '2026-07-05 10:30:00', 'Pendente'),
(229, 229, 'Reunião Comercial', 213, 'Alinhar objetivos de consultoria com o cliente.', '2026-07-07 11:30:00', 'Pendente'),
(230, 200, 'Enviar orçamento corrigido', 202, 'O cliente pediu urgência na revisão dos valores.', '2026-05-15 14:00:00', 'Pendente'),
(231, 204, 'Telefonar para fechar contrato', 207, 'Ligar após a aprovação da administração deles.', '2026-05-20 10:30:00', 'Concluída'),
(232, 210, 'Validar requisitos técnicos', 216, 'Confirmar com a equipa de TI se o servidor deles aguenta.', '2026-05-25 17:00:00', 'Pendente');

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizadores`
--

CREATE TABLE `utilizadores` (
  `id_utilizador` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `perfil` varchar(20) NOT NULL,
  `data_criacao` date NOT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `utilizadores`
--

INSERT INTO `utilizadores` (`id_utilizador`, `nome`, `email`, `password_hash`, `perfil`, `data_criacao`, `reset_token`, `reset_expires`) VALUES
(2, 'Fradique', 'fradique@admin.com', '$2y$10$sl6ItRMq.eItpPC0tPqMZej2S2fb4HCHmrXDLMGlMn/EadrpfX8Qa', 'admin', '2026-05-26', '6b4fc7eac62d510a5a766ceb8a08bf29073308b51ab55aea0e7432c0ae64caaf', '2026-05-26 17:57:22'),
(136, 'Fradique', 'fradique@gestor.com', '$2y$10$hRh.q5ADwSq1h2LRo8X9z.wj0B916.feGrvx3CktYFZ8YiVViLjZu', 'gestor', '2026-06-25', NULL, NULL),
(137, 'Fradique', 'fradique@comercial.com', '$2y$10$qabRX2IpxPKH.I/4Xm5Wqu0q2JIPPTPsRD3bD7c6v.9nS1.celvoy', 'comercial', '2026-06-25', NULL, NULL),
(200, 'Ana Silva', 'ana.silva@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'admin', '2026-01-10', NULL, NULL),
(201, 'Bruno Santos', 'bruno.santos@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'gestor', '2026-01-12', NULL, NULL),
(202, 'Carla Costa', 'carla.costa@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-01-15', NULL, NULL),
(203, 'Diogo Rocha', 'diogo.rocha@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-01-15', NULL, NULL),
(204, 'Elena Gomes', 'elena.gomes@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'gestor', '2026-01-20', NULL, NULL),
(205, 'Fabio Pinto', 'fabio.pinto@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-01-22', NULL, NULL),
(206, 'Gisela Martins', 'gisela.martins@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-01-25', NULL, NULL),
(207, 'Hugo Ribeiro', 'hugo.ribeiro@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-02-01', NULL, NULL),
(208, 'Ines Carvalho', 'ines.carvalho@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'gestor', '2026-02-02', NULL, NULL),
(209, 'Joao Almeida', 'joao.almeida@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-02-05', NULL, NULL),
(210, 'Katia Mendes', 'katia.mendes@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-02-10', NULL, NULL),
(211, 'Luis Antunes', 'luis.antunes@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-02-11', NULL, NULL),
(212, 'Marta Fonseca', 'marta.fonseca@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'admin', '2026-02-15', NULL, NULL),
(213, 'Nuno Moreira', 'nuno.moreira@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-02-18', NULL, NULL),
(214, 'Olivia Soares', 'olivia.soares@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-02-20', NULL, NULL),
(215, 'Pedro Neves', 'pedro.neves@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'gestor', '2026-02-22', NULL, NULL),
(216, 'Quelia Ramos', 'quelia.ramos@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-02-25', NULL, NULL),
(217, 'Rui Teixeira', 'rui.teixeira@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-03-01', NULL, NULL),
(218, 'Sofia Machado', 'sofia.machado@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-03-03', NULL, NULL),
(219, 'Tiago Correia', 'tiago.correia@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'gestor', '2026-03-05', NULL, NULL),
(220, 'Vania Dias', 'vania.dias@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-03-10', NULL, NULL),
(221, 'Vitor Jorge', 'vitor.jorge@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-03-12', NULL, NULL),
(222, 'Xavier Lopes', 'xavier.lopes@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-03-15', NULL, NULL),
(223, 'Yara Cruz', 'yara.cruz@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'gestor', '2026-03-18', NULL, NULL),
(224, 'Zelia Couto', 'zelia.couto@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-03-20', NULL, NULL),
(225, 'Carlos Abreu', 'carlos.abreu@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-03-22', NULL, NULL),
(226, 'Diana Ferraz', 'diana.ferraz@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-03-25', NULL, NULL),
(227, 'Eduardo Neto', 'eduardo.neto@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'gestor', '2026-03-26', NULL, NULL),
(228, 'Fernanda Sa', 'fernanda.sa@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-03-28', NULL, NULL),
(229, 'Gabriel Lima', 'gabriel.lima@crm.com', '$2y$10$7R0Z4dZp6M5E4SBlVfSgZeZ7Fh.1bA6iL2gY8U6QpS9O1kC/Y2mDe', 'comercial', '2026-03-30', NULL, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `crm_opcoes`
--
ALTER TABLE `crm_opcoes`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `interacoes`
--
ALTER TABLE `interacoes`
  ADD PRIMARY KEY (`id_interacao`),
  ADD KEY `fk_interacao_utilizador` (`id_utilizador`),
  ADD KEY `fk_interacao_lead` (`id_lead`);

--
-- Índices para tabela `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id_lead`),
  ADD KEY `fk_lead_responsavel` (`id_responsavel`);

--
-- Índices para tabela `tarefas`
--
ALTER TABLE `tarefas`
  ADD PRIMARY KEY (`id_tarefa`),
  ADD KEY `fk_tarefa_utilizador` (`id_utilizador`),
  ADD KEY `fk_tarefa_lead` (`id_lead`);

--
-- Índices para tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  ADD PRIMARY KEY (`id_utilizador`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `crm_opcoes`
--
ALTER TABLE `crm_opcoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT de tabela `interacoes`
--
ALTER TABLE `interacoes`
  MODIFY `id_interacao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=215;

--
-- AUTO_INCREMENT de tabela `leads`
--
ALTER TABLE `leads`
  MODIFY `id_lead` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=230;

--
-- AUTO_INCREMENT de tabela `tarefas`
--
ALTER TABLE `tarefas`
  MODIFY `id_tarefa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=233;

--
-- AUTO_INCREMENT de tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  MODIFY `id_utilizador` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=230;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `interacoes`
--
ALTER TABLE `interacoes`
  ADD CONSTRAINT `fk_interacao_lead` FOREIGN KEY (`id_lead`) REFERENCES `leads` (`id_lead`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_interacao_utilizador` FOREIGN KEY (`id_utilizador`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `fk_lead_responsavel` FOREIGN KEY (`id_responsavel`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `tarefas`
--
ALTER TABLE `tarefas`
  ADD CONSTRAINT `fk_tarefa_lead` FOREIGN KEY (`id_lead`) REFERENCES `leads` (`id_lead`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tarefa_utilizador` FOREIGN KEY (`id_utilizador`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
