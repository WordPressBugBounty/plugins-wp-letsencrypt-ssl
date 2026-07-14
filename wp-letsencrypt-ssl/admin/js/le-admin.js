(function ($) {
  'use strict';

  $('.le-section-title').click(function () {
    var scn = $(this).attr('data-section');
    $('.le-section-title').removeClass('active');
    $(this).addClass('active');

    localStorage.setItem('le-state', scn);

    $('.le-section.active')
      .fadeOut('fast')
      .removeClass('active')
      .promise()
      .done(function () {
        $('.' + scn)
          .fadeIn('fast')
          .addClass('active');
      });
  });

  //restore last tab
  if (localStorage.getItem('le-state') !== null) {
    var section = localStorage.getItem('le-state');

    $('.le-section-title[data-section=' + section + ']').click();
  }

  // Since 2.5.0
  $('.wple-tooltip').each(function () {
    var $this = $(this);

    tippy('.wple-tooltip:not(.bottom)', {
      //content: $this.attr('data-content'),
      placement: 'top',
      onShow(instance) {
        instance.popper.hidden = instance.reference.dataset.tippy
          ? false
          : true;
        instance.setContent(instance.reference.dataset.tippy);
      },
      //arrow: false
    });

    tippy('.wple-tooltip.bottom', {
      //content: $this.attr('data-content'),
      placement: 'bottom',
      onShow(instance) {
        instance.popper.hidden = instance.reference.dataset.tippy
          ? false
          : true;
        instance.setContent(instance.reference.dataset.tippy);
      },
      //arrow: false
    });
  });

  $('.toggle-debugger').click(function () {
    $(this).find('span').toggleClass('rotate');

    $('.le-debugger').slideToggle('fast');
  });

  //since 4.6.0
  $('#admin-verify-dns').submit(function (e) {
    e.preventDefault();

    var $this = $(this);

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_admin_dnsverify',
        nc: $('#checkdns').val(),
      },
      beforeSend: function () {
        $('.dns-notvalid').removeClass('active');
        $this.addClass('buttonrotate');
        $this.find('button').attr('disabled', true);
      },
      error: function () {
        $('.dns-notvalid').removeClass('active');
        $this.removeClass('buttonrotate');
        $this.find('button').removeAttr('disabled');
        alert('Something went wrong! Please try again');
      },
      success: function (response) {
        $this.removeClass('buttonrotate');
        $this.find('button').removeAttr('disabled');

        if (response === '1') {
          $this.find('button').text('Verified');
          setTimeout(function () {
            window.location.href = window.location.href + '&wpleauto=dns';
            exit();
          }, 1000);

          // } else if (response !== 'fail') {
          //   alert("Partially verified. Could not verify " + String(response));
        } else {
          $('.dns-notvalid').addClass('active');
        }
      },
    });

    return false;
  });

  //since 4.7.0
  $('#verify-subdns').click(function (e) {
    e.preventDefault();

    var $this = $(this);

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_admin_dnsverify',
        nc: $this.prev().val(),
      },
      beforeSend: function () {
        $('.dns-notvalid').removeClass('active');
        $this.addClass('buttonrotate');
        $this.attr('disabled', true);
        $('#wple-letsdebug').html('');
      },
      error: function () {
        $('.dns-notvalid').removeClass('active');
        $this.removeClass('buttonrotate');
        $this.removeAttr('disabled');
        alert('Something went wrong! Please try again');
      },
      success: function (response) {
        $this.removeClass('buttonrotate');
        $this.removeAttr('disabled');

        if (response === '1') {
          $this.text('Verified');
          $('#wple-error-popper .wple-error').hide();
          $('#wple-error-popper').fadeIn('fast');
          $('#wple-error-popper .wple-flex img').show();

          setTimeout(function () {
            window.location.href =
              window.location.href + '&subdir=1&wpleauto=dns';
            exit();
          }, 1000);

          // } else if (response !== 'fail') {
          //   alert("Partially verified. Could not verify " + String(response));
        } else {
          //fail
          if (response.indexOf('ul') >= 0) {
            $('#wple-letsdebug').html(response);
          }

          $('.dns-notvalid').addClass('active');
        }
      },
    });

    return false;
  });

  $('#verify-subhttp').click(function (e) {
    e.preventDefault();

    var $this = $(this);

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_admin_httpverify',
        nc: $this.prev().val(),
      },
      beforeSend: function () {
        $('.http-notvalid').removeClass('active');
        $this.addClass('buttonrotate');
        $this.attr('disabled', true);
        $('#wple-letsdebug').html('');
      },
      error: function () {
        $('.http-notvalid').removeClass('active');
        $this.removeClass('buttonrotate');
        $this.removeAttr('disabled');
        alert('Something went wrong! Please try again');
      },
      success: function (response) {
        $this.removeClass('buttonrotate');
        $this.removeAttr('disabled');

        if (response == 'empty') {
          alert('HTTP challenges empty. Please use RESET once and try again.');
        } else if (response == 'not_possible') {
          //deprecated
          $('.http-notvalid-blocked').addClass('active');
        } else if (response === '1') {
          $this.text('Verified');
          $('#wple-error-popper .wple-error').hide();
          $('#wple-error-popper').fadeIn('fast');
          $('#wple-error-popper .wple-flex img').show();

          setTimeout(function () {
            window.location.href =
              window.location.href + '&subdir=1&wpleauto=http';
            return false;
          }, 1000);
        } else {
          //fail

          if (response.indexOf('ul') >= 0) {
            $('#wple-letsdebug').html(response);
          }

          $('.http-notvalid').addClass('active');
        }
      },
    });

    return false;
  });

  //since 4.7.1
  $('#singledvssl').click(function (e) {
    //e.preventDefault();

    var flag = 0;
    if ($('input.wple_email').val() == '') {
      flag = 1;
      $('#wple-error-popper .wple-error').text('Email address is required');
      $('#wple-error-popper').fadeIn('slow');
    } else if (
      !$('input.wple_agree_le').is(':checked') ||
      !$('input.wple_agree_gws').is(':checked')
    ) {
      flag = 1;
      $('#wple-error-popper .wple-error').text('Agree to TOS required');
      $('#wple-error-popper').fadeIn('slow');
    }

    if (flag == 0) {
      $('#wple-error-popper .wple-error').hide();
      $('#wple-error-popper').fadeIn('fast');
      $('#wple-error-popper .wple-flex img').show();
      //$(this).closest(".le-genform").submit();
    } else {
      setTimeout(function () {
        $('#wple-error-popper').fadeOut(500);
      }, 2000);
      return false;
    }
  });

  /* <fs_premium_only> */

  /**
   * Firewall AJAX
   *
   * Since v5.0.0
   */
  var loadingIcon = '<i class="fas fa-circle-notch fa-spin"></i>';
  var completeIcon = '<i class="far fa-check-circle"></i>';
  var errorIcon = '<i class="far fa-times-circle"></i>';
  var infoIcon = '<i class="fas fa-info-circle"></i>';
  var token;
  var apiURL = 'https://api.wpencryption.com/v3/';

  var ajaxCall = (jsonData, stageClass, next, endPoint = '') => {
    ///jsonData = JSON.stringify(jsonData);

    // if (endPoint == 'v2') {
    //   apiURL = 'https://api.wpencryption.com/v2/?v=' + new Date().getTime();
    // }

    $.ajax({
      type: 'POST',
      url: apiURL,
      async: true,
      timeout: 30000,
      cache: false,
      dataType: 'text',
      crossDomain: true,
      // headers: {
      //   Connection: close
      // },
      data: jsonData,
      beforeSend: function () {
        $('.firewall-error').slideUp('fast');
        $('#start-firewall').attr('disabled', 'disabled').find('span').show();
        $(stageClass).find('span').html(loadingIcon);
      },
      error: function (ob, stat) {
        $('#start-firewall').removeAttr('disabled').find('span').hide();
        alert('Unable to make request. Please try again. - ' + stat);
      },
      complete: next,
    });
  };

  var verifyConfig = () => {
    $.ajax({
      type: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_firewall_verify_config',
        nc: $('#firewall-dns-validate').attr('data-nc'),
      },
      beforeSend: function () {
        $('.wplef-config-test').find('span').html(loadingIcon);
      },
      error: function () {
        $('#start-firewall').removeAttr('disabled').find('span').hide();
        alert(
          'Change in connection. Please reload this page once and continue.',
        );
      },
      success: function (response) {
        if (response === false) {
          $('#start-firewall').removeAttr('disabled').find('span').hide();
          $('.wplef-config-test').find('span').html(errorIcon);
          $('#firewall-running').slideDown('fast');
          alert(
            'Change in connection. Please reload this page once and continue.',
          );
          return false;
        } else {
          $('.wplef-config-test').find('span').html(completeIcon);

          $('#start-firewall').text('All Set!').attr('disabled', 'disabled');
          localStorage.removeItem('wple_fire_stage');
          window.location = window.location.href.replace('http:', 'https:');
        }
      },
    });
  };

  //since 5.2.0
  var verifyConfigPRO = () => {
    $.ajax({
      type: 'POST',
      url: ajaxurl,
      async: false,
      cache: false,
      dataType: 'text',
      data: {
        action: 'wple_firewall_verify_config',
        write: 1,
      },
      beforeSend: function () {},
      error: function () {
        $('#firewall-dns-validate').removeAttr('disabled').find('span').hide();
        alert(
          'Change in connection. Please reload this page once and continue.',
        );
      },
      success: function (response) {
        console.log(response);

        if (response === false) {
          $('#firewall-dns-validate')
            .removeAttr('disabled')
            .find('span')
            .hide();
          //$("#firewall-running").slideDown('fast');
          alert(
            'Could not complete or validate .htaccess changes. Contact support@wpencryption.com',
          );
          return false;
        } else if (response == 'fail') {
          $('#firewall-dns').hide();
          $('#firewall-wpconfig').show();
        } else {
          $('.spmode-status').text('');
          $('#firewall-dns-validate')
            .text('All Set! SSL Setup Complete.')
            .attr('disabled', 'disabled');
          var hrf = window.location.href;
          window.location.href = hrf
            .replace('http:', 'https:')
            .substr(0, hrf.indexOf('&') + 1);
        }
      },
    });
  };

  if ($('#start-firewall').length) {
    if (
      typeof WPLE !== 'undefined' &&
      typeof WPLE.firewall_stage !== 'undefined'
    ) {
      if (parseInt(WPLE.firewall_stage) == 5) {
        $(
          '.wplef-validate-license span,.wplef-register-site span,.wplef-dns-retrieve span,.wplef-ssl-allocate span',
        ).html(completeIcon);
        $('.wplef-config-test').find('span').html(infoIcon);
        $('#firewall-wpconfig').slideDown('fast');
        verifyConfig();
      } else if (parseInt(WPLE.firewall_stage) == 6) {
        //All complete
        $(
          '.wplef-validate-license span,.wplef-register-site span,.wplef-dns-retrieve span,.wplef-ssl-allocate span,.wplef-config-test span',
        ).html(completeIcon);
        $('#start-firewall').text('All Set!').attr('disabled', 'disabled');
        $('#firewall-running').show();
      }
    }
  }

  //Start
  $('#start-firewall').click(function () {
    var resume = localStorage.getItem('wple_fire_stage');
    if (null !== resume && resume == 5) {
      $(
        '.wplef-validate-license span,.wplef-register-site span,.wplef-dns-retrieve span,.wplef-ssl-allocate span',
      ).html(completeIcon);
      $('.wplef-config-test').find('span').html(infoIcon);
      $('#firewall-wpconfig').slideDown('fast');
      verifyConfig();
    } else {
      $('#firewall-steps h3 span').text('');

      var lidvalue = $(this).attr('data-lid');

      var payload = {
        stage: 'validate',
        site: 'http://' + window.location.hostname,
        lid: lidvalue,
      };

      ajaxCall(payload, '.wplef-validate-license', startRegistration);
    }
  });

  //Stage 2
  var startRegistration = (response) => {
    var response = JSON.parse(response.responseText);

    if (response.error) {
      $('#start-firewall').removeAttr('disabled').find('span').hide();
      $('.wplef-validate-license').find('span').html(errorIcon);
      $('.firewall-error').text(response.error.msg);
      $('.firewall-error').slideDown(500);
      return false;
    } else {
      $('.wplef-validate-license').find('span').html(completeIcon);

      token = response.success.token;

      var payload = {
        stage: 'register',
        site: 'http://' + window.location.hostname,
        token: token,
        lid: $('#start-firewall').attr('data-lid'),
      };

      ajaxCall(payload, '.wplef-register-site', showDNSChallenges);
    }
  };

  //Stage 3
  // var startDNSRegister = (response) => {
  //   var response = JSON.parse(response.responseText);

  //   if (response.error) {
  //     $("#start-firewall").removeAttr("disabled").find("span").hide();
  //     $('.wplef-register-site').find('span').html(errorIcon);
  //     $(".firewall-error").text(response.error.msg);
  //     $(".firewall-error").slideDown(500);
  //     return false;
  //   } else {
  //     $('.wplef-register-site').find('span').html(completeIcon);
  //     localStorage.setItem('wple_fire_stage', 2);

  //     var payload = {
  //       'stage': 'DNSfetch',
  //       'token': token
  //     };

  //     ajaxCall(payload, '.wplef-dns-retrieve', showDNSChallenges);
  //   }
  // }

  var showDNSChallenges = (response) => {
    var response = JSON.parse(response.responseText);
    if (response.error) {
      $('#start-firewall').removeAttr('disabled').find('span').hide();
      $('.wplef-dns-retrieve').find('span').html(errorIcon);
      $('.firewall-error').text(response.error.msg);
      $('.firewall-error').slideDown(500);
      return false;
    } else {
      //console.log(response);
      $('.wplef-register-site').find('span').html(completeIcon);
      $('.wplef-dns-retrieve').find('span').html(infoIcon);
      $('#start-firewall').find('span').hide();
      localStorage.setItem('wple_fire_stage', 2);

      var Arecord = response.success.challenges.A;
      var CNAMErecord = response.success.challenges.DNS.addresses[0];

      $('.firewall-ip').text(Arecord);
      $('.firewall-cname').text(CNAMErecord);

      $('#firewall-dns').slideDown('fast');

      localStorage.setItem('wple_firewall_A', Arecord);
      localStorage.setItem('wple_firewall_CNAME', CNAMErecord);
    }
  };

  //Local DNS verify

  $('.spmode-dns-apply a').click(function (e) {
    e.preventDefault();
    e.stopPropagation();
    var url = e.target.href;
    var applydns = window.open(
      url,
      '',
      'resizable=yes, width=800, height=600, top=50, left=50',
    );
    var keepcheck = setInterval(isDNSAuthorized, 500);

    var countdown180;

    function isDNSAuthorized() {
      if (applydns.closed) {
        clearInterval(keepcheck);
        $('#firewall-dns-validate').before(
          '<p class="spmode-wait-timer">Please wait <strong>180</strong> seconds to automatically validate new DNS changes.</p>',
        );
        countdown180 = setInterval(secondsCounter, 1000);
      }
    }

    function secondsCounter() {
      var curVal = $('.spmode-wait-timer strong').text();

      if (curVal <= 0) {
        clearInterval(countdown180);
        $('#firewall-dns-validate').removeAttr('disabled');
        $('.spmode-wait-timer').remove();
        $('#firewall-dns-validate').click();
      } else {
        $('#firewall-dns-validate').attr('disabled', true);
        $('.spmode-wait-timer strong').text(parseInt(curVal) - 1);
      }
    }
  });

  $('#firewall-dns-validate').click(function (e) {
    localStorage.setItem('wple_fire_stage', 3);
    var $dnsbutton = $(this);
    e.preventDefault();

    $.ajax({
      type: 'POST',
      url: ajaxurl,
      async: true,
      data: {
        action: 'wple_firewall_dns_verify',
        arecord: localStorage.getItem('wple_firewall_A'),
        cnamerecord: localStorage.getItem('wple_firewall_CNAME'),
        subdomain: $('.wpenfr-domain').is(':checked'),
        nc: $('#firewall-dns-validate').attr('data-nc'),
      },
      beforeSend: function () {
        $('#firewall-dns-validate').attr('disabled', 'disabled');
        $('#firewall-dns-validate span').show();
        $('.firewall-error').slideUp('fast');
      },
      error: function () {
        $('#firewall-dns-validate').removeAttr('disabled').find('span').hide();
        alert('Unable to make request. Please reload and try again.');
      },
      success: function (response) {
        console.log(response);

        if (!$dnsbutton.hasClass('cpanel-fallback')) {
          $('#firewall-dns-validate')
            .removeAttr('disabled')
            .find('span')
            .hide();
        }

        //try {
        response = JSON.parse(response);

        if ($dnsbutton.hasClass('cpanel-fallback')) {
          if (response.error) {
            $('#firewall-dns-validate')
              .removeAttr('disabled')
              .find('span')
              .hide();
            alert(response.error);
            return false;
          }

          if (response.success) {
            $('.spmode-status').text(
              'DNS Validated.. Initiating SSL allocation..',
            );

            var subdomain = 0;
            if ($('.wpenfr-domain').is(':checked')) {
              subdomain = 1;
            }

            $.ajax({
              type: 'POST',
              url: ajaxurl,
              async: true,
              data: {
                action: 'wple_firewall_installssl',
                issubdomain: subdomain,
              },
              beforeSend: function () {},
              error: function () {
                $('#firewall-dns-validate')
                  .removeAttr('disabled')
                  .find('span')
                  .hide();
                alert(
                  'Unable to make request. Please reload this page once & try again.',
                );
              },
              success: function (response) {
                allocatedSSLPRO(response);
              },
            });
          }
        } else {
          if (response.error) {
            $('.firewall-error').html(response.error);
            $('.firewall-error').slideDown(500);
            return false;
          }

          if (response.success) {
            $('.wplef-dns-retrieve').find('span').html(completeIcon);
            $('#firewall-dns').slideUp('fast');

            //proceed to next step
            allocateSSLCertificate();
          }
        }

        // } catch (e) {
        //   window.location.href = window.location.href + '&firewall_routed=1';
        //   return false;
        // }
      },
    });
  });

  $('#firewall-config-validate').click(function () {
    $.ajax({
      type: 'POST',
      url: ajaxurl,
      async: true,
      cache: false,
      dataType: 'text',
      data: {
        action: 'wple_firewall_verify_config',
      },
      beforeSend: function () {
        $('#firewall-config-validate').attr('disabled', 'disabled');
        $('#firewall-config-validate span').show();
      },
      error: function () {
        $('#firewall-config-validate')
          .removeAttr('disabled')
          .find('span')
          .hide();
        alert(
          'Change in connection. Please reload this page once and continue.',
        );
      },
      success: function (response) {
        if (response == 0) {
          $('#firewall-config-validate')
            .removeAttr('disabled')
            .find('span')
            .hide();
          alert(
            'Verification failure. Please check if you have correctly added the code into .htaccess file',
          );
          return false;
        } else {
          $('#firewall-config-validate')
            .text('All Done! SSL Setup Complete.')
            .attr('disabled', 'disabled');
          var hrf = window.location.href.replace('http:', 'https:');
          window.location.href = hrf.substr(0, hrf.indexOf('&') + 2);
        }
      },
    });
  });

  var allocateSSLCertificate = () => {
    localStorage.setItem('wple_fire_stage', 4);

    var subdomain = 0;
    if ($('.wpenfr-domain').is(':checked')) {
      subdomain = 1;
    }

    var payload = {
      stage: 'allocateSSL',
      site: 'http://' + window.location.hostname,
      token: token,
      issubdomain: subdomain,
    };

    ajaxCall(payload, '.wplef-ssl-allocate', allocatedSSL);
  };

  //stage 4
  var allocatedSSL = (response) => {
    var response = JSON.parse(response.responseText);
    if (response.error) {
      $('#start-firewall').removeAttr('disabled').find('span').hide();
      $('.wplef-ssl-allocate').find('span').html(errorIcon);
      $('.firewall-error').text(response.error.msg);
      $('.firewall-error').slideDown(500);
      return false;
    } else {
      $('#start-firewall').removeAttr('disabled').find('span').hide();
      $('.wplef-ssl-allocate').find('span').html(completeIcon);
      localStorage.setItem('wple_fire_stage', 5);
      verifyConfig();
    }
  };

  //since 5.2.0
  var validateSSL;
  var allocatedSSLPRO = (response) => {
    response = JSON.parse(response);

    if (response.error) {
      $('.spmode-status').text('');
      $('#firewall-dns-validate').removeAttr('disabled').find('span').hide();
      alert(response.error.msg);
      return false;
    } else {
      $('.spmode-status').html(
        'Finalizing SSL installation... <small>(This might take upto 10mins)</small>',
      );
      //$(".spmode-status").text("SSL Installed.. Completing Final Configurations..");
      ///localStorage.setItem('wple_fire_stage', 5);
      ///verifyConfigPRO();

      validateSSL = setInterval(function () {
        var payload = {
          stage: 'validateSSL',
          site: 'http://' + window.location.hostname,
          token: localStorage.getItem('wple_spmode_token'),
        };

        ajaxCall(payload, '.wplef-ssl-allocate', validatedSSLInstallation);
      }, 5000);
    }
  };

  var validatedSSLInstallation = (response) => {
    console.log(response);

    response = JSON.parse(response.responseText);

    if (typeof response.error != 'undefined') {
      $('.spmode-status').text('');
      $('#firewall-dns-validate').removeAttr('disabled').find('span').hide();
      alert(response.error.msg);
      return false;
    } else {
      var result = response.success.results;

      if (result.length <= 0) {
        clearInterval(validateSSL);
        alert(
          'SSL allocation not initiated. Please reload the page and try again.',
        );
        return false;
      }

      result = result[0];

      if (result.certificate.status == 'FAILED') {
        clearInterval(validateSSL);
        alert(
          'SSL allocation failed. Please contact support@wpencryption.com.',
        );
        return false;
      }

      if (result.certificate.status == 'ACTIVE') {
        clearInterval(validateSSL);
        verifyConfigPRO();
      }
    }
  };

  //Firewall Metrics
  //Since v5.0.3

  // var WPLELineChart = (ctxID, lbls, datas, titletext) => {

  //   new Chart(ctxID, {
  //     type: 'line',
  //     data: {
  //       labels: lbls,
  //       datasets: datas
  //     },
  //     options: {
  //       responsive: true,
  //       maintainAspectRatio: false,
  //       hover: {
  //         mode: 'nearest',
  //         intersect: true
  //       },
  //       tooltips: {
  //         mode: 'index',
  //         intersect: false,
  //         callbacks: {
  //           label: (i, d) => `${i.value} ${d.datasets[i.datasetIndex].label.replace(/\(.* Total\)$/, '')}`
  //         }
  //       },
  //       scales: {
  //         xAxes: [{
  //           type: 'time'
  //         }],
  //         yAxes: [{
  //           display: true,
  //           scaleLabel: {
  //             display: true,
  //             labelString: 'Requests'
  //           }
  //         }]
  //       },
  //       title: {
  //         display: true,
  //         fontSize: 15,
  //         padding: 20,
  //         text: titletext
  //       }
  //     }
  //   });

  // }

  // var DrawChartByData = (success) => {
  //   var chartDiv = $('#wple-firewall-requests-chart');

  //   var blockData = [];
  //   var passData = [];
  //   var totalRequests = [];
  //   var dates = [];

  //   var sEngines = [];
  //   var ddosBlocks = [];
  //   var spamBlocks = [];

  //   var totalBlocks = 0;
  //   var totalPass = 0;
  //   var totalRequestCount = 0;

  //   var totalSearchEnginesCount = 0;
  //   var totalDDoSCount = 0;
  //   var totalSpamCount = 0;

  //   $.each(success, (index, obj) => {
  //     // var randomnumber = Math.floor(Math.random() * (5000 - 1000 + 1)) + 1000;
  //     // var randomnumber2 = Math.floor(Math.random() * (2000 - 100 + 1)) + 100;
  //     // var randomnumber3 = Math.floor(Math.random() * (4000 - 100 + 1)) + 100;

  //     var dtime; //only date
  //     if ($("#wple-metrics-period option:selected").val() == 1 || $("#wple-metrics-period option:selected").val() == 'undefined') { //24hrs data
  //       dtime = obj.timestamp;
  //     } else {
  //       dtime = obj.timestamp.substr(0, 10);
  //     }

  //     dates.push(dtime);
  //     blockData.push(obj.totalBlocked);
  //     passData.push(obj.totalAllowed);

  //     sEngines.push(obj.searchEngines);
  //     ddosBlocks.push(obj.ddos);
  //     spamBlocks.push(obj.spamAbuse);

  //     var countTogethr = parseInt(obj.totalAllowed) + parseInt(obj.totalBlocked);

  //     totalRequests.push(countTogethr);

  //     totalBlocks += parseInt(obj.totalBlocked);
  //     totalPass += parseInt(obj.totalAllowed);
  //     totalRequestCount += countTogethr;

  //     totalSearchEnginesCount += parseInt(obj.searchEngines);
  //     totalDDoSCount += parseInt(obj.ddos);
  //     totalSpamCount += parseInt(obj.spamAbuse);
  //   });

  //   // console.log(Object.keys(success));
  //   // console.log(blockData);
  //   // console.log(passData);

  //   var datafeed = [{
  //       label: `Blocked Requests (${totalBlocks} Total)`,
  //       backgroundColor: 'rgba(237,39,39,0.6)',
  //       borderColor: 'rgba(231,69,69, 0.8)',
  //       data: blockData
  //     },
  //     {
  //       label: `Total Requests (${totalRequestCount} Total)`,
  //       backgroundColor: 'rgba(58,204,56, 0.4)',
  //       borderColor: 'rgba(58,204,56, 0.8)',
  //       data: totalRequests
  //     }
  //   ];

  //   WPLELineChart(chartDiv, dates, datafeed, 'Total Requests vs Blocked Requests (Count Excludes DDoS)');

  //   var datafeed = [{
  //     label: `Total Requests (${totalSearchEnginesCount > 0 ? totalSearchEnginesCount + ' Total' : 'No requests yet'})`,
  //     backgroundColor: 'rgba(58,204,56, 0.4)',
  //     borderColor: 'rgba(58,204,56, 0.8)',
  //     data: sEngines
  //   }];

  //   WPLELineChart($("#wple-firewall-searchengines-chart"), dates, datafeed, 'Total allowed search engine requests');

  //   var datafeed = [{
  //     label: `Total Requests (${totalDDoSCount > 0 ? totalDDoSCount + ' Total' : 'All safe! No such incident yet'})`,
  //     backgroundColor: 'rgba(237,39,39,0.6)',
  //     borderColor: 'rgba(231,69,69, 0.8)',
  //     data: ddosBlocks
  //   }];

  //   WPLELineChart($("#wple-firewall-ddos-chart"), dates, datafeed, 'Total No. of DDoS attacks caught and blocked');

  //   var datafeed = [{
  //     label: `Total Requests (${totalSpamCount > 0 ? totalSpamCount + ' Total' : 'All good! No spammers yet'})`,
  //     backgroundColor: 'rgba(237,39,39,0.6)',
  //     borderColor: 'rgba(231,69,69, 0.8)',
  //     data: spamBlocks
  //   }];

  //   WPLELineChart($("#wple-firewall-spamabuse-chart"), dates, datafeed, 'Total No. of Spam and Abuse caught & blocked');
  // }

  // var metricsGenerator = (response) => {

  //   if (response.error) {
  //     if (response.error.msg == 'Validation token invalid') {
  //       window.location.href = window.location.href + '&resetwpletoken=1';
  //       return false;
  //     } else {
  //       metricsDiv.html('<div class="wple-error"><span>' + response.error.msg + '</span></div>');
  //     }
  //   } else {

  //     metricsDiv.hide();

  //     var success = response.success;

  //     //store in cache
  //     jQuery.ajax({
  //       method: "POST",
  //       url: ajaxurl.replace('https', 'http'),
  //       dataType: "json",
  //       data: {
  //         action: 'wple_save_metrics_cache',
  //         period: metricsDiv.attr('data-period'),
  //         nc: metricsDiv.attr('data-nc'),
  //         dataobj: {
  //           success
  //         }
  //       },
  //       error: function (res) {},
  //       success: function (response) {}
  //     });

  //     DrawChartByData(success);

  //   }

  // }

  //try saving the token
  var saveTheToken = (newtoken) => {
    jQuery.ajax({
      method: 'POST',
      url: ajaxurl.replace('https', 'http'),
      dataType: 'text',
      data: {
        action: 'wple_save_firewall_token',
        token: newtoken,
      },
      error: function () {},
      success: function (response) {},
    });
  };

  // var metricsTokenHandler = (response) => {
  //   if (response.error) {
  //     metricsDiv.text(response.error.msg);
  //     return false
  //   } else {

  //     var newtoken = response.success.token;

  //     if (!response.success.stored) {
  //       saveTheToken(newtoken);
  //     }

  //     //proceed with metrics fetching
  //     var payload = {
  //       'stage': 'getmetrics',
  //       'granularity': parseInt($("#wple-metrics-period option:selected").val()),
  //       'token': newtoken
  //     };
  //     var jsonData = JSON.stringify(payload);

  //     $.ajax({
  //       type: "POST",
  //       url: apiURL,
  //       dataType: "json",
  //       crossDomain: true,
  //       data: jsonData,
  //       beforeSend: function () {
  //         metricsDiv.html(loadingIcon);
  //       },
  //       error: function () {
  //         metricsDiv.text("Unable to make request. Please try re-loading the page after some time.");
  //       },
  //       success: metricsGenerator
  //     });
  //   }
  // }

  // var metricsDiv = $("#wple-firewall-metrics");
  // if (metricsDiv.length) {

  //   if (metricsDiv.hasClass("needsupdate")) {

  //     if (typeof WPLE != "undefined" && typeof WPLE.token != "undefined") {
  //       var response = {
  //         success: {
  //           token: WPLE.token,
  //           stored: 1
  //         }
  //       }

  //       metricsTokenHandler(response);

  //     } else {
  //       var lidvalue = metricsDiv.attr('data-lid');
  //       var payload = {
  //         'stage': 'validate',
  //         'lid': lidvalue
  //       };
  //       var jsonData = JSON.stringify(payload);

  //       $.ajax({
  //         type: "POST",
  //         url: apiURL,
  //         dataType: "json",
  //         crossDomain: true,
  //         data: jsonData,
  //         beforeSend: function () {
  //           metricsDiv.html(loadingIcon);
  //         },
  //         error: function () {
  //           metricsDiv.text("Unable to make request. Please try re-loading the page after some time.");
  //         },
  //         success: metricsTokenHandler
  //       });
  //     }

  //   } else {

  //     jQuery.ajax({
  //       method: "POST",
  //       url: ajaxurl.replace('https', 'http'),
  //       dataType: "json",
  //       data: {
  //         action: 'wple_get_cached_metrics',
  //         period: metricsDiv.attr('data-period'),
  //         nc: metricsDiv.attr('data-nc')
  //       },
  //       error: function (res) {
  //         alert('Somethin went wrong! Please try again later.');
  //       },
  //       success: function (response) {

  //         if (response.error) {
  //           alert("Data expired! Please try re-loading the page.");
  //         } else {
  //           var res = response.success;
  //           DrawChartByData(res);
  //         }

  //       }
  //     });

  //   }

  // }

  // $("#wple-metrics-period").change(function () {
  //   var slctd = $("#wple-metrics-period option:selected").val();

  //   window.location.href = window.location.href + '&period=' + parseInt(slctd);
  //   return false;
  // })

  $('.wpenfr-domain').change(function () {
    var prefx = $('.domainprefix').attr('data-prefix');
    if ($(this).is(':checked')) {
      $('.firewall-arecord').addClass('hideit');
      $('.domainprefix').text(prefx);
    } else {
      $('.firewall-arecord').removeClass('hideit');
      $('.domainprefix').text('www');
    }
  });
  /* </fs_premium_only> */

  $('.wple_include_www').change(function () {
    if ($(this).is(':checked')) {
      var $this = $(this);

      $.ajax({
        type: 'GET',
        url: ajaxurl,
        async: true,
        dataType: 'text',
        data: {
          action: 'wple_include_www',
          nc: $('#letsencrypt').attr('value'),
        },
        beforeSend: function () {},
        error: function () {
          alert('Something went wrong. Please re-try..');
        },
        success: function (response) {
          if (response != 1 && response != '1') {
            $this.removeAttr('checked');

            if (response == 'www') {
              alert(
                'Your www domain is not reachable, so this option cannot be enabled.',
              );
            } else if (response == 'nonwww') {
              alert(
                'Your non-www domain is not reachable, so this option cannot be enabled.',
              );
            } else {
              alert('Authentication failure! Please try again');
            }
          } else {
            $('.wple-www').addClass('active');
          }
        },
      });
    } else {
      $('.wple-www').removeClass('active');
    }
  });

  $('.single-wildcard-switch').change(function () {
    if ($(this).is(':checked')) {
      $('.single-genform').fadeOut('fast');
      $('.wildcard-genform').fadeIn('fast');
      $('.wple-wc').addClass('active');
    } else {
      $('.wildcard-genform').fadeOut('fast');
      $('.single-genform').fadeIn('fast');
      $('.wple-wc').removeClass('active');
    }
  });

  $('.initplan-switch').change(function () {
    if ($(this).is(':checked')) {
      $('.wplepricingcol.proplan').removeClass('hiddenplan');
      $('.wplepricingcol.firewallplan').addClass('hiddenplan');
    } else {
      $('.wplepricingcol.proplan').addClass('hiddenplan');
      $('.wplepricingcol.firewallplan').removeClass('hiddenplan');
    }
  });

  jQuery('.wple-scan').click(function () {
    var $button = $(this);
    $('.wple-frameholder').html('');
    $(this).text('SCANNING').attr('disabled', 'disabled');

    jQuery.ajax({
      method: 'POST',
      url: SCAN.adminajax,
      dataType: 'html',
      data: {
        action: 'wple_start_scanner',
        nc: $button.attr('data-nc'),
      },
      beforeSend: function () {
        $('.mxnossl').remove();
        $('#wple-scanner-iframe').css('height', '510px');

        var frm = document.createElement('iframe');

        frm.src = SCAN.base;
        frm.width = 500;
        frm.height = 500;
        frm.scrolling = 'no';
        document.getElementsByClassName('wple-frameholder')[0].appendChild(frm);
      },
      error: function () {
        alert('Request failed! Please try again.');
        $button.text('SCAN').removeAttr('disabled');
        $('.wple-frameholder').slideUp('fast');
      },
      success: function (response) {
        if (response == 'nossl') {
          $button.text('START THE SCAN').removeAttr('disabled');
          $('#wple-scanner-iframe').fadeOut('fast');
          $('#wple-scanner').after(
            '<div class="mxnossl">Valid SSL Certificate could not be detected on your site! Please install SSL Certificate & force HTTPS before checking for mixed content issues.</div>',
          );
          return false;
        } else {
          $('.wple-scanbar')
            .css('animation', 'none')
            .text('Populating Mixed Content Stats! Please wait...')
            .addClass('complete');

          if (response == 'success') {
            $('.wple-scan').text('COMPLETED');
            $('.wple-scanbar')
              .text('All good! Mixed content issues not found.')
              .addClass('success');
            $('.wple-frameholder').slideUp('fast');
            return false;
          }

          $('#wple-scanner-iframe').fadeOut('fast');
          $('#wple-scanresults').html(response);
          $('.wple-scan').text('COMPLETED');

          $('.wple-tooltip').each(function () {
            var $this = $(this);

            tippy('.wple-tooltip:not(.bottom)', {
              //content: $this.attr('data-content'),
              placement: 'top',
              onShow(instance) {
                instance.popper.hidden = instance.reference.dataset.tippy
                  ? false
                  : true;
                instance.setContent(instance.reference.dataset.tippy);
              },
              //arrow: false
            });
          });
        }
      },
    });
  });

  /**
   * v5.2.6
   */

  $('.have-root-ssh').click(function () {
    $(this).siblings().removeClass('active');
    $(this).addClass('active');

    $('.rootssh-check').fadeOut('fast');
    $('.havessh').fadeIn('fast');
  });

  $('.no-root-ssh').click(function () {
    $(this).siblings().removeClass('active');
    $(this).addClass('active');

    $('.rootssh-check').fadeOut('fast');
    $('.nossh').fadeIn('fast');
  });

  $('.check-root-ssh li').click(function () {
    $('.nocp-ssl-validation').show();
  });

  $('#validate-nocp-ssl').click(function () {
    var $this = $(this);

    jQuery.ajax({
      method: 'GET',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_validate_ssl',
      },
      beforeSend: function () {
        $this.find('span').show();
        $('.wple-validate-nossl').hide();
      },
      error: function () {
        $this.find('span').hide();
        alert('Could not validate SSL! Please try later.');
      },
      success: function (response) {
        $this.find('span').hide();

        if (response == 1) {
          var currenthref = window.location.href;
          window.location.href = currenthref + '&success=1';
          return false;
        } else {
          $('.wple-validate-nossl').fadeIn('fast');
        }
      },
    });
  });

  $('.email-certs-switch,.disable-spmode-switch,.force-spmode-switch').change(
    function () {
      var $this = $(this);

      jQuery.ajax({
        method: 'POST',
        url: ajaxurl,
        dataType: 'text',
        data: {
          action: 'wple_email_certs',
          emailcert: $('.email-certs-switch').is(':checked'),
          spmode: $('.disable-spmode-switch').is(':checked'),
          forcespmode: $('.force-spmode-switch').is(':checked'),
          nc: $('.download-certs').attr('data-update'),
        },
        beforeSend: function () {},
        error: function () {
          alert('Failed to save opt! Please try again');
        },
        success: function (response) {
          if (response == 'failed') {
            alert("Couldn't save your settings! Please re-try.");
          } else {
            alert('Settings Saved!');
          }
        },
      });
    },
  );

  $('.wple-did-review,.wple-later-review').click(function (e) {
    var $this = $(this);
    e.preventDefault();

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_review_notice',
        nc: $this.attr('data-nc'),
        choice: $this.attr('data-action'),
      },
      beforeSend: function () {},
      error: function () {
        alert('Failed to save! Please try again');
      },
      success: function (response) {
        $('.wple-admin-review').fadeOut('slow');
      },
    });
  });

  $('.wple-mx-ignore').click(function (e) {
    var $this = $(this);
    e.preventDefault();

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_mxerror_ignore',
        remind: $this.hasClass('wple-hire-later'),
      },
      beforeSend: function () {},
      error: function () {
        //alert("Failed to save! Please try again");
      },
      success: function (response) {
        $('.wple-mx-prom').fadeOut('slow');
      },
    });
  });

  //since 7.8.1
  $('.wple-notice-dismiss').click(function (e) {
    var $this = $(this);
    e.preventDefault();

    var ctxt = $this.attr('data-context');

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_dismiss_notice',
        context: ctxt,
      },
      beforeSend: function () {},
      error: function () {
        alert('Failed to save! Please try again');
      },
      success: function (response) {
        $('.notice-info.' + ctxt).fadeOut('slow');
      },
    });
  });

  function copycert(elem) {
    var element = document.querySelector(elem);
    if (typeof element !== 'undefined') {
      element.select();
      element.setSelectionRange(0, 9999999);
      return document.execCommand('copy');
    } else {
      return false;
    }
  }

  $('.copycert').click(function () {
    var $this = $(this);
    var ftype = $this.attr('data-type');
    var txtarea = $('.crt-content textarea');

    jQuery.ajax({
      method: 'GET',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_getcert_for_copy',
        nc: txtarea.attr('data-nc'),
        gettype: ftype,
      },
      beforeSend: function () {
        txtarea.slideUp('fast');
      },
      error: function () {
        alert('Something went wrong! Please try again');
      },
      success: function (response) {
        txtarea.text(response).slideDown('fast');
        $('.copied-success')
          .fadeIn('fast')
          .delay(2000)
          .promise()
          .done(function () {
            $('.copied-success').fadeOut('fast');
          });
        copycert('.crt-content textarea');
      },
    });
  });

  /** 5.5.0 */
  function colorSwitch($new_score) {
    var $scorebar = $('.wple-scorebar span');

    if ($new_score >= 30 && $new_score <= 70) {
      $scorebar.css('background', '#e2d754');
    } else if ($new_score > 70) {
      $scorebar.css('background', '#67d467');
    } else {
      $scorebar.css('background', '#ff5252');
    }
  }

  $('.wple-setting').click(function () {
    var $this = $(this);
    var $opt = $this.attr('data-opt');
    var $val = 0;

    if ($this.is(':checked')) {
      $val = 1;
    }

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_update_settings',
        opt: $opt,
        nc: $('.wple-ssl-settings').attr('data-update'),
        val: $val,
      },
      beforeSend: function () {
        $('li.wple-setting-error').fadeOut('fast');
      },
      error: function () {
        alert('Could not update setting! Please try again.');
      },
      success: function (response) {
        var $scorebar = $('.wple-scorebar span');
        var $existing_score = $scorebar.attr('data-width');
        var $new_score;

        if (response == '1') {
          $this.removeAttr('checked');
          $('.wple-setting-error').fadeIn('fast');
          return false;
        } else if (response > 0) {
          $new_score = parseInt($existing_score) + 10;

          $scorebar.animate({
            width: $new_score + '%',
          });

          $scorebar.attr('data-width', $new_score);

          $('li.' + $opt + ' span')
            .removeClass('wple-no')
            .addClass('wple-yes')
            .text('Yes');
          colorSwitch($new_score);

          if ($new_score == 80 && !$('.score-prom').length) {
            $('.wple-scorebar').after(
              "<h3 class='score-prom'>You still have major task pending!</h3>",
            );
          }
        } else if (response < 0) {
          $new_score = parseInt($existing_score) - 10;

          $scorebar.animate({
            width: $new_score + '%',
          });

          $scorebar.attr('data-width', $new_score);

          $('li.' + $opt + ' span')
            .removeClass('wple-yes')
            .addClass('wple-no')
            .text('No');
          colorSwitch($new_score);
        } else if (response == 'htaccessnotwritable') {
          alert(
            '.htaccess file not writable! Please change .htaccess file permission to 644 in order to implement security headers.',
          );
          $this.removeAttr('checked');
          return false;
        } else if (response == 'wpconfignotwritable') {
          alert(
            'wp-config.php file not writable! Please change wp-config file permission to 644 in order to implement HttpOnly cookies.',
          );
          $this.removeAttr('checked');
          return false;
        }

        if ($opt == 'vulnerability_scan') {
          if ($val == 1) {
            $('#wple-vulnerability-scanner:not(.wple-malware-scanner)').show();
          } else {
            $('#wple-vulnerability-scanner:not(.wple-malware-scanner)').hide();
          }
        }

        $('.wple-score').text($new_score);
        //append html to sibiling label
        var $label = $this.closest('li').children('label').first();
        var $saved = $("<span class='wple-opt-saved'>Saved!</span>");
        $label.append($saved);
        $saved.delay(2000).fadeOut(400, function () {
          $(this).remove();
        });
      },
    });
  });

  /** 5.7.14 **/
  $('.wple-backup-skip').click(function (e) {
    var $this = $(this);
    e.preventDefault();

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_backup_ignore',
      },
      beforeSend: function () {},
      error: function () {
        //alert("Failed to save! Please try again");
      },
      success: function (response) {
        $('.le-powered').fadeOut('slow');
      },
    });
  });

  $('#wple-security-settings input').change(function () {
    var opts = $('#wple-security-settings').serializeArray();
    var nc = $('#wple-security-settings').attr('data-update');

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_update_security',
        opt: opts,
        nc: nc,
      },
      beforeSend: function () {},
      error: function () {
        alert('Could not update setting! Please try again.');
      },
      success: function (response) {
        if (response == 0) {
          alert('Could not update setting! Please try again.');
        } else {
          console.log(response);
        }
      },
    });
  });

  //since 7.7.0
  $('.wple-ignore-btn').click(function (e) {
    var $this = $(this);
    e.preventDefault();

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_global_ignore',
        context: $this.attr('data-context'),
      },
      beforeSend: function () {},
      error: function () {
        //alert("Failed to save! Please try again");
      },
      success: function (response) {
        $('.wple-notice-' + $this.attr('data-context')).fadeOut('slow');
      },
    });
  });

  $('.wple-dont-show-btn').click(function (e) {
    var $this = $(this);
    e.preventDefault();

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_global_dontshow',
        context: $this.attr('data-context'),
      },
      beforeSend: function () {},
      error: function () {
        //alert("Failed to save! Please try again");
      },
      success: function (response) {
        $('.wple-notice-' + $this.attr('data-context')).fadeOut('slow');
      },
    });
  });

  //since 7.8.0
  $('.wple-mscan-ignorefile').click(function (e) {
    var $this = $(this);
    e.preventDefault();

    var ky = $this.attr('data-key');
    var filename = $this.attr('data-file');
    var nc = $('#wple-mscan-table').attr('data-nc');

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_mscan_ignorefile',
        fyle: filename,
        nc: nc,
        remove: $this.attr('data-remove'),
      },
      beforeSend: function () {
        $this.text('Processing...');
      },
      error: function () {
        $this.text('Failed. Click to re-try..');
        //alert("Failed to save! Please try again");
      },
      success: function (response) {
        if (response == 1) {
          $('tr.mscanfile-' + ky).fadeOut('fast');
        } else {
          $this.text('Failed. Click to re-try..');
        }
      },
    });
  });

  $('.wple-passkey-feature').change(function () {
    var $this = $(this);

    jQuery.ajax({
      method: 'POST',
      url: ajaxurl,
      dataType: 'text',
      data: {
        action: 'wple_enable_passkey',
        enabled: $('.wple-passkey-feature').is(':checked'),
        nc: SCAN.nc,
      },
      beforeSend: function () {},
      error: function () {
        alert('Failed to save opt! Please try again');
      },
      success: function (response) {
        if (response == 'failed') {
          alert("Couldn't save your settings! Please re-try.");
        } else {
          alert('Settings Saved!');
        }
      },
    });
  });
})(jQuery);

(function () {
  const btn = document.getElementById('wple-register-passkey');
  const msg = document.getElementById('wple-passkey-msg');

  function showMessage(text, isError) {
    if (!msg) return;
    msg.textContent = text;
    msg.style.color = isError ? 'red' : 'green';
  }

  function base64ToUint8Array(base64) {
    // server uses base64 (not base64url)
    const raw = atob(base64);
    const arr = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; ++i) arr[i] = raw.charCodeAt(i);
    return arr;
  }

  function arrayBufferToBase64(buffer) {
    const bytes =
      buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
    const chunkSize = 0x8000; // 32KB - avoids call stack / arg length limits
    let binary = '';
    for (let i = 0; i < bytes.length; i += chunkSize) {
      binary += String.fromCharCode.apply(
        null,
        bytes.subarray(i, i + chunkSize),
      );
    }
    return btoa(binary);
  }

  function arrayBufferToBase64Url(buffer) {
    const b64 = arrayBufferToBase64(buffer);
    return b64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  async function postForm(data) {
    const form = new URLSearchParams();
    for (const k in data) {
      if (Array.isArray(data[k]) || typeof data[k] === 'object') {
        form.append(k, JSON.stringify(data[k]));
      } else {
        form.append(k, data[k]);
      }
    }
    const res = await fetch(SCAN.adminajax, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    return res.json();
  }

  async function registerPasskey() {
    showMessage('', false);
    btn.disabled = true;
    try {
      const optResp = await postForm({
        action: 'wple_get_register_options',
        nonce: SCAN.nc,
      });
      if (!optResp.success) {
        showMessage(optResp.data || 'Failed to load options', true);
        btn.disabled = false;
        return;
      }

      const options = optResp.data;
      // convert server base64 -> Uint8Array
      options.challenge = base64ToUint8Array(options.challenge);
      options.user.id = base64ToUint8Array(options.user.id);

      if (options.excludeCredentials) {
        // convert base64url -> base64 then to Uint8Array
        options.excludeCredentials = options.excludeCredentials.map((c) => {
          const b64u = c.id;
          let b64 = b64u.replace(/-/g, '+').replace(/_/g, '/');
          while (b64.length % 4) b64 += '=';
          return {
            ...c,
            id: base64ToUint8Array(b64),
          };
        });
      }

      console.log(options);

      const cred = await navigator.credentials.create({ publicKey: options });
      if (!cred) {
        showMessage('No credential created', true);
        btn.disabled = false;
        return;
      }

      const response = cred.response;
      const payload = {
        action: 'wple_register_passkey',
        nonce: SCAN.nc,
        challenge: arrayBufferToBase64(optResp.data.challenge), // original base64 challenge from server
        id: cred.id,
        rawId: arrayBufferToBase64(cred.rawId),
        type: cred.type,
        clientDataJSON: arrayBufferToBase64(response.clientDataJSON),
        attestationObject: arrayBufferToBase64(response.attestationObject),
        transports: cred.response.getTransports
          ? cred.response.getTransports()
          : [],
      };

      //console.log(response);

      const reg = await postForm(payload);
      if (reg.success) {
        showMessage(reg.data.message || 'Passkey registered', false);
        const cleanUrl =
          window.location.protocol +
          '//' +
          window.location.host +
          window.location.pathname +
          window.location.hash;
        setTimeout(() => {
          window.location.replace(cleanUrl);
        }, 5000);
      } else {
        showMessage(reg.data || 'Registration failed', true);
      }
    } catch (err) {
      console.error(err);
      showMessage(err.message || 'Error', true);
    } finally {
      btn.disabled = false;
    }
  }

  if (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      registerPasskey();
    });
  }
})();
