(function () {
  'use strict';

  var config = window.BubbaHubWP || {};
  var wpUrl = config.apiUrl;
  var wpNonce = config.nonce;
  var googleUrl = 'https://script.google.com/macros/s/AKfycbwL70V_m3FIoxNTuc0MiI_tdAIfMlJrt0Zw62vnefNwctEmg5Pe93UhFaWzaRl3gTv1/exec';

  if (!wpUrl || !window.fetch) return;

  var nativeFetch = window.fetch.bind(window);

  window.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || '';

    if (url !== googleUrl) {
      return nativeFetch(input, init);
    }

    var options = Object.assign({}, init || {});
    options.credentials = 'same-origin';
    options.headers = Object.assign({}, options.headers || {});

    if (wpNonce) options.headers['X-WP-Nonce'] = wpNonce;
    if (options.method && String(options.method).toUpperCase() === 'POST') {
      options.headers['Content-Type'] = 'application/json';
    }

    return nativeFetch(wpUrl, options);
  };
})();
