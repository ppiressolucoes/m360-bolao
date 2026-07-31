# Sprint Comercial C.1 — Fundação multi-competição

Esta entrega permanece sem número de release até a homologação do baseline
real. Todo o runtime pertence exclusivamente ao plugin Mega Bolão 360.

## Componentes implementados

- resolução explícita de bolão por ID ou `slug_bolao`;
- fallback por slug de competição somente quando o resultado é inequívoco;
- isolamento de palpites, rankings, dashboard e ligas por
  `bolao_competicao_id`;
- estados `RASCUNHO`, `ABERTO`, `BLOQUEADO`, `EM_APURACAO`, `ENCERRADO` e
  `ARQUIVADO`;
- catálogo administrativo somente leitura a partir de `dim_competicoes`,
  `dim_competicao_modelo` e `fato_jogos`;
- criação administrativa de bolões como rascunho;
- janela de fechamento configurável por bolão;
- guard único de palpites no servidor;
- participantes isolados por bolão;
- overrides temporários e auditáveis para atraso da API;
- trilha de migrações, auditoria e sincronizações.

## Limite de escrita

O código do plugin pode escrever somente no domínio `bolao_*`. Não existem
comandos `INSERT`, `UPDATE` ou `DELETE` contra `dim_*` ou `fato_*`.

O painel operacional legado foi adaptado: um resultado manual exige referência
oficial, justificativa e expiração. Ele grava
`bolao_resultados_overrides`, `bolao_resultados_partidas` e
`bolao_auditoria`, sem alterar `fato_jogos`.

## Migração controlada

1. validar backup e restore do banco;
2. homologar com uma cópia do schema de produção;
3. definir temporariamente:

   ```php
   define('MENGAO360_BOLAO_ALLOW_SCHEMA_MIGRATIONS', true);
   ```

4. acessar **Mega Bolão 360 → Gerenciar Bolões**;
5. executar o preflight;
6. classificar explicitamente cada bolão legado ativo sem data de fechamento;
7. para o protótipo da Copa do Mundo FIFA 2026, selecionar `ENCERRADO`;
8. confirmar o backup e aplicar a migração C.1;
9. remover ou definir a constante como `false`;
10. validar o bolão legado da Copa;
11. criar um bolão de homologação como rascunho;
12. publicar somente depois dos testes de isolamento e bloqueio.

## Pré-homologação controlada em produção

Na ausência de staging, o plugin pode ser instalado primeiro com a migração
bloqueada. O build operacional é identificado pela constante
`MENGAO360_BOLAO_BUILD`, sem atribuir número de release à frente C.1.

Após a atualização, acessar **Mega Bolão 360 → Pré-homologação**. Essa tela:

- executa somente `SELECT`, consultas em `information_schema` e leitura de
  atributos da conexão;
- não registra handler de escrita;
- não executa DDL;
- não altera opções do WordPress;
- confirma tabelas, procedure pós-ETL e compatibilidade dos dados;
- registra contagens para comparação antes/depois;
- exibe o estado legado do bolão da Copa;
- verifica o catálogo PT-BR/EN-US;
- bloqueia o gate quando a constante de migração estiver habilitada fora da
  janela controlada.

O resultado esperado antes do backup é
`PRONTO PARA PRÉ-HOMOLOGAÇÃO`. A constante
`MENGAO360_BOLAO_ALLOW_SCHEMA_MIGRATIONS` deve permanecer ausente ou `false`.

O widget público continua resolvendo o idioma nesta ordem:

1. atributo `idioma` do shortcode;
2. parâmetro `?lang=`;
3. prefixo público `/en/` ou `/es/`;
4. locale resolvido pelo WordPress;
5. helper do portal;
6. fallback `pt-BR`.

O JavaScript usa prioritariamente o `data-lang` do próprio widget. Isso evita
que mensagens AJAX ou interativas recebam o idioma global de outra página.

A migração:

- cria as tabelas da fundação;
- converte o vínculo de competição para `BIGINT UNSIGNED`;
- converte temporada para `VARCHAR(20)`;
- exige decisão administrativa explícita para bolões ativos sem data de
  fechamento e falha fechado se essa decisão estiver ausente;
- usa `BLOQUEADO` como fallback conservador, sem reabrir palpites
  automaticamente;
- faz backfill de participantes a partir de palpites, ligas e rankings;
- remove a restrição que impedia bolões paralelos da mesma
  competição/temporada;
- registra a aplicação em `bolao_schema_migrations`.

## Casos mínimos de homologação

1. O shortcode com `bolao` inexistente falha fechado.
2. O fallback por competição falha quando há dois bolões ativos.
3. Palpites de dois bolões da mesma competição não se misturam.
4. Ranking, dashboard e ligas permanecem isolados.
5. Jogo com time nulo, placeholder `9999` ou times iguais é bloqueado.
6. Jogo iniciado, encerrado, suspenso, cancelado ou adiado é bloqueado.
7. A janela configurada é respeitada no front-end e no AJAX.
8. Bolão fora de `ABERTO` não aceita palpite, criação ou entrada em liga.
9. Override manual expira e deixa de ser publicado.
10. Nenhuma ação do WordPress altera `dim_*` ou `fato_*`.

## Shortcode

Forma recomendada:

```text
[bolao_mengao bolao="brasileirao-2026" idioma="pt-BR"]
```

O atributo `competicao` continua disponível para compatibilidade com a Copa,
mas só é aceito quando identifica exatamente um bolão ativo.
