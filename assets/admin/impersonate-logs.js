(function () {
    'use strict';

    var gravRequest = window.Grav && window.Grav.default && window.Grav.default.Utils
        ? window.Grav.default.Utils.request
        : null;

    function isVisible(el) {
        if (!el) {
            return false;
        }

        if (el.offsetParent !== null) {
            return true;
        }

        var rects = el.getClientRects ? el.getClientRects() : null;
        return !!(rects && rects.length);
    }

    function initLogViewer() {
        var viewer = document.querySelector('.js-impersonate-log-viewer');
        if (!viewer) {
            return;
        }

        var textarea = viewer.querySelector('.js-impersonate-log-content');
        if (!textarea) {
            return;
        }
        var refreshButton = viewer.querySelector('.js-impersonate-log-refresh');
        var clearButton = viewer.querySelector('.js-impersonate-log-clear');

        var fetchUrl = viewer.getAttribute('data-fetch-url') || '';
        var clearUrl = viewer.getAttribute('data-clear-url') || '';
        var loadingText = viewer.getAttribute('data-loading-text') || 'Loading log...';
        var failedText = viewer.getAttribute('data-load-failed-text') || 'Failed to load log. Open Logs tab to retry.';

        if (!fetchUrl && window.GravAdmin && window.GravAdmin.config) {
            var config = window.GravAdmin.config;
            var base = (config.base_url_relative || '').replace(/\/+$/, '');
            var sep = config.param_sep || ':';
            var nonce = config.admin_nonce || '';
            if (base && nonce) {
                fetchUrl = base + '/task' + sep + 'getImpersonateLog/admin-nonce' + sep + nonce;
            }
        }

        if (!fetchUrl) {
            textarea.value = failedText;
            return;
        }

        var loaded = false;
        var loading = false;
        var clearing = false;
        var retryTimer = null;

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
                headers: options.headers || {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: options.body || null
            })
                .then(function (response) {
                    if (!response || !response.ok) {
                        return null;
                    }

                    return response.json();
                })
                .catch(function () {
                    return null;
                });
        }

        function load() {
            if (loaded || loading || !isVisible(viewer)) {
                return;
            }

            loading = true;
            textarea.value = loadingText;
            var sep = fetchUrl.indexOf('?') === -1 ? '?' : '&';
            requestJson(fetchUrl + sep + 'format=json')
                .then(function (data) {
                    loading = false;
                    if (!data || data.status !== 'success') {
                        textarea.value = failedText;
                        return;
                    }

                    textarea.value = data.content || '';
                    loaded = true;
                })
                .catch(function () {
                    loading = false;
                    textarea.value = failedText;
                });
        }

        function forceReload() {
            loaded = false;
            load();
            if (!loaded) {
                triggerLoadWithRetry();
            }
        }

        function clearLog() {
            if (clearing || !clearUrl) {
                return;
            }

            clearing = true;
            if (clearButton) {
                clearButton.disabled = true;
            }

            var sep = clearUrl.indexOf('?') === -1 ? '?' : '&';
            requestJson(clearUrl + sep + 'format=json')
                .then(function (data) {
                    if (!data || data.status !== 'success') {
                        textarea.value = failedText;
                        return;
                    }

                    textarea.value = '';
                    forceReload();
                })
                .catch(function () {
                    textarea.value = failedText;
                })
                .finally(function () {
                    clearing = false;
                    if (clearButton) {
                        clearButton.disabled = false;
                    }
                });
        }

        function triggerLoadWithRetry() {
            var attempts = 0;
            if (retryTimer) {
                window.clearInterval(retryTimer);
                retryTimer = null;
            }

            retryTimer = window.setInterval(function () {
                attempts += 1;
                load();
                if (loaded || attempts >= 20) {
                    window.clearInterval(retryTimer);
                    retryTimer = null;
                }
            }, 100);
        }

        load();

        document.addEventListener('click', function (event) {
            var tabTrigger = event.target && event.target.closest
                ? event.target.closest('.tab-nav a, .tabs-nav a, .tabs-navigation a, [data-tabid], a[href*="#logs"]')
                : null;
            if (!tabTrigger) {
                return;
            }

            triggerLoadWithRetry();
        });

        window.addEventListener('hashchange', function () {
            triggerLoadWithRetry();
        });

        if (refreshButton) {
            refreshButton.addEventListener('click', function (event) {
                event.preventDefault();
                forceReload();
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', function (event) {
                event.preventDefault();
                clearLog();
            });
        }

        if ('MutationObserver' in window) {
            var observer = new MutationObserver(function () {
                load();
            });
            observer.observe(document.body, { childList: true, subtree: true, attributes: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLogViewer);
        return;
    }

    initLogViewer();
})();
