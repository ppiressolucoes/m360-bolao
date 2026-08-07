# Sprint Comercial C.2 — Mega Bolão 360 Product Hub

## Objetivo

Criar a página principal do produto comercial Mega Bolão 360 com catálogo
dinâmico das competições assistidas pelo DW Esportivo e dos bolões administrados
pelo portal.

## Fronteiras

- Mega Bolão 360 permanece independente do M360 Core editorial.
- DW/ETL é somente leitura e fonte de verdade dos fatos esportivos.
- WordPress controla apenas estado, visibilidade, conteúdo comercial e páginas.
- Não inclui autoatendimento, planos, pagamentos ou edição manual de jogos.

## Entrega inicial

Shortcode:

    [mega_bolao_360_home idioma="pt-BR"]
    [mega_bolao_360_home idioma="en-US"]

Catálogo padrão:

- Copa do Mundo FIFA 2026;
- Brasileirão Série A 2026;
- Conmebol Libertadores 2026;
- Copa do Brasil 2026.

Estados comerciais:

- ABERTO: CTA para participação;
- ENCERRADO: CTA para resultados e ranking;
- BLOQUEADO: indisponibilidade temporária;
- EM_BREVE: competição elegível sem bolão público.

## Critérios de aceite

1. PT-BR e EN-US.
2. Cards lidos do DW e de bolao_competicoes.
3. CTA somente para bolão público aberto ou encerrado.
4. Links resolvidos a partir das páginas que contêm o shortcode do bolão.
5. Nenhuma escrita em dim_* ou fato_*.
6. Layout responsivo, acessível e isolado do widget existente.
7. Homologação controlada em produção e rollback pelo pacote anterior.

## Catálogo evolutivo — 03/08/2026

A seleção do Product Hub segue duas regras:

1. todo bolão ativo administrado pelo portal é incluído, sem depender de um slug rígido;
2. competições futuras só aparecem quando já existem no DW com modelo suportado e jogos em `fato_jogos`.

Códigos de fonte preparados para expansão: `CL`, `BL1`, `DED`, `PD`, `FL1`,
`ELC`, `PPL`, `EC`, `SA` e `PL`. A cobertura da API não autoriza publicação por
si só: cadastro, temporada, carga ETL e gate operacional do DW continuam obrigatórios.
## Evolução visual — versão 0.2.2

- contêiner horizontal limitado a 1200 px;
- menu interno responsivo com âncoras para as seções do produto;
- widgets consolidados de bolões, competições e calendário do DW;
- blocos comerciais de palpites, rankings, ligas e idiomas;
- FAQ ampliado para persistência, rankings e expansão do catálogo;
- componentes continuam renderizados pelo shortcode e isolados do tema.