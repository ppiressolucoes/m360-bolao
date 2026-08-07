-- Diagnóstico somente leitura: Libertadores 2026 no Mega Bolão 360.
-- Não executa INSERT, UPDATE, DELETE, DDL ou procedures.
-- Objetivo: verificar os vínculos que o widget usa para decidir se um confronto
-- está definido: fato_jogos.mandante_id/visitante_id -> dim_times.id.

SET @competicao_slug := 'copa-libertadores';
SET @data_consulta := '2026-08-10';

-- 1) Confirma a competição efetivamente consultada pelo shortcode.
SELECT id, nome, slug, temporada, ativo
FROM dim_competicoes
WHERE slug = @competicao_slug;

-- 2) Detalhe por jogo da data: IDs nulos ou sem correspondente em dim_times
-- aparecem como NAO_DEFINIDO para o widget, ainda que data/local/fase existam.
SELECT
    fj.id AS jogo_id,
    fj.jogo_uid,
    fj.data_jogo,
    fj.rodada AS fase_ou_rodada,
    fj.estadio_nome,
    fj.status_jogo,
    fj.mandante_id,
    COALESCE(tm.nome_popular, tm.nome) AS mandante_lido_pelo_bolao,
    fj.visitante_id,
    COALESCE(tv.nome_popular, tv.nome) AS visitante_lido_pelo_bolao,
    CASE
        WHEN fj.mandante_id IS NULL OR fj.visitante_id IS NULL THEN 'IDS_AUSENTES_EM_FATO_JOGOS'
        WHEN tm.id IS NULL OR tv.id IS NULL THEN 'ID_SEM_CORRESPONDENCIA_EM_DIM_TIMES'
        ELSE 'CONFRONTO_DEFINIDO_PARA_O_BOLAO'
    END AS diagnostico
FROM fato_jogos fj
INNER JOIN dim_competicoes dc ON dc.id = fj.competicao_id
LEFT JOIN dim_times tm ON tm.id = fj.mandante_id
LEFT JOIN dim_times tv ON tv.id = fj.visitante_id
WHERE dc.slug = @competicao_slug
  AND DATE(fj.data_jogo) = @data_consulta
ORDER BY fj.data_jogo, fj.id;

-- 3) Resumo para o gate de abertura: total, liberáveis e ainda bloqueados.
SELECT
    DATE(fj.data_jogo) AS data_jogo,
    COUNT(*) AS total_jogos,
    SUM(CASE WHEN fj.mandante_id IS NOT NULL
                  AND fj.visitante_id IS NOT NULL
                  AND tm.id IS NOT NULL
                  AND tv.id IS NOT NULL THEN 1 ELSE 0 END) AS confrontos_definidos,
    SUM(CASE WHEN fj.mandante_id IS NULL
                  OR fj.visitante_id IS NULL
                  OR tm.id IS NULL
                  OR tv.id IS NULL THEN 1 ELSE 0 END) AS confrontos_bloqueados
FROM fato_jogos fj
INNER JOIN dim_competicoes dc ON dc.id = fj.competicao_id
LEFT JOIN dim_times tm ON tm.id = fj.mandante_id
LEFT JOIN dim_times tv ON tv.id = fj.visitante_id
WHERE dc.slug = @competicao_slug
  AND DATE(fj.data_jogo) = @data_consulta
GROUP BY DATE(fj.data_jogo);
