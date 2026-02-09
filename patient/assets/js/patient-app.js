(function () {
  function getBasePath() {
    var path = window.location.pathname || '';
    var lower = path.toLowerCase();
    var idx = lower.indexOf('/patient/');
    if (idx === -1) return '';
    return path.slice(0, idx);
  }

  function formatDate(value) {
    if (!value) return '';
    var dt = new Date(value + 'T00:00:00');
    if (Number.isNaN(dt.getTime())) return value;
    return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' });
  }

  function formatTime(value) {
    if (!value) return '';
    var dt = new Date('1970-01-01T' + value);
    if (Number.isNaN(dt.getTime())) return value;
    return dt.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
  }

  function statusBadgeHtml(status) {
    var normalized = (status || '').toLowerCase();
    var map = {
      confirmed: 'badge-gradient-success',
      pending: 'badge-gradient-warning',
      cancelled: 'badge-gradient-danger',
      completed: 'badge-gradient-info'
    };
    var cls = map[normalized] || 'badge-gradient-secondary';
    var label = normalized ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : 'Unknown';
    return '<label class="badge ' + cls + '">' + label + '</label>';
  }

  function apiBase() {
    return getBasePath() + '/auth/api';
  }

  function apiFetch(path, options) {
    return fetch(apiBase() + path, options || {}).then(function (res) {
      return res.text().then(function (text) {
        var data = null;
        try {
          data = JSON.parse(text);
        } catch (e) {
          throw new Error(text || 'Invalid server response');
        }
        if (!res.ok) {
          var message = (data && data.message) ? data.message : ('HTTP ' + res.status);
          throw new Error(message);
        }
        return data;
      });
    });
  }

  function requirePatient() {
    return apiFetch('/current-user.php').then(function (data) {
      if (!data.user || data.user.type !== 'patient') {
        throw new Error('Unauthorized');
      }
      var nameEls = document.querySelectorAll('[data-patient-name]');
      nameEls.forEach(function (el) {
        el.textContent = data.user.name || 'Patient';
      });
      return data.user;
    }).catch(function () {
      window.location.href = getBasePath() + '/auth/login.html';
    });
  }

  function setBadgeCount(el, count) {
    if (!el) return;
    if (!count || count <= 0) {
      el.textContent = '';
      el.classList.add('d-none');
      return;
    }
    el.textContent = String(count);
    el.classList.remove('d-none');
  }

  function renderNotificationItems(items) {
    var listEl = document.getElementById('notificationList');
    if (!listEl) return;
    listEl.innerHTML = '';
    if (!items || items.length === 0) {
      listEl.innerHTML = '<div class="dropdown-item text-muted">No notifications</div>';
      return;
    }
    items.forEach(function (item) {
      var row = document.createElement('a');
      row.className = 'dropdown-item preview-item';
      row.innerHTML =
        '<div class="preview-thumbnail">' +
          '<div class="preview-icon bg-info">' +
            '<i class="mdi mdi-bell-outline"></i>' +
          '</div>' +
        '</div>' +
        '<div class="preview-item-content d-flex align-items-start flex-column justify-content-center">' +
          '<h6 class="preview-subject font-weight-normal mb-1">Notification</h6>' +
          '<p class="text-gray ellipsis mb-0">' + (item.message || '-') + '</p>' +
        '</div>';
      listEl.appendChild(row);
      listEl.appendChild(document.createElement('div')).className = 'dropdown-divider';
    });
  }

  function renderMessageItems(items) {
    var listEl = document.getElementById('messageList');
    if (!listEl) return;
    listEl.innerHTML = '';
    if (!items || items.length === 0) {
      listEl.innerHTML = '<div class="dropdown-item text-muted">No messages</div>';
      return;
    }
    items.forEach(function (item) {
      var row = document.createElement('a');
      row.className = 'dropdown-item preview-item';
      row.innerHTML =
        '<div class="preview-thumbnail">' +
          '<img src="' + (item.avatar || 'assets/images/faces/face1.jpg') + '" alt="image" class="profile-pic">' +
        '</div>' +
        '<div class="preview-item-content d-flex align-items-start flex-column justify-content-center">' +
          '<h6 class="preview-subject ellipsis mb-1 font-weight-normal">' + (item.sender_name || 'Message') + '</h6>' +
          '<p class="text-gray mb-0">' + (item.subject || item.body || '-') + '</p>' +
        '</div>';
      listEl.appendChild(row);
      listEl.appendChild(document.createElement('div')).className = 'dropdown-divider';
    });
  }

  function loadHeaderData() {
    var profileImg = document.getElementById('patientProfileImage');
    var fallback = profileImg ? (profileImg.getAttribute('data-default-src') || 'assets/images/faces/face1.jpg') : null;
    if (profileImg && fallback) {
      profileImg.src = 'data:image/gif;base64,R0lGODlhAQABAAAAACw=';
    }

    return apiFetch('/patient-header.php').then(function (data) {
      var user = data.user || {};
      var profileNameEls = document.querySelectorAll('[data-patient-name]');
      profileNameEls.forEach(function (el) {
        el.textContent = user.name || 'Patient';
      });

      if (profileImg) {
        var finalSrc = null;
        if (user.profile_image) {
          if (user.profile_image.indexOf('http') === 0 || user.profile_image.indexOf('/') === 0) {
            finalSrc = user.profile_image;
          } else {
            finalSrc = getBasePath() + '/' + user.profile_image;
          }
        } else {
          finalSrc = fallback;
        }
        if (finalSrc) {
          var sep = finalSrc.indexOf('?') === -1 ? '?' : '&';
          profileImg.src = finalSrc + sep + 'v=' + Date.now();
        }
      }

      setBadgeCount(document.getElementById('notificationCount'), data.unread_notifications || 0);
      setBadgeCount(document.getElementById('messageCount'), data.unread_messages || 0);
      renderNotificationItems(data.notifications || []);
      renderMessageItems(data.messages || []);
    }).catch(function () {
      if (profileImg && fallback) {
        profileImg.src = fallback;
      }
    });
  }

  window.PatientApp = {
    getBasePath: getBasePath,
    apiFetch: apiFetch,
    requirePatient: requirePatient,
    loadHeaderData: loadHeaderData,
    formatDate: formatDate,
    formatTime: formatTime,
    statusBadgeHtml: statusBadgeHtml
  };

  function enforceAuthIfRequired() {
    var body = document.body;
    if (!body || !body.hasAttribute('data-require-auth')) return;
    var img = document.getElementById('patientProfileImage');
    if (img) {
      img.src = 'data:image/gif;base64,R0lGODlhAQABAAAAACw=';
    }
    try { localStorage.removeItem('patient_profile_image'); } catch (e) {}
    requirePatient().then(function () {
      return loadHeaderData();
    }).catch(function () {
      return null;
    });
  }

  window.addEventListener('DOMContentLoaded', enforceAuthIfRequired);
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      enforceAuthIfRequired();
    }
  });
})();
