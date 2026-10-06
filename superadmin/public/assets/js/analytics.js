(function () {
    'use strict';

    var tag = document.currentScript;
    if (!tag || navigator.doNotTrack === '1' || window.doNotTrack === '1') {
        return;
    }

    var provider = tag.getAttribute('data-provider');
    var base = tag.getAttribute('data-url') || '';
    var siteId = tag.getAttribute('data-site-id') || '';
    if (!/^https:\/\//.test(base) || siteId === '') {
        return;
    }

    var load = function (src, attributes) {
        var script = document.createElement('script');
        script.async = true;
        script.src = src;
        Object.keys(attributes || {}).forEach(function (name) {
            script.setAttribute(name, attributes[name]);
        });
        document.head.appendChild(script);
    };

    if (provider === 'matomo') {
        var queue = window._paq = window._paq || [];
        queue.push(['disableCookies']);
        queue.push(['trackPageView']);
        queue.push(['enableLinkTracking']);
        queue.push(['setTrackerUrl', base + 'matomo.php']);
        queue.push(['setSiteId', siteId]);
        load(base + 'matomo.js');
    } else if (provider === 'plausible') {
        load(base + 'js/script.js', { 'data-domain': siteId, 'data-api': base + 'api/event' });
    }
})();
