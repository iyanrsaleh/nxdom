(function() {
  var baseUrl = window.location.pathname.replace(/\/?$/, '');
  var addUrl = baseUrl + '/add';
  var editUrl = baseUrl + '/edit';
  var deleteUrl = baseUrl + '/delete';
  var modal = document.getElementById('packageFormModal');
  var form = document.getElementById('formPackageItem');
  var keyInput = document.getElementById('pkgFormKey');

  if (!modal || !form) return;

  var devSelect = document.getElementById('pkgFormDevelopment');

  window.openPackageForm = function(id, label, icon, key, development) {
    document.getElementById('packageFormTitle').innerHTML = '<i class="fas fa-key"></i> ' + (id ? 'Edit' : 'Tambah') + ' Package';
    document.getElementById('pkgFormId').value = id || '';
    document.getElementById('pkgFormLabel').value = label || '';
    document.getElementById('pkgFormIcon').value = icon || 'fas fa-circle';
    if (devSelect) {
      var n = parseInt(development, 10);
      devSelect.value = (n === 1 || n === 2 || n === 3) ? String(n) : '2';
    }
    if (keyInput) {
      keyInput.value = key || '';
      keyInput.readOnly = !!id;
    }
    modal.classList.add('active');
  };

  window.closePackageForm = function() {
    modal.classList.remove('active');
  };

  modal.addEventListener('click', function(e) {
    if (e.target === modal) closePackageForm();
  });

  var btnAdd = document.getElementById('btnAddPackage');
  if (btnAdd) btnAdd.onclick = function() { openPackageForm(); };

  document.querySelectorAll('.pkg-edit').forEach(function(btn) {
    btn.onclick = function() {
      openPackageForm(btn.dataset.id, btn.dataset.label, btn.dataset.icon, btn.dataset.key, btn.dataset.development);
    };
  });

  document.querySelectorAll('.pkg-delete').forEach(function(btn) {
    btn.onclick = function() {
      if (!confirm('Hapus package "' + btn.dataset.key + '"?')) return;
      var fd = new FormData();
      fd.append('id', btn.dataset.id);
      fetch(deleteUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(res) {
          if (res.success) location.reload();
          else alert(res.message || 'Gagal menghapus');
        })
        .catch(function() { alert('Terjadi kesalahan jaringan'); });
    };
  });

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    var id = document.getElementById('pkgFormId').value;
    var fd = new FormData(form);
    var url = id ? editUrl : addUrl;
    var btn = form.querySelector('button[type="submit"]');
    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r) {
        return r.text().then(function(txt) {
          var res = {};
          try { res = JSON.parse(txt); } catch (_) { res = { message: txt || 'Response tidak valid' }; }
          if (!r.ok) throw new Error(res.message || 'Server error ' + r.status);
          return res;
        });
      })
      .then(function(res) {
        if (res.success) { closePackageForm(); location.reload(); }
        else alert(res.message || 'Gagal menyimpan');
      })
      .catch(function(err) {
        alert('Terjadi kesalahan: ' + (err.message || 'Cek koneksi'));
      })
      .finally(function() {
        btn.disabled = false;
        btn.innerHTML = orig;
      });
  });
})();
