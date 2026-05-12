(function ($) {
  var $wrap = $('#wpencryption-passkey-wrapper');
  var $submit = $('#loginform .submit').first();
  if ($wrap.length && $submit.length) {
    $submit.after($wrap);
  }

  const loginbtn = document.getElementById('wple-passkey-login');
  const msg = document.getElementById('wple-login-error');
  const successMsg = document.getElementById('wple-login-success');

  function showMessage(text, isError) {
    if (!msg || text == '') return;
    if (isError) {
      msg.textContent = text;
      loginbtn.disabled = false;
      msg.style.display = 'block';
      successMsg.style.display = 'none';
    } else {
      successMsg.textContent = text;
      successMsg.style.display = 'block';
      msg.style.display = 'none';
    }
  }

  async function postForm(data) {
    console.log(data);
    const form = new URLSearchParams();
    for (const k in data) {
      if (Array.isArray(data[k]) || typeof data[k] === 'object') {
        form.append(k, JSON.stringify(data[k]));
      } else {
        form.append(k, data[k]);
      }
    }
    const res = await fetch(SIGNIN.adminajax, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    return res.json();
  }

  function b64u(buf) {
    var bytes = new Uint8Array(buf),
      bin = '';
    for (var i = 0; i < bytes.length; i++) {
      bin += String.fromCharCode(bytes[i]);
    }
    return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
  }

  function b64uToBuf(s) {
    var r = s.length % 4,
      p = r ? s + '==='.slice(0, 4 - r) : s;
    var bin = atob(p.replace(/-/g, '+').replace(/_/g, '/')),
      buf = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) {
      buf[i] = bin.charCodeAt(i);
    }
    return buf.buffer;
  }

  async function loginWithPasskey() {
    showMessage('', true); //clear error msg
    showMessage('', false); //clear success msg
    loginbtn.disabled = true;

    if (!window.PublicKeyCredential) {
      showMessage('Passkeys not supported by this browser.', true);
      return;
    }

    showMessage('Starting passkey login…', false);

    var userLogin = (document.getElementById('user_login') || {}).value || '';
    var challengeToken = '';

    postForm({
      action: 'wple_passkey_login_options',
      userLogin: userLogin,
      nonce: SIGNIN.nc,
    })
      .then(function (res) {
        if (!res || !res.success) {
          throw new Error(res?.data || 'Failed to get login options');
        }

        var o = res.data;
        challengeToken = o.challengeToken || '';

        return navigator.credentials.get({
          publicKey: {
            rpId: o.rpId,
            challenge: b64uToBuf(o.challenge),
            timeout: o.timeout,
            userVerification: o.userVerification,
            allowCredentials: (o.allowCredentials || []).map(function (c) {
              return { type: c.type, id: b64uToBuf(c.id) };
            }),
          },
        });
      })
      .then(function (assertion) {
        // User may have cancelled the credential picker
        if (!assertion) {
          throw new Error('No credential selected');
        }

        return postForm({
          action: 'wple_passkey_login_verify',
          nonce: SIGNIN.nc,
          credentialId: b64u(assertion.rawId),
          clientDataJSON: b64u(assertion.response.clientDataJSON),
          authenticatorData: b64u(assertion.response.authenticatorData),
          signature: b64u(assertion.response.signature),
          challengeToken: challengeToken,
        });
      })
      .then(function (verifyRes) {
        // Final verification response
        if (!verifyRes || !verifyRes.data.redirect) {
          throw new Error(
            verifyRes.data?.message || 'Login verification failed',
          );
        }

        showMessage(verifyRes.data?.message || 'Logged in successfully', false);

        // Redirect or reload after short delay
        if (verifyRes.data?.redirect) {
          setTimeout(function () {
            window.location.href = verifyRes.data.redirect;
          }, 500);
        } else {
          setTimeout(function () {
            window.location.reload();
          }, 500);
        }
      })
      .catch(function (err) {
        console.error('Passkey login error:', err);
        showMessage(err?.message || 'Error during passkey login', true);
      })
      .finally(function () {
        loginbtn.disabled = false;
      });
  }

  if (loginbtn) {
    loginbtn.addEventListener('click', function (e) {
      e.preventDefault();
      loginWithPasskey();
    });
  }
})(jQuery);
