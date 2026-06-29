/* GDPR Consent Auditor - admin JS */
(function ($) {
  'use strict';

  if (typeof GdprCaAdmin === 'undefined') {
    return;
  }

  var $body = $(document.body);

  function showAdminNotice(message, type) {
    var $wrap = $('.gdpr-ca-wrap').first();
    if (!$wrap.length || !message) {
      return;
    }
    $wrap.find('.gdpr-ca-ajax-notice').remove();
    $('<div class="notice gdpr-ca-ajax-notice"><p></p></div>')
      .addClass(type === 'error' ? 'notice-error' : 'notice-success')
      .find('p').text(message).end()
      .insertAfter($wrap.find('h1').first());
  }

  // Run scan.
  if (!window.__gdprCaScanBound) {
    window.__gdprCaScanBound = true;
    $body.on('click', '#gdpr-ca-run-scan', function (e) {
    e.preventDefault();
    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + GdprCaAdmin.i18n.scanning);

    $.post(GdprCaAdmin.ajaxUrl, {
      action: 'gdpr_ca_run_scan',
      nonce: GdprCaAdmin.nonce
    }, function (res) {
      $btn.prop('disabled', false);
      if (res && res.success) {
        var hasWarnings = !!(res.data && res.data.warnings && res.data.warnings.length);
        $btn.html('<span class="dashicons dashicons-search"></span> ' + (hasWarnings ? GdprCaAdmin.i18n.scanDoneWithWarnings : GdprCaAdmin.i18n.scanDone));
        showAdminNotice((res.data && res.data.message) || (hasWarnings ? GdprCaAdmin.i18n.scanDoneWithWarnings : GdprCaAdmin.i18n.scanDone), hasWarnings ? 'error' : 'success');
        // Reload to show the updated report.
        setTimeout(function () { window.location.reload(); }, 800);
      } else {
        $btn.html('<span class="dashicons dashicons-search"></span> ' + GdprCaAdmin.i18n.scanFailed);
        showAdminNotice((res && res.data && res.data.message) || GdprCaAdmin.i18n.scanFailed, 'error');
      }
    }).fail(function (xhr) {
      var message = GdprCaAdmin.i18n.scanFailed;
      if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
        message = xhr.responseJSON.data.message;
      }
      $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + GdprCaAdmin.i18n.scanFailed);
      showAdminNotice(message, 'error');
    });
  });
  }

  // Export report (.txt / .csv / .json) - single handler for all formats.
  $body.on('click', '#gdpr-ca-export-report, #gdpr-ca-export-report-csv, #gdpr-ca-export-report-json', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var action;
    if (this.id === 'gdpr-ca-export-report-csv') {
      action = 'gdpr_ca_export_report_csv';
    } else if (this.id === 'gdpr-ca-export-report-json') {
      action = 'gdpr_ca_export_report_json';
    } else {
      action = 'gdpr_ca_export_report';
    }

    $btn.prop('disabled', true).text(GdprCaAdmin.i18n.exporting);

    // Use a hidden POST form to trigger a real file download (avoids nonce in URL).
    var $form = $('<form></form>').css({ display: 'none' }).attr({ method: 'POST', action: GdprCaAdmin.ajaxUrl });
    $form.append($('<input>').attr({ type: 'hidden', name: 'action', value: action }));
    $form.append($('<input>').attr({ type: 'hidden', name: 'nonce', value: GdprCaAdmin.nonce }));
    $body.append($form);
    $form.submit();
    setTimeout(function () {
      $btn.prop('disabled', false);
      $form.remove();
    }, 6000);
  });

  // Purge logs.
  $body.on('click', '#gdpr-ca-purge-logs', function (e) {
    e.preventDefault();
    if (!window.confirm(GdprCaAdmin.i18n.confirmPurge)) {
      return;
    }
    var $btn = $(this);
    $btn.prop('disabled', true);
    $.post(GdprCaAdmin.ajaxUrl, {
      action: 'gdpr_ca_purge_logs',
      nonce: GdprCaAdmin.nonce
    }, function (res) {
      $btn.prop('disabled', false);
      if (res && res.success && res.data && res.data.message) {
        window.alert(res.data.message);
        window.location.reload();
      } else if (res && res.data && res.data.message) {
        window.alert(res.data.message);
      }
    });
  });

  // Manual block rules - add/remove rows.
  $body.on('click', '#gdpr-ca-add-row', function (e) {
    e.preventDefault();
    var $tbody = $('#gdpr-ca-manual-blocks tbody');
    var $template = $tbody.find('tr:first').clone();
    $template.find('input[type="text"]').val('');
    $template.find('select').prop('selectedIndex', 0);
    // Re-key the name attributes so each row has a unique index.
    var idx = $tbody.find('tr').length;
    $template.find('input, select').each(function () {
      var name = $(this).attr('name');
      if (name) {
        name = name.replace(/\[manual_blocks\]\[\d+\]/, '[manual_blocks][' + idx + ']');
        $(this).attr('name', name);
      }
    });
    $tbody.append($template);
  });

  $body.on('click', '.gdpr-ca-remove-row', function (e) {
    e.preventDefault();
    var $tbody = $('#gdpr-ca-manual-blocks tbody');
    if ($tbody.find('tr').length > 1) {
      $(this).closest('tr').remove();
    } else {
      // Don't remove the last row - just clear it.
      $(this).closest('tr').find('input[type="text"]').val('');
    }
  });

  // Live update opacity slider percentage display.
  $body.on('input', '.gdpr-ca-opacity-range', function () {
    var $val = $(this).closest('.gdpr-ca-opacity-wrap').find('.gdpr-ca-opacity-val');
    if ($val.length) {
      $val.text(this.value + '%');
    }
  });

  // Spinning dashicon helper.
  var style = document.createElement('style');
  style.innerHTML = '.dashicons.spin { animation: gdprCaSpin 1s linear infinite; } @keyframes gdprCaSpin { 0% { transform: rotate(0); } 100% { transform: rotate(360deg); } }';
  document.head.appendChild(style);

})(jQuery);
