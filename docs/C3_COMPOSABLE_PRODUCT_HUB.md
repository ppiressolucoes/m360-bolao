# Sprint Comercial C.3 — Composable Product Hub

## Objetivo

Transformar a landing do Mega Bolão 360 em uma composição editável no WordPress,
compatível com o widget Shortcode do Elementor e com blocos Shortcode do editor.
O shortcode completo permanece retrocompatível.

## Componentes

    [m360_hub_hero idioma="pt-BR"]
    [m360_hub_menu idioma="pt-BR"]
    [m360_hub_overview idioma="pt-BR"]
    [m360_hub_competitions idioma="pt-BR"]
    [m360_hub_benefits idioma="pt-BR"]
    [m360_hub_plans idioma="pt-BR"]
    [m360_hub_how idioma="pt-BR"]
    [m360_hub_trust idioma="pt-BR"]
    [m360_hub_faq idioma="pt-BR"]
    [m360_hub_cta idioma="pt-BR"]

O shortcode `[mega_bolao_360_home]` continua renderizando toda a experiência.

## Planos nesta fundação

Os cards Free, Jogador e Dirigente são apresentação comercial, não entitlement.
Os limites numéricos ainda não constam no Roadmap e não são inventados pelo
plugin. URLs dos CTAs são configuráveis por atributos `free_url`, `jogador_url`,
`dirigente_url` e `cta_url`.

## Fronteiras

- Jogos, times, horários e resultados continuam somente leitura no DW/ETL.
- Ordem, textos adicionais, imagens e vídeos são controlados pela página WordPress.
- Limites reais, propriedade, assinatura e pagamentos exigem sprint própria.
- Toda autorização futura será validada no servidor, nunca apenas no front-end.
## Menu editável

O plugin registra a localização `Mega Bolão 360 — Product Hub`. Em Aparência >
Menus, o portal pode associar um menu PT-BR ou EN-US. Sem menu associado, o
componente usa as âncoras padrão como fallback seguro.

## Exemplo de composição Elementor

1. widget Shortcode com `[m360_hub_hero idioma="pt-BR"]`;
2. widget Shortcode com `[m360_hub_menu idioma="pt-BR"]`;
3. elementos editoriais livres do Elementor;
4. widgets Shortcode das seções desejadas, em qualquer ordem;
5. planos com URLs próprias:

    [m360_hub_plans idioma="pt-BR" free_url="/cadastro/" jogador_url="/lista-de-interesse/" dirigente_url="/contato/"]