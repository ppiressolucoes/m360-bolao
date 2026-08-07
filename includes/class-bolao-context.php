<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve um bolão de forma explícita e valida seu vínculo com o DW.
 */
class Mengao360_Bolao_Context {

    public static function resolve($pdo, $bolao_slug = '', $competicao_slug = '', $bolao_id = 0) {
        $where = ['bc.ind_ativo = 1'];
        $params = [];

        if ((int) $bolao_id > 0) {
            $where[] = 'bc.bolao_competicao_id = ?';
            $params[] = (int) $bolao_id;
        } elseif ($bolao_slug !== '') {
            $where[] = 'bc.slug_bolao = ?';
            $params[] = $bolao_slug;
        } elseif ($competicao_slug !== '') {
            $where[] = 'dc.slug = ?';
            $params[] = $competicao_slug;
        } else {
            return new WP_Error('m360_bolao_contexto_ausente', 'Informe o slug do bolão.');
        }

        $has_state = class_exists('Mengao360_Bolao_Schema')
            && Mengao360_Bolao_Schema::column_exists($pdo, 'bolao_competicoes', 'estado_operacional');
        $has_window = class_exists('Mengao360_Bolao_Schema')
            && Mengao360_Bolao_Schema::column_exists($pdo, 'bolao_competicoes', 'janela_fechamento_minutos');
        $has_visibility = class_exists('Mengao360_Bolao_Schema')
            && Mengao360_Bolao_Schema::column_exists($pdo, 'bolao_competicoes', 'visibilidade');

        $sql = '
            SELECT
                bc.bolao_competicao_id,
                bc.slug_bolao,
                bc.titulo,
                bc.descricao,
                bc.competicao_id,
                bc.temporada,
                bc.ind_publico,
                bc.ind_ativo,
                dc.slug AS competicao_slug,
                dc.nome AS competicao_nome,
                dc.modelo_id,
                ' . ($has_state ? 'bc.estado_operacional' : "'ABERTO' AS estado_operacional") . ',
                ' . ($has_visibility ? 'bc.visibilidade' : "'PUBLICO' AS visibilidade") . ',
                ' . ($has_window ? 'bc.janela_fechamento_minutos' : '10 AS janela_fechamento_minutos') . '
            FROM bolao_competicoes bc
            INNER JOIN dim_competicoes dc
                ON dc.id = bc.competicao_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY bc.bolao_competicao_id DESC
            LIMIT 2
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        if (count($rows) === 0) {
            return new WP_Error('m360_bolao_nao_encontrado', 'Bolão não encontrado ou inativo.');
        }

        if (count($rows) > 1 && (int) $bolao_id <= 0 && $bolao_slug === '') {
            return new WP_Error(
                'm360_bolao_contexto_ambiguo',
                'Há mais de um bolão ativo para esta competição. Informe o atributo bolao no shortcode.'
            );
        }

        return $rows[0];
    }

    public static function is_visible_to_current_user($context) {
        $visibility = strtoupper((string) ($context->visibilidade ?? 'PUBLICO'));

        if ($visibility === 'PUBLICO') {
            return true;
        }

        return $visibility === 'ADMIN' && current_user_can('manage_options');
    }

    public static function ensure_participant($pdo, $bolao_id, $usuario_bolao_id, $wp_user_id) {
        if (!class_exists('Mengao360_Bolao_Schema')
            || !Mengao360_Bolao_Schema::table_exists($pdo, 'bolao_participantes')) {
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO bolao_participantes
                (bolao_competicao_id, usuario_bolao_id, papel, status, criado_por_wp_user_id)
             VALUES (?, ?, 'PARTICIPANTE', 'ATIVO', ?)
             ON DUPLICATE KEY UPDATE
                status = 'ATIVO',
                dth_saida = NULL"
        );
        $stmt->execute([(int) $bolao_id, (int) $usuario_bolao_id, (int) $wp_user_id]);
    }
}
