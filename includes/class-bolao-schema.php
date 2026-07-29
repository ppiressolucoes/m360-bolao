<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migrações idempotentes do domínio exclusivo do Mega Bolão 360.
 *
 * Nenhuma migração é executada automaticamente. Em produção, a constante
 * MENGAO360_BOLAO_ALLOW_SCHEMA_MIGRATIONS precisa ser definida como true e um
 * administrador deve confirmar a ação no painel.
 */
class Mengao360_Bolao_Schema {

    const TARGET_VERSION = 'c1-foundation-1';
    const MIGRATION_LOCK = 'm360_bolao_schema_c1_foundation';

    public static function migrations_allowed() {
        return defined('MENGAO360_BOLAO_ALLOW_SCHEMA_MIGRATIONS')
            && MENGAO360_BOLAO_ALLOW_SCHEMA_MIGRATIONS === true;
    }

    public static function table_exists($pdo, $table) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() === 1;
    }

    public static function column_exists($pdo, $table, $column) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() === 1;
    }

    public static function index_exists($pdo, $table, $index) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?'
        );
        $stmt->execute([$table, $index]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function preflight($pdo) {
        $required_legacy = [
            'bolao_competicoes',
            'bolao_usuarios',
            'bolao_palpites',
            'bolao_ligas',
            'bolao_liga_participantes',
            'bolao_ranking',
            'bolao_status_competicao',
            'dim_competicoes',
            'fato_jogos',
        ];
        $missing = [];

        foreach ($required_legacy as $table) {
            if (!self::table_exists($pdo, $table)) {
                $missing[] = $table;
            }
        }

        $foundation_tables = [
            'bolao_participantes',
            'bolao_resultados_overrides',
            'bolao_auditoria',
            'bolao_sincronizacoes',
            'bolao_schema_migrations',
        ];
        $foundation_missing = [];

        foreach ($foundation_tables as $table) {
            if (!self::table_exists($pdo, $table)) {
                $foundation_missing[] = $table;
            }
        }

        $legacy_unique_exists = self::table_exists($pdo, 'bolao_competicoes')
            && self::index_exists($pdo, 'bolao_competicoes', 'uk_bolao_competicao_temporada');

        $state_column_exists = self::table_exists($pdo, 'bolao_competicoes')
            && self::column_exists($pdo, 'bolao_competicoes', 'estado_operacional');

        $expected_columns = [
            'bolao_competicoes' => [
                'estado_operacional',
                'visibilidade',
                'janela_fechamento_minutos',
                'criado_por_wp_user_id',
                'atualizado_por_wp_user_id',
                'dth_publicacao',
                'dth_arquivamento',
            ],
            'bolao_participantes' => ['participante_id', 'bolao_competicao_id', 'usuario_bolao_id', 'status'],
            'bolao_resultados_overrides' => ['override_id', 'bolao_competicao_id', 'jogo_id', 'status', 'dth_expiracao'],
            'bolao_auditoria' => ['auditoria_id', 'request_id', 'evento', 'dth_evento'],
            'bolao_sincronizacoes' => ['sincronizacao_id', 'bolao_competicao_id', 'chave_idempotencia', 'status'],
            'bolao_schema_migrations' => ['migration_id', 'versao', 'checksum', 'aplicado_em'],
        ];
        $missing_foundation_columns = [];
        foreach ($expected_columns as $table => $columns) {
            if (!self::table_exists($pdo, $table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (!self::column_exists($pdo, $table, $column)) {
                    $missing_foundation_columns[] = $table . '.' . $column;
                }
            }
        }

        $expected_indexes = [
            'bolao_competicoes' => [
                'idx_bolao_estado_operacional',
                'idx_bolao_competicao_temporada',
            ],
            'bolao_participantes' => ['uk_bolao_participante'],
            'bolao_sincronizacoes' => ['uk_bolao_sincronizacao_chave'],
            'bolao_schema_migrations' => ['uk_bolao_schema_versao'],
        ];
        $missing_foundation_indexes = [];
        foreach ($expected_indexes as $table => $indexes) {
            if (!self::table_exists($pdo, $table)) {
                continue;
            }
            foreach ($indexes as $index) {
                if (!self::index_exists($pdo, $table, $index)) {
                    $missing_foundation_indexes[] = $table . '.' . $index;
                }
            }
        }

        $migration_recorded = self::migration_recorded($pdo);
        $compatibility_issues = empty($missing)
            ? self::get_compatibility_issues($pdo)
            : ['Baseline obrigatório incompleto.'];
        $schema_ready = empty($missing)
            && empty($foundation_missing)
            && empty($missing_foundation_columns)
            && empty($missing_foundation_indexes)
            && empty($compatibility_issues)
            && !$legacy_unique_exists
            && $state_column_exists;

        return [
            'ready' => $schema_ready && $migration_recorded,
            'schema_ready' => $schema_ready,
            'missing_legacy_tables' => $missing,
            'missing_foundation_tables' => $foundation_missing,
            'missing_foundation_columns' => $missing_foundation_columns,
            'missing_foundation_indexes' => $missing_foundation_indexes,
            'legacy_unique_exists' => $legacy_unique_exists,
            'state_column_exists' => $state_column_exists,
            'migration_recorded' => $migration_recorded,
            'compatibility_issues' => $compatibility_issues,
            'target_version' => self::TARGET_VERSION,
            'migrations_allowed' => self::migrations_allowed(),
        ];
    }

    public static function migrate($pdo, $wordpress_user_id) {
        if (!self::migrations_allowed()) {
            throw new RuntimeException(
                'Migração bloqueada. Defina MENGAO360_BOLAO_ALLOW_SCHEMA_MIGRATIONS como true somente durante a janela controlada.'
            );
        }

        $stmt_lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $stmt_lock->execute([self::MIGRATION_LOCK]);
        if ((int) $stmt_lock->fetchColumn() !== 1) {
            throw new RuntimeException('Já existe uma migração do Mega Bolão 360 em andamento.');
        }

        try {
            $preflight = self::preflight($pdo);
            if (!empty($preflight['missing_legacy_tables'])) {
                throw new RuntimeException(
                    'Baseline incompleto. Tabelas ausentes: ' . implode(', ', $preflight['missing_legacy_tables'])
                );
            }
            if (!empty($preflight['compatibility_issues'])) {
                throw new RuntimeException(
                    'Dados incompatíveis com a migração: ' . implode(' ', $preflight['compatibility_issues'])
                );
            }

            $migration_was_recorded = !empty($preflight['migration_recorded']);

            self::create_foundation_tables($pdo);
            self::align_legacy_columns($pdo);
            if (!$migration_was_recorded) {
                self::normalize_legacy_pool_states($pdo);
            }
            self::remove_single_pool_constraint($pdo);
            self::backfill_participants($pdo);

            $stmt = $pdo->prepare(
                'INSERT INTO bolao_schema_migrations
                    (versao, aplicado_por_wp_user_id, checksum, observacao)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    aplicado_por_wp_user_id = VALUES(aplicado_por_wp_user_id),
                    checksum = VALUES(checksum),
                    observacao = VALUES(observacao)'
            );
            $stmt->execute([
                self::TARGET_VERSION,
                (int) $wordpress_user_id,
                hash('sha256', self::TARGET_VERSION),
                'Sprint Comercial C.1 — Multi-Competition Foundation',
            ]);

            $postflight = self::preflight($pdo);
            if (!$postflight['ready']) {
                throw new RuntimeException(
                    'A migração terminou sem atingir o estado esperado. Não prossiga com a publicação.'
                );
            }

            return $postflight;
        } finally {
            $stmt_release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmt_release->execute([self::MIGRATION_LOCK]);
        }
    }

    private static function create_foundation_tables($pdo) {
        $statements = [
            "CREATE TABLE IF NOT EXISTS bolao_schema_migrations (
                migration_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                versao VARCHAR(80) NOT NULL,
                aplicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                aplicado_por_wp_user_id BIGINT UNSIGNED DEFAULT NULL,
                checksum CHAR(64) NOT NULL,
                observacao VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (migration_id),
                UNIQUE KEY uk_bolao_schema_versao (versao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS bolao_participantes (
                participante_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                bolao_competicao_id INT NOT NULL,
                usuario_bolao_id INT NOT NULL,
                papel VARCHAR(20) NOT NULL DEFAULT 'PARTICIPANTE',
                status VARCHAR(20) NOT NULL DEFAULT 'ATIVO',
                dth_entrada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                dth_saida DATETIME DEFAULT NULL,
                criado_por_wp_user_id BIGINT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (participante_id),
                UNIQUE KEY uk_bolao_participante (bolao_competicao_id, usuario_bolao_id),
                KEY idx_bolao_participantes_status (bolao_competicao_id, status),
                KEY idx_bolao_participantes_usuario (usuario_bolao_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS bolao_resultados_overrides (
                override_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                bolao_competicao_id INT NOT NULL,
                jogo_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'ATIVO',
                placar_mandante INT NOT NULL,
                placar_visitante INT NOT NULL,
                placar_penaltis_mandante INT DEFAULT NULL,
                placar_penaltis_visitante INT DEFAULT NULL,
                fonte_oficial VARCHAR(255) NOT NULL,
                justificativa VARCHAR(500) NOT NULL,
                hash_estado_dw CHAR(64) NOT NULL,
                criado_por_wp_user_id BIGINT UNSIGNED NOT NULL,
                dth_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                dth_expiracao DATETIME NOT NULL,
                dth_conciliacao DATETIME DEFAULT NULL,
                resultado_conciliacao VARCHAR(255) DEFAULT NULL,
                revogado_por_wp_user_id BIGINT UNSIGNED DEFAULT NULL,
                dth_revogacao DATETIME DEFAULT NULL,
                PRIMARY KEY (override_id),
                KEY idx_bolao_override_ativo (bolao_competicao_id, jogo_id, status, dth_expiracao),
                KEY idx_bolao_override_conciliacao (status, dth_conciliacao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS bolao_auditoria (
                auditoria_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                request_id CHAR(36) NOT NULL,
                evento VARCHAR(80) NOT NULL,
                entidade VARCHAR(80) NOT NULL,
                entidade_id VARCHAR(80) DEFAULT NULL,
                bolao_competicao_id INT DEFAULT NULL,
                wp_user_id BIGINT UNSIGNED DEFAULT NULL,
                ip_hash CHAR(64) DEFAULT NULL,
                estado_anterior LONGTEXT DEFAULT NULL,
                estado_novo LONGTEXT DEFAULT NULL,
                contexto LONGTEXT DEFAULT NULL,
                dth_evento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (auditoria_id),
                KEY idx_bolao_auditoria_bolao (bolao_competicao_id, dth_evento),
                KEY idx_bolao_auditoria_evento (evento, dth_evento),
                KEY idx_bolao_auditoria_request (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS bolao_sincronizacoes (
                sincronizacao_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                bolao_competicao_id INT NOT NULL,
                chave_idempotencia VARCHAR(120) NOT NULL,
                tipo VARCHAR(40) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'PENDENTE',
                hash_estado_dw CHAR(64) DEFAULT NULL,
                resumo LONGTEXT DEFAULT NULL,
                dth_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                dth_fim DATETIME DEFAULT NULL,
                PRIMARY KEY (sincronizacao_id),
                UNIQUE KEY uk_bolao_sincronizacao_chave (bolao_competicao_id, chave_idempotencia),
                KEY idx_bolao_sincronizacao_status (status, dth_inicio)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
    }

    private static function align_legacy_columns($pdo) {
        $pdo->exec(
            'ALTER TABLE bolao_competicoes
             MODIFY competicao_id BIGINT UNSIGNED NOT NULL,
             MODIFY temporada VARCHAR(20) NOT NULL'
        );

        $columns = [
            'estado_operacional' => "VARCHAR(20) NOT NULL DEFAULT 'RASCUNHO' AFTER status_competicao_id",
            'visibilidade' => "VARCHAR(20) NOT NULL DEFAULT 'PUBLICO' AFTER estado_operacional",
            'janela_fechamento_minutos' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 10 AFTER visibilidade',
            'criado_por_wp_user_id' => 'BIGINT UNSIGNED DEFAULT NULL AFTER ind_ativo',
            'atualizado_por_wp_user_id' => 'BIGINT UNSIGNED DEFAULT NULL AFTER criado_por_wp_user_id',
            'dth_publicacao' => 'DATETIME DEFAULT NULL AFTER atualizado_por_wp_user_id',
            'dth_arquivamento' => 'DATETIME DEFAULT NULL AFTER dth_publicacao',
        ];

        foreach ($columns as $column => $definition) {
            if (!self::column_exists($pdo, 'bolao_competicoes', $column)) {
                $pdo->exec("ALTER TABLE bolao_competicoes ADD COLUMN {$column} {$definition}");
            }
        }

        if (!self::index_exists($pdo, 'bolao_competicoes', 'idx_bolao_estado_operacional')) {
            $pdo->exec(
                'ALTER TABLE bolao_competicoes
                 ADD KEY idx_bolao_estado_operacional (estado_operacional, ind_ativo)'
            );
        }
    }

    private static function normalize_legacy_pool_states($pdo) {
        $pdo->exec(
            "UPDATE bolao_competicoes
             SET estado_operacional = CASE
                    WHEN ind_ativo = 0 THEN 'ARQUIVADO'
                    WHEN data_fechamento IS NOT NULL
                     AND data_fechamento <= NOW() THEN 'ENCERRADO'
                    WHEN data_abertura IS NULL
                      OR data_abertura > NOW() THEN 'RASCUNHO'
                    ELSE 'ABERTO'
                 END,
                 visibilidade = CASE
                    WHEN ind_publico = 1 THEN 'PUBLICO'
                    ELSE 'PRIVADO'
                 END,
                 dth_publicacao = CASE
                    WHEN ind_ativo = 1
                     AND data_abertura IS NOT NULL
                     AND data_abertura <= NOW()
                    THEN COALESCE(data_abertura, dth_criacao, NOW())
                    ELSE NULL
                 END,
                 dth_arquivamento = CASE
                    WHEN ind_ativo = 0 THEN NOW()
                    ELSE NULL
                 END"
        );
    }

    private static function remove_single_pool_constraint($pdo) {
        if (self::index_exists($pdo, 'bolao_competicoes', 'uk_bolao_competicao_temporada')) {
            $pdo->exec(
                'ALTER TABLE bolao_competicoes
                 DROP INDEX uk_bolao_competicao_temporada'
            );
        }

        if (!self::index_exists($pdo, 'bolao_competicoes', 'idx_bolao_competicao_temporada')) {
            $pdo->exec(
                'ALTER TABLE bolao_competicoes
                 ADD KEY idx_bolao_competicao_temporada (competicao_id, temporada)'
            );
        }
    }

    private static function backfill_participants($pdo) {
        $pdo->exec(
            "INSERT IGNORE INTO bolao_participantes
                (bolao_competicao_id, usuario_bolao_id, papel, status, dth_entrada)
             SELECT origem.bolao_competicao_id,
                    origem.usuario_bolao_id,
                    'PARTICIPANTE',
                    'ATIVO',
                    NOW()
             FROM (
                 SELECT bolao_competicao_id, usuario_bolao_id
                 FROM bolao_palpites
                 UNION
                 SELECT bl.bolao_competicao_id, blp.usuario_bolao_id
                 FROM bolao_liga_participantes blp
                 INNER JOIN bolao_ligas bl ON bl.liga_id = blp.liga_id
                 UNION
                 SELECT bolao_competicao_id, usuario_bolao_id
                 FROM bolao_ranking
             ) origem
             WHERE origem.bolao_competicao_id IS NOT NULL
               AND origem.usuario_bolao_id IS NOT NULL"
        );
    }

    private static function migration_recorded($pdo) {
        if (!self::table_exists($pdo, 'bolao_schema_migrations')) {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM bolao_schema_migrations
             WHERE versao = ?'
        );
        $stmt->execute([self::TARGET_VERSION]);

        return (int) $stmt->fetchColumn() === 1;
    }

    private static function get_compatibility_issues($pdo) {
        $issues = [];

        $null_competitions = (int) $pdo->query(
            'SELECT COUNT(*) FROM bolao_competicoes WHERE competicao_id IS NULL'
        )->fetchColumn();
        if ($null_competitions > 0) {
            $issues[] = $null_competitions . ' bolão(ões) sem competicao_id.';
        }

        $invalid_seasons = (int) $pdo->query(
            "SELECT COUNT(*)
             FROM bolao_competicoes
             WHERE temporada IS NULL
                OR CHAR_LENGTH(CAST(temporada AS CHAR)) > 20"
        )->fetchColumn();
        if ($invalid_seasons > 0) {
            $issues[] = $invalid_seasons . ' temporada(s) incompatível(is) com VARCHAR(20).';
        }

        $orphan_competitions = (int) $pdo->query(
            'SELECT COUNT(*)
             FROM bolao_competicoes bc
             LEFT JOIN dim_competicoes dc ON dc.id = bc.competicao_id
             WHERE dc.id IS NULL'
        )->fetchColumn();
        if ($orphan_competitions > 0) {
            $issues[] = $orphan_competitions . ' vínculo(s) com competição inexistente no DW.';
        }

        $invalid_slugs = (int) $pdo->query(
            "SELECT COUNT(*)
             FROM bolao_competicoes
             WHERE slug_bolao IS NULL OR TRIM(slug_bolao) = ''"
        )->fetchColumn();
        if ($invalid_slugs > 0) {
            $issues[] = $invalid_slugs . ' bolão(ões) sem slug.';
        }

        return $issues;
    }
}
