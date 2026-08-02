-- Mega Bolão 360 — diagnóstico somente leitura do catálogo elegível.
--
-- Objetivo: explicar por que a Copa Conmebol Libertadores 2026 não aparece
-- no seletor administrativo. Não executa INSERT, UPDATE, DELETE ou DDL.
-- Execute no schema do DW Esportivo e compartilhe somente o resultado.

SELECT
    dc.id AS competicao_id,
    dc.nome AS competicao,
    dc.slug AS competicao_slug,
    dc.temporada,
    dc.ativo AS competicao_ativa,
    dc.modelo_id,
    dcm.nome_modelo,
    dcm.slug_modelo,
    dcm.tipo_mata_mata,
    dcm.ativo AS modelo_ativo,
    COUNT(fj.id) AS total_jogos,
    SUM(CASE
        WHEN fj.data_jogo IS NOT NULL
         AND fj.status_jogo IS NOT NULL
        THEN 1 ELSE 0
    END) AS jogos_com_calendario,
    CASE
        WHEN dc.ativo <> 1 THEN 'COMPETICAO_INATIVA'
        WHEN dc.temporada IS NULL OR TRIM(dc.temporada) = '' THEN 'TEMPORADA_AUSENTE'
        WHEN dc.modelo_id IS NULL THEN 'MODELO_NAO_VINCULADO'
        WHEN dcm.id IS NULL THEN 'MODELO_NAO_ENCONTRADO'
        WHEN dcm.ativo <> 1 THEN 'MODELO_INATIVO'
        WHEN COUNT(fj.id) = 0 THEN 'SEM_JOGOS_EM_FATO_JOGOS'
        WHEN SUM(CASE
            WHEN fj.data_jogo IS NOT NULL
             AND fj.status_jogo IS NOT NULL
            THEN 1 ELSE 0
        END) = 0 THEN 'CALENDARIO_SEM_DATA_OU_STATUS'
        WHEN LOWER(CONCAT_WS(' ', dcm.slug_modelo, dcm.nome_modelo)) NOT REGEXP 'grupo|ponto|corrido|liga|mata|elimin'
            THEN 'MODELO_NAO_RECONHECIDO_PELA_ENGINE_ATUAL'
        ELSE 'ELEGIVEL_PARA_O_CATALOGO'
    END AS diagnostico_catalogo
FROM dim_competicoes dc
LEFT JOIN dim_competicao_modelo dcm
    ON dcm.id = dc.modelo_id
LEFT JOIN fato_jogos fj
    ON fj.competicao_id = dc.id
WHERE LOWER(CONCAT_WS(' ', dc.nome, dc.slug, dc.codigo))
    REGEXP 'libertadores|conmebol'
GROUP BY
    dc.id,
    dc.nome,
    dc.slug,
    dc.temporada,
    dc.ativo,
    dc.modelo_id,
    dcm.id,
    dcm.nome_modelo,
    dcm.slug_modelo,
    dcm.tipo_mata_mata,
    dcm.ativo
ORDER BY dc.temporada DESC, dc.nome;
