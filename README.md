# Mengão Bolão 360

Plugin WordPress do Mega Bolão 360.

## Identidade e independência

O **Mega Bolão 360** é um plugin esportivo independente, responsável pelo
domínio de competições, bolões, participantes, palpites, apuração e ranking.
Ele não é módulo, componente ou extensão do **M360 Core**.

O **M360 Core** permanece como plugin editorial independente. Os dois plugins:

- possuem repositórios, versionamento, pacotes ZIP e ciclos de publicação próprios;
- podem coexistir na mesma instalação WordPress sem dependência de runtime;
- não carregam arquivos, classes, migrations ou assets um do outro;
- mantêm painéis administrativos e responsabilidades separados;
- só podem interoperar futuramente por contratos públicos, opcionais e
  versionados, nunca por incorporação de código.

Status atual: baseline de produção recuperado e fundação multi-competição C.1
implementada em branch de homologação. Nenhuma release foi numerada.

Escopo oficial:
[Sprint Comercial C.1 — Mega Bolão 360 Multi-Competition Foundation](https://github.com/ppiressolucoes/M360-Core/issues/24).

Esta frente não possui número de release. A versão será definida somente após
a homologação da migração sobre o baseline real.

## Baseline recuperado

- cabeçalho WordPress: `0.1.0`;
- versão interna dos assets: `0.1.4`;
- commit local de importação: `5c7e4e4`;
- tag: `baseline-production-0.1.0-assets-0.1.4`;
- origem: ZIP instalado em produção em 2026-07-28.

O baseline deve permanecer reproduzível. Alterações funcionais pertencem a
commits posteriores à tag.

## Dependências atuais

- WordPress;
- usuário autenticado para palpites e ligas;
- função global `conectar_dw_esportes_m360()`;
- schema MariaDB do DW Esportivo;
- tabelas `bolao_*`;
- views `vw_bolao_*`;
- procedures `sp_bolao_*`;
- tabelas esportivas `dim_competicoes`, `dim_times` e `fato_jogos`;
- catálogo `m360_i18n_publico`.

O plugin não migra o schema durante a ativação. A migração C.1 é idempotente,
bloqueada por padrão e só pode ser iniciada por administrador durante uma
janela controlada.

## Contratos públicos atuais

- shortcode legado: `[bolao_mengao competicao="fifa-world-cup"]`;
- shortcode explícito multi-competição:
  `[bolao_mengao bolao="slug-do-bolao" idioma="pt-BR"]`;
- AJAX autenticado:
  - `m360_salvar_palpite`;
  - `m360_criar_liga`;
  - `m360_entrar_liga`;
- nonce: `m360_bolao_nonce`;
- administração: capability `manage_options`.

## Regra arquitetural obrigatória

O DW/ETL é a única fonte de verdade para times, jogos, horários, status e
resultados esportivos. A evolução do plugin não poderá executar `INSERT`,
`UPDATE` ou `DELETE` em tabelas `dim_*` ou `fato_*`.

Leia:

- [Baseline recuperado](docs/BASELINE.md);
- [Schema atual](docs/SCHEMA_BASELINE.md);
- [Arquitetura multi-competição](docs/ARCHITECTURE.md).
- [Fundação C.1 e homologação](docs/C1_FOUNDATION.md).

## Empacotamento

O ZIP de homologação deve conter uma única pasta raiz estável:

```text
mengao360-bolao/
├── assets/
├── includes/
├── templates/
└── mengao360-bolao.php
```

Arquivos de discovery, dumps, credenciais e dados de produção nunca entram no
pacote WordPress.
