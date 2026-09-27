/* Bubba Hub standalone API client.
 * Safe for static HTML pages: no WordPress dependency and no database credentials in JS.
 */
(function (window) {
  'use strict';

  var DEFAULT_BASE = '/beta/api/';
  var config = window.BubbaHubConfig || {};
  var base = String(config.apiBase || DEFAULT_BASE).replace(/\/+$/, '') + '/';

  function request(path, options) {
    options = options || {};
    var url = base + String(path || '').replace(/^\/+/, '');
    var fetchOptions = {
      method: options.method || 'GET',
      headers: Object.assign({ 'Accept': 'application/json' }, options.headers || {})
    };
    if (options.body !== undefined) {
      fetchOptions.headers['Content-Type'] = 'application/json';
      fetchOptions.body = JSON.stringify(options.body);
    }
    return fetch(url, fetchOptions).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok) {
          var message = data && data.error ? data.error : 'API request failed';
          throw new Error(message);
        }
        return data;
      });
    });
  }

  function activities(filters) {
    var params = new URLSearchParams();
    filters = filters || {};
    Object.keys(filters).forEach(function (key) {
      var value = filters[key];
      if (value !== undefined && value !== null && value !== '') {
        params.set(key, value);
      }
    });
    return request('activities' + (params.toString() ? '?' + params.toString() : ''));
  }

  window.BubbaHubAPI = {
    baseUrl: base,
    get: request,
    activities: activities
  };
})(window);
