/**
 * Mercury Schema inside the Elementor editor.
 * Adds a button to Elementor's top bar (or its menu on older versions) that
 * opens the regular schema editor (editor-ui.js) in a dialog.
 */
(function () {
    'use strict';

    var cfg = window.MercurySchemaElementor;
    if (!cfg) {
        return;
    }
    var dialog = null;
    var lastFocus = null;

    function open() {
        if (!dialog) {
            dialog = build();
            document.body.appendChild(dialog);
            window.MercurySchemaEditor.mount(dialog.querySelector('.ms-app'));
        }
        lastFocus = document.activeElement;
        dialog.hidden = false;
        document.documentElement.classList.add('ms-modal-open');
        dialog.querySelector('.ms-modal__close').focus();
    }

    function close() {
        if (!dialog || dialog.hidden) {
            return;
        }
        dialog.hidden = true; // Unsaved edits stay in the dialog until it is reopened.
        document.documentElement.classList.remove('ms-modal-open');
        if (lastFocus && lastFocus.focus) {
            lastFocus.focus();
        }
    }

    function build() {
        var wrap = document.createElement('div');
        wrap.className = 'ms-modal wp-core-ui'; // wp-core-ui: WordPress button styles apply only inside it.
        wrap.hidden = true;
        wrap.setAttribute('role', 'dialog');
        wrap.setAttribute('aria-modal', 'true');
        wrap.setAttribute('aria-labelledby', 'ms-modal-title');

        var backdrop = document.createElement('div');
        backdrop.className = 'ms-modal__backdrop';
        backdrop.addEventListener('click', close);

        var panel = document.createElement('div');
        panel.className = 'ms-modal__panel';

        var head = document.createElement('div');
        head.className = 'ms-modal__head';
        var mark = document.createElement('span');
        mark.className = 'ms-logo ms-logo--sm';
        mark.setAttribute('aria-hidden', 'true');
        mark.innerHTML = cfg.logo; // Static SVG from the server (Brand::mark).
        var titles = document.createElement('div');
        var title = document.createElement('strong');
        title.id = 'ms-modal-title';
        title.textContent = cfg.i18n.title;
        var sub = document.createElement('span');
        sub.textContent = cfg.i18n.subtitle;
        titles.append(title, sub);
        var x = document.createElement('button');
        x.type = 'button';
        x.className = 'ms-modal__close';
        x.setAttribute('aria-label', cfg.i18n.close);
        x.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
        x.addEventListener('click', close);
        head.append(mark, titles, x);

        var body = document.createElement('div');
        body.className = 'ms-modal__body';
        var app = document.createElement('div');
        app.className = 'ms-app';
        body.appendChild(app);

        panel.append(head, body);
        wrap.append(backdrop, panel);

        wrap.addEventListener('keydown', function (e) {
            e.stopPropagation(); // Keep Elementor's shortcuts out of our fields.
            if (e.key === 'Escape' && !wrap.querySelector('.ms-menu, .ms-tokens, .ms-picker:not([hidden])')) {
                close();
            }
        });
        return wrap;
    }

    function icon(React) {
        var h = React.createElement;
        var Svg = window.elementorV2 && elementorV2.ui && elementorV2.ui.SvgIcon;
        return function (props) {
            var kids = [
                h('g', { key: 'm', transform: 'translate(21,0) skewX(-12)' },
                    h('polyline', { points: '14,86 14,14 58,66 102,14 102,86', fill: 'none', stroke: 'currentColor', strokeWidth: 26, strokeLinecap: 'round', strokeLinejoin: 'round' })),
                h('path', { key: 's', transform: 'translate(142,-10) scale(1.3)', fill: 'currentColor', d: 'M0,-10 C1.2,-2.4 2.4,-1.2 10,0 C2.4,1.2 1.2,2.4 0,10 C-1.2,2.4 -2.4,1.2 -10,0 C-2.4,-1.2 -1.2,-2.4 0,-10Z' })
            ];
            return Svg ? h(Svg, Object.assign({ viewBox: '0 -26 154 126' }, props), kids) : h('svg', { viewBox: '0 -26 154 126', width: 20, height: 20 }, kids);
        };
    }

    // Elementor 3.16+ / 4.x: a button in the top bar, next to Elementor's own tools.
    var bar = window.elementorV2 && elementorV2.editorAppBar;
    if (bar && bar.utilitiesMenu && window.React) {
        bar.utilitiesMenu.registerAction({ id: 'mercury-schema', priority: 5, props: { title: cfg.i18n.button, icon: icon(window.React), onClick: open } });
        return;
    }

    // Older Elementor: an item in the panel's hamburger menu.
    if (window.jQuery) {
        jQuery(window).on('elementor/init', function () {
            elementor.on('panel:init', function () {
                try {
                    elementor.modules.layouts.panel.pages.menu.Menu.addItem({ name: 'mercury-schema', icon: 'eicon-code', title: cfg.i18n.button, callback: open }, 'more');
                } catch (e) {
                    // No supported location; the Schema Markup box stays on the WordPress edit screen.
                }
            });
        });
    }
})();
