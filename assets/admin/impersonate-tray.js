(function () {
    'use strict';

    var CHANNEL_NAME = 'grav-impersonate';
    var STORAGE_KEY = 'grav:impersonate:event';
    var gravRequest = window.Grav && window.Grav.default && window.Grav.default.Utils
        ? window.Grav.default.Utils.request
        : null;
    var channel = null;
    var currentState = window.GravImpersonateAdminState || { active: false, target: '', mode: '', actor: '' };
    var runtimeConfig = window.GravImpersonateAdminConfig || { confirmOnSwitch: true };
    var applyQueued = false;
    var SWITCH_MODAL_ID = 'impersonate-switch-confirm';

    function text(key, fallback) {
        var texts = runtimeConfig && runtimeConfig.texts ? runtimeConfig.texts : null;
        if (texts && typeof texts[key] === 'string' && texts[key] !== '') {
            return texts[key];
        }
        return fallback;
    }

    function switchMessage(currentTarget, nextTarget) {
        var template = text('switchText', 'You are already logged in as %current%. Switch to %target%?');
        return template
            .replace('%current%', currentTarget || '')
            .replace('%target%', nextTarget || '');
    }

    function iconClass(key, fallback) {
        var icons = runtimeConfig && runtimeConfig.icons ? runtimeConfig.icons : null;
        if (icons && typeof icons[key] === 'string' && icons[key] !== '') {
            return icons[key];
        }
        return fallback;
    }

    function getConfig() {
        if (!window.GravAdmin || !window.GravAdmin.config) {
            return null;
        }

        return window.GravAdmin.config;
    }

    function buildTaskUrl(config, task) {
        var base = (config.base_url_relative || '').replace(/\/+$/, '');
        var sep = config.param_sep || ':';
        var nonce = config.admin_nonce || '';

        if (!base || !nonce) {
            return null;
        }

        return base + '/task' + sep + task + '/admin-nonce' + sep + nonce;
    }

    function findButtonNode() {
        return document.querySelector('#admin-nav-quick-tray li.impersonate-stop, #admin-nav-quick-tray li.impersonate-self');
    }

    function applyButtonState(config, state) {
        var node = findButtonNode();
        if (!node) {
            return;
        }

        var link = node.querySelector('a');
        var icon = node.querySelector('i.fa');
        if (!link || !icon) {
            return;
        }

        var active = !!(state && state.active);
        var mode = state && state.mode ? state.mode : '';
        if (active && mode === 'self') {
            var stopUrl = buildTaskUrl(config, 'stopImpersonate');
            if (!stopUrl) {
                return;
            }

            node.classList.remove('impersonate-self');
            node.classList.add('impersonate-stop');
            node.setAttribute('data-hint', 'Stop frontend impersonation');
            link.setAttribute('href', '#');
            link.setAttribute('data-imp-stop-task-url', stopUrl);
            icon.className = 'fa fa-fw ' + iconClass('stop', 'fa-arrow-right-from-bracket');
            return;
        }

        var selfUrl = buildTaskUrl(config, 'impersonateSelf');
        if (!selfUrl) {
            return;
        }

        node.classList.remove('impersonate-stop');
        node.classList.add('impersonate-self');
        node.setAttribute('data-hint', 'Open frontend as current admin');
        link.removeAttribute('data-imp-stop-task-url');
        link.setAttribute('href', selfUrl);
        icon.className = 'fa fa-fw ' + iconClass('self', 'fa-arrow-right-to-bracket');
    }

    function applyUserListState(active, target) {
        var links = document.querySelectorAll('a.impersonate-user-action');
        if (!links || !links.length) {
            return;
        }

        links.forEach(function (link) {
            var rowTarget = link.getAttribute('data-imp-target') || '';
            var startUrl = link.getAttribute('data-imp-start-url') || '';
            var stopUrl = link.getAttribute('data-imp-stop-url') || '';
            var mode = link.getAttribute('data-imp-mode') || '';
            var icon = link.querySelector('i.fa');

            if (!rowTarget || !startUrl || !stopUrl || !icon) {
                return;
            }

            if (active && target && rowTarget === target) {
                link.setAttribute('href', '#');
                link.setAttribute('title', "Stop impersonate '" + rowTarget + "'");
                link.classList.remove('impersonate-start-action');
                link.classList.add('impersonate-stop-action');
                link.setAttribute('data-imp-stop-task-url', stopUrl);
                link.setAttribute('data-imp-active', '1');
                link.removeAttribute('target');
                icon.className = 'fa ' + iconClass('stop', 'fa-arrow-right-from-bracket');
                return;
            }

            link.setAttribute('href', startUrl);

            if (mode === 'self') {
                // Use self icon and title
                link.setAttribute('title', "Impersonate Self");
                icon.className = 'fa ' + iconClass('self', 'fa-arrow-right-to-bracket');
            } else {
                // Standard user impersonate
                link.setAttribute('title', "Impersonate '" + rowTarget + "'");
                icon.className = 'fa ' + iconClass('start', 'fa-arrow-right-arrow-left');
            }

            link.classList.remove('impersonate-stop-action');
            link.classList.add('impersonate-start-action');
            link.removeAttribute('data-imp-stop-task-url');
            link.setAttribute('data-imp-active', '0');
            link.setAttribute('target', '_blank');
        });
    }

    function applyAllState(payload) {
        var config = getConfig();
        if (config) {
            applyButtonState(config, payload);
        }
        applyUserListState(!!payload.active, payload.target || '');
    }

    function queueApplyState() {
        if (applyQueued) {
            return;
        }
        applyQueued = true;
        window.requestAnimationFrame(function () {
            applyQueued = false;
            applyAllState(currentState);
        });
    }

    function ensureSwitchModal() {
        var existing = document.querySelector('[data-remodal-id="' + SWITCH_MODAL_ID + '"]');
        if (existing) {
            return existing;
        }

        var container = document.createElement('div');
        container.className = 'remodal';
        container.setAttribute('data-remodal-id', SWITCH_MODAL_ID);
        container.setAttribute('data-remodal-options', 'hashTracking: false, closeOnOutsideClick: false');
        container.innerHTML = '' +
            '<form>' +
            '<h1>' + text('switchTitle', 'Confirm Switch') + '</h1>' +
            '<p class="bigger js-imp-switch-text"></p>' +
            '<div class="button-bar">' +
            '<button type="button" data-remodal-action="cancel" class="button secondary remodal-cancel"><i class="fa fa-fw fa-close"></i> ' + text('switchCancel', 'Cancel') + '</button>' +
            ' <button type="button" data-remodal-action="confirm" class="button remodal-confirm"><i class="fa fa-fw fa-check"></i> ' + text('switchConfirm', 'Switch') + '</button>' +
            '</div>' +
            '</form>';
        document.body.appendChild(container);

        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.remodal === 'function') {
            window.jQuery(container).remodal();
        }

        return container;
    }

    function openSwitchModal(currentTarget, nextTarget) {
        return new Promise(function (resolve) {
            if (!window.jQuery || !window.jQuery.remodal || !window.jQuery.fn || typeof window.jQuery.fn.remodal !== 'function') {
                resolve(window.confirm(switchMessage(currentTarget, nextTarget)));
                return;
            }

            var modal = ensureSwitchModal();
            var textNode = modal.querySelector('.js-imp-switch-text');
            if (textNode) {
                textNode.textContent = switchMessage(currentTarget, nextTarget);
            }

            var $ = window.jQuery;
            var $modal = $(modal);
            var instance = $.remodal.lookup[$modal.data('remodal')];
            if (!instance) {
                resolve(window.confirm(switchMessage(currentTarget, nextTarget)));
                return;
            }

            var resolved = false;
            var cleanup = function () {
                $modal.off('confirmation.impersonate-switch');
                $modal.off('cancellation.impersonate-switch');
                $modal.off('closed.impersonate-switch');
            };

            $modal.on('confirmation.impersonate-switch', function () {
                if (resolved) {
                    return;
                }
                resolved = true;
                cleanup();
                resolve(true);
            });

            $modal.on('cancellation.impersonate-switch', function () {
                if (resolved) {
                    return;
                }
                resolved = true;
                cleanup();
                resolve(false);
            });

            $modal.on('closed.impersonate-switch', function () {
                if (resolved) {
                    return;
                }
                resolved = true;
                cleanup();
                resolve(false);
            });

            instance.open();
        });
    }

    function applyEventPayload(payload) {
        if (!payload || payload.type !== 'impersonate_state') {
            return;
        }

        currentState = {
            active: !!payload.active,
            target: payload.target || '',
            mode: payload.mode || '',
            actor: payload.actor || currentState.actor || ''
        };
        applyAllState(currentState);
    }

    function requestJson(url, options) {
        options = options || {};
        var method = (options.method || 'GET').toUpperCase();

        if (gravRequest && method === 'GET') {
            return new Promise(function (resolve) {
                try {
                    gravRequest(url, function (result) {
                        resolve(result || null);
                    });
                } catch (e) {
                    resolve(null);
                }
            });
        }

        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: options.headers || { 'Accept': 'application/json' },
            body: options.body || null
        })
            .then(function (response) { return response && response.ok ? response.json() : null; })
            .catch(function () { return null; });
    }

    function reconcileWithFrontendStatus() {
        var statusUrl = runtimeConfig && runtimeConfig.statusUrl ? runtimeConfig.statusUrl : '';
        if (!statusUrl) {
            return;
        }

        var actor = currentState && currentState.actor ? currentState.actor : '';
        var sep = statusUrl.indexOf('?') === -1 ? '?' : '&';
        var url = statusUrl + sep + 'format=json';
        if (actor) {
            url += '&actor=' + encodeURIComponent(actor);
        }

        requestJson(url)
            .then(function (data) {
                if (!data || data.status !== 'success' || !data.state) {
                    return;
                }

                applyEventPayload({
                    type: 'impersonate_state',
                    active: !!data.state.active,
                    actor: data.state.actor || actor || '',
                    target: data.state.target || '',
                    mode: data.state.mode || '',
                    ts: Date.now()
                });
            })
            .catch(function () { });
    }

    function emitPayload(payload) {
        if (!payload || payload.type !== 'impersonate_state') {
            return;
        }

        if (channel) {
            try {
                channel.postMessage(payload);
            } catch (e) { }
        }

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch (e) { }
    }

    function parseStopTaskUrl(url) {
        if (!url || typeof url !== 'string') {
            return null;
        }

        var decoded = decodeURIComponent(url);
        var match = decoded.match(/^(.*\/impersonate\/stop)\/([^/?#]+)(?:[?#].*)?$/);
        if (!match) {
            return null;
        }

        return {
            endpoint: match[1],
            token: match[2]
        };
    }

    function fetchFrontendStopNonce() {
        var statusUrl = runtimeConfig && runtimeConfig.statusUrl ? runtimeConfig.statusUrl : '';
        if (!statusUrl) {
            return Promise.resolve(null);
        }

        var actor = currentState && currentState.actor ? currentState.actor : '';
        var sep = statusUrl.indexOf('?') === -1 ? '?' : '&';
        var url = statusUrl + sep + 'format=json';
        if (actor) {
            url += '&actor=' + encodeURIComponent(actor);
        }

        return requestJson(url)
            .then(function (data) {
                if (!data || data.status !== 'success' || !data.state) {
                    return null;
                }

                var nonce = data.state.stop_nonce || '';
                return nonce || null;
            })
            .catch(function () {
                return null;
            });
    }

    function stopImpersonationInBackground(link) {
        var taskHref = link.getAttribute('data-imp-stop-task-url') || link.getAttribute('href');
        if (!taskHref || taskHref === '#') {
            return;
        }

        var config = getConfig();
        if (!config) {
            return;
        }

        var sep = config.param_sep || ':';
        var taskUrl = taskHref + '/format' + sep + 'json';

        requestJson(taskUrl)
            .then(function (data) {
                if (!data || data.status !== 'success' || !data.url) {
                    return;
                }

                var stopData = parseStopTaskUrl(data.url);
                if (!stopData) {
                    return;
                }

                return fetchFrontendStopNonce().then(function (stopNonce) {
                    if (!stopNonce) {
                        return;
                    }

                    var body = new URLSearchParams();
                    body.set('token', stopData.token);
                    body.set('impersonate-stop-nonce', stopNonce);
                    body.set('format', 'json');

                    return fetch(stopData.endpoint, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: body.toString()
                    }).then(function (response) {
                        return response && response.ok ? response.json() : null;
                    }).then(function (postResult) {
                        if (!postResult || postResult.status !== 'success') {
                            return;
                        }

                        currentState = { active: false, target: '', mode: '', actor: currentState.actor || '' };
                        applyAllState(currentState);
                        emitPayload({ type: 'impersonate_state', active: false, target: '', ts: Date.now() });
                    });
                });
            })
            .catch(function () { });
    }

    document.addEventListener('click', function (event) {
        var link = event.target && event.target.closest
            ? event.target.closest('#admin-nav-quick-tray li.impersonate-stop a, a.impersonate-stop-action')
            : null;

        if (!link) {
            return;
        }

        event.preventDefault();
        stopImpersonationInBackground(link);
    });

    document.addEventListener('click', function (event) {
        var link = event.target && event.target.closest
            ? event.target.closest('a.impersonate-start-action, #admin-nav-quick-tray li.impersonate-self a')
            : null;

        if (!link) {
            return;
        }

        if (!runtimeConfig.confirmOnSwitch) {
            return;
        }

        var isSelfQuickTray = !!(link.closest && link.closest('#admin-nav-quick-tray li.impersonate-self'));
        var target = isSelfQuickTray
            ? (currentState.actor || '')
            : (link.getAttribute('data-imp-target') || '');
        if (!currentState.active || !currentState.target || !target || currentState.target === target) {
            return;
        }

        event.preventDefault();
        openSwitchModal(currentState.target, target).then(function (ok) {
            if (!ok) {
                return;
            }

            var href = link.getAttribute('href');
            var targetAttr = link.getAttribute('target');
            if (!href) {
                return;
            }

            if (targetAttr && targetAttr !== '_self') {
                window.open(href, targetAttr);
                return;
            }

            window.location.href = href;
        });
    });

    if ('BroadcastChannel' in window) {
        try {
            channel = new BroadcastChannel(CHANNEL_NAME);
            channel.addEventListener('message', function (event) {
                applyEventPayload(event && event.data ? event.data : null);
            });
        } catch (e) { }
    }

    window.addEventListener('storage', function (event) {
        if (!event || event.key !== STORAGE_KEY || !event.newValue) {
            return;
        }

        try {
            applyEventPayload(JSON.parse(event.newValue));
        } catch (e) { }
    });

    applyAllState(currentState);
    reconcileWithFrontendStatus();

    if ('MutationObserver' in window) {
        var observer = new MutationObserver(function () {
            queueApplyState();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
})();
