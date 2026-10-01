/**
 * UnlimitedSchema metabox UI. Vanilla JS, no build step.
 * Everything goes through the REST API; this file holds no schema logic.
 */
(function () {
    'use strict';

    var cfg = window.UnlimitedSchemaData;
    if (!cfg) {
        return;
    }
    var t = cfg.i18n;
    var app;
    var types = {};
    var notice;
    var list;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        app = document.getElementById('unlimited-schema-app');
        if (!app) {
            return;
        }
        // The metabox sits inside the classic editor's <form>; Enter must not submit the post.
        app.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
                event.preventDefault();
            }
        });
        Promise.all([api('schema-types'), api('schemas/' + cfg.postId)])
            .then(function (res) {
                types = res[0];
                renderShell();
                renderList(res[1].schemas || []);
            })
            .catch(function (err) {
                app.textContent = err.message || t.error;
            });
    }

    // ---- REST -------------------------------------------------------------

    function api(path, method, body) {
        var opts = {
            method: method || 'GET',
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': cfg.nonce }
        };
        if (body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        return fetch(cfg.restUrl + path, opts).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (json) {
                if (!response.ok) {
                    var err = new Error(json.message || t.error);
                    err.errors = (json.data && json.data.errors) || [];
                    throw err;
                }
                return json;
            });
        });
    }

    // ---- DOM helpers ------------------------------------------------------

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (key) {
            var value = attrs[key];
            if (value === null || value === undefined || value === false) {
                return;
            }
            if (key === 'text') {
                node.textContent = value;
            } else if (key.indexOf('on') === 0) {
                node.addEventListener(key.slice(2), value);
            } else if (value === true) {
                node.setAttribute(key, '');
            } else {
                node.setAttribute(key, value);
            }
        });
        (children || []).forEach(function (child) {
            if (child) {
                node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
            }
        });
        return node;
    }

    function showNotice(message, kind) {
        notice.textContent = message;
        notice.className = 'us-notice' + (kind ? ' us-notice--' + kind : '');
    }

    function shortLabel(value) {
        // "https://schema.org/InStock" -> "InStock"
        return String(value).replace(/^https?:\/\/schema\.org\//, '');
    }

    // ---- Rendering --------------------------------------------------------

    function renderShell() {
        app.textContent = '';

        var select = el('select', { 'class': 'us-type-select', 'aria-label': t.selectType }, [
            el('option', { value: '', text: t.selectType })
        ]);
        Object.keys(types).forEach(function (name) {
            select.appendChild(el('option', { value: name, text: types[name].label || name }));
        });

        var addBtn = el('button', {
            type: 'button',
            'class': 'button',
            text: t.add,
            onclick: function () {
                if (!select.value) {
                    select.focus();
                    return;
                }
                addBtn.disabled = true;
                api('schemas/' + cfg.postId, 'POST', { type: select.value, data: {}, conditions: {} })
                    .then(function (res) {
                        var card = renderCard(res.schema);
                        list.appendChild(card);
                        card.open = true;
                        updateEmptyState();
                        select.value = '';
                        showNotice(t.added, 'success');
                    })
                    .catch(function (err) { showNotice(err.message, 'error'); })
                    .then(function () { addBtn.disabled = false; });
            }
        });

        var datalist = el('datalist', { id: 'us-tokens' });
        cfg.tokens.forEach(function (token) {
            datalist.appendChild(el('option', { value: token }));
        });

        notice = el('div', { 'class': 'us-notice', role: 'status', 'aria-live': 'polite' });
        list = el('div', { 'class': 'us-list' });

        app.appendChild(el('div', { 'class': 'us-toolbar' }, [select, addBtn]));
        app.appendChild(notice);
        app.appendChild(list);
        app.appendChild(datalist);
        app.appendChild(el('p', { 'class': 'us-muted us-tokens-help' }, [
            t.tokensHelp + ' ',
            el('code', { text: cfg.tokens.join(' ') })
        ]));
    }

    function renderList(schemas) {
        list.textContent = '';
        schemas.forEach(function (schema) {
            list.appendChild(renderCard(schema));
        });
        updateEmptyState();
    }

    function updateEmptyState() {
        var empty = list.querySelector('.us-empty');
        var hasCards = list.querySelector('.us-card');
        if (!hasCards && !empty) {
            list.appendChild(el('p', { 'class': 'us-empty us-muted', text: t.empty }));
        } else if (hasCards && empty) {
            empty.remove();
        }
    }

    function renderCard(schema) {
        var def = types[schema.type] || { label: schema.type, fields: {} };
        var inputs = {};
        var errorSlots = {};

        var status = el('span', { 'class': 'us-card__status' });
        var toggle = el('input', {
            type: 'checkbox',
            checked: schema.enabled,
            onchange: function () {
                toggle.disabled = true;
                api('schemas/' + cfg.postId + '/' + schema.id, 'PUT', { enabled: toggle.checked })
                    .then(function (res) {
                        schema.enabled = res.schema.enabled;
                        setBadge();
                    })
                    .catch(function (err) {
                        toggle.checked = !toggle.checked;
                        showNotice(err.message, 'error');
                    })
                    .then(function () { toggle.disabled = false; });
            }
        });
        var badge = el('span', { 'class': 'us-badge' });

        function setBadge() {
            badge.textContent = schema.enabled ? t.enabled : t.disabled;
            badge.className = 'us-badge' + (schema.enabled ? ' us-badge--on' : '');
        }
        setBadge();

        var fieldsWrap = el('div', { 'class': 'us-fields' });
        Object.keys(def.fields).forEach(function (key) {
            var field = def.fields[key];
            var id = 'us-' + schema.id + '-' + key;
            var input = buildInput(field, id, schema.data[key]);
            var error = el('span', { 'class': 'us-field__error', role: 'alert' });
            inputs[key] = { node: input, field: field };
            errorSlots[key] = error;

            var label = el('label', { 'for': id, 'class': 'us-field__label' }, [
                field.label || key,
                field.required ? el('span', { 'class': 'us-required', title: t.required, text: ' *' }) : null
            ]);
            fieldsWrap.appendChild(el('div', { 'class': 'us-field' + (field.type === 'text' || field.type === 'array' ? ' us-field--wide' : '') }, [
                label,
                input,
                field.description ? el('span', { 'class': 'us-muted us-field__desc', text: field.description }) : null,
                error
            ]));
        });

        var cond = schema.conditions || {};
        var condInputs = {
            post_types: el('input', { type: 'text', value: (cond.post_types || []).join(', ') }),
            categories: el('input', { type: 'text', value: (cond.categories || []).join(', ') }),
            user_roles: el('input', { type: 'text', value: (cond.user_roles || []).join(', ') }),
            post_ids: el('input', { type: 'text', value: (cond.post_ids || []).join(', ') })
        };
        var condLabels = { post_types: t.postTypes, categories: t.categories, user_roles: t.userRoles, post_ids: t.postIds };
        var condWrap = el('details', { 'class': 'us-conditions' }, [
            el('summary', { text: t.conditions }),
            el('p', { 'class': 'us-muted', text: t.conditionHelp })
        ]);
        var condGrid = el('div', { 'class': 'us-fields' });
        Object.keys(condInputs).forEach(function (key) {
            condGrid.appendChild(el('label', { 'class': 'us-field' }, [
                el('span', { 'class': 'us-field__label', text: condLabels[key] }),
                condInputs[key]
            ]));
        });
        condWrap.appendChild(condGrid);

        function collect() {
            var data = {};
            Object.keys(inputs).forEach(function (key) {
                var value = inputs[key].node.value;
                if (inputs[key].field.type === 'array') {
                    value = value.split(/\r?\n/).map(function (v) { return v.trim(); }).filter(Boolean);
                }
                if (value.length) {
                    data[key] = value; // Omitted fields are cleared; keeps the stored JSON small.
                }
            });
            var conditions = {};
            Object.keys(condInputs).forEach(function (key) {
                conditions[key] = condInputs[key].value.split(',').map(function (v) { return v.trim(); }).filter(Boolean);
            });
            return { data: data, conditions: conditions };
        }

        function showErrors(errors) {
            Object.keys(errorSlots).forEach(function (key) {
                errorSlots[key].textContent = '';
                inputs[key].node.classList.remove('us-invalid');
            });
            (errors || []).forEach(function (e) {
                if (errorSlots[e.field]) {
                    errorSlots[e.field].textContent = e.message;
                    inputs[e.field].node.classList.add('us-invalid');
                }
            });
        }

        var saveBtn = el('button', {
            type: 'button',
            'class': 'button button-primary',
            text: t.save,
            onclick: function () {
                saveBtn.disabled = true;
                api('schemas/' + cfg.postId + '/' + schema.id, 'PUT', collect())
                    .then(function (res) {
                        schema.data = res.schema.data;
                        schema.conditions = res.schema.conditions;
                        showErrors([]);
                        status.textContent = '';
                        showNotice(t.saved, 'success');
                    })
                    .catch(function (err) {
                        showErrors(err.errors);
                        showNotice(err.message, 'error');
                    })
                    .then(function () { saveBtn.disabled = false; });
            }
        });

        var validateBtn = el('button', {
            type: 'button',
            'class': 'button',
            text: t.validate,
            onclick: function () {
                api('validate', 'POST', { type: schema.type, data: collect().data })
                    .then(function (res) {
                        showErrors(res.errors);
                        showNotice(res.valid ? t.valid : t.invalid, res.valid ? 'success' : 'error');
                    })
                    .catch(function (err) { showNotice(err.message, 'error'); });
            }
        });

        var card;
        var deleteBtn = el('button', {
            type: 'button',
            'class': 'button-link button-link-delete',
            text: t['delete'],
            onclick: function () {
                if (!window.confirm(t.confirmDelete)) {
                    return;
                }
                api('schemas/' + cfg.postId + '/' + schema.id, 'DELETE')
                    .then(function () {
                        card.remove();
                        updateEmptyState();
                        showNotice(t.deleted, 'success');
                    })
                    .catch(function (err) { showNotice(err.message, 'error'); });
            }
        });

        card = el('details', { 'class': 'us-card' }, [
            el('summary', { 'class': 'us-card__head' }, [
                el('strong', { text: def.label || schema.type }),
                badge,
                status,
                el('small', { 'class': 'us-muted', text: schema.id })
            ]),
            el('div', { 'class': 'us-card__body' }, [
                el('label', { 'class': 'us-toggle' }, [toggle, ' ' + t.enabled]),
                fieldsWrap,
                condWrap,
                el('div', { 'class': 'us-card__actions' }, [deleteBtn, el('span', { 'class': 'us-spacer' }), validateBtn, saveBtn])
            ])
        ]);

        card.addEventListener('input', function (event) {
            if (event.target !== toggle) {
                status.textContent = t.unsaved;
            }
        });

        return card;
    }

    function buildInput(field, id, value) {
        var type = field.type || 'string';
        if (value === undefined || value === null) {
            value = '';
        }

        if (type === 'enum') {
            var select = el('select', { id: id }, [el('option', { value: '', text: '—' })]);
            (field.options || []).forEach(function (opt) {
                select.appendChild(el('option', { value: opt, text: shortLabel(opt), selected: opt === value }));
            });
            return select;
        }

        if (type === 'text' || type === 'array') {
            var area = el('textarea', { id: id, rows: type === 'array' ? 3 : 2 });
            area.value = Array.isArray(value) ? value.join('\n') : String(value);
            return area;
        }

        return el('input', {
            id: id,
            type: 'text',
            value: String(value),
            list: 'us-tokens',
            inputmode: type === 'number' || type === 'integer' ? 'decimal' : null,
            placeholder: type === 'url' ? 'https://' : (type === 'date' ? 'YYYY-MM-DD' : null)
        });
    }
})();
