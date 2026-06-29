/* GDPR Consent Auditor - front-end banner JS */
(function () {
  'use strict';

  if (typeof GdprCaBanner === 'undefined') {
    return;
  }

  var cfg = GdprCaBanner;
  var labels = cfg.labels || {};

  function $(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }
  function $all(sel, ctx) {
    return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
  }

  var banner;
  var backdrop;

  function mountBannerToBody() {
    banner = $('#gdpr-ca-banner');
    backdrop = $('[data-gdpr-ca-backdrop]');

    if (banner && document.body) {
      // Always move to direct child of body to escape Elementor stacking contexts
      if (banner.parentNode !== document.body) {
        document.body.appendChild(banner);
      }
    }

    if (backdrop && document.body) {
      if (backdrop.parentNode !== document.body) {
        document.body.appendChild(backdrop);
      }
    }
  }

  // Run immediately if possible, or on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountBannerToBody);
  } else {
    mountBannerToBody();
  }

  // Re-mount after a short delay for Elementor that may move elements after load
  if (document.readyState !== 'complete') {
    window.addEventListener('load', function () {
      setTimeout(mountBannerToBody, 100);
    });
  } else {
    setTimeout(mountBannerToBody, 100);
  }

  /* ---------------------------------------------------------------
   * Consent API call.
   * ------------------------------------------------------------- */
  function postConsent(action, categories, cb) {
    var body = new URLSearchParams();
    body.append('action', action);
    body.append('nonce', cfg.nonce);
    if (categories && categories.length) {
      categories.forEach(function (c) { body.append('categories[]', c); });
    }

    fetch(cfg.restUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-WP-Nonce': cfg.nonce
      },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success) {
          if (cb) { cb(null, res); }
        } else {
          if (cb) { cb(new Error((res && res.message) || 'Error de consentimiento')); }
        }
      })
      .catch(function (err) {
        if (cb) { cb(err); }
      });
  }

  /* ---------------------------------------------------------------
   * UI helpers.
   * ------------------------------------------------------------- */
  function hideBanner() {
    if (banner) {
      banner.classList.add('gdpr-ca-banner--hidden');
      banner.setAttribute('aria-hidden', 'true');
    }
    if (backdrop) {
      backdrop.classList.add('gdpr-ca-banner--hidden');
    }
  }

  function showBanner() {
    if (banner) {
      banner.classList.remove('gdpr-ca-banner--hidden');
      banner.setAttribute('aria-hidden', 'false');
    }
    if (backdrop) {
      backdrop.classList.remove('gdpr-ca-banner--hidden');
    }
  }

  function togglePreferences() {
    if (!banner) { return; }
    var panel = $('[data-gdpr-ca-panel="configure"]', banner);
    if (!panel) { return; }
    if (panel.hasAttribute('hidden')) {
      panel.removeAttribute('hidden');
    } else {
      panel.setAttribute('hidden', '');
    }
  }

  function getCheckedCategories() {
    var out = [];
    if (!banner) { return out; }
    $all('input[data-gdpr-ca-cat]', banner).forEach(function (input) {
      if (input.checked) {
        out.push(input.getAttribute('data-gdpr-ca-cat'));
      }
    });
    return out;
  }

  function clearConsentCookie() {
    var name = cfg.cookieName || 'gdpr_ca_consent';
    var path = cfg.cookiePath || '/';
    var domain = cfg.cookieDomain || '';
    var expires = 'expires=Thu, 01 Jan 1970 00:00:00 GMT';
    var base = name + '=; ' + expires + '; Max-Age=0; path=' + path + '; SameSite=Lax';

    document.cookie = base;
    if (domain) {
      document.cookie = base + '; domain=' + domain;
    }
    if (path !== '/') {
      document.cookie = name + '=; ' + expires + '; Max-Age=0; path=/; SameSite=Lax';
      if (domain) {
        document.cookie = name + '=; ' + expires + '; Max-Age=0; path=/; SameSite=Lax; domain=' + domain;
      }
    }
  }

  /* ---------------------------------------------------------------
   * Google Consent Mode v2 update.
   * ------------------------------------------------------------- */
  function pushGcmUpdate(activeCategories) {
    if (!cfg.gcmEnabled || typeof window.gtag !== 'function') {
      return;
    }
    var ads  = activeCategories.indexOf('marketing')   > -1 ? 'granted' : 'denied';
    var anal = activeCategories.indexOf('statistics')  > -1 ? 'granted' : 'denied';
    var func = activeCategories.indexOf('preferences') > -1 ? 'granted' : 'denied';
    var pads = ads;

    window.gtag('consent', 'update', {
      'ad_storage': ads,
      'ad_user_data': ads,
      'ad_personalization': pads,
      'analytics_storage': anal,
      'functionality_storage': func,
      'security_storage': 'granted'
    });

    if (window.dataLayer) {
      window.dataLayer.push({ event: 'gdpr_ca_consent_update', categories: activeCategories });
    }
  }

  /* ---------------------------------------------------------------
   * Meta Pixel (Facebook Pixel) consent update.
   * ------------------------------------------------------------- */
  function pushMetaPixelUpdate(activeCategories) {
    if (!cfg.metaPixelEnabled) {
      return;
    }
    if (typeof window.fbq !== 'function') {
      return;
    }

    var marketingGranted = activeCategories.indexOf('marketing') > -1;

    if (marketingGranted) {
      if (!window.__gdprCaMetaPixelLoaded) {
        window.__gdprCaMetaPixelLoaded = true;
        (function (f, b, e, v) {
          var n = f.fbq;
          if (n && n.loaded) {
            return;
          }
          var t = b.createElement('script');
          t.async = true;
          t.src = v;
          var s = b.getElementsByTagName('script')[0];
          if (s && s.parentNode) {
            s.parentNode.insertBefore(t, s);
          } else {
            b.head.appendChild(t);
          }
        })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
      }

      window.fbq('consent', 'grant');
      if (cfg.metaPixelId) {
        window.fbq('init', cfg.metaPixelId);
        window.fbq('track', 'PageView');
      }
    } else {
      window.fbq('consent', 'revoke');
    }
  }

  /* ---------------------------------------------------------------
   * Script re-activation: replace type="text/plain" back to
   * "text/javascript" for the categories the user accepted.
   * ------------------------------------------------------------- */
  function reactivateScripts(activeCategories) {
    if (typeof window.__gdprCaUpdateActiveCategories === 'function') {
      window.__gdprCaUpdateActiveCategories(activeCategories);
    }

    var scripts = document.querySelectorAll('script[type="text/plain"][data-gdpr-ca-category]');
    var list = Array.prototype.slice.call(scripts);
    list.forEach(function (oldScript) {
      var cat = oldScript.getAttribute('data-gdpr-ca-category');
      if (activeCategories.indexOf(cat) === -1) {
        return;
      }
      var clone = document.createElement('script');
      for (var i = 0; i < oldScript.attributes.length; i++) {
        var a = oldScript.attributes[i];
        if (a.name === 'type') {
          clone.setAttribute('type', 'text/javascript');
        } else if (a.name === 'data-gdpr-ca-category' || a.name === 'data-gdpr-ca-handle' || a.name === 'data-gdpr-ca-dynamic') {
          // skip internal markers
        } else {
          clone.setAttribute(a.name, a.value);
        }
      }
      if (oldScript.textContent) {
        clone.textContent = oldScript.textContent;
      }
      oldScript.parentNode.replaceChild(clone, oldScript);
    });

    var placeholders = document.querySelectorAll('.gdpr-ca-iframe-placeholder');
    placeholders.forEach(function (ph) {
      var cat = ph.getAttribute('data-gdpr-ca-category');
      if (activeCategories.indexOf(cat) === -1) {
        return;
      }
      var html = ph.getAttribute('data-gdpr-ca-html');
      var src  = ph.getAttribute('data-gdpr-ca-src');
      var attrs = ph.getAttribute('data-gdpr-ca-attrs');

      if (html) {
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        while (wrapper.firstChild) {
          ph.parentNode.insertBefore(wrapper.firstChild, ph);
        }
        ph.parentNode.removeChild(ph);
      } else if (src) {
        var iframe = document.createElement('iframe');
        iframe.src = src;
        if (attrs) {
          var tmp = document.createElement('div');
          tmp.innerHTML = '<iframe ' + attrs + '>';
          var probe = tmp.firstChild;
          if (probe && probe.attributes) {
            for (var j = 0; j < probe.attributes.length; j++) {
              var at = probe.attributes[j];
              if (at.name !== 'src') {
                iframe.setAttribute(at.name, at.value);
              }
            }
          }
        }
        ph.parentNode.replaceChild(iframe, ph);
      }
    });
  }

  /* ---------------------------------------------------------------
   * Event handlers.
   * ------------------------------------------------------------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('#gdpr-ca-banner [data-gdpr-ca-action]');
    if (btn) {
      var action = btn.getAttribute('data-gdpr-ca-action');
      handleAction(action);
      return;
    }
    var toggle = e.target.closest('#gdpr-ca-banner [data-gdpr-ca-toggle]');
    if (toggle) {
      togglePreferences();
    }
  });

  document.addEventListener('click', function (e) {
    var loadBtn = e.target.closest('[data-gdpr-ca-load]');
    if (!loadBtn) { return; }
    var placeholder = loadBtn.closest('.gdpr-ca-iframe-placeholder');
    if (!placeholder) { return; }
    var src = placeholder.getAttribute('data-gdpr-ca-src');
    var attrs = placeholder.getAttribute('data-gdpr-ca-attrs');
    if (src) {
      var iframe = document.createElement('iframe');
      iframe.src = src;
      if (attrs) {
        var tmp = document.createElement('div');
        tmp.innerHTML = '<iframe ' + attrs + '>';
        var probe = tmp.firstChild;
        if (probe && probe.attributes) {
          for (var i = 0; i < probe.attributes.length; i++) {
            var at = probe.attributes[i];
            if (at.name !== 'src') { iframe.setAttribute(at.name, at.value); }
          }
        }
      }
      placeholder.parentNode.replaceChild(iframe, placeholder);
    } else {
      var html = placeholder.getAttribute('data-gdpr-ca-html');
      if (html) {
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        while (wrapper.firstChild) {
          placeholder.parentNode.insertBefore(wrapper.firstChild, placeholder);
        }
        placeholder.parentNode.removeChild(placeholder);
      }
    }
  });

  document.addEventListener('click', function (e) {
    var link = e.target.closest('.gdpr-ca-revoke-link');
    if (!link) { return; }
    e.preventDefault();
    if (window.confirm(labels.revokeConfirm || 'Restablecer preferencias?')) {
      clearConsentCookie();
      postConsent('revoke', [], function () {
        window.location.reload();
      });
    }
  });

  /**
   * Handle a consent action.
   */
  function handleAction(action) {
    var categories = [];
    if (action === 'accept_all') {
      categories = Object.keys(labels.categories || {});
    } else if (action === 'custom') {
      categories = getCheckedCategories();
      if (categories.indexOf('necessary') === -1) {
        categories.push('necessary');
      }
    } else if (action === 'reject_all' || action === 'revoke') {
      categories = ['necessary'];
    }

    postConsent(action, categories, function (err, res) {
      if (err) {
        console.error('[Auditor GDPR]', err);
        return;
      }
      var active = (res && res.active_categories) || categories;
      pushGcmUpdate(active);
      pushMetaPixelUpdate(active);
      reactivateScripts(active);
      hideBanner();
    });
  }

  /* ---------------------------------------------------------------
   * Initial state.
   * ------------------------------------------------------------- */
  if (cfg.hasConsent && cfg.currentCategories && cfg.currentCategories.length) {
    document.addEventListener('DOMContentLoaded', function () {
      pushGcmUpdate(cfg.currentCategories);
      pushMetaPixelUpdate(cfg.currentCategories);
      reactivateScripts(cfg.currentCategories);
    });
  }
})();
