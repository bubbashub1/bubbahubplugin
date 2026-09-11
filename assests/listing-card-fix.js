(function () {
  'use strict';

  var config = window.BubbaHubWP || {};
  var apiUrl = config.apiUrl;
  if (!apiUrl || !window.fetch || !document) return;

  var listings = [];
  var busy = false;
  var lastRun = 0;

  function text(value) {
    return value == null ? '' : String(value).replace(/\s+/g, ' ').trim();
  }

  function esc(value) {
    return text(value).replace(/[&<>"']/g, function (c) {
      return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' })[c];
    });
  }

  function normaliseTitle(value) {
    return text(value).toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
  }

  function findListing(title) {
    var wanted = normaliseTitle(title);
    if (!wanted) return null;
    for (var i = 0; i < listings.length; i++) {
      if (normaliseTitle(listings[i].title) === wanted) return listings[i];
    }
    return null;
  }

  function daysFromHours(hours) {
    if (!hours) return '';
    if (typeof hours === 'string') {
      try { hours = JSON.parse(hours); } catch (e) {
        var simple = hours.match(/Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday/gi);
        return simple ? Array.from(new Set(simple.map(function (d) { return d.slice(0,3); }))).join(', ') : '';
      }
    }
    if (Array.isArray(hours)) {
      return hours.map(function (row) {
        return typeof row === 'string' ? row : (row.day || row.dayName || '');
      }).filter(Boolean).map(function (d) { return text(d).slice(0,3); }).filter(function (v, i, a) { return a.indexOf(v) === i; }).join(', ');
    }
    if (typeof hours === 'object') {
      return Object.keys(hours).filter(function (key) {
        var value = hours[key];
        return value && text(value).toLowerCase() !== 'closed' && text(value) !== '-';
      }).map(function (d) { return d.slice(0,3); }).join(', ');
    }
    return '';
  }

  function getCardTitle(card) {
    var candidates = card.querySelectorAll('h1,h2,h3,h4,h5,h6,a,[role="heading"]');
    for (var i = 0; i < candidates.length; i++) {
      var value = text(candidates[i].textContent);
      if (value && value.toLowerCase() !== 'view details' && value.toLowerCase() !== 'view details →' && value.length < 180) return value;
    }
    return '';
  }

  function getCards() {
    var all = Array.prototype.slice.call(document.querySelectorAll('#root *'));
    var cards = [];
    all.forEach(function (el) {
      var value = text(el.textContent);
      if (!value || !/view\s+details/i.test(value)) return;
      var node = el;
      for (var i = 0; i < 7 && node && node !== document.body; i++, node = node.parentElement) {
        var title = getCardTitle(node);
        if (title && node.querySelector('button,a')) {
          if (cards.indexOf(node) === -1) cards.push(node);
          break;
        }
      }
    });
    return cards;
  }

  function decorateCard(card, item) {
    if (!card || !item || card.getAttribute('data-bh-card-fixed') === '1') return;

    card.setAttribute('data-bh-card-fixed', '1');
    card.classList.add('bubbahub-listing-card');

    var imageUrl = item.image || '';
    var city = item.city || '';
    var region = item.region || '';
    var age = item.ageRange || '';
    var price = item.isFree ? 'Free' : (item.price || '');
    var days = daysFromHours(item.openingHours || item.business_hours || item.timetable);
    var description = text(item.description).replace(/<[^>]+>/g, '');
    var title = text(item.title);
    var existingImg = card.querySelector('img');

    var media = document.createElement('div');
    media.className = 'bubbahub-card-media';
    if (existingImg) {
      existingImg.remove();
      media.appendChild(existingImg);
    } else if (imageUrl) {
      var img = document.createElement('img');
      img.src = imageUrl;
      img.alt = title;
      img.loading = 'lazy';
      media.appendChild(img);
    } else {
      media.innerHTML = '<div class="bubbahub-card-placeholder" aria-hidden="true">BubbaHub</div>';
    }

    var body = document.createElement('div');
    body.className = 'bubbahub-card-enhanced-content';
    body.innerHTML =
      '<div class="bubbahub-card-badges">' +
        (item.isFeatured ? '<span class="bubbahub-badge">Featured</span>' : '') +
        (item.isClaimed ? '<span class="bubbahub-badge">Verified</span>' : '') +
      '</div>' +
      '<a class="bubbahub-card-title" href="' + esc(item.url || '#') + '">' + esc(title) + '</a>' +
      ((city || region) ? '<div class="bubbahub-card-location">' + esc([city, region].filter(Boolean).join(', ')) + '</div>' : '') +
      '<div class="bubbahub-card-meta">' +
        (age ? '<span>Age ' + esc(age) + '</span>' : '') +
        (price ? '<span>' + esc(price) + '</span>' : '') +
        (days ? '<span>' + esc(days) + '</span>' : '') +
      '</div>' +
      (description ? '<div class="bubbahub-card-description">' + esc(description.slice(0, 125)) + (description.length > 125 ? '…' : '') + '</div>' : '') +
      '<a class="bubbahub-card-view" href="' + esc(item.url || '#') + '">View details <span aria-hidden="true">→</span></a>';

    card.innerHTML = '';
    card.appendChild(media);
    card.appendChild(body);
  }

  function apply() {
    if (busy || !listings.length) return;
    var now = Date.now();
    if (now - lastRun < 400) return;
    lastRun = now;
    busy = true;
    try {
      getCards().forEach(function (card) {
        var title = getCardTitle(card);
        var item = findListing(title);
        if (item) decorateCard(card, item);
      });
    } finally {
      busy = false;
    }
  }

  function load() {
    fetch(apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'per_page=100', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (payload) {
        listings = Array.isArray(payload) ? payload : ((payload && (payload.data || payload.listings || payload.results)) || []);
        if (!Array.isArray(listings)) listings = [];
        apply();
        setTimeout(apply, 700);
        setTimeout(apply, 1600);
      })
      .catch(function () {});
  }

  function start() {
    load();
    var root = document.getElementById('root');
    if (!root || !window.MutationObserver) return;
    var observer = new MutationObserver(function () { apply(); });
    observer.observe(root, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
