(function($) {

  var getIssuers;

  // check the currently checked radio is for ideal
  // if it is update the issuers and show the issuer selection
  // else hide the issuers

  /**
   * Save payment methods
   */
  function savePaymentMethod() {
    var $form = $('#shippingPaymentForm');

    var url = $form.attr('action');

    var data = $form.find('input,select')
      .map(function() {
        return { name: $(this).attr('name'), value: $(this).val() };
      })
      .get()
      .reduce(function(data, o) {
        data[o.name] = o.value;
        return data;
      }, {
        isXHR: true,
        saveMollieIdealIssuer: true // extra key to make sure the ideal issuer select is not updated
      });

    $.post(url, data);
  }

  function isIdealRadioChecked() {
    return $('#mollie-ideal-issuers').parents('.payment--method').find('input[type="radio"]').is(':checked');
  }

  function showHideIssuers(checked) {
    checked ? $('#mollie-ideal-issuers').show() : $('#mollie-ideal-issuers').hide();
  }

  function showLoading() {
    var loadingIcon = (
      '<div class="js--loading-indicator" style="display: block">' +
        '<div class="icon--default" />' +
      '</div>'
    );
    $('#mollie-ideal-issuer-list').empty().html(loadingIcon);
  }

  function getIdealIssuersUrl() {
    return $('#mollie-ideal-issuers').data('url');
  }

  function buildIssuers(issuers) {
    var radios = issuers
      .map(function(issuer) {
        return '<option ' + (issuer.isSelected ? 'selected ' : '') + 'value="' + issuer.id + '">' + issuer.name + '</option>';
      })
      .join("\n");

    var $select = $(
      '<select id="mollie-ideal-issuer-select" name="mollie-ideal-issuer">' +
        '<option value="0">...</option>' +
        radios +
      '</select>'
    );

    $('#mollie-ideal-issuer-list').empty().append($select);

    // save payment method when changing issuer
    $select.on('change', savePaymentMethod);
  }

  getIssuers = (function() {
    var issuers = null;
    var timeout = null;

    return function getIssuers(cb) {

      if( issuers === null && timeout === null ) {
        showLoading();

        setTimeout(function() {
          timeout = null;
          issuers = null;
        }, 10000);

        $.getJSON(getIdealIssuersUrl(), function(result, textStatus, jqXHR) {
          if (timeout !== null) {
            clearTimeout(timeout);
            timeout = null;
          }

          if (result.success) {
            issuers = result.data;

            cb(issuers);
          }
        });
      } else {

        setTimeout(function() {
          cb(issuers);
        }, 0);
      }

    };
  })();

  function onGetIssuers() {
    var idealChecked = isIdealRadioChecked();
    showHideIssuers(idealChecked);
    getIssuers(buildIssuers);
  }

  /**
   * Only listen for events if it is the select payment method page
   */
  if( $('#mollie-ideal-issuers').length ) {

    // on click of an payment method radio button
    $('.content-main').on('click', '.payment--method input[type="radio"]', onGetIssuers);

    // on completion of an ajax call
    $(document).ajaxComplete(function(event, xhr, settings) {
      var url = settings.url;
      var data = settings.data;

      if( url.indexOf('checkout/saveShippingPayment') !== -1
        && data.indexOf('saveMollieIdealIssuer') === -1 ) {
        onGetIssuers();
      }

    });

    // on document ready
    $(document).ready(onGetIssuers);

  }

})(jQuery);
