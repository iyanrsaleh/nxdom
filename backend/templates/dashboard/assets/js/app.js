  (function(){
    var c=document.cookie;
    if(c.indexOf('sidebarCollapsed=1')>=0)document.documentElement.classList.add('sidebar-collapsed');
    if(c.indexOf('darkmode=1')>=0||(typeof localStorage!=='undefined'&&localStorage.getItem('darkmode')==='1'))
      document.documentElement.classList.add('dark-mode');
  })();

  function toggleSidebar() {
    var s = document.getElementById('sidebar');
    var o = document.getElementById('sidebarOverlay');
    if (s.classList.contains('open')) {
      s.classList.remove('open');
      o.classList.remove('show');
      o.style.display = 'none';
    } else {
      s.classList.add('open');
      o.style.display = 'block';
      setTimeout(function(){ o.classList.add('show'); }, 10);
    }
  }

  function toggleSidebarCollapsed() {
    if (window.innerWidth <= 992) {
      toggleSidebar();
      return;
    }
    var c = document.querySelector('.container');
    var btn = document.getElementById('menuToggleBtn');
    var icon = btn ? btn.querySelector('i') : null;
    if (c.classList.contains('sidebar-collapsed')) {
      c.classList.remove('sidebar-collapsed');
      document.documentElement.classList.remove('sidebar-collapsed');
      if (icon) icon.className = 'fas fa-bars';
      localStorage.setItem('sidebarCollapsed', '0');
      document.cookie = 'sidebarCollapsed=0;path=/;max-age=31536000';
    } else {
      c.classList.add('sidebar-collapsed');
      document.documentElement.classList.add('sidebar-collapsed');
      if (icon) icon.className = 'fas fa-bars';
      localStorage.setItem('sidebarCollapsed', '1');
      document.cookie = 'sidebarCollapsed=1;path=/;max-age=31536000';
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    var container = document.querySelector('.container');
    var hasCollapsed = document.documentElement.classList.contains('sidebar-collapsed') ||
      (window.innerWidth > 992 && (localStorage.getItem('sidebarCollapsed') === '1' || document.cookie.indexOf('sidebarCollapsed=1') >= 0));
    if (container && hasCollapsed) {
      container.classList.add('sidebar-collapsed');
    }
    if (document.documentElement.classList.contains('dark-mode')) {
      document.body.classList.add('dark-mode');
    }
    document.documentElement.classList.remove('sidebar-collapsed');
    setTimeout(function() {
      if (container) container.classList.remove('no-transition');
    }, 100);

    // Dark mode toggle
    var themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
      if (document.cookie.indexOf('darkmode=1') >= 0 || localStorage.getItem('darkmode') === '1') {
        document.body.classList.add('dark-mode');
      }
      themeToggle.addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        document.documentElement.classList.toggle('dark-mode');
        var isDark = document.body.classList.contains('dark-mode');
        localStorage.setItem('darkmode', isDark ? '1' : '0');
        document.cookie = 'darkmode=' + (isDark ? '1' : '0') + ';path=/;max-age=31536000';
      });
    }
  });