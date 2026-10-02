/**
 * UnlimitedSchema editor. Vanilla JS, no build step, no dependencies.
 * Talks only to the REST API; holds no schema logic of its own.
 * Runs in the post editor (scope "post") and on the settings page (scope "global").
 */
(function () {
    'use strict';

    var cfg = window.UnlimitedSchemaData;
    if (!cfg) {
        return;
    }
    var t = cfg.i18n;
    var isGlobal = cfg.scope === 'global';
    var base = isGlobal ? 'global' : 'schemas/' + cfg.postId;
    var app, list, empty, picker, toasts, siteWideBox;
    var types = {};
    var siteWide = [];
    var dirty = {};

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ---- Boot --------------------------------------------------------------

    function init() {
        app = document.getElementById('unlimited-schema-app');
        if (!app) {
            return;
        }
        // The metabox sits inside the editor's <form>: Enter must not submit the post.
        app.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                e.preventDefault();
            }
            if (e.key === 'Escape') {
                closeMenus();
                togglePicker(false);
            }
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.us-menu, .us-menu-btn, .us-token-btn, .us-tokens')) {
                closeMenus();
            }
        });
        window.addEventListener('beforeunload', function (e) {
            if (Object.keys(dirty).length) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        Promise.all([api('schema-types'), api(base)])
            .then(function (res) {
                types = res[0];
                siteWide = res[1].site_wide || [];
                renderShell();
                (res[1].schemas || []).forEach(function (s) {
                    list.appendChild(card(s, (res[1].status || {})[s.id]));
                });
                refresh();
            })
            .catch(function (err) {
                app.textContent = err.message || t.error;
            });
    }

    // ---- Helpers -----------------------------------------------------------

    function api(path, method, body) {
        var opts = { method: method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce } };
        if (body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        return fetch(cfg.restUrl + path, opts).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (json) {
                if (!r.ok) {
                    var err = new Error(json.message || t.error);
                    err.errors = (json.data && json.data.errors) || [];
                    throw err;
                }
                return json;
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

    function icon(name) {
        return el('span', { 'class': 'dashicons dashicons-' + (name || 'editor-code'), 'aria-hidden': 'true' });
    }

    function button(label, cls, onclick, extra) {
        var b = el('button', Object.assign({ type: 'button', 'class': cls, onclick: onclick }, extra || {}));
        b.append.apply(b, [].concat(label));
        return b;
    }

    function fmt(str, value) {
        return String(str).replace('%s', value);
    }

    function toast(message, kind) {
        var n = el('div', { 'class': 'us-toast' + (kind ? ' is-' + kind : ''), role: kind === 'error' ? 'alert' : 'status' }, [message]);
        toasts.appendChild(n);
        setTimeout(function () { n.classList.add('is-leaving'); }, kind === 'error' ? 6000 : 3200);
        setTimeout(function () { n.remove(); }, kind === 'error' ? 6400 : 3600);
    }

    function closeMenus() {
        app.querySelectorAll('.us-menu, .us-tokens').forEach(function (m) { m.remove(); });
        app.querySelectorAll('[aria-expanded="true"]').forEach(function (b) {
            if (!b.classList.contains('us-add')) {
                b.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function shortLabel(v) {
        return String(v).replace(/^https?:\/\/schema\.org\//, '').replace(/([a-z])([A-Z])/g, '$1 $2');
    }

    function isToken(v) {
        return typeof v === 'string' && /\{\{\s*[a-z0-9_]+\s*\}\}/.test(v);
    }

    // ---- Shell -------------------------------------------------------------

    function renderShell() {
        app.textContent = '';
        app.classList.add('is-ready');

        var addBtn = button([icon('plus-alt2'), t.add], 'button button-primary us-add', function () {
            togglePicker(picker.hidden);
        }, { 'aria-expanded': 'false', 'aria-controls': 'us-picker' });

        app.appendChild(el('div', { 'class': 'us-head' }, [
            el('div', { 'class': 'us-head__text' }, [
                el('strong', { text: isGlobal ? t.siteWideTitle : t.title }),
                el('span', { 'class': 'us-muted', text: isGlobal ? t.siteWideIntro : t.intro })
            ]),
            addBtn
        ]));

        picker = buildPicker(addBtn);
        app.appendChild(picker);

        if (!isGlobal && siteWide.length) {
            siteWideBox = el('div', { 'class': 'us-sitewide' });
            app.appendChild(siteWideBox);
        }

        list = el('div', { 'class': 'us-list' });
        app.appendChild(list);

        empty = el('div', { 'class': 'us-empty' }, [
            el('span', { 'class': 'us-empty__icon' }, [icon('editor-code')]),
            el('strong', { text: t.emptyTitle }),
            el('span', { 'class': 'us-muted', text: isGlobal ? t.emptyGlobal : t.emptyText })
        ]);
        var quick = el('div', { 'class': 'us-quick' });
        (isGlobal ? ['Organization', 'LocalBusiness', 'Article'] : ['Article', 'Product', 'FAQPage']).forEach(function (name) {
            if (types[name]) {
                quick.appendChild(button([icon(types[name].icon), types[name].label], 'button', function () { addSchema(name); }));
            }
        });
        empty.appendChild(quick);
        app.appendChild(empty);

        toasts = el('div', { 'class': 'us-toasts', 'aria-live': 'polite' });
        document.body.appendChild(toasts);
    }

    function buildPicker(addBtn) {
        var grid = el('div', { 'class': 'us-picker__grid', role: 'list' });
        var search = el('input', { type: 'search', 'class': 'us-picker__search', placeholder: t.searchTypes, 'aria-label': t.searchTypes });
        Object.keys(types).forEach(function (name) {
            var d = types[name];
            var tile = button([
                el('span', { 'class': 'us-tile__icon' }, [icon(d.icon)]),
                el('span', { 'class': 'us-tile__text' }, [
                    el('strong', { text: d.label || name }),
                    el('span', { text: d.description || '' })
                ])
            ], 'us-tile', function () { addSchema(name); }, { role: 'listitem', 'data-search': (name + ' ' + d.label + ' ' + d.description).toLowerCase() });
            grid.appendChild(tile);
        });
        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            grid.querySelectorAll('.us-tile').forEach(function (tile) {
                tile.hidden = q !== '' && tile.getAttribute('data-search').indexOf(q) === -1;
            });
        });
        var p = el('div', { 'class': 'us-picker', id: 'us-picker', hidden: true }, [
            el('div', { 'class': 'us-picker__bar' }, [
                search,
                button([icon('no-alt')], 'us-icon-btn', function () { togglePicker(false); addBtn.focus(); }, { 'aria-label': t.close })
            ]),
            grid
        ]);
        p.search = search;
        p.addBtn = addBtn;
        return p;
    }

    function togglePicker(open) {
        if (!picker) {
            return;
        }
        picker.hidden = !open;
        picker.addBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            picker.search.value = '';
            picker.search.dispatchEvent(new Event('input'));
            picker.search.focus();
        }
    }

    function refresh() {
        var cards = list.querySelectorAll('.us-card');
        empty.hidden = cards.length > 0;
        if (siteWideBox) {
            var own = [];
            cards.forEach(function (c) {
                if (c.schema.enabled) {
                    own.push(c.schema.type);
                }
            });
            siteWideBox.textContent = '';
            siteWideBox.append(icon('admin-site-alt3'), el('span', { text: t.siteWideOnPage + ' ' }));
            siteWide.filter(function (s) { return s.enabled; }).forEach(function (s) {
                var over = own.indexOf(s.type) !== -1;
                siteWideBox.appendChild(el('span', {
                    'class': 'us-chip' + (over ? ' is-off' : ''),
                    title: over ? t.overridden : '',
                    text: (types[s.type] ? types[s.type].label : s.type) + (over ? ' · ' + t.overriddenShort : '')
                }));
            });
            if (cfg.settingsUrl) {
                siteWideBox.appendChild(el('a', { href: cfg.settingsUrl, text: t.manage }));
            }
        }
    }

    // ---- Create / duplicate --------------------------------------------------

    function addSchema(type, copyFrom) {
        var body = { type: type, data: {}, conditions: {} };
        if (copyFrom) {
            body.data = copyFrom.data;
            body.conditions = copyFrom.conditions;
            body.enabled = copyFrom.enabled;
        } else if (isGlobal) {
            // Sensible default: brand-level types on the front page, content types on posts.
            body.conditions = { locations: ['Organization', 'LocalBusiness', 'Person'].indexOf(type) !== -1 ? ['front_page'] : ['singular'] };
        }
        togglePicker(false);
        return api(base, 'POST', body)
            .then(function (res) {
                var c = card(res.schema, res.status);
                list.appendChild(c);
                c.open = true;
                refresh();
                c.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                var first = c.querySelector('.us-field input, .us-field textarea, .us-field select');
                if (first) {
                    first.focus();
                }
                toast(copyFrom ? t.duplicated : fmt(t.added, types[type].label || type), 'success');
            })
            .catch(function (err) { toast(err.message, 'error'); });
    }

    // ---- Card ----------------------------------------------------------------

    function card(schema, status) {
        var def = types[schema.type] || { label: schema.type, fields: {} };
        var c = el('details', { 'class': 'us-card', 'data-id': schema.id });
        c.schema = schema;
        var fields = {};

        // Header
        var pill = el('span', { 'class': 'us-pill' });
        var sw = el('input', { type: 'checkbox', role: 'switch', 'class': 'us-switch__input', 'aria-label': t.enabled, checked: schema.enabled });
        var swWrap = el('label', { 'class': 'us-switch', title: t.enabled }, [sw, el('span', { 'class': 'us-switch__track', 'aria-hidden': 'true' })]);
        var menuBtn = button([icon('ellipsis')], 'us-icon-btn us-menu-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openMenu(menuBtn);
        }, { 'aria-label': t.actions, 'aria-haspopup': 'menu', 'aria-expanded': 'false' });

        [swWrap, menuBtn].forEach(function (n) {
            n.addEventListener('click', function (e) { e.stopPropagation(); });
        });

        var head = el('summary', { 'class': 'us-card__head' }, [
            el('span', { 'class': 'us-card__icon' }, [icon(def.icon)]),
            el('span', { 'class': 'us-card__title' }, [
                el('strong', { text: def.label || schema.type }),
                el('span', { 'class': 'us-muted us-card__sub', text: summaryLine() })
            ]),
            pill,
            swWrap,
            menuBtn
        ]);

        // Body: tabs
        var notice = el('div', { 'class': 'us-callout', hidden: true });
        var fieldsPanel = el('div', { 'class': 'us-panel' }, [notice, renderFields(def.fields, schema.data || {}, fields, '')]);
        if (!isGlobal && siteWide.some(function (s) { return s.type === schema.type && s.enabled; })) {
            fieldsPanel.insertBefore(el('p', { 'class': 'us-hint' }, [icon('info-outline'), fmt(t.replacesSiteWide, def.label || schema.type)]), notice);
        }
        var rules = renderRules(schema.conditions || {});
        var previewPanel = el('div', { 'class': 'us-panel us-preview' });

        var tabs = [[t.tabFields, fieldsPanel], [t.tabRules, rules.panel], [t.tabPreview, previewPanel]];
        var tabBar = el('div', { 'class': 'us-tabs', role: 'tablist' });
        var panels = el('div', { 'class': 'us-panels' });
        tabs.forEach(function (pair, i) {
            var id = 'us-' + schema.id + '-tab' + i;
            var tab = button(pair[0], 'us-tab', function () { selectTab(i); }, { role: 'tab', id: id, 'aria-selected': i === 0 ? 'true' : 'false' });
            pair[1].setAttribute('role', 'tabpanel');
            pair[1].setAttribute('aria-labelledby', id);
            pair[1].hidden = i !== 0;
            tabBar.appendChild(tab);
            panels.appendChild(pair[1]);
        });
        function selectTab(i) {
            tabBar.querySelectorAll('.us-tab').forEach(function (tab, j) {
                tab.setAttribute('aria-selected', j === i ? 'true' : 'false');
                tabs[j][1].hidden = j !== i;
            });
            if (tabs[i][1] === previewPanel) {
                loadPreview();
            }
        }

        var saveBtn = button(t.save, 'button button-primary', save, { disabled: true });
        var foot = el('div', { 'class': 'us-card__foot' }, [el('span', { 'class': 'us-muted us-card__id', text: schema.id }), saveBtn]);
        c.append(head, el('div', { 'class': 'us-card__body' }, [tabBar, panels, foot]));

        setStatus(status);

        // Events
        c.addEventListener('input', function (e) {
            if (e.target !== sw) {
                setDirty(true);
            }
        });
        c.addEventListener('change', function (e) {
            if (e.target !== sw) {
                setDirty(true);
            }
        });
        sw.addEventListener('change', function () {
            sw.disabled = true;
            api(base + '/' + schema.id, 'PUT', { enabled: sw.checked })
                .then(function (res) {
                    schema.enabled = res.schema.enabled;
                    setStatus(res.status);
                    refresh();
                    toast(schema.enabled ? t.nowEnabled : t.nowDisabled, 'success');
                })
                .catch(function (err) {
                    sw.checked = !sw.checked;
                    toast(err.message, 'error');
                })
                .then(function () { sw.disabled = false; });
        });

        function summaryLine() {
            var d = schema.data || {};
            var first = d.headline || d.name || d.itemReviewed || '';
            if (Array.isArray(d.questions)) {
                first = fmt(t.nQuestions, d.questions.length);
            }
            return isToken(first) ? first.replace(/\{\{\s*|\s*\}\}/g, '').replace(/_/g, ' ') : first;
        }

        function setDirty(on) {
            if (on) {
                dirty[schema.id] = true;
            } else {
                delete dirty[schema.id];
            }
            saveBtn.disabled = !on;
            c.classList.toggle('is-dirty', on);
            if (on) {
                pill.className = 'us-pill is-unsaved';
                pill.textContent = t.unsaved;
            }
        }

        function setStatus(st) {
            c.status = st || { valid: true, errors: [] };
            var state = !schema.enabled ? 'off' : (c.status.valid ? 'ok' : 'warn');
            pill.className = 'us-pill is-' + state;
            pill.textContent = state === 'off' ? t.disabled : (state === 'ok' ? t.valid : t.needsAttention);
            c.setAttribute('data-state', state);
            showErrors(c.status.valid ? [] : c.status.errors, true);
        }

        function showErrors(errors, soft) {
            c.querySelectorAll('.us-invalid').forEach(function (n) { n.classList.remove('us-invalid'); });
            c.querySelectorAll('.us-field__error').forEach(function (n) { n.textContent = ''; });
            var unmatched = [];
            (errors || []).forEach(function (e) {
                var f = fields[e.field];
                if (f) {
                    f.input.classList.add('us-invalid');
                    f.error.textContent = humanError(e, f.def);
                } else {
                    unmatched.push(humanError(e));
                }
            });
            notice.hidden = !errors || !errors.length;
            notice.className = 'us-callout ' + (soft ? 'is-warn' : 'is-error');
            notice.textContent = '';
            if (errors && errors.length) {
                notice.append(icon('warning'), el('span', { text: fmt(soft ? t.missingSummary : t.fixSummary, errors.length) + (unmatched.length ? ' ' + unmatched.join(' ') : '') }));
            }
        }

        function save() {
            saveBtn.disabled = true;
            saveBtn.classList.add('is-busy');
            var payload = collect();
            api(base + '/' + schema.id, 'PUT', payload)
                .then(function (res) {
                    schema.data = res.schema.data;
                    schema.conditions = res.schema.conditions;
                    head.querySelector('.us-card__sub').textContent = summaryLine();
                    setDirty(false);
                    setStatus(res.status);
                    toast(res.status && !res.status.valid ? t.savedIncomplete : t.saved, res.status && !res.status.valid ? 'warn' : 'success');
                    if (!previewPanel.hidden) {
                        loadPreview();
                    }
                })
                .catch(function (err) {
                    selectTab(0);
                    showErrors(err.errors, false);
                    toast(err.message, 'error');
                    saveBtn.disabled = false;
                })
                .then(function () { saveBtn.classList.remove('is-busy'); });
        }

        function collect() {
            return { data: collectFields(def.fields, fields, ''), conditions: rules.collect() };
        }

        function loadPreview() {
            previewPanel.textContent = '';
            previewPanel.appendChild(el('p', { 'class': 'us-muted', text: t.loading }));
            var body = { type: schema.type, data: collect().data };
            if (!isGlobal) {
                body.post_id = cfg.postId;
            }
            api('preview', 'POST', body)
                .then(function (res) { renderPreview(previewPanel, res); })
                .catch(function (err) { previewPanel.textContent = err.message; });
        }

        function openMenu(anchor) {
            var open = anchor.getAttribute('aria-expanded') === 'true';
            closeMenus();
            if (open) {
                return;
            }
            anchor.setAttribute('aria-expanded', 'true');
            var del = button([icon('trash'), t['delete']], 'us-menu__item is-danger', function () {
                if (!del.classList.contains('is-confirm')) {
                    del.classList.add('is-confirm');
                    del.lastChild.textContent = t.confirmDelete;
                    return;
                }
                closeMenus();
                api(base + '/' + schema.id, 'DELETE')
                    .then(function () {
                        setDirty(false);
                        c.remove();
                        refresh();
                        toast(t.deleted, 'success');
                    })
                    .catch(function (err) { toast(err.message, 'error'); });
            }, { role: 'menuitem' });
            var menu = el('div', { 'class': 'us-menu', role: 'menu' }, [
                button([icon('admin-page'), t.duplicate], 'us-menu__item', function () {
                    closeMenus();
                    addSchema(schema.type, { data: collect().data, conditions: collect().conditions, enabled: schema.enabled });
                }, { role: 'menuitem' }),
                button([icon('editor-code'), t.copyJson], 'us-menu__item', function () {
                    closeMenus();
                    var body = { type: schema.type, data: collect().data };
                    if (!isGlobal) {
                        body.post_id = cfg.postId;
                    }
                    api('preview', 'POST', body).then(function (res) { copy(JSON.stringify(res.json_ld, null, 2)); });
                }, { role: 'menuitem' }),
                del
            ]);
            anchor.parentNode.appendChild(menu);
            menu.querySelector('button').focus();
        }

        return c;
    }

    function humanError(e, def) {
        if (/^Required field missing/.test(e.message)) {
            return fmt(t.required, def ? def.label : e.field);
        }
        return e.message;
    }

    // ---- Fields --------------------------------------------------------------

    function renderFields(defs, data, registry, prefix) {
        var grid = el('div', { 'class': 'us-fields' });
        Object.keys(defs).forEach(function (key) {
            var def = defs[key];
            var path = prefix + key;
            if (def.type === 'objects') {
                grid.appendChild(renderRepeater(def, data[key], registry, path));
                return;
            }
            var id = 'us-f-' + Math.random().toString(36).slice(2, 9);
            var input = buildInput(def, id, data[key]);
            var error = el('span', { 'class': 'us-field__error', role: 'alert' });
            var counter = def.maxLength ? el('span', { 'class': 'us-counter' }) : null;
            registry[path] = { input: input, error: error, def: def };

            var control = el('div', { 'class': 'us-control' }, [input]);
            if (['string', 'url', 'date', 'text', 'number', 'integer', 'duration'].indexOf(def.type || 'string') !== -1) {
                control.appendChild(tokenButton(input));
            }
            if (counter) {
                var update = function () {
                    var n = input.value.length;
                    counter.textContent = isToken(input.value) ? '' : n + ' / ' + def.maxLength;
                    counter.classList.toggle('is-over', n > def.maxLength && !isToken(input.value));
                };
                input.addEventListener('input', update);
                update();
            }
            var wide = def.type === 'text' || def.type === 'array';
            grid.appendChild(el('div', { 'class': 'us-field' + (wide ? ' is-wide' : '') }, [
                el('div', { 'class': 'us-field__top' }, [
                    el('label', { 'for': id, 'class': 'us-field__label' }, [
                        def.label || key,
                        def.required ? el('span', { 'class': 'us-req', 'aria-hidden': 'true', text: ' *' }) : null
                    ]),
                    counter
                ]),
                control,
                def.description ? el('span', { 'class': 'us-field__desc', text: def.description }) : null,
                error
            ]));
        });
        return grid;
    }

    function renderRepeater(def, items, registry, path) {
        var wrap = el('div', { 'class': 'us-field is-wide us-repeater' });
        var rows = el('ol', { 'class': 'us-repeater__rows' });
        var error = el('span', { 'class': 'us-field__error', role: 'alert' });
        registry[path] = { input: wrap, error: error, def: def, repeater: rows };
        var itemLabel = def.itemLabel || t.item;

        function addRow(data) {
            var rowRegistry = {};
            var li = el('li', { 'class': 'us-row' });
            li.registry = rowRegistry;
            var num = el('span', { 'class': 'us-row__num' });
            var tools = el('div', { 'class': 'us-row__tools' }, [
                button([icon('arrow-up-alt2')], 'us-icon-btn', function () { move(li, -1); }, { 'aria-label': t.moveUp }),
                button([icon('arrow-down-alt2')], 'us-icon-btn', function () { move(li, 1); }, { 'aria-label': t.moveDown }),
                button([icon('no-alt')], 'us-icon-btn is-danger', function () {
                    li.remove();
                    renumber();
                    wrap.dispatchEvent(new Event('input', { bubbles: true }));
                }, { 'aria-label': t.remove })
            ]);
            li.append(el('div', { 'class': 'us-row__head' }, [num, tools]), renderFields(def.itemFields || {}, data || {}, rowRegistry, ''));
            rows.appendChild(li);
            renumber();
            return li;
        }
        function move(li, dir) {
            var sib = dir < 0 ? li.previousElementSibling : li.nextElementSibling;
            if (sib) {
                rows.insertBefore(li, dir < 0 ? sib : sib.nextElementSibling);
                renumber();
                wrap.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
        function renumber() {
            Array.prototype.forEach.call(rows.children, function (li, i) {
                li.querySelector('.us-row__num').textContent = itemLabel + ' ' + (i + 1);
                Object.keys(li.registry).forEach(function (k) {
                    registry[path + '.' + i + '.' + k] = li.registry[k];
                });
            });
        }

        (Array.isArray(items) && items.length ? items : [{}]).forEach(addRow);
        wrap.append(
            el('div', { 'class': 'us-field__top' }, [el('span', { 'class': 'us-field__label' }, [def.label, def.required ? el('span', { 'class': 'us-req', text: ' *' }) : null])]),
            rows,
            button([icon('plus-alt2'), fmt(t.addItem, itemLabel.toLowerCase())], 'button us-repeater__add', function () {
                var li = addRow({});
                li.querySelector('input, textarea').focus();
                wrap.dispatchEvent(new Event('input', { bubbles: true }));
            }),
            error
        );
        return wrap;
    }

    function collectFields(defs, registry, prefix) {
        var data = {};
        Object.keys(defs).forEach(function (key) {
            var def = defs[key];
            var entry = registry[prefix + key];
            if (!entry) {
                return;
            }
            if (def.type === 'objects') {
                var items = [];
                Array.prototype.forEach.call(entry.repeater.children, function (li) {
                    var item = collectFields(def.itemFields || {}, li.registry, '');
                    if (Object.keys(item).length) {
                        items.push(item);
                    }
                });
                if (items.length) {
                    data[key] = items;
                }
                return;
            }
            var v = entry.input.value;
            if (def.type === 'array') {
                v = v.split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
            }
            if (v.length) {
                data[key] = v; // Omitted fields are cleared; keeps the stored JSON small.
            }
        });
        return data;
    }

    function buildInput(def, id, value) {
        var type = def.type || 'string';
        if (value === undefined || value === null) {
            value = '';
        }
        if (type === 'enum') {
            var s = el('select', { id: id }, [el('option', { value: '', text: '—' })]);
            var opts = (def.options || []).slice();
            [value, def['default']].forEach(function (v) {
                if (isToken(v) && opts.indexOf(v) === -1) {
                    opts.unshift(v); // Dynamic value, e.g. {{product_availability}}.
                }
            });
            opts.forEach(function (o) {
                s.appendChild(el('option', { value: o, text: isToken(o) ? tokenLabel(o) : shortLabel(o), selected: o === value }));
            });
            return s;
        }
        if (type === 'text' || type === 'array') {
            var a = el('textarea', { id: id, rows: type === 'array' ? 4 : 3 });
            a.value = Array.isArray(value) ? value.join('\n') : String(value);
            return a;
        }
        var ph = { url: 'https://', date: 'YYYY-MM-DD', duration: 'PT30M', number: '0.00' }[type] || null;
        return el('input', {
            id: id,
            type: 'text',
            value: String(value),
            inputmode: type === 'number' || type === 'integer' ? 'decimal' : null,
            placeholder: ph,
            spellcheck: type === 'url' ? 'false' : null
        });
    }

    // ---- Tokens --------------------------------------------------------------

    function tokenLabel(token) {
        var name = token.replace(/\{\{\s*|\s*\}\}/g, '');
        return '⚡ ' + (cfg.tokens[name] || name);
    }

    function tokenButton(input) {
        var b = button('{ }', 'us-token-btn', function (e) {
            e.preventDefault();
            var open = b.getAttribute('aria-expanded') === 'true';
            closeMenus();
            if (open) {
                return;
            }
            b.setAttribute('aria-expanded', 'true');
            var menu = el('div', { 'class': 'us-tokens', role: 'menu' }, [el('span', { 'class': 'us-tokens__title', text: t.insertToken })]);
            Object.keys(cfg.tokens).forEach(function (name) {
                menu.appendChild(button([el('span', { text: cfg.tokens[name] }), el('code', { text: '{{' + name + '}}' })], 'us-menu__item', function () {
                    insertAtCursor(input, '{{' + name + '}}');
                    closeMenus();
                }, { role: 'menuitem' }));
            });
            b.parentNode.appendChild(menu);
            menu.querySelector('button').focus();
        }, { title: t.insertToken, 'aria-label': t.insertToken, 'aria-haspopup': 'menu', 'aria-expanded': 'false' });
        return b;
    }

    function insertAtCursor(input, text) {
        var start = input.selectionStart == null ? input.value.length : input.selectionStart;
        var end = input.selectionEnd == null ? input.value.length : input.selectionEnd;
        input.value = input.value.slice(0, start) + text + input.value.slice(end);
        input.focus();
        input.setSelectionRange(start + text.length, start + text.length);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // ---- Display rules ---------------------------------------------------------

    function renderRules(cond) {
        var panel = el('div', { 'class': 'us-panel' }, [el('p', { 'class': 'us-muted', text: isGlobal ? t.rulesIntroGlobal : t.rulesIntro })]);
        var groups = {};
        function chips(key, label, options) {
            var box = el('fieldset', { 'class': 'us-chips' }, [el('legend', { text: label })]);
            var selected = cond[key] || [];
            Object.keys(options).forEach(function (value) {
                box.appendChild(el('label', { 'class': 'us-chip-toggle' }, [
                    el('input', { type: 'checkbox', value: value, checked: selected.indexOf(value) !== -1 }),
                    el('span', { text: options[value] })
                ]));
            });
            groups[key] = function () {
                return Array.prototype.map.call(box.querySelectorAll('input:checked'), function (i) { return i.value; });
            };
            panel.appendChild(box);
        }
        function text(key, label, help) {
            var input = el('input', { type: 'text', value: (cond[key] || []).join(', ') });
            groups[key] = function () {
                return input.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            };
            panel.appendChild(el('label', { 'class': 'us-field' }, [
                el('span', { 'class': 'us-field__label', text: label }), input,
                el('span', { 'class': 'us-field__desc', text: help })
            ]));
        }
        if (isGlobal) {
            chips('locations', t.where, cfg.locations);
        }
        chips('post_types', t.postTypes, cfg.postTypes);
        text('categories', t.categories, t.commaHelp);
        chips('user_roles', t.userRoles, cfg.roles);
        if (isGlobal) {
            text('post_ids', t.postIds, t.commaHelp);
        }
        return {
            panel: panel,
            collect: function () {
                var out = {};
                Object.keys(groups).forEach(function (k) { out[k] = groups[k](); });
                return out;
            }
        };
    }

    // ---- Preview -------------------------------------------------------------

    function renderPreview(panel, res) {
        panel.textContent = '';
        var json = JSON.stringify(res.json_ld, null, 2);
        var status = el('div', { 'class': 'us-callout ' + (res.valid ? 'is-ok' : 'is-warn') }, [
            icon(res.valid ? 'yes-alt' : 'warning'),
            el('span', { text: res.valid ? t.previewValid : fmt(t.previewInvalid, res.errors.length) })
        ]);
        if (!res.valid) {
            var ul = el('ul', { 'class': 'us-errlist' });
            res.errors.forEach(function (e) { ul.appendChild(el('li', { text: humanError(e) })); });
            status.appendChild(ul);
        }
        var actions = el('div', { 'class': 'us-preview__actions' }, [
            button([icon('admin-page'), t.copy], 'button', function () { copy(json); })
        ]);
        if (cfg.testUrl) {
            actions.appendChild(el('a', {
                'class': 'button',
                href: 'https://search.google.com/test/rich-results?url=' + encodeURIComponent(cfg.testUrl),
                target: '_blank',
                rel: 'noopener noreferrer'
            }, [icon('external'), t.testGoogle]));
        }
        panel.append(status, el('pre', { 'class': 'us-code', tabindex: '0' }, [highlight(json)]), actions,
            el('p', { 'class': 'us-muted us-preview__note', text: isGlobal ? t.previewNoteGlobal : t.previewNote }));
    }

    function highlight(json) {
        var frag = document.createDocumentFragment();
        var re = /("(?:\\.|[^"\\])*")(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/g;
        var last = 0, m;
        while ((m = re.exec(json))) {
            frag.appendChild(document.createTextNode(json.slice(last, m.index)));
            var cls = m[1] ? (m[2] ? 'k' : 's') : (m[3] ? 'b' : 'n');
            frag.appendChild(el('span', { 'class': 'us-j' + cls, text: m[1] || m[0] }));
            if (m[2]) {
                frag.appendChild(document.createTextNode(m[2]));
            }
            last = re.lastIndex;
        }
        frag.appendChild(document.createTextNode(json.slice(last)));
        return frag;
    }

    function copy(text) {
        var done = function () { toast(t.copied, 'success'); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); });
        } else {
            fallbackCopy(text, done);
        }
    }

    function fallbackCopy(text, done) {
        var a = el('textarea', { 'class': 'us-offscreen', readonly: true });
        a.value = text;
        document.body.appendChild(a);
        a.select();
        try {
            document.execCommand('copy');
            done();
        } catch (e) {
            toast(t.error, 'error');
        }
        a.remove();
    }
})();
