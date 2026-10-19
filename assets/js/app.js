/* app.js - Rank Sistem: graficos, contadores e formularios dinamicos */
(function () {
    'use strict';

    /* ---------- Configuracao global dos graficos ---------- */
    function configurarTemaGraficos() {
        if (typeof Chart === 'undefined') return;
        Chart.defaults.color = '#8ba0c4';
        Chart.defaults.font.family = "'Outfit', 'Segoe UI', sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.borderColor = 'rgba(38, 50, 78, .55)';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(10, 14, 24, .95)';
        Chart.defaults.plugins.tooltip.borderColor = '#26324e';
        Chart.defaults.plugins.tooltip.borderWidth = 1;
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.titleColor = '#e8eefb';
        Chart.defaults.plugins.tooltip.bodyColor = '#cbd8ee';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.boxWidth = 8;
        Chart.defaults.animation.duration = 700;
    }

    function renderGraficos() {
        if (!window.RANK_CHARTS || !window.RANK_CHARTS.length) return;
        if (typeof Chart === 'undefined') {
            document.querySelectorAll('canvas').forEach(function (cv) {
                cv.insertAdjacentHTML('afterend', '<p class="muted">Grafico indisponivel (sem conexao ao CDN do Chart.js).</p>');
            });
            return;
        }
        configurarTemaGraficos();
        window.RANK_CHARTS.forEach(function (item) {
            var cv = document.getElementById(item.id);
            if (!cv) return;
            try {
                new Chart(cv.getContext('2d'), item.config);
            } catch (e) {
                console.warn('Falha ao renderizar grafico ' + item.id, e);
            }
        });
    }

    /* ---------- Contador animado dos gauges ---------- */
    function animarContadores() {
        document.querySelectorAll('.gauge-num[data-count]').forEach(function (el) {
            var alvo = parseFloat(el.getAttribute('data-count')) || 0;
            var atual = 0;
            var passos = 40;
            var inc = alvo / passos;
            var i = 0;
            var timer = setInterval(function () {
                i++;
                atual += inc;
                if (i >= passos) { atual = alvo; clearInterval(timer); }
                el.textContent = atual.toFixed(0) + '%';
            }, 16);
        });
    }

    /* ---------- Linhas dinamicas do treino ---------- */
    function renderLinhasTreino() {
        var container = document.getElementById('linhasTreino');
        var btn = document.getElementById('addLinhaTreino');
        if (!container) return;
        var exercicios = window.RANK_EXERCICIOS || [];

        function opcoesExercicios() {
            var html = '<option value="">Exercicio...</option>';
            exercicios.forEach(function (ex) {
                html += '<option value="' + ex.id + '">' + ex.nome + '</option>';
            });
            return html;
        }

        function opcoesNiveis(exId) {
            var html = '';
            var ex = exercicios.filter(function (e) { return String(e.id) === String(exId); })[0];
            if (!ex) return '<option value="1">Nivel 1</option>';
            ex.niveis.forEach(function (n) {
                html += '<option value="' + n.nivel + '">N' + n.nivel + ' · ' + n.nome + '</option>';
            });
            return html;
        }

        function addLinha() {
            var linha = document.createElement('div');
            linha.className = 'linha-treino';
            linha.innerHTML =
                '<select name="exercicio_id[]" class="sel-ex">' + opcoesExercicios() + '</select>' +
                '<select name="nivel[]" class="sel-nv"><option value="1">Nivel 1</option></select>' +
                '<input type="number" name="series[]" min="0" placeholder="Series" value="3">' +
                '<input type="number" name="reps[]" min="0" placeholder="Reps" value="10">' +
                '<button type="button" class="btn-remover" title="Remover">×</button>';
            container.appendChild(linha);

            var selEx = linha.querySelector('.sel-ex');
            var selNv = linha.querySelector('.sel-nv');
            selEx.addEventListener('change', function () {
                selNv.innerHTML = opcoesNiveis(selEx.value);
            });
            linha.querySelector('.btn-remover').addEventListener('click', function () {
                linha.remove();
            });
        }

        if (btn) btn.addEventListener('click', addLinha);
        addLinha();
    }

    /* ---------- Linhas dinamicas de niveis (cadastros) ---------- */
    function renderLinhasNivel() {
        var container = document.getElementById('linhasNivel');
        var btn = document.getElementById('addLinhaNivel');
        if (!container) return;
        var contador = 0;

        function addLinha() {
            contador++;
            var linha = document.createElement('div');
            linha.className = 'nivel-linha';
            linha.innerHTML =
                '<span class="ordem">' + contador + '</span>' +
                '<input type="text" name="nivel_nome[]" placeholder="Nome do nivel (ex: Flexao Diamond)">' +
                '<input type="number" name="nivel_fator[]" step="0.05" min="0.1" value="1.00" title="Fator de dificuldade">' +
                '<button type="button" class="btn-remover" title="Remover">×</button>';
            container.appendChild(linha);
            linha.querySelector('.btn-remover').addEventListener('click', function () {
                linha.remove();
            });
        }

        if (btn) btn.addEventListener('click', addLinha);
        addLinha();
    }

    /* ---------- Data tables: pesquisa + ordenacao + paginacao ---------- */
    function iniciarTabelasDados() {
        var wrappers = document.querySelectorAll('[data-tabela]');
        for (var w = 0; w < wrappers.length; w++) {
            (function (wrap) {
                var table = wrap.querySelector('table');
                var tbody = table ? table.tBodies[0] : null;
                var input = wrap.querySelector('.tabela-pesquisa');
                var info = wrap.querySelector('.tabela-info');
                var paginacao = wrap.querySelector('.tabela-paginacao');
                var select = wrap.querySelector('.tabela-por-pagina');
                if (!table || !tbody) return;

                if (tbody.rows.length === 1 && tbody.rows[0].querySelector('.tabela-vazia')) {
                    if (input) input.disabled = true;
                    if (select) select.disabled = true;
                    if (info) info.textContent = '0 registros';
                    return;
                }

                var linhas = Array.prototype.slice.call(tbody.rows);
                var ordem = linhas.slice();
                var filtradas = linhas.slice();
                var ths = Array.prototype.slice.call(table.tHead.rows[0].cells);
                var estado = {
                    idx: -1,
                    dir: 1,
                    pagina: 1,
                    porPagina: select ? (parseInt(select.value, 10) || 0) : 10
                };

                function valorCelula(td) {
                    var txt = (td.textContent || '').replace(/\s+/g, ' ').trim();
                    if (txt === '' || txt === '—' || txt === '-') return null;
                    var data = txt.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
                    if (data) return parseInt(data[3] + data[2] + data[1], 10);
                    var moeda = txt.match(/^-?R\$\s*-?\s*([\d.]+),(\d{2})/);
                    if (moeda) return parseFloat(moeda[1].replace(/\./g, '') + '.' + moeda[2]);
                    var num = txt.replace(/[^\d,.-]/g, '');
                    if (num && /^\d/.test(num)) {
                        if (num.indexOf(',') > -1) {
                            num = num.replace(/\./g, '').replace(',', '.');
                        }
                        var f = parseFloat(num);
                        if (!isNaN(f)) return f;
                    }
                    return txt.toLowerCase();
                }

                function comparar(a, b) {
                    var va = valorCelula(a.cells[estado.idx]);
                    var vb = valorCelula(b.cells[estado.idx]);
                    if (va === null && vb === null) return 0;
                    if (va === null) return 1;
                    if (vb === null) return -1;
                    var r;
                    if (typeof va === 'number' && typeof vb === 'number') {
                        r = va - vb;
                    } else {
                        r = String(va).localeCompare(String(vb), 'pt-BR');
                    }
                    return r * estado.dir;
                }

                function renderPaginacao(paginas) {
                    if (!paginacao) return;
                    paginacao.innerHTML = '';
                    if (estado.porPagina === 0 || paginas <= 1) return;

                    function botao(rotulo, pagina, opts) {
                        opts = opts || {};
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'pagina-btn' + (opts.ativo ? ' ativa' : '');
                        b.textContent = rotulo;
                        if (opts.disabled) {
                            b.disabled = true;
                        } else {
                            b.addEventListener('click', function () {
                                estado.pagina = pagina;
                                render();
                            });
                        }
                        paginacao.appendChild(b);
                    }

                    botao('«', 1, { disabled: estado.pagina === 1 });
                    botao('‹', estado.pagina - 1, { disabled: estado.pagina === 1 });

                    var nums = [1];
                    for (var p = estado.pagina - 2; p <= estado.pagina + 2; p++) {
                        if (p > 1 && p < paginas) nums.push(p);
                    }
                    if (paginas > 1) nums.push(paginas);
                    nums.sort(function (a, b) { return a - b; });

                    var anterior = 0;
                    for (var i = 0; i < nums.length; i++) {
                        var n = nums[i];
                        if (n === anterior) continue;
                        if (anterior && n - anterior > 1) {
                            var el = document.createElement('span');
                            el.className = 'pagina-ellipsis';
                            el.textContent = '…';
                            paginacao.appendChild(el);
                        }
                        botao(String(n), n, { ativo: n === estado.pagina });
                        anterior = n;
                    }

                    botao('›', estado.pagina + 1, { disabled: estado.pagina === paginas });
                    botao('»', paginas, { disabled: estado.pagina === paginas });
                }

                function render() {
                    var total = filtradas.length;
                    var per = estado.porPagina;
                    var paginas = per === 0 ? 1 : Math.max(1, Math.ceil(total / per));

                    if (estado.pagina > paginas) estado.pagina = paginas;
                    if (estado.pagina < 1) estado.pagina = 1;

                    for (var i = 0; i < linhas.length; i++) {
                        linhas[i].style.display = 'none';
                    }

                    var inicio = per === 0 ? 0 : (estado.pagina - 1) * per;
                    var fim = per === 0 ? total : Math.min(inicio + per, total);
                    for (var j = inicio; j < fim; j++) {
                        filtradas[j].style.display = '';
                    }

                    if (info) {
                        info.textContent = total === 0
                            ? '0 registros'
                            : 'Mostrando ' + (inicio + 1) + '–' + fim + ' de ' + total + ' registros';
                    }
                    renderPaginacao(paginas);
                }

                function aplicarFiltro() {
                    var q = input ? input.value.toLowerCase().trim() : '';
                    filtradas = [];
                    for (var i = 0; i < ordem.length; i++) {
                        var tr = ordem[i];
                        if (!q || tr.textContent.toLowerCase().indexOf(q) > -1) {
                            filtradas.push(tr);
                        }
                    }
                    estado.pagina = 1;
                    render();
                }

                if (input) input.addEventListener('input', aplicarFiltro);

                if (select) {
                    select.addEventListener('change', function () {
                        estado.porPagina = parseInt(this.value, 10) || 0;
                        estado.pagina = 1;
                        render();
                    });
                }

                ths.forEach(function (th, idx) {
                    if (!th.textContent.trim()) return;
                    th.classList.add('th-ordenavel');
                    th.setAttribute('tabindex', '0');
                    th.setAttribute('role', 'button');
                    var seta = document.createElement('span');
                    seta.className = 'th-seta';
                    seta.textContent = '↕';
                    th.appendChild(seta);

                    function ordenar() {
                        if (estado.idx === idx) {
                            estado.dir = -estado.dir;
                        } else {
                            estado.idx = idx;
                            estado.dir = 1;
                        }
                        ordem.sort(comparar);
                        for (var i = 0; i < ordem.length; i++) {
                            tbody.appendChild(ordem[i]);
                        }
                        ths.forEach(function (t) {
                            t.classList.remove('sort-asc', 'sort-desc');
                            var s = t.querySelector('.th-seta');
                            if (s) s.textContent = '↕';
                        });
                        th.classList.add(estado.dir === 1 ? 'sort-asc' : 'sort-desc');
                        seta.textContent = estado.dir === 1 ? '▲' : '▼';
                        aplicarFiltro();
                    }

                    th.addEventListener('click', ordenar);
                    th.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            ordenar();
                        }
                    });
                });

                aplicarFiltro();
            })(wrappers[w]);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderGraficos();
        animarContadores();
        renderLinhasTreino();
        renderLinhasNivel();
        iniciarTabelasDados();
    });
})();
