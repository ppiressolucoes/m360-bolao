# Schema baseline do Mega Bolão 360

Fonte: exportação `Structure Only` de 2026-07-28.

O dump não contém linhas de usuários, palpites ou outros dados de produção.
Os `INSERT` encontrados pertencem ao corpo das stored procedures.

## Objetos do domínio

| Objeto | Responsabilidade |
|---|---|
| `bolao_competicoes` | Configuração do bolão vinculado à competição/temporada |
| `bolao_usuarios` | Identidade do participante associada ao WordPress/Google |
| `bolao_palpites` | Palpite por bolão, usuário e jogo |
| `bolao_resultados_partidas` | Resultado derivado usado na apuração |
| `bolao_pontuacao` | Pontuação calculada por palpite |
| `bolao_ranking` | Materialização de rankings por escopo |
| `bolao_ligas` | Liga vinculada ao bolão |
| `bolao_liga_participantes` | Associação usuário/liga |
| `bolao_notificacoes` | Fila de notificações derivadas |
| `bolao_log_operacional` | Auditoria operacional |
| `bolao_regras_pontuacao` | Regra de pontuação |
| `bolao_regras_creditos` | Regra legada de créditos |
| `bolao_creditos` | Movimento legado de créditos |

## Fonte esportiva somente leitura

- `dim_competicoes`;
- `dim_competicao_modelo`;
- `dim_competicao_modelo_fases`;
- `dim_competicao_fases`;
- `dim_competicao_fase_jogo`;
- `dim_times`;
- `fato_jogos`;
- views `vw_frontend_*`.

## Views do Bolão

- `vw_bolao_cards_pos_jogo`;
- `vw_bolao_ranking_geral`;
- `vw_bolao_ranking_por_jogo`;
- `vw_bolao_resumo_jogo`.

## Procedures do Bolão

- `sp_bolao_apurar_jogo`;
- `sp_bolao_atualizar_rankings_jogos_apurados`;
- `sp_bolao_atualizar_rankings_jogos_sem_ranking`;
- `sp_bolao_atualizar_ranking_dia`;
- `sp_bolao_atualizar_ranking_geral`;
- `sp_bolao_atualizar_ranking_jogo`;
- `sp_bolao_atualizar_ranking_liga`;
- `sp_bolao_conciliar_resultados_api_manual`;
- `sp_bolao_gerar_notificacoes_pos_jogo`;
- `sp_bolao_processar_jogos_finalizados_api`;
- `sp_bolao_processar_jogos_finalizados_api_por_competicao_dw`;
- `sp_bolao_registrar_log_operacional`.

## Integridade já existente

O schema já isola:

- palpites por `bolao_competicao_id + usuario_bolao_id + jogo_id`;
- resultados por `bolao_competicao_id + jogo_id`;
- ligas por `bolao_competicao_id`;
- pontuação e ranking por `bolao_competicao_id`.

## Lacunas para multi-competição

### Vários bolões na mesma competição

A constraint atual:

```text
uk_bolao_competicao_temporada (competicao_id, temporada)
```

permite somente um bolão por competição/temporada. Ela precisa ser substituída
por índice não único. `slug_bolao` permanece globalmente único.

### Participação

Não existe tabela explícita de participantes por bolão. Usuários e
participantes de ligas existem, mas a participação no bolão é inferida por
palpites/ranking.

A evolução deve criar `bolao_participantes` com unicidade por
`bolao_competicao_id + usuario_bolao_id`.

### Ranking

`uk_bolao_ranking_escopo` contém colunas anuláveis e não inclui `jogo_id`.
MariaDB permite múltiplas combinações únicas quando existe `NULL`, portanto a
constraint não garante todos os escopos materializados.

A migração deverá criar uma chave de escopo determinística após auditar e
deduplicar os dados existentes.

### Tipos incompatíveis

As tabelas `bolao_*` usam vários identificadores `INT`, enquanto as dimensões
e fatos esportivos usam `BIGINT UNSIGNED`. A migração deve alinhar os tipos
antes de criar FKs adicionais.

### Resultados manuais e delay da API

`bolao_resultados_partidas.fonte_resultado` ainda aceita `ADMIN_MANUAL`.
O painel utiliza esse caminho quando um resultado oficial ainda não foi
entregue pela API em tempo para publicação.

Valores legados devem ser preservados para auditoria. A evolução continuará
aceitando intervenção manual controlada, porém sem promover a entrada do
WordPress diretamente a fato definitivo em `fato_jogos`.

O schema alvo deve possuir uma camada de override temporário com:

- jogo e bolão/competição;
- placar provisório de publicação;
- fonte oficial consultada;
- justificativa e evidência;
- operador WordPress;
- data de criação e expiração;
- status `ATIVO`, `CONCILIADO`, `REVOGADO` ou `EXPIRADO`;
- hash do estado observado no DW;
- data e resultado da reconciliação.
