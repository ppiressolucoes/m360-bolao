# Gate de abertura controlada C.1

Este gate permite homologar um bolão em produção sem exposição pública.

## Fase 1 — diagnóstico sem abertura

1. Instalar o pacote e manter o bolão em `RASCUNHO`.
2. Em **Mega Bolão 360 → Gerenciar Bolões**, alterar a visibilidade do novo
   bolão de `PUBLICO` para `ADMIN`.
3. Atualizar a página e registrar o relatório **Gate de abertura controlada**.
4. Todos os itens bloqueantes devem estar `OK`. Itens `AVISO` sobre confrontos
   ainda indefinidos são aceitáveis: apenas esses jogos continuarão fechados.
5. Não marcar a confirmação de abertura nesta fase.

## Fase 2 — abertura restrita

1. Exportar backup e registrar as contagens de referência.
2. Selecionar `ABERTO`, marcar a confirmação do gate e aplicar.
3. Confirmar que a visibilidade continua `ADMIN`.
4. Como administrador, validar PT-BR e EN-US:
   - formulário somente em jogos futuros, agendados e com os dois times;
   - fechamento no horário configurado;
   - jogos iniciados/finalizados bloqueados;
   - confrontos sem os dois times bloqueados;
   - criação e entrada em ligas isoladas pelo bolão;
   - respostas AJAX no idioma da página.
5. Em janela combinada, executar **Sincronizar pós-ETL** e revisar o registro
   idempotente e o resumo da conciliação.
6. Em sessão anônima, confirmar que o widget restrito não renderiza.

## Fase 3 — publicação

Somente após a aprovação das fases anteriores, marcar **Confirmo a publicação
para visitantes** e alterar a visibilidade para `PUBLICO`. A qualquer momento,
**Restringir a administradores** retira novamente a exposição pública sem
alterar fatos esportivos do DW.

## Rollback operacional

- Para interromper palpites: alterar o estado para `BLOQUEADO`.
- Para retirar exposição: alterar a visibilidade para `ADMIN`.
- Não editar jogos, horários, times, status ou resultados no WordPress.
