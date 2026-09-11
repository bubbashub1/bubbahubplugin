(function () {
  'use strict';

  var config = window.BubbaHubWP || {};
  var wpUrl = config.apiUrl;
  var wpNonce = config.nonce;
  var googleUrl = 'https://script.google.com/macros/s/AKfycbwL70V_m3FIoxNTuc0MiI_tdAIfMlJrt0Zw62vnefNwctEmg5Pe93UhFaWzaRl3gTv1/exec';

  if (!wpUrl || !window.fetch) return;

  var nativeFetch = window.fetch.bind(window);

  function cloneRequestOptions(init) {
    var options = Object.assign({}, init || {});
    options.credentials = 'same-origin';
    options.headers = Object.assign({}, options.headers || {});
    if (wpNonce) options.headers['X-WP-Nonce'] = wpNonce;
    if (options.method && String(options.method).toUpperCase() === 'POST') {
      options.headers['Content-Type'] = 'application/json';
    }
    return options;
  }

  window.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || '';

    if (url !== googleUrl) {
      return nativeFetch(input, init);
    }

    var options = cloneRequestOptions(init);

    // Keep the Figma app's original Google-Sheets response contract while
    // WordPress becomes the actual data source. The old app expects JSON
    // rather than a raw WordPress REST array, so normalise the response here.
    return nativeFetch(wpUrl, options).then(function (response) {
      if (!response || typeof response.clone !== 'function') return response;

      var contentType = response.headers && response.headers.get
        ? (response.headers.get('content-type') || '')
        : '';
      if (contentType.indexOf('application/json') === -1) return response;

      return response.clone().json().then(function (payload) {
        var data = Array.isArray(payload) ? payload : (payload && Array.isArray(payload.data) ? payload.data : null);
        if (!data) return response;

        // Support common response shapes used by the existing Figma app.
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
