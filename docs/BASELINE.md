# Baseline de produção

Data da recuperação: 2026-07-28

## Proveniência

Arquivo recebido: `mengao360-bolao-V0.1.0.zip`

SHA-256:

```text
727D5B1D0881F6DD54B26B05BA5BB25E518E2653A1EC6D497565755429305D67
```

O ZIP possuía 11 arquivos, pasta raiz `mengao360-bolao/` e nenhuma entrada
absoluta ou com travessia de diretório.

Nenhum padrão óbvio de senha, token, chave de API ou chave privada foi
encontrado.

## Versionamento

O arquivo principal possui versões divergentes:

```text
Plugin header: 0.1.0
MENGAO360_BOLAO_VERSION: 0.1.4
```

A constante interna explica os assets públicos carregados com `?ver=0.1.4`.
Até uma release posterior corrigir o cabeçalho, esta origem é identificada
como `0.1.0+assets.0.1.4`.

Referências locais:

- commit de importação: `5c7e4e4`;
- tag anotada: `baseline-production-0.1.0-assets-0.1.4`.

## Compatibilidade

O baseline não declara:

- `Requires at least`;
- `Requires PHP`;
- `Text Domain`;
- migration version;
- activation hook;
- uninstall hook.

As versões mínimas de WordPress, PHP e MariaDB precisam ser definidas durante
a homologação, não inferidas silenciosamente.

## Operação manual legada

O painel administrativo atual permite salvar resultado manual e, quando o
status aceita apuração, executa:

```sql
UPDATE fato_jogos
SET status_jogo = 'FINISHED',
    placar_mandante = ?,
    placar_visitante = ?
WHERE id = ?;
```

Essa operação atende uma necessidade real: refletir temporariamente no portal
um resultado oficial quando a API está atrasada.

A evolução multi-competição deve preservar a capacidade operacional, mas
substituir o `UPDATE fato_jogos` direto por um override temporário, com origem,
evidência, operador, motivo, expiração e reconciliação automática.

O fato oficial continua pertencendo ao ETL. Quando a API entregar o resultado,
o override é conciliado, encerrado e mantido apenas como auditoria.

## Limitações de validação local

O runtime PHP não estava disponível no workspace durante a recuperação.
Consequentemente, o baseline ainda precisa de lint e testes em uma matriz
homologada de PHP/WordPress.
