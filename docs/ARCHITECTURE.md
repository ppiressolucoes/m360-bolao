# Arquitetura multi-competição

## Princípios

1. O DW/ETL é a única autoridade esportiva.
2. O WordPress administra bolões, não jogos.
3. Toda consulta funcional é filtrada por bolão, competição e temporada.
4. Palpites são bloqueados no cliente e novamente no servidor.
5. Sincronização, apuração e ranking são idempotentes.
6. Participantes e rankings não atravessam bolões.
7. O baseline da Copa continua migrável e reversível.

## Fronteiras de dados

```text
DW esportivo — somente leitura
├── dim_competicoes
├── dim_competicao_*
├── dim_times
├── fato_jogos
└── vw_frontend_*

Domínio do Bolão — leitura e escrita controlada
├── bolao_competicoes
├── bolao_participantes
├── bolao_usuarios
├── bolao_palpites
├── bolao_resultados_partidas
├── bolao_pontuacao
├── bolao_ranking
├── bolao_ligas
├── bolao_liga_participantes
├── bolao_sincronizacoes
└── bolao_auditoria
```

O nome físico legado `bolao_competicoes` será preservado na primeira migração
para reduzir risco. No domínio PHP, cada linha será tratada como um `Bolao`.

## Registro de competições elegíveis

Uma competição/temporada só pode aparecer no seletor administrativo quando:

- `dim_competicoes.ativo = 1`;
- possui modelo ativo;
- possui ao menos um jogo em `fato_jogos`;
- os jogos possuem `data_jogo` e `status_jogo`;
- o modelo é suportado:
  - pontos corridos;
  - grupos + mata-mata;
  - mata-mata;
- o calendário não contém inconsistência impeditiva.

Ter confrontos futuros ainda indefinidos não torna toda a competição
inelegível. Apenas esses jogos ficam bloqueados até a definição oficial.

## Estados do bolão

```text
RASCUNHO → ABERTO → BLOQUEADO → EM_APURACAO → ENCERRADO → ARQUIVADO
```

Transições administrativas são explícitas, validadas por capability e
registradas em auditoria. Não existe retorno implícito de `ARQUIVADO`.

## Política de palpites

Um palpite só pode ser gravado quando:

- o bolão está `ABERTO`;
- o usuário é participante ativo;
- o jogo pertence à competição e temporada do bolão;
- mandante e visitante são conhecidos;
- nenhum time usa o placeholder `9999`;
- os times são diferentes;
- o status ainda não indica início, encerramento, cancelamento, suspensão ou
  adiamento impeditivo;
- o horário atual é anterior à janela de bloqueio configurada.

O endpoint AJAX é a autoridade final. Estados visuais não concedem permissão.

## Sincronização pós-ETL

O sincronizador:

1. recebe ou gera uma chave idempotente;
2. adquire lock por bolão;
3. lê os jogos elegíveis no DW;
4. deriva resultados do Bolão sem escrever em `fato_jogos`;
5. apura apenas resultados novos ou alterados;
6. recalcula os rankings afetados;
7. registra contagens, hashes e erros;
8. libera o lock.

Reexecutar a mesma chave com o mesmo estado do DW não pode duplicar pontuação,
ranking, notificações ou auditoria.

## Camadas PHP propostas

```text
Domain/
├── Bolao
├── BolaoStatus
├── CompetitionModel
└── PredictionPolicy

Application/
├── CreateBolao
├── ChangeBolaoStatus
├── SavePrediction
└── SyncAfterEtl

Infrastructure/
├── DwReadRepository
├── BolaoRepository
├── AuditRepository
└── LockRepository

Presentation/
├── Admin
├── Ajax
└── Shortcodes
```

`DwReadRepository` expõe somente SELECT. Qualquer tentativa de mutação em
`dim_*` ou `fato_*` deve falhar em testes e, idealmente, também por grants do
usuário de banco.

## Migração segura

1. backup do ZIP e banco;
2. preflight de duplicidades e tipos;
3. criação das novas tabelas sem remover estruturas legadas;
4. backfill de participantes a partir de palpites, ligas e rankings;
5. remoção da unicidade competição/temporada;
6. introdução dos novos repositórios PHP;
7. desativação do resultado manual;
8. homologação em bolão não destacado;
9. rollback testado antes da publicação.

