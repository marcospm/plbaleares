/*
 * Vista previa de preguntas pegadas: fichas legibles y edición de una en una.
 * El textarea oculto sigue siendo el bloque que se envía al guardar.
 */
(function () {
    if (window.iniciarVistaPreguntas) {
        window.iniciarVistaPreguntas();
        return;
    }

    var ORDEN = ['TEMA', 'LEY', 'ARTICULO', 'PREGUNTA', 'A', 'B', 'C', 'D', 'CORRECTA', 'EXPLICACION'];
    var REQUERIDOS = ['ARTICULO', 'PREGUNTA', 'A', 'B', 'C', 'D'];
    var iniciados = new WeakSet();

    function iniciar() {
        var root = document.getElementById('preguntas-preview-root');
        if (!root || iniciados.has(root)) {
            return;
        }

        var resumen = root.querySelector('[data-preview="resumen"]');
        var indice = root.querySelector('[data-preview="indice"]');
        var lista = root.querySelector('[data-preview="lista"]');
        var fuente = root.querySelector('textarea[name="bloque"]');
        var form = root.closest('form');
        if (!resumen || !indice || !lista || !fuente) {
            return;
        }
        iniciados.add(root);

        var mostrarTema = root.getAttribute('data-mostrar-tema') === '1';
        var mostrarLey = root.getAttribute('data-mostrar-ley') === '1';
        var avisosServidor = {};
        try {
            JSON.parse(root.getAttribute('data-omitidas') || '[]').forEach(function (texto) {
                var coincidencia = /^Bloque\s+(\d+)\s*:\s*(.*)$/i.exec(String(texto).trim());
                if (!coincidencia) {
                    return;
                }
                var numero = coincidencia[1];
                avisosServidor[numero] = avisosServidor[numero] || [];
                avisosServidor[numero].push(coincidencia[2] || String(texto));
            });
        } catch (error) {
            avisosServidor = {};
        }

        var piezas = [];
        var editando = null;
        var baseCampos = null;
        var editadas = new Set();

        function normalizarCorrecta(valor) {
            var coincidencia = /^([ABCD])\b/i.exec(String(valor || '').trim());
            return coincidencia ? coincidencia[1].toUpperCase() : '';
        }

        function leerCampos(trozo) {
            var campos = {};
            var actual = null;
            var tiene = false;
            trozo.split('\n').forEach(function (linea) {
                var limpia = linea.trim();
                if (limpia === '' || limpia.indexOf('```') === 0) {
                    return;
                }
                var coincidencia = /^(?:[\s>#`*_-]*)(ARTICULO|PREGUNTA|A|B|C|D|CORRECTA|EXPLICACION|TEMA|LEY)(?:[*_`]*)\s*[:)]\s*(.*)$/i.exec(limpia);
                if (coincidencia) {
                    tiene = true;
                    actual = coincidencia[1].toUpperCase();
                    if (actual === 'PREGUNTA' && Object.prototype.hasOwnProperty.call(campos, 'PREGUNTA')) {
                        campos.__duplicada = '1';
                    }
                    campos[actual] = limpiarTexto(coincidencia[2]);
                    return;
                }
                if (actual !== null && limpia !== '') {
                    campos[actual] = limpiarTexto(campos[actual] + ' ' + limpia);
                }
            });
            return tiene ? campos : null;
        }

        function parsear(texto) {
            var normalizado = String(texto || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();
            if (normalizado === '') {
                return [];
            }
            var resultado = [];
            var numero = 0;
            normalizado.split(/^\s*---\s*$/m).forEach(function (trozo) {
                var raw = trozo.trim();
                if (raw === '' || /^```[\w-]*$/.test(raw)) {
                    return;
                }
                var campos = leerCampos(raw);
                if (!campos) {
                    resultado.push({ tipo: 'nota', raw: raw, numero: null, campos: null });
                    return;
                }
                numero += 1;
                resultado.push({ tipo: 'pregunta', raw: raw, numero: numero, campos: campos });
            });
            return resultado;
        }

        function formatear(campos) {
            var lineas = [];
            ORDEN.forEach(function (clave) {
                if (!Object.prototype.hasOwnProperty.call(campos, clave)) {
                    return;
                }
                lineas.push(clave + ': ' + String(campos[clave] == null ? '' : campos[clave]).trim());
            });
            return lineas.join('\n');
        }

        function clonarParaEditar(campos) {
            var copia = {};
            ORDEN.forEach(function (clave) {
                if (Object.prototype.hasOwnProperty.call(campos, clave)) {
                    copia[clave] = campos[clave];
                }
            });
            if (mostrarTema && !Object.prototype.hasOwnProperty.call(copia, 'TEMA')) {
                copia.TEMA = '';
            }
            if (mostrarLey && !Object.prototype.hasOwnProperty.call(copia, 'LEY')) {
                copia.LEY = '';
            }
            REQUERIDOS.concat(['CORRECTA', 'EXPLICACION']).forEach(function (clave) {
                if (!Object.prototype.hasOwnProperty.call(copia, clave)) {
                    copia[clave] = '';
                }
            });
            copia.CORRECTA = normalizarCorrecta(copia.CORRECTA);
            return copia;
        }

        function escribir() {
            var cuerpos = piezas.map(function (pieza) {
                return pieza.raw;
            }).filter(function (texto) {
                return texto.trim() !== '';
            });
            fuente.value = cuerpos.length ? '---\n' + cuerpos.join('\n---\n') + '\n---\n' : '';
        }

        function avisosLocales(campos) {
            var faltan = [];
            REQUERIDOS.forEach(function (clave) {
                if (!String(campos[clave] || '').trim()) {
                    faltan.push(clave);
                }
            });
            var avisos = [];
            if (faltan.length) {
                avisos.push('Faltan ' + faltan.join(', ') + '.');
            }
            if (!normalizarCorrecta(campos.CORRECTA)) {
                avisos.push('La respuesta correcta tiene que ser A, B, C o D.');
            }
            if (mostrarTema && !String(campos.TEMA || '').trim()) {
                avisos.push('Falta el tema.');
            }
            if (mostrarLey && !String(campos.LEY || '').trim()) {
                avisos.push('Falta la ley.');
            }
            return avisos;
        }

        function estado(pieza) {
            var id = String(pieza.numero);
            if (editadas.has(id)) {
                return { avisos: avisosLocales(pieza.campos), revisada: true };
            }
            return { avisos: avisosServidor[id] || [], revisada: false };
        }

        function el(tag, clase, texto) {
            var nodo = document.createElement(tag);
            if (clase) {
                nodo.className = clase;
            }
            if (texto !== undefined && texto !== null) {
                nodo.textContent = texto;
            }
            return nodo;
        }

        function camposDesdeFormulario(card) {
            var campos = {};
            ORDEN.forEach(function (clave) {
                if (Object.prototype.hasOwnProperty.call(baseCampos, clave)) {
                    campos[clave] = baseCampos[clave];
                }
            });
            card.querySelectorAll('[data-campo]').forEach(function (campo) {
                campos[campo.getAttribute('data-campo')] = campo.value.trim();
            });
            var marcada = card.querySelector('[data-accion="correcta"].activa');
            campos.CORRECTA = marcada ? marcada.getAttribute('data-letra') : '';
            return campos;
        }

        function editorSucio() {
            if (editando === null || !piezas[editando]) {
                return false;
            }
            var card = lista.querySelector('[data-indice="' + editando + '"]');
            if (!card) {
                return false;
            }
            var pieza = piezas[editando];
            if (pieza.tipo === 'nota') {
                var area = card.querySelector('[data-nota]');
                return area ? area.value !== pieza.raw : false;
            }
            return JSON.stringify(camposDesdeFormulario(card)) !== JSON.stringify(baseCampos);
        }

        function aplicarEnPieza() {
            var pieza = piezas[editando];
            var card = lista.querySelector('[data-indice="' + editando + '"]');
            if (!pieza || !card) {
                return;
            }
            if (pieza.tipo === 'nota') {
                var area = card.querySelector('[data-nota]');
                pieza.raw = area ? area.value.trim() : pieza.raw;
                return;
            }
            var campos = camposDesdeFormulario(card);
            pieza.campos = campos;
            pieza.raw = formatear(campos);
        }

        function abrir(indice) {
            if (editando === indice) {
                return;
            }
            if (editorSucio() && !window.confirm('Has cambiado esta pregunta y no has pulsado Listo. ¿Descartas los cambios?')) {
                return;
            }
            editando = indice;
            var pieza = piezas[indice];
            baseCampos = pieza && pieza.tipo === 'pregunta' ? clonarParaEditar(pieza.campos) : null;
            render();
            var card = lista.querySelector('[data-indice="' + indice + '"]');
            if (!card) {
                return;
            }
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            var foco = card.querySelector('[data-campo="PREGUNTA"]') || card.querySelector('input, textarea');
            if (foco) {
                foco.focus();
            }
        }

        function cerrarEditor() {
            editando = null;
            baseCampos = null;
            render();
        }

        function aplicar() {
            if (editando === null) {
                return;
            }
            var numero = piezas[editando] ? piezas[editando].numero : null;
            if (editorSucio()) {
                aplicarEnPieza();
                escribir();
                if (numero) {
                    editadas.add(String(numero));
                }
                piezas = parsear(fuente.value);
            }
            editando = null;
            baseCampos = null;
            render();
            if (numero) {
                var card = lista.querySelector('[data-numero="' + numero + '"]');
                if (card) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    var boton = card.querySelector('[data-accion="editar"]');
                    if (boton) {
                        boton.focus();
                    }
                }
            }
        }

        function cancelar() {
            if (editorSucio() && !window.confirm('Has cambiado esta pregunta y no has pulsado Listo. ¿Descartas los cambios?')) {
                return;
            }
            cerrarEditor();
        }

        function limpiarTexto(valor) {
            return String(valor || '')
                .replace(/^(?:\*\*|__|`)+/, '')
                .replace(/(?:\*\*|__|`)+$/, '')
                .replace(/\*\*(.+?)\*\*/g, '$1')
                .replace(/__(.+?)__/g, '$1')
                .replace(/`([^`]+)`/g, '$1')
                .replace(/\*\*|__|`/g, '')
                .trim();
        }

        function lineaMeta(campos) {
            var partes = [];
            if (campos.ARTICULO) {
                partes.push(etiquetaArticulo(campos.ARTICULO));
            }
            if (campos.TEMA) {
                partes.push(campos.TEMA);
            }
            if (campos.LEY) {
                partes.push(campos.LEY);
            }
            return partes.join(' · ');
        }

        function etiquetaArticulo(valor) {
            var texto = String(valor || '').trim();
            if (!texto) {
                return '';
            }
            return /^art/i.test(texto) ? texto : 'Art. ' + texto;
        }

        function crearCampo(clave, etiqueta, valor, filas) {
            var grupo = el('label', 'pregunta-preview-campo');
            grupo.appendChild(el('span', 'form-label', etiqueta));
            var control = document.createElement(filas ? 'textarea' : 'input');
            control.className = 'form-control';
            control.setAttribute('data-campo', clave);
            if (filas) {
                control.rows = filas;
            } else {
                control.type = 'text';
            }
            control.value = valor || '';
            control.autocomplete = 'off';
            if (clave === 'ARTICULO') {
                control.placeholder = '14 o 14 bis';
            }
            grupo.appendChild(control);
            return grupo;
        }

        function pintarAvisos(contenedor, info) {
            if (info.avisos.length) {
                var caja = el('div', 'pregunta-preview-alertas');
                var titulo = el('strong', null, 'Esta pregunta no se guardará así');
                caja.appendChild(titulo);
                var items = el('ul');
                info.avisos.forEach(function (aviso) {
                    items.appendChild(el('li', null, aviso));
                });
                caja.appendChild(items);
                contenedor.appendChild(caja);
            }
            if (info.revisada) {
                contenedor.appendChild(el('p', 'pregunta-preview-revisada', 'Se comprobará de nuevo al guardar.'));
            }
        }

        function pintarPregunta(pieza, posicion) {
            var info = estado(pieza);
            var campos = pieza.campos;
            var card = el('article', 'pregunta-preview' + (info.avisos.length ? ' tiene-aviso' : '') + (editando === posicion ? ' esta-editando' : ''));
            card.setAttribute('data-indice', String(posicion));
            card.setAttribute('data-numero', String(pieza.numero));

            var cabecera = el('header', 'pregunta-preview-cabecera');
            cabecera.appendChild(el('span', 'pregunta-preview-num', String(pieza.numero)));
            var identidad = el('div', 'pregunta-preview-identidad');
            identidad.appendChild(el('p', 'pregunta-preview-kicker', editando === posicion ? 'Editando la pregunta ' + pieza.numero : 'Pregunta ' + pieza.numero));
            if (editando !== posicion) {
                var meta = lineaMeta(campos);
                if (meta) {
                    identidad.appendChild(el('p', 'pregunta-preview-meta', meta));
                }
                if ((info.revisada && !info.avisos.length) || info.avisos.length) {
                    var chips = el('div', 'pregunta-preview-chips');
                    if (info.revisada && !info.avisos.length) {
                        chips.appendChild(el('span', 'pregunta-preview-chip es-editada', 'Editada'));
                    }
                    if (info.avisos.length) {
                        chips.appendChild(el('span', 'pregunta-preview-chip es-aviso', 'Por revisar'));
                    }
                    identidad.appendChild(chips);
                }
            }
            cabecera.appendChild(identidad);
            if (editando !== posicion) {
                var editar = el('button', 'pregunta-preview-boton', 'Editar');
                editar.type = 'button';
                editar.setAttribute('data-accion', 'editar');
                editar.setAttribute('data-indice', String(posicion));
                cabecera.appendChild(editar);
            }
            card.appendChild(cabecera);

            if (editando === posicion) {
                var formEdicion = el('div', 'pregunta-preview-form');
                pintarAvisos(formEdicion, info);
                var meta = el('div', 'pregunta-preview-campos es-meta');
                if (Object.prototype.hasOwnProperty.call(baseCampos, 'TEMA')) {
                    meta.appendChild(crearCampo('TEMA', 'Tema', baseCampos.TEMA, 2));
                }
                if (Object.prototype.hasOwnProperty.call(baseCampos, 'LEY')) {
                    meta.appendChild(crearCampo('LEY', 'Ley', baseCampos.LEY, 2));
                }
                meta.appendChild(crearCampo('ARTICULO', 'Artículo', baseCampos.ARTICULO, 0));
                formEdicion.appendChild(meta);
                formEdicion.appendChild(crearCampo('PREGUNTA', 'Enunciado', baseCampos.PREGUNTA, 4));

                var opciones = el('div', 'pregunta-preview-campos es-opciones');
                ['A', 'B', 'C', 'D'].forEach(function (letra) {
                    opciones.appendChild(crearCampo(letra, 'Opción ' + letra, baseCampos[letra], 2));
                });
                formEdicion.appendChild(opciones);

                var correcta = el('fieldset', 'pregunta-preview-correcta');
                correcta.appendChild(el('legend', null, 'Respuesta correcta'));
                var letras = el('div', 'pregunta-preview-letras');
                ['A', 'B', 'C', 'D'].forEach(function (letra) {
                    var opcion = el('button', 'pregunta-preview-letra-opcion' + (baseCampos.CORRECTA === letra ? ' activa' : ''), letra);
                    opcion.type = 'button';
                    opcion.setAttribute('data-accion', 'correcta');
                    opcion.setAttribute('data-letra', letra);
                    opcion.setAttribute('aria-pressed', baseCampos.CORRECTA === letra ? 'true' : 'false');
                    letras.appendChild(opcion);
                });
                correcta.appendChild(letras);
                formEdicion.appendChild(correcta);
                formEdicion.appendChild(crearCampo('EXPLICACION', 'Explicación', baseCampos.EXPLICACION, 3));

                var acciones = el('div', 'pregunta-preview-acciones');
                var listo = el('button', 'pregunta-preview-boton pregunta-preview-boton-principal', 'Listo');
                listo.type = 'button';
                listo.setAttribute('data-accion', 'aplicar');
                var descartar = el('button', 'pregunta-preview-boton', 'Cancelar');
                descartar.type = 'button';
                descartar.setAttribute('data-accion', 'cancelar');
                acciones.appendChild(listo);
                acciones.appendChild(descartar);
                formEdicion.appendChild(acciones);
                card.appendChild(formEdicion);
                return card;
            }

            var cuerpo = el('div', 'pregunta-preview-cuerpo');
            cuerpo.appendChild(el('p', 'pregunta-preview-texto' + (campos.PREGUNTA ? '' : ' es-vacio'), campos.PREGUNTA || 'Sin enunciado'));
            var opcionesVista = el('ul', 'pregunta-preview-opciones');
            var letraCorrecta = normalizarCorrecta(campos.CORRECTA);
            ['A', 'B', 'C', 'D'].forEach(function (letra) {
                var item = el('li', 'pregunta-preview-opcion' + (letra === letraCorrecta ? ' es-correcta' : ''));
                item.appendChild(el('span', 'pregunta-preview-letra', letra));
                var contenido = el('span', 'pregunta-preview-opcion-texto' + (campos[letra] ? '' : ' es-vacio'), campos[letra] || 'Sin texto');
                item.appendChild(contenido);
                if (letra === letraCorrecta) {
                    item.appendChild(el('span', 'pregunta-preview-marca', 'Correcta'));
                }
                opcionesVista.appendChild(item);
            });
            cuerpo.appendChild(opcionesVista);
            if (String(campos.EXPLICACION || '').trim()) {
                var explica = el('div', 'pregunta-preview-explica');
                explica.appendChild(el('span', null, 'Explicación'));
                explica.appendChild(el('p', null, campos.EXPLICACION));
                cuerpo.appendChild(explica);
            }
            pintarAvisos(cuerpo, info);
            card.appendChild(cuerpo);
            return card;
        }

        function pintarNota(pieza, posicion) {
            var card = el('article', 'pregunta-preview es-nota' + (editando === posicion ? ' esta-editando' : ''));
            card.setAttribute('data-indice', String(posicion));
            var cabecera = el('header', 'pregunta-preview-cabecera');
            cabecera.appendChild(el('span', 'pregunta-preview-num es-nota', '…'));
            var identidad = el('div', 'pregunta-preview-identidad');
            identidad.appendChild(el('p', 'pregunta-preview-kicker', 'Texto fuera de las preguntas'));
            cabecera.appendChild(identidad);
            if (editando !== posicion) {
                var editar = el('button', 'pregunta-preview-boton', 'Editar');
                editar.type = 'button';
                editar.setAttribute('data-accion', 'editar');
                editar.setAttribute('data-indice', String(posicion));
                cabecera.appendChild(editar);
            }
            card.appendChild(cabecera);
            if (editando === posicion) {
                var formEdicion = el('div', 'pregunta-preview-form');
                var area = document.createElement('textarea');
                area.className = 'form-control';
                area.rows = 6;
                area.setAttribute('data-nota', '1');
                area.value = pieza.raw;
                formEdicion.appendChild(area);
                var acciones = el('div', 'pregunta-preview-acciones');
                var listo = el('button', 'pregunta-preview-boton pregunta-preview-boton-principal', 'Listo');
                listo.type = 'button';
                listo.setAttribute('data-accion', 'aplicar');
                var descartar = el('button', 'pregunta-preview-boton', 'Cancelar');
                descartar.type = 'button';
                descartar.setAttribute('data-accion', 'cancelar');
                acciones.appendChild(listo);
                acciones.appendChild(descartar);
                formEdicion.appendChild(acciones);
                card.appendChild(formEdicion);
                return card;
            }
            card.appendChild(el('p', 'pregunta-preview-nota', pieza.raw));
            return card;
        }

        function render() {
            var preguntas = piezas.filter(function (pieza) {
                return pieza.tipo === 'pregunta';
            });
            indice.replaceChildren();
            lista.replaceChildren();

            if (!piezas.length) {
                if (lista.children.length) {
                    return;
                }
                fuente.hidden = false;
                fuente.classList.add('form-control');
                fuente.rows = 16;
                resumen.textContent = 'No se reconoció ninguna pregunta. Corrige el texto y vuelve a comprobar.';
                return;
            }

            fuente.hidden = true;
            var porRevisar = preguntas.filter(function (pieza) {
                return estado(pieza).avisos.length > 0;
            }).length;
            resumen.textContent = preguntas.length + (preguntas.length === 1 ? ' pregunta' : ' preguntas')
                + (porRevisar ? ' · ' + porRevisar + ' por revisar' : '')
                + '. La opción en verde es la correcta.';

            if (preguntas.length > 1) {
                preguntas.forEach(function (pieza) {
                    var boton = el('button', 'pregunta-preview-ir' + (estado(pieza).avisos.length ? ' tiene-aviso' : ''), String(pieza.numero));
                    boton.type = 'button';
                    boton.setAttribute('data-accion', 'ir');
                    boton.setAttribute('data-numero', String(pieza.numero));
                    boton.title = 'Ir a la pregunta ' + pieza.numero;
                    indice.appendChild(boton);
                });
            }

            piezas.forEach(function (pieza, posicion) {
                lista.appendChild(pieza.tipo === 'pregunta' ? pintarPregunta(pieza, posicion) : pintarNota(pieza, posicion));
            });
        }

        root.addEventListener('click', function (evento) {
            var boton = evento.target.closest('button');
            if (!boton || !root.contains(boton)) {
                return;
            }
            var accion = boton.getAttribute('data-accion');
            if (accion === 'editar') {
                var indice = boton.getAttribute('data-indice');
                if (indice === null || indice === '') {
                    var numero = boton.getAttribute('data-numero');
                    indice = String(piezas.findIndex(function (pieza) {
                        return String(pieza.numero) === String(numero);
                    }));
                }
                if (Number(indice) >= 0) {
                    abrir(Number(indice));
                }
            } else if (accion === 'correcta') {
                var grupo = boton.parentElement;
                if (!grupo) {
                    return;
                }
                grupo.querySelectorAll('[data-accion="correcta"]').forEach(function (otra) {
                    var activa = otra === boton;
                    otra.classList.toggle('activa', activa);
                    otra.setAttribute('aria-pressed', activa ? 'true' : 'false');
                });
            } else if (accion === 'aplicar') {
                aplicar();
            } else if (accion === 'cancelar') {
                cancelar();
            } else if (accion === 'ir') {
                var destino = lista.querySelector('[data-numero="' + boton.getAttribute('data-numero') + '"]');
                if (destino) {
                    destino.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });

        root.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && editando !== null) {
                evento.preventDefault();
                cancelar();
            }
            if (evento.key === 'Enter' && evento.target.matches('input')) {
                evento.preventDefault();
            }
        });

        if (form) {
            form.addEventListener('submit', function () {
                if (editando === null || !editorSucio()) {
                    return;
                }
                aplicarEnPieza();
                if (piezas[editando] && piezas[editando].numero) {
                    editadas.add(String(piezas[editando].numero));
                }
                escribir();
            });
        }

        piezas = parsear(fuente.value);
        render();
    }

    window.iniciarVistaPreguntas = iniciar;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
    document.addEventListener('turbo:load', iniciar);
})();
