(function () {
    'use strict';

    var CHANNEL_NAME = 'grav-impersonate';
    var STORAGE_KEY = 'grav:impersonate:event';
    var gravRequest = window.Grav && window.Grav.default && window.Grav.default.Utils
        ? window.Grav.default.Utils.request
        : null;

    function getState() {
        var state = window.GravImpersonateSyncState;
        if (!state || typeof state.active === 'undefined') {
            return null;
        }

        return {
            type: 'impersonate_state',
            active: !!state.active,
            actor: state.actor || '',
            target: state.target || '',
            mode: state.mode || '',
            ts: Date.now()
        };
    }

    function emit(payload) {
        if (!payload) {
            return;
        }

        if ('BroadcastChannel' in window) {
            try {
                var channel = new BroadcastChannel(CHANNEL_NAME);
                channel.postMessage(payload);
                channel.close();
            } catch (e) {}
        }

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch (e) {}
    }

    function normalizeStatusPayload(data) {
        if (!data || data.status !== 'success' || !data.state) {
            return null;
        }

        return {
            type: 'impersonate_state',
            active: !!data.state.active,
            actor: data.state.actor || '',
            target: data.state.target || '',
            mode: data.state.mode || '',
            ts: Date.now()
        };
    }

    function requestJson(url) {
        if (gravRequest) {
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

        if (!window.fetch) {
            return Promise.resolve(null);
        }

        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) { return response && response.ok ? response.json() : null; })
            .catch(function () { return null; });
    }

    function requestFallbackState() {
        return requestJson('/impersonate/status?format=json')
            .then(function (data) {
                return normalizeStatusPayload(data);
            })
            .catch(function () {
                return null;
            });
    }

    var payload = getState();
    if (payload) {
        emit(payload);
        return;
    }

    requestFallbackState().then(function (fallbackPayload) {
        emit(fallbackPayload);
    });
})();
