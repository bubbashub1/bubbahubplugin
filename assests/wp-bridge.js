(function () {
  'use strict';

  var config = window.BubbaHubWP || {};
  var wpUrl = config.frontendListingsUrl || config.apiUrl;
  var wpNonce = config.nonce;
  var googleUrl = 'https://script.google.com/macros/s/AKfycbwL70V_m3FIoxNTuc0MiI_tdAIfMlJrt0Zw62vnefNwctEmg5Pe93UhFaWzaRl3gTv1/exec';

  if (!wpUrl || !window.fetch) return;

  var nativeFetch = window.fetch.bind(window);

  function cloneRequestOptions(init) {
    var options = Object.assign({}, init || {});
    options.credentials = 'same-origin';
    options.headers = Object.assign({}, options.headers || {});
    if (wpNonce) options.headers['X-WP-Nonce'] = wpNonce;
    return options;
  }

  function isGoogleListingsRequest(url) {
    try {
      var absolute = new URL(url, window.location.href).href;
      return absolute.indexOf(googleUrl) === 0;
    } catch (e) {
      return String(url || '').indexOf(googleUrl) === 0;
    }
  }

  window.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || '';
    if (!isGoogleListingsRequest(url)) return nativeFetch(input, init);

    var options = cloneRequestOptions(init);
    return nativeFetch(wpUrl, options).then(function (response) {
      if (!response || typeof response.clone !== 'function') return response;

      var contentType = response.headers && response.headers.get
        ? (response.headers.get('content-type') || '')
        : '';
      if (contentType.indexOf('application/json') === -1) return response;

      return response.clone().json().then(function (payload) {
        var data = Array.isArray(payload)
          ? payload
          : (payload && Array.isArray(payload.data) ? payload.data : []);

        var wrapped = Object.assign({}, payload && !Array.isArray(payload) ? payload : {}, {
          data: data,
          listings: data,
          results: data,
          success: true
        });

        return new Response(JSON.stringify(wrapped), {
          status: response.status,
          statusText: response.statusText,
          headers: response.headers
        });
      }).catch(function () {
        return response;
      });
    });
  };
})();
