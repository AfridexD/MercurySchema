/**
 * Mercury Schema setup wizard and Schema Types dashboard.
 * Vanilla JS, no build step. Talks only to /mercury-schema/v1/setup.
 */
(function () {
    'use strict';

    var cfg = window.MercurySchemaSetup;
    if (!cfg) {
        return;
    }
    var t = cfg.i18n;
    var root, toasts;
    var state = { step: 1, visited: [1], preset: 'smart', enabled: [], types: [], presets: {}, result: null, busy: false };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        root = document.getElementById('mercury-schema-setup');
        if (!root) {
            return;
        }
        toasts = el('div', { 'class': 'ms-toasts', 'aria-live': 'polite' });
        document.body.appendChild(toasts);
        api('setup').then(function (s) {
            state.types = s.types;
            state.presets = s.presets;
            state.enabled = s.types.filter(function (x) { return x.enabled; }).map(function (x) { return x.type; });
            if (s.preset) {
                state.preset = s.preset;
            }
            if (cfg.mode === 'wizard' && !s.enabled_types) {
                state.enabled = state.presets.smart.slice(); // Fresh site: Custom starts from Smart.
            }
            render();
        }).catch(function (e) { root.textContent = e.message || t.error; });
    }

    // ---- helpers -------------------------------------------------------------

    function api(path, body) {
        var opts = { method: body ? 'POST' : 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce } };
        if (body) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        return fetch(cfg.restUrl + path, opts).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (!r.ok) {
                    throw new Error(j.message || t.error);
                }
                return j;
            });
        });
    }

    function el(tag, attrs, kids) {
        var n = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            var v = attrs[k];
            if (v === null || v === undefined || v === false) {
                return;
            }
            if (k === 'text') {
                n.textContent = v;
            } else if (k.slice(0, 2) === 'on') {
                n.addEventListener(k.slice(2), v);
            } else {
                n.setAttribute(k, v === true ? '' : v);
            }
        });
        (kids || []).forEach(function (c) {
            if (c) {
                n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
            }
        });
        return n;
    }

    function fmt(s) {
        var args = Array.prototype.slice.call(arguments, 1);
        return String(s).replace(/%(\d)\$s/g, function (m, i) { return args[i - 1]; }).replace('%s', args[0]);
    }

    function logo() {
        var span = el('span', { 'class': 'ms-mark', 'aria-hidden': 'true' });
        span.innerHTML = cfg.logo; // Static SVG generated server-side (Brand::mark), not user data.
        return span;
    }

    function check() {
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        var p = document.createElementNS(ns, 'polyline');
        p.setAttribute('points', '6,12.5 10.5,17 18,8');
        svg.appendChild(p);
        return svg;
    }

    function toast(msg, kind) {
        var n = el('div', { 'class': 'ms-toast' + (kind ? ' is-' + kind : ''), role: kind === 'error' ? 'alert' : 'status', text: msg });
        toasts.appendChild(n);
        setTimeout(function () { n.classList.add('is-leaving'); }, 2600);
        setTimeout(function () { n.remove(); }, 3000);
    }

    function label(name) {
        var x = state.types.filter(function (y) { return y.type === name; })[0];
        return x ? x.label : name;
    }

    function isOn(name) {
        return state.enabled.indexOf(name) !== -1;
    }

    // ---- shared: type toggles -----------------------------------------------------

    function toggleGrid(onChange) {
        var wrap = el('div', { 'class': 'msw-groups' });
        var order = ['foundations', 'content', 'commerce', 'local', 'other'];
        order.forEach(function (g) {
            var items = state.types.filter(function (x) { return x.group === g; });
            if (!items.length) {
                return;
            }
            var on = items.filter(function (x) { return isOn(x.type); }).length;
            var grid = el('div', { 'class': 'msw-toggles' });
            items.forEach(function (x) {
                grid.appendChild(el('button', {
                    type: 'button', role: 'switch', 'aria-checked': isOn(x.type) ? 'true' : 'false', 'class': 'msw-toggle' + (isOn(x.type) ? ' is-on' : ''),
                    onclick: function () { onChange([x.type], !isOn(x.type)); }
                }, [
                    el('span', { 'class': 'msw-toggle__text' }, [el('strong', { text: x.label }), el('span', { text: x.description })]),
                    el('span', { 'class': 'msw-switch', 'aria-hidden': 'true' })
                ]));
            });
            wrap.appendChild(el('section', { 'class': 'msw-group' }, [
                el('h2', {}, [t.groups[g] || g, el('span', { 'class': 'msw-mono', text: on + '/' + items.length })]),
                grid
            ]));
        });
        return wrap;
    }

    function typesHeader(title, text, onChange) {
        var all = state.enabled.length === state.types.length;
        return el('div', { 'class': 'msw-typeshead' }, [
            el('div', { 'class': 'msw-typeshead__text' }, [
                el('span', { 'class': 'msw-icon is-soft' }, [logo()]),
                el('div', {}, [el('h1', { text: title }), el('p', { text: text })])
            ]),
            el('div', { 'class': 'msw-typeshead__tools' }, [
                el('span', { 'class': 'msw-mono', text: fmt(t.countOn, state.enabled.length, state.types.length) }),
                el('button', {
                    type: 'button', role: 'switch', 'aria-checked': all ? 'true' : 'false', 'class': 'msw-all' + (all ? ' is-on' : ''),
                    onclick: function () { onChange(state.types.map(function (x) { return x.type; }), !all); }
                }, [el('span', { text: t.enableAll }), el('span', { 'class': 'msw-switch', 'aria-hidden': 'true' })])
            ])
        ]);
    }

    function applyChange(names, on) {
        names.forEach(function (n) {
            var i = state.enabled.indexOf(n);
            if (on && i === -1) {
                state.enabled.push(n);
            } else if (!on && i !== -1) {
                state.enabled.splice(i, 1);
            }
        });
    }

    // ---- dashboard -------------------------------------------------------------

    function renderDashboard() {
        root.textContent = '';
        var change = function (names, on) {
            var before = state.enabled.slice();
            applyChange(names, on);
            renderDashboard();
            api('setup', { enabled_types: state.enabled })
                .then(function () {
                    toast(names.length > 1 ? (on ? t.allOn : t.allOff) : fmt(on ? t.enabledToast : t.disabledToast, label(names[0])), 'success');
                })
                .catch(function (e) {
                    state.enabled = before;
                    renderDashboard();
                    toast(e.message, 'error');
                });
        };
        root.append(
            el('div', { 'class': 'msw-card msw-pad' }, [typesHeader(t.dashTitle, t.dashText, change), toggleGrid(change)]),
            el('div', { 'class': 'msw-foot' }, [
                el('button', {
                    type: 'button', 'class': 'button', text: t.rerun,
                    onclick: function () {
                        if (window.confirm(t.rerunConfirm)) {
                            api('setup/reset', {}).then(function () { window.location = cfg.urls.setup; }).catch(function (e) { toast(e.message, 'error'); });
                        }
                    }
                })
            ])
        );
    }

    // ---- wizard ----------------------------------------------------------------

    function render() {
        if (cfg.mode !== 'wizard') {
            return renderDashboard();
        }
        root.textContent = '';
        var custom = state.preset === 'custom';
        var tabs = el('div', { 'class': 'msw-tabs', role: 'tablist' });
        t.tabs.forEach(function (name, i) {
            var n = i + 1;
            var skipped = n === 3 && !custom && state.step > 2;
            var disabled = (n === 3 && !custom) || state.visited.indexOf(n) === -1 || (state.step === 4 && n < 4);
            tabs.appendChild(el('button', {
                type: 'button', role: 'tab', 'class': 'msw-tab' + (n === state.step ? ' is-active' : ''),
                'aria-selected': n === state.step ? 'true' : 'false', disabled: disabled && n !== state.step,
                onclick: function () { go(n); }
            }, [el('span', { 'class': 'msw-tab__num', text: String(n) }), el('span', { text: name }), skipped ? el('span', { 'class': 'msw-badge', text: t.skipped }) : null]));
        });

        var body = [step1, step2, step3, step4][state.step - 1]();
        root.append(el('div', { 'class': 'msw-card' }, [tabs, body]));
        if (state.step > 1) {
            root.appendChild(footer());
        }
        var focus = root.querySelector('h1');
        if (focus) {
            focus.setAttribute('tabindex', '-1');
            focus.focus({ preventScroll: true });
        }
    }

    function go(n) {
        state.step = n;
        if (state.visited.indexOf(n) === -1) {
            state.visited.push(n);
        }
        render();
        if (root.getBoundingClientRect().top < 0) {
            root.scrollIntoView({ block: 'start' });
        }
    }

    function footer() {
        var custom = state.preset === 'custom';
        if (state.step === 4) {
            return el('div', { 'class': 'msw-foot' }, [el('a', { 'class': 'msw-btn', href: cfg.urls.types, text: t.goTypes })]);
        }
        var nextLabel = state.step === 3 || (state.step === 2 && !custom) ? t.finish : t.next;
        return el('div', { 'class': 'msw-foot' }, [
            el('button', { type: 'button', 'class': 'msw-link', text: t.previous, onclick: function () { go(state.step - 1); } }),
            el('button', { type: 'button', 'class': 'msw-btn', text: state.busy ? t.saving : nextLabel, disabled: state.busy, onclick: next })
        ]);
    }

    function next() {
        if (state.step === 2 && state.preset === 'custom') {
            return go(3);
        }
        finish(state.preset === 'custom' ? { preset: 'custom', enabled_types: state.enabled, complete: true } : { preset: state.preset, complete: true });
    }

    function finish(body, then) {
        state.busy = true;
        render();
        api('setup', body).then(function (res) {
            state.busy = false;
            state.result = res;
            state.enabled = res.enabled_types || state.enabled;
            if (then) {
                return then();
            }
            go(4);
        }).catch(function (e) {
            state.busy = false;
            render();
            toast(e.message, 'error');
        });
    }

    function step1() {
        return el('div', { 'class': 'msw-body msw-center' }, [
            el('div', { 'class': 'msw-brand' }, [logo(), el('span', { 'class': 'msw-brand__name' }, [el('strong', { text: 'Mercury' }), ' Schema']), el('span', { 'class': 'msw-chip', text: 'v' + cfg.version })]),
            el('h1', { text: t.welcomeTitle }),
            el('p', { 'class': 'msw-lead', text: t.welcomeText }),
            el('ul', { 'class': 'msw-points' }, t.welcomePoints.map(function (p) { return el('li', {}, [check(), el('span', { text: p })]); })),
            el('div', { 'class': 'msw-actions' }, [
                el('button', { type: 'button', 'class': 'msw-btn', text: t.start, onclick: function () { go(2); } }),
                el('button', {
                    type: 'button', 'class': 'msw-link', text: t.skip,
                    onclick: function () { finish({ preset: 'basic', complete: true }, function () { window.location = cfg.urls.types; }); }
                })
            ])
        ]);
    }

    function step2() {
        var cards = el('div', { 'class': 'msw-presets', role: 'radiogroup', 'aria-label': t.presetTitle });
        ['basic', 'smart', 'custom'].forEach(function (id) {
            var on = state.preset === id;
            var count = id === 'custom' ? t.youChoose : fmt(t.nSchemas, state.presets[id].length);
            cards.appendChild(el('button', {
                type: 'button', role: 'radio', 'aria-checked': on ? 'true' : 'false', 'class': 'msw-preset' + (on ? ' is-on' : ''),
                onclick: function () {
                    state.preset = id;
                    if (id !== 'custom') {
                        state.enabled = state.presets[id].slice();
                    }
                    render();
                }
            }, [
                el('span', { 'class': 'msw-preset__top' }, [el('span', { 'class': 'msw-radio' }, [check()]), el('span', { 'class': 'msw-mono', text: count })]),
                el('span', { 'class': 'msw-preset__title' }, [t.presets[id][0], id === 'smart' ? el('span', { 'class': 'msw-pill', text: t.recommended }) : null]),
                el('span', { 'class': 'msw-preset__desc', text: t.presets[id][1] })
            ]));
        });
        return el('div', { 'class': 'msw-body msw-center' }, [
            el('span', { 'class': 'msw-icon' }, [logo()]),
            el('h1', { text: t.presetTitle }),
            el('p', { 'class': 'msw-lead', text: t.presetText }),
            cards
        ]);
    }

    function step3() {
        var change = function (names, on) {
            applyChange(names, on);
            render();
        };
        return el('div', { 'class': 'msw-body msw-pad' }, [typesHeader(t.typesTitle, t.typesText, change), el('div', { 'class': 'msw-scroll' }, [toggleGrid(change)])]);
    }

    function step4() {
        var res = state.result || { enabled_types: state.enabled, site_wide_added: [] };
        var presetName = t.presets[res.preset || state.preset] ? t.presets[res.preset || state.preset][0] : '';
        var added = res.site_wide_added || [];
        return el('div', { 'class': 'msw-body msw-pad' }, [
            el('div', { 'class': 'msw-donehead' }, [
                el('div', { 'class': 'msw-typeshead__text' }, [
                    el('span', { 'class': 'msw-icon is-ok' }, [check()]),
                    el('div', {}, [el('h1', { text: t.doneTitle }), el('p', { text: t.doneText })])
                ]),
                el('span', { 'class': 'msw-summary' }, [el('span', { 'class': 'msw-dot' }), fmt(t.summary, presetName, (res.enabled_types || []).length)])
            ]),
            added.length ? el('p', { 'class': 'msw-added' }, [el('strong', { text: t.addedSiteWide + ' ' }), added.map(label).join(', ')]) : null,
            el('div', { 'class': 'msw-guides' }, t.guides.map(function (g) {
                return el('a', { 'class': 'msw-guide', href: cfg.urls[g.url], target: g.external ? '_blank' : null, rel: g.external ? 'noopener noreferrer' : null }, [
                    el('span', { 'class': 'msw-guide__tag', text: g.tag }),
                    el('strong', { text: g.title }),
                    el('span', { text: g.desc }),
                    el('span', { 'class': 'msw-guide__cta', text: g.cta + (g.external ? ' ↗' : ' →') })
                ]);
            }))
        ]);
    }
})();
