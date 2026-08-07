# Arquitetura multi-competição

## Princípios

1. O DW/ETL é a única autoridade esportiva.
2. O WordPress administra bolões, não jogos.
3. Toda consulta funcional é filtrada por bolão, competição e temporada.
4. Palpites são bloqueados no cliente e novamente no servidor.
5. Sincronização, apuração e ranking são idempotentes.
6. Participantes e rankings não atravessam bolões.
7. O baseline da Copa continua migrável e reversível.
8. O Mega Bolão 360 é um plugin esportivo independente do plugin editorial
   M360 Core, sem dependência de runtime em qualquer direção.

## Limite entre plugins

```text
WordPress
├── M360 Core
│   └── domínio editorial
└── Mega Bolão 360
    └── domínio esportivo + integração de leitura com DW/ETL
```

O código do Mega Bolão 360 permanece exclusivamente no repositório
`ppiressolucoes/m360-bolao`, com bootstrap, namespace/prefixos, migrations,
assets, administração, releases e pacote ZIP próprios. Nenhum arquivo do Bolão
será incorporado ao repositório ou ao pacote do M360 Core.

Da mesma forma, o Mega Bolão 360 não inclui nem inicializa arquivos internos do
M360 Core. A coexistência no portal não constitui dependência. Uma integração
futura, se necessária, deverá usar contrato público, opcional e versionado,
com funcionamento degradável quando o outro plugin estiver ausente.

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
├── bolao_resultados_overrides
├── bolao_pontuacao
├── bolao_ranking
├── bolao_ligas
├── bolao_liga_participantes
├── bolao_sincronizacoes
└── bolao_auditoria
```

O nome físico legado `bolao_competicoes` será preservado na primeira migração
para reduzir risco. No domínio PHP, cada linha será tratada como um `Bolao`.

## Intervenção manual por atraso da API

O painel operacional é necessário quando o resultado já é oficial, mas a API
ainda não atualizou `fato_jogos`. A evolução preservará essa capacidade como
override de publicação:

```text
Resultado oficial confirmado
            ↓
Operador registra override temporário
            ↓
bolao_resultados_overrides + auditoria
            ↓
view/publicador de resultado efetivo
            ↓
portal + apuração assistida
            ↓
ETL recebe o resultado oficial
            ↓
conciliação automática encerra o override
```

Regras:

- o operador não edita times, calendário ou status estrutural do jogo;
- o override exige `manage_options`, nonce, justificativa e referência da
  fonte oficial;
- o estado anterior do DW é armazenado por hash;
- o override possui expiração e não pode permanecer indefinidamente ativo;
- divergência entre override e API gera alerta crítico e bloqueia a
  finalização automática;
- coincidência com a API encerra o override como `CONCILIADO`;
- revogação nunca apaga a auditoria;
- `fato_jogos` não é atualizado diretamente pelo WordPress;
- uma eventual promoção ao DW é responsabilidade de workflow ETL autorizado,
  transacional e auditado.

Para compatibilidade operacional, a primeira homologação deve comparar o
resultado efetivo exibido pelo portal com o painel legado antes de desligar o
write path direto.

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

A transição para `ABERTO` é protegida por um gate recalculado no servidor. O
gate exige competição/modelo ativos, calendário futuro, ao menos um confronto
liberável, janela válida, status de palpite e procedure pós-ETL disponíveis.
Confrontos futuros ainda sem times são reportados e permanecem bloqueados, sem
impedir outros jogos válidos da mesma competição.

Na homologação controlada, a visibilidade deve ser `ADMIN`. Nesse modo, o
estado pode ser `ABERTO` para testar toda a operação com administradores, mas
visitantes não renderizam o widget nem acessam palpites ou ligas via AJAX. A
mudança para `PUBLICO` é uma ação posterior, separada e confirmada.

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
A validação final e a gravação ocorrem na mesma transação, com bloqueio do
bolão e do jogo consultados, reduzindo corridas com mudanças de estado e ETL.

## Sincronização pós-ETL

O sincronizador:

1. recebe ou gera uma chave idempotente;
2. adquire lock por bolão;
3. lê os jogos elegíveis no DW;
4. deriva resultados do Bolão e aplica override temporário válido, sem
   escrever em `fato_jogos`;
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
7. migração do resultado manual para override temporário e conciliável;
8. homologação em bolão não destacado;
9. rollback testado antes da publicação.
