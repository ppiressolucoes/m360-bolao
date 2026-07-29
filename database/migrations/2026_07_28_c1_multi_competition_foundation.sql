-- Mega Bolão 360 — Sprint Comercial C.1
-- Fundação multi-competição, sem alteração de fatos esportivos.
--
-- Execução:
-- 1. validar backup e restore;
-- 2. executar em homologação;
-- 3. validar o preflight no painel do plugin;
-- 4. executar em produção somente em janela controlada.
--
-- Este arquivo documenta o contrato. A aplicação idempotente é feita por
-- Mengao360_Bolao_Schema, após habilitação explícita da constante
-- MENGAO360_BOLAO_ALLOW_SCHEMA_MIGRATIONS.

ALTER TABLE bolao_competicoes
    MODIFY competicao_id BIGINT UNSIGNED NOT NULL,
    MODIFY temporada VARCHAR(20) NOT NULL;

ALTER TABLE bolao_competicoes
    DROP INDEX uk_bolao_competicao_temporada,
    ADD KEY idx_bolao_competicao_temporada (competicao_id, temporada),
    ADD COLUMN estado_operacional VARCHAR(20) NOT NULL DEFAULT 'RASCUNHO' AFTER status_competicao_id,
    ADD COLUMN visibilidade VARCHAR(20) NOT NULL DEFAULT 'PUBLICO' AFTER estado_operacional,
    ADD COLUMN janela_fechamento_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 10 AFTER visibilidade,
    ADD COLUMN criado_por_wp_user_id BIGINT UNSIGNED DEFAULT NULL AFTER ind_ativo,
    ADD COLUMN atualizado_por_wp_user_id BIGINT UNSIGNED DEFAULT NULL AFTER criado_por_wp_user_id,
    ADD COLUMN dth_publicacao DATETIME DEFAULT NULL AFTER atualizado_por_wp_user_id,
    ADD COLUMN dth_arquivamento DATETIME DEFAULT NULL AFTER dth_publicacao,
    ADD KEY idx_bolao_estado_operacional (estado_operacional, ind_ativo);

-- As tabelas completas são criadas de forma idempotente pela classe de
-- migração do plugin. Não existem FKs para dim_* ou fato_*: o vínculo esportivo
-- é somente leitura e validado por aplicação.
