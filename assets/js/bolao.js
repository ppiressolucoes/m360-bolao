(function ($) {
    'use strict';

    const M360_BOLAO_DICT = {
        'pt-BR': {
            informe_dois_placares: 'Informe os dois placares antes de salvar.',
            informe_placares_validos: 'Informe placares válidos entre 0 e 20.',
            salvando: 'Salvando...',
            salvo: 'Salvo ✓',
            salvar_palpite: 'Salvar palpite',
            palpite_salvo: 'Palpite salvo com sucesso!',
            erro_salvar_palpite: 'Erro ao salvar palpite.',
            falha_comunicacao: 'Falha de comunicação com o servidor.',

            informe_nome_liga: 'Informe um nome de liga com pelo menos 3 caracteres.',
            criando: 'Criando...',
            liga_criada: 'Liga criada ✓',
            liga_criada_msg: 'Liga "{nome}" criada com sucesso! Código: {codigo}',
            criar_liga: 'Criar liga',
            erro_criar_liga: 'Erro ao criar liga.',

            informe_codigo_liga: 'Informe um código de convite válido.',
            entrando: 'Entrando...',
            entrou: 'Entrou ✓',
            entrar: 'Entrar',
            entrou_liga: 'Você entrou na liga com sucesso!',
            erro_entrar_liga: 'Erro ao entrar na liga.'
        },
        'en-US': {
            informe_dois_placares: 'Enter both scores before saving.',
            informe_placares_validos: 'Enter valid scores from 0 to 20.',
            salvando: 'Saving...',
            salvo: 'Saved ✓',
            salvar_palpite: 'Save prediction',
            palpite_salvo: 'Prediction saved successfully!',
            erro_salvar_palpite: 'Error saving prediction.',
            falha_comunicacao: 'Communication failure with the server.',

            informe_nome_liga: 'Enter a league name with at least 3 characters.',
            criando: 'Creating...',
            liga_criada: 'League created ✓',
            liga_criada_msg: 'League "{nome}" created successfully! Code: {codigo}',
            criar_liga: 'Create league',
            erro_criar_liga: 'Error creating league.',

            informe_codigo_liga: 'Enter a valid invitation code.',
            entrando: 'Joining...',
            entrou: 'Joined ✓',
            entrar: 'Join',
            entrou_liga: 'You joined the league successfully!',
            erro_entrar_liga: 'Error joining league.'
        },
        'es-ES': {
            informe_dois_placares: 'Introduce los dos marcadores antes de guardar.',
            informe_placares_validos: 'Introduce marcadores válidos entre 0 y 20.',
            salvando: 'Guardando...',
            salvo: 'Guardado ✓',
            salvar_palpite: 'Guardar pronóstico',
            palpite_salvo: '¡Pronóstico guardado con éxito!',
            erro_salvar_palpite: 'Error al guardar el pronóstico.',
            falha_comunicacao: 'Error de comunicación con el servidor.',

            informe_nome_liga: 'Introduce un nombre de liga con al menos 3 caracteres.',
            criando: 'Creando...',
            liga_criada: 'Liga creada ✓',
            liga_criada_msg: 'Liga "{nome}" creada con éxito. Código: {codigo}',
            criar_liga: 'Crear liga',
            erro_criar_liga: 'Error al crear la liga.',

            informe_codigo_liga: 'Introduce un código de invitación válido.',
            entrando: 'Entrando...',
            entrou: 'Entró ✓',
            entrar: 'Entrar',
            entrou_liga: '¡Entraste en la liga con éxito!',
            erro_entrar_liga: 'Error al entrar en la liga.'
        }
    };

    const M360_PLACAR_MAXIMO = 20;

    function getLang() {
        const allowed = ['pt-BR', 'en-US', 'es-ES'];

        if (window.m360Bolao && m360Bolao.lang && allowed.includes(m360Bolao.lang)) {
            return m360Bolao.lang;
        }

        if (window.M360_BOLAO_I18N && M360_BOLAO_I18N.lang && allowed.includes(M360_BOLAO_I18N.lang)) {
            return M360_BOLAO_I18N.lang;
        }

        const wrapperLang = $('.m360-bolao').first().data('lang');
        if (wrapperLang && allowed.includes(wrapperLang)) {
            return wrapperLang;
        }

        const urlLang = new URLSearchParams(window.location.search).get('lang');
        if (urlLang && allowed.includes(urlLang)) {
            return urlLang;
        }

        return 'pt-BR';
    }

    function t(chave, vars) {
        const lang = getLang();

        let texto =
            (window.M360_BOLAO_I18N && M360_BOLAO_I18N[chave]) ||
            (window.m360Bolao && m360Bolao.i18n && m360Bolao.i18n[chave]) ||
            (M360_BOLAO_DICT[lang] && M360_BOLAO_DICT[lang][chave]) ||
            (M360_BOLAO_DICT['pt-BR'] && M360_BOLAO_DICT['pt-BR'][chave]) ||
            chave;

        vars = vars || {};

        Object.keys(vars).forEach(function (key) {
            texto = texto.replaceAll('{' + key + '}', vars[key]);
        });

        return texto;
    }

    function getCompeticaoSlug() {
        return $('.m360-bolao').data('competicao') || '';
    }

    function getBolaoId() {
        return parseInt($('.m360-bolao').first().data('bolao-id'), 10) || 0;
    }

    function setFeedback(elemento, mensagem, tipo) {
        if (!elemento || elemento.length === 0) {
            return;
        }

        elemento
            .removeClass('sucesso erro')
            .addClass(tipo)
            .text(mensagem);
    }

    function teclaPermitidaPlacar(event) {
        const teclasControle = [
            'Backspace',
            'Delete',
            'Tab',
            'Escape',
            'Enter',
            'ArrowLeft',
            'ArrowRight',
            'ArrowUp',
            'ArrowDown',
            'Home',
            'End'
        ];

        if (teclasControle.includes(event.key)) {
            return true;
        }

        if ((event.ctrlKey || event.metaKey) && ['a', 'c', 'v', 'x'].includes(String(event.key).toLowerCase())) {
            return true;
        }

        if (!/^\d$/.test(event.key)) {
            return false;
        }

        const valorAtual = String(event.currentTarget.value || '').replace(/\D/g, '');

        if (valorAtual.length >= 2) {
            return false;
        }

        const proximoValor = Number(valorAtual + event.key);

        return proximoValor <= M360_PLACAR_MAXIMO;
    }

    function normalizarCampoPlacar(campo) {
        const input = $(campo);
        const valorOriginal = String(input.val() || '');
        let valor = valorOriginal.replace(/\D/g, '').slice(0, 2);

        if (valor !== '' && Number(valor) > M360_PLACAR_MAXIMO) {
            valor = String(M360_PLACAR_MAXIMO);
        }

        if (valor !== valorOriginal) {
            input.val(valor);
        }

        return valor;
    }

    function lerPlacarValido(campo) {
        const valor = normalizarCampoPlacar(campo);

        if (valor === '') {
            return null;
        }

        if (!/^\d{1,2}$/.test(valor)) {
            return null;
        }

        const numero = Number(valor);

        if (!Number.isInteger(numero) || numero < 0 || numero > M360_PLACAR_MAXIMO) {
            return null;
        }

        return numero;
    }

    function getLigaBox(botao) {
        let box = botao.closest('.m360-bolao-ligas-box');

        if (box.length === 0) {
            box = botao.closest('.m360-bolao-card');
        }

        return box;
    }

    $(document).on('keydown', '.m360-palpite-placar, .m360-palpite-mandante, .m360-palpite-visitante', function (event) {
        if (!teclaPermitidaPlacar(event)) {
            event.preventDefault();
        }
    });

    $(document).on('beforeinput', '.m360-palpite-placar, .m360-palpite-mandante, .m360-palpite-visitante', function (event) {
        const originalEvent = event.originalEvent || event;

        if (originalEvent.data && !/^\d+$/.test(originalEvent.data)) {
            event.preventDefault();
        }
    });

    $(document).on('paste', '.m360-palpite-placar, .m360-palpite-mandante, .m360-palpite-visitante', function (event) {
        event.preventDefault();

        const clipboardData = event.originalEvent && event.originalEvent.clipboardData;
        const colado = clipboardData ? clipboardData.getData('text') : '';
        const valor = String(colado || '').replace(/\D/g, '').slice(0, 2);

        $(this).val(valor);
        normalizarCampoPlacar(this);
    });

    $(document).on('input blur change', '.m360-palpite-placar, .m360-palpite-mandante, .m360-palpite-visitante', function () {
        const campo = this;

        window.setTimeout(function () {
            normalizarCampoPlacar(campo);
        }, 0);
    });

    /**
     * Salvar / atualizar palpite
     */
    $(document).on('click', '.m360-bolao-salvar-palpite', function (event) {
        event.preventDefault();

        const botao = $(this);
        const card = botao.closest('.m360-bolao-jogo');

        const jogoId = botao.data('jogo-id');
        const competicaoSlug = getCompeticaoSlug();

        const campoMandante = card.find('.m360-palpite-mandante');
        const campoVisitante = card.find('.m360-palpite-visitante');
        const placarMandante = lerPlacarValido(campoMandante);
        const placarVisitante = lerPlacarValido(campoVisitante);
        const feedback = card.find('.m360-bolao-feedback');

        if (placarMandante === null || placarVisitante === null) {
            alert(t('informe_dois_placares'));
            setFeedback(feedback, t('informe_placares_validos'), 'erro');
            return;
        }

        botao.prop('disabled', true).text(t('salvando'));

        $.post(m360Bolao.ajaxUrl, {
            action: 'm360_salvar_palpite',
            nonce: m360Bolao.nonce,
            idioma: getLang(),
            bolao_id: getBolaoId(),
            jogo_id: jogoId,
            competicao_slug: competicaoSlug,
            placar_mandante: placarMandante,
            placar_visitante: placarVisitante
        }).done(function (response) {
            if (response.success) {
                botao.text(t('salvo'));
                setFeedback(feedback, response.data.mensagem || t('palpite_salvo'), 'sucesso');
            } else {
                botao.prop('disabled', false).text(t('salvar_palpite'));
                setFeedback(feedback, response.data.mensagem || t('erro_salvar_palpite'), 'erro');
            }
        }).fail(function () {
            botao.prop('disabled', false).text(t('salvar_palpite'));
            setFeedback(feedback, t('falha_comunicacao'), 'erro');
        });
    });

    /**
     * Criar liga privada
     */
    $(document).on('click', '.m360-bolao-criar-liga', function (event) {
        event.preventDefault();

        const botao = $(this);
        const box = getLigaBox(botao);

        const nomeLiga = box.find('.m360-liga-nome, .m360-bolao-nome-liga').val();
        const competicaoSlug = getCompeticaoSlug();
        const feedback = box.find('.m360-bolao-liga-feedback, .m360-bolao-feedback').first();

        if (!nomeLiga || nomeLiga.trim().length < 3) {
            setFeedback(feedback, t('informe_nome_liga'), 'erro');
            return;
        }

        botao.prop('disabled', true).text(t('criando'));

        $.post(m360Bolao.ajaxUrl, {
            action: 'm360_criar_liga',
            nonce: m360Bolao.nonce,
            idioma: getLang(),
            bolao_id: getBolaoId(),
            competicao_slug: competicaoSlug,
            nome_liga: nomeLiga
        }).done(function (response) {
            if (response.success) {
                const codigo = response.data.codigo_convite || '';
                const nome = response.data.nome || nomeLiga;

                botao.text(t('liga_criada'));
                setFeedback(
                    feedback,
                    response.data.mensagem || t('liga_criada_msg', { nome: nome, codigo: codigo }),
                    'sucesso'
                );

                box.find('.m360-liga-nome, .m360-bolao-nome-liga').val('');

                if (codigo) {
                    box.find('.m360-bolao-codigo-gerado')
                        .removeClass('m360-oculto')
                        .find('strong')
                        .text(codigo);
                }
            } else {
                botao.prop('disabled', false).text(t('criar_liga'));
                setFeedback(feedback, response.data.mensagem || t('erro_criar_liga'), 'erro');
            }
        }).fail(function () {
            botao.prop('disabled', false).text(t('criar_liga'));
            setFeedback(feedback, t('falha_comunicacao'), 'erro');
        });
    });

    /**
     * Entrar em liga por código
     */
    $(document).on('click', '.m360-bolao-entrar-liga', function (event) {
        event.preventDefault();

        const botao = $(this);
        const box = getLigaBox(botao);

        const codigoConvite = box.find('.m360-liga-codigo, .m360-bolao-codigo-liga').val();
        const competicaoSlug = getCompeticaoSlug();
        const feedback = box.find('.m360-bolao-liga-feedback, .m360-bolao-feedback').first();

        if (!codigoConvite || codigoConvite.trim().length < 4) {
            setFeedback(feedback, t('informe_codigo_liga'), 'erro');
            return;
        }

        botao.prop('disabled', true).text(t('entrando'));

        $.post(m360Bolao.ajaxUrl, {
            action: 'm360_entrar_liga',
            nonce: m360Bolao.nonce,
            idioma: getLang(),
            bolao_id: getBolaoId(),
            competicao_slug: competicaoSlug,
            codigo_convite: codigoConvite
        }).done(function (response) {
            if (response.success) {
                botao.text(t('entrou'));
                setFeedback(feedback, response.data.mensagem || t('entrou_liga'), 'sucesso');

                box.find('.m360-liga-codigo, .m360-bolao-codigo-liga').val('');
            } else {
                botao.prop('disabled', false).text(t('entrar'));
                setFeedback(feedback, response.data.mensagem || t('erro_entrar_liga'), 'erro');
            }
        }).fail(function () {
            botao.prop('disabled', false).text(t('entrar'));
            setFeedback(feedback, t('falha_comunicacao'), 'erro');
        });
    });

})(jQuery);
