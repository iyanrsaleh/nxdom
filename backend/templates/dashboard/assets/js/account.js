(function() {
  function handleAvatarChange(event) {
    var file = event.target.files[0];
    if (!file) return;
    var validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    if (validTypes.indexOf(file.type) < 0) {
      showModal('Gagal', 'Format file tidak valid. Hanya JPG dan PNG yang diperbolehkan', 'error');
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      showModal('Gagal', 'Ukuran file terlalu besar. Maksimal 2MB', 'error');
      return;
    }
    var reader = new FileReader();
    reader.onload = function(e) { document.getElementById('avatarPreview').src = e.target.result; };
    reader.readAsDataURL(file);
    uploadAvatar(file);
  }

  function uploadAvatar(file) {
    var formData = new FormData();
    formData.append('avatar', file);
    var parts = window.location.pathname.split('/').filter(Boolean);
    var username = parts[0] || '';
    var btn = document.querySelector('.account-avatar-edit');
    var orig = btn ? btn.innerHTML : '';
    if (btn) {
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
      btn.disabled = true;
    }
    var url = username ? '/' + username + '/account/updateAvatar' : '';
    if (!url) {
      showModal('Error', 'URL tidak valid', 'error');
      if (btn) { btn.innerHTML = orig; btn.disabled = false; }
      return;
    }
    fetch(url, { method: 'POST', body: formData })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) showModal('Berhasil', 'Foto profil berhasil diperbarui', 'success');
        else {
          showModal('Gagal', data.message || 'Gagal', 'error');
          if (data.old_avatar) {
            var prev = document.getElementById('avatarPreview');
            if (prev) prev.src = data.old_avatar;
          }
        }
      })
      .catch(function(err) { showModal('Error', 'Terjadi kesalahan: ' + err.message, 'error'); })
      .finally(function() {
        if (btn) { btn.innerHTML = orig; btn.disabled = false; }
      });
  }

  function togglePassword(id) {
    var inp = document.getElementById(id);
    if (!inp) return;
    var wrap = inp.parentElement;
    var icon = wrap ? wrap.querySelector('.account-toggle-pw i') : null;
    if (inp.type === 'password') {
      inp.type = 'text';
      if (icon) icon.className = 'fas fa-eye-slash';
    } else {
      inp.type = 'password';
      if (icon) icon.className = 'fas fa-eye';
    }
  }

  function showModal(title, message, type, callback) {
    var titleEl = document.getElementById('modalTitle');
    var msgEl = document.getElementById('modalMessage');
    var iconEl = document.getElementById('modalIcon');
    var overlay = document.getElementById('modalOverlay');
    if (titleEl) titleEl.textContent = title;
    if (msgEl) msgEl.textContent = message;
    if (iconEl) {
      iconEl.className = 'account-modal-icon ' + (type || 'info');
      iconEl.innerHTML = type === 'success' ? '<i class="fas fa-check-circle"></i>' : type === 'error' ? '<i class="fas fa-times-circle"></i>' : type === 'warning' ? '<i class="fas fa-exclamation-triangle"></i>' : '<i class="fas fa-info-circle"></i>';
    }
    if (overlay) overlay.classList.add('active');
    window.modalCallback = callback || null;
  }

  function closeModal() {
    var overlay = document.getElementById('modalOverlay');
    if (overlay) overlay.classList.remove('active');
    if (window.modalCallback) {
      window.modalCallback();
      window.modalCallback = null;
    }
  }

  function updatePersonalInfo(event) {
    event.preventDefault();
    var form = event.target;
    var fd = new FormData(form);
    var btn = form.querySelector('button[type="submit"]');
    if (fd.get('nik') && String(fd.get('nik')).length !== 16) {
      showModal('Gagal', 'NIK harus 16 digit', 'error');
      return;
    }
    var parts = window.location.pathname.split('/').filter(Boolean);
    var username = parts[0] || '';
    var url = username ? '/' + username + '/account/updateProfile' : '';
    if (!url) {
      showModal('Error', 'URL tidak valid', 'error');
      return;
    }
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    }
    fetch(url, { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) showModal('Berhasil', data.message || 'Berhasil', 'success', function() { location.reload(); });
        else showModal('Gagal', data.message || 'Gagal', 'error');
      })
      .catch(function(err) { showModal('Error', 'Terjadi kesalahan: ' + err.message, 'error'); })
      .finally(function() {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-save"></i> Simpan Perubahan';
        }
      });
  }

  function updatePassword(event) {
    event.preventDefault();
    var form = event.target;
    var fd = new FormData(form);
    var cur = fd.get('current_password') || '';
    var pwd = fd.get('new_password') || '';
    var conf = fd.get('confirm_password') || '';
    if (!pwd && !conf) {
      showModal('Informasi', 'Isi password baru dan konfirmasi untuk mengubah password. Kosongkan password lama jika daftar via Google.', 'info');
      return;
    }
    if (!pwd || !conf) {
      showModal('Gagal', 'Password baru dan konfirmasi wajib diisi', 'error');
      return;
    }
    if (pwd !== conf) {
      showModal('Gagal', 'Password baru dan konfirmasi password tidak cocok', 'error');
      return;
    }
    var parts = window.location.pathname.split('/').filter(Boolean);
    var username = parts[0] || '';
    var url = username ? '/' + username + '/account/updatePassword' : '';
    if (!url) {
      showModal('Error', 'URL tidak valid', 'error');
      return;
    }
    var btn = form.querySelector('button[type="submit"]');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengubah...';
    }
    // Sama persis dengan updateProfile: FormData + fetch
    fetch(url, { method: 'POST', body: fd })
      .then(function(r) {
        return r.text().then(function(t) {
          try { return JSON.parse(t.replace(/^\uFEFF/, '')); }
          catch (e) { return { success: false, message: t.slice(0, 150) || 'Response invalid' }; }
        });
      })
      .then(function(data) {
        if (data.success) {
          showModal('Berhasil', data.message || 'Password berhasil diubah', 'success', function() { form.reset(); });
        } else {
          showModal('Gagal', data.message || 'Terjadi kesalahan. Periksa data yang diisi.', 'error');
        }
      })
      .catch(function(err) { showModal('Error', 'Terjadi kesalahan: ' + err.message, 'error'); })
      .finally(function() {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-lock"></i> Ubah Password';
        }
      });
  }

  window.handleAvatarChange = handleAvatarChange;
  window.togglePassword = togglePassword;
  window.closeModal = closeModal;
  window.showModal = showModal;

  function init() {
    var form1 = document.getElementById('formPersonalInfo');
    var form2 = document.getElementById('formPassword');
    if (form1) form1.addEventListener('submit', updatePersonalInfo);
    if (form2) form2.addEventListener('submit', updatePassword);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
