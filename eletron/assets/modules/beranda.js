import {
  DockviewComponent,
  themeGithubDark,
  themeGithubLight,
} from '/vendor/dockview-core/package/main.esm.mjs';

const DOCKVIEW_CSS = '/vendor/dockview-core/styles/dockview.css';
let dockviewCssLoaded = false;
let dockviewInstance = null;
let tabCounter = 1;
let dockviewResizeCleanup = null;
let berandaThemeObserver = null;

const BODY_BERANDA_CLASS = 'body-beranda-dockview';
const BODY_BERANDA_FRAMELESS_CLASS = 'body-beranda-frameless';
let titlebarCleanup = null;
let titlebarActionCleanup = null;

const TITLEBAR_ICONS = {
  minimize: '<svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><rect x="1" y="4.5" width="8" height="1" fill="currentColor"/></svg>',
  maximize: '<svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><rect x="1.5" y="1.5" width="7" height="7" fill="none" stroke="currentColor" stroke-width="1"/></svg>',
  restore: '<svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1" d="M3 2.5h4.5V7"/><rect x="2.5" y="3" width="5" height="5" fill="none" stroke="currentColor" stroke-width="1"/></svg>',
  close: '<svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M2 2l6 6M8 2l-6 6" stroke="currentColor" stroke-width="1.2"/></svg>',
  sidebar: '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M2 2.5A.5.5 0 0 1 2.5 2h11a.5.5 0 0 1 .5.5v11a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11zM3 3v10h4V3H3zm5 0v10h5V3H8z"/></svg>',
  back: '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M10.5 3.5 6 8l4.5 4.5-.708.708L4.584 8l5.208-5.208.708.708z"/></svg>',
  forward: '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M5.5 3.5 10 8l-4.5 4.5.708.708L11.416 8 6.208 2.792 5.5 3.5z"/></svg>',
};

const TITLEBAR_APP_LOGO = '/assets/images/icon.png';

const TITLEBAR_MENUS = [
  {
    id: 'file',
    label: 'File',
    items: [
      { label: 'New Text File', shortcut: 'Ctrl+N', action: 'file.newText' },
      { label: 'New Window', shortcut: 'Ctrl+Shift+N', action: 'file.newWindow' },
      { type: 'separator' },
      { label: 'Open File…', shortcut: 'Ctrl+O', action: 'file.open' },
      { label: 'Open Folder…', shortcut: 'Ctrl+K Ctrl+O', action: 'file.openFolder' },
      { type: 'separator' },
      { label: 'Save', shortcut: 'Ctrl+S', action: 'file.save' },
      { label: 'Save As…', shortcut: 'Ctrl+Shift+S', action: 'file.saveAs' },
      { type: 'separator' },
      { label: 'Close Editor', shortcut: 'Ctrl+W', action: 'file.closeEditor' },
      { label: 'Close Window', shortcut: 'Alt+F4', action: 'file.closeWindow' },
    ],
  },
  {
    id: 'edit',
    label: 'Edit',
    items: [
      { label: 'Undo', shortcut: 'Ctrl+Z', action: 'edit.undo' },
      { label: 'Redo', shortcut: 'Ctrl+Y', action: 'edit.redo' },
      { type: 'separator' },
      { label: 'Cut', shortcut: 'Ctrl+X', action: 'edit.cut' },
      { label: 'Copy', shortcut: 'Ctrl+C', action: 'edit.copy' },
      { label: 'Paste', shortcut: 'Ctrl+V', action: 'edit.paste' },
      { type: 'separator' },
      { label: 'Find', shortcut: 'Ctrl+F', action: 'edit.find' },
      { label: 'Replace', shortcut: 'Ctrl+H', action: 'edit.replace' },
    ],
  },
  {
    id: 'selection',
    label: 'Selection',
    items: [
      { label: 'Select All', shortcut: 'Ctrl+A', action: 'selection.selectAll' },
      { label: 'Expand Selection', shortcut: 'Shift+Alt+Right', action: 'selection.expand' },
      { label: 'Shrink Selection', shortcut: 'Shift+Alt+Left', action: 'selection.shrink' }, 
    ],
  },
  {
    id: 'view',
    label: 'View',
    items: [
      { label: 'Command Palette…', shortcut: 'Ctrl+Shift+P', action: 'view.commandPalette' },
      { type: 'separator' },
      { label: 'Explorer', shortcut: 'Ctrl+Shift+E', action: 'view.explorer' },
      { label: 'Search', shortcut: 'Ctrl+Shift+F', action: 'view.search' },
      { type: 'separator' },
      { label: 'Appearance', action: 'view.appearance' },
      { label: 'Toggle Developer Tools', shortcut: 'F12', action: 'view.toggleDevTools' },
      { type: 'separator' },
      { label: 'Reload', shortcut: 'Ctrl+R', action: 'view.reload' },
    ],
  },
  {
    id: 'go',
    label: 'Go',
    items: [
      { label: 'Back', shortcut: 'Alt+Left', action: 'go.back' },
      { label: 'Forward', shortcut: 'Alt+Right', action: 'go.forward' },
      { type: 'separator' },
      { label: 'Go to File…', shortcut: 'Ctrl+P', action: 'go.file' },
      { label: 'Go to Line…', shortcut: 'Ctrl+G', action: 'go.line' },
    ],
  },
  {
    id: 'run',
    label: 'Run',
    items: [
      { label: 'Start Debugging', shortcut: 'F5', action: 'run.start' },
      { label: 'Run Without Debugging', shortcut: 'Ctrl+F5', action: 'run.withoutDebug' },
      { label: 'Stop Debugging', shortcut: 'Shift+F5', action: 'run.stop' },
    ],
  },
  {
    id: 'terminal',
    label: 'Terminal',
    items: [
      { label: 'New Terminal', shortcut: 'Ctrl+`', action: 'terminal.new' },
      { label: 'Split Terminal', shortcut: 'Ctrl+Shift+5', action: 'terminal.split' },
    ],
  },
  {
    id: 'help',
    label: 'Help',
    items: [
      { label: 'Documentation', action: 'help.docs' },
      { label: 'Keyboard Shortcuts', action: 'help.shortcuts' },
      { type: 'separator' },
      { label: 'About', action: 'help.about' },
    ],
  },
];

function escapeHtml(text) {
  return String(text ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function setTitlebarMaximizeIcon(btn, maximized) {
  if (!btn) return;
  btn.innerHTML = maximized ? TITLEBAR_ICONS.restore : TITLEBAR_ICONS.maximize;
  btn.title = maximized ? 'Restore' : 'Maximize';
  btn.setAttribute('aria-label', btn.title);
}

function buildTitlebarMenubarHtml() {
  return TITLEBAR_MENUS.map((menu) => `
    <button
      type="button"
      class="beranda-window-titlebar__menu"
      data-menu-id="${menu.id}"
      aria-haspopup="true"
      aria-expanded="false"
    >${escapeHtml(menu.label)}</button>
  `).join('');
}

function buildTitlebarMenuDropdownHtml(menu) {
  const rows = menu.items.map((item) => {
    if (item.type === 'separator') {
      return '<div class="beranda-window-titlebar__menu-sep" role="separator"></div>';
    }
    const shortcut = item.shortcut
      ? `<span class="beranda-window-titlebar__menu-shortcut">${escapeHtml(item.shortcut)}</span>`
      : '';
    return `
      <button
        type="button"
        class="beranda-window-titlebar__menu-item"
        data-menu-action="${escapeHtml(item.action)}"
        role="menuitem"
      >
        <span class="beranda-window-titlebar__menu-label">${escapeHtml(item.label)}</span>
        ${shortcut}
      </button>
    `;
  }).join('');
  return `<div class="beranda-window-titlebar__menu-panel" role="menu" hidden data-menu-panel="${menu.id}">${rows}</div>`;
}

function buildTitlebarHtml(title) {
  const menuPanels = TITLEBAR_MENUS.map(buildTitlebarMenuDropdownHtml).join('');
  return `
    <div class="beranda-window-titlebar__left">
      <button type="button" class="beranda-window-titlebar__logo" title="App" aria-label="App">
        <img src="${TITLEBAR_APP_LOGO}" alt="" width="16" height="16" />
      </button>
      <nav class="beranda-window-titlebar__menubar" role="menubar">
        ${buildTitlebarMenubarHtml()}
      </nav>
      <div class="beranda-window-titlebar__nav">
        <button type="button" class="beranda-window-titlebar__icon-btn" data-action="sidebar" title="Toggle Primary Side Bar" aria-label="Toggle Primary Side Bar">${TITLEBAR_ICONS.sidebar}</button>
        <button type="button" class="beranda-window-titlebar__icon-btn" data-action="back" title="Go Back" aria-label="Go Back">${TITLEBAR_ICONS.back}</button>
        <button type="button" class="beranda-window-titlebar__icon-btn" data-action="forward" title="Go Forward" aria-label="Go Forward">${TITLEBAR_ICONS.forward}</button>
      </div>
    </div>
    <div class="beranda-window-titlebar__center">
      <span class="beranda-window-titlebar__title">${escapeHtml(title)}</span>
    </div>
    <div class="beranda-window-titlebar__controls">
      <button type="button" class="beranda-window-titlebar__btn" data-action="minimize" title="Minimize" aria-label="Minimize">${TITLEBAR_ICONS.minimize}</button>
      <button type="button" class="beranda-window-titlebar__btn" data-action="maximize" title="Maximize" aria-label="Maximize">${TITLEBAR_ICONS.maximize}</button>
      <button type="button" class="beranda-window-titlebar__btn beranda-window-titlebar__btn--close" data-action="close" title="Close" aria-label="Close">${TITLEBAR_ICONS.close}</button>
    </div>
    ${menuPanels}
  `;
}

function runTitlebarMenuAction(action, api) {
  switch (action) {
    case 'file.newWindow':
      api?.openRouteWindow?.('/beranda');
      break;
    case 'file.closeWindow':
      api?.windowClose?.();
      break;
    case 'view.toggleDevTools':
      api?.toggleDevTools?.();
      break;
    case 'view.reload':
      if (api?.reloadWindow) api.reloadWindow();
      else location.reload();
      break;
    case 'view.explorer':
      document.dispatchEvent(new CustomEvent('beranda:titlebar-action', { detail: 'sidebar' }));
      break;
    case 'go.back':
      history.back();
      break;
    case 'go.forward':
      history.forward();
      break;
    case 'edit.undo':
      document.execCommand('undo');
      break;
    case 'edit.redo':
      document.execCommand('redo');
      break;
    case 'edit.cut':
      document.execCommand('cut');
      break;
    case 'edit.copy':
      document.execCommand('copy');
      break;
    case 'edit.paste':
      document.execCommand('paste');
      break;
    case 'edit.selectAll':
    case 'selection.selectAll':
      document.execCommand('selectAll');
      break;
    case 'help.docs':
      if (typeof location !== 'undefined') location.hash = '#/beranda';
      break;
    default:
      break;
  }
}

function attachTitlebarMenus(bar, api) {
  let openPanel = null;

  const closeMenu = () => {
    if (!openPanel) return;
    openPanel.hidden = true;
    const trigger = bar.querySelector(`[data-menu-id="${openPanel.dataset.menuPanel}"]`);
    trigger?.setAttribute('aria-expanded', 'false');
    openPanel = null;
  };

  const openMenu = (menuId, trigger) => {
    closeMenu();
    const panel = bar.querySelector(`[data-menu-panel="${menuId}"]`);
    if (!panel || !trigger) return;
    panel.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    openPanel = panel;
    const left = trigger.offsetLeft;
    panel.style.left = `${left}px`;
  };

  bar.querySelectorAll('.beranda-window-titlebar__menu').forEach((trigger) => {
    trigger.addEventListener('click', (event) => {
      event.stopPropagation();
      const menuId = trigger.getAttribute('data-menu-id');
      if (openPanel?.dataset.menuPanel === menuId) {
        closeMenu();
        return;
      }
      openMenu(menuId, trigger);
    });
  });

  bar.querySelectorAll('[data-menu-action]').forEach((item) => {
    item.addEventListener('click', (event) => {
      event.stopPropagation();
      runTitlebarMenuAction(item.getAttribute('data-menu-action'), api);
      closeMenu();
    });
  });

  const onDocPointer = (event) => {
    if (!openPanel) return;
    if (event.target.closest('.beranda-window-titlebar__menu-panel')
      || event.target.closest('.beranda-window-titlebar__menu')) return;
    closeMenu();
  };

  document.addEventListener('pointerdown', onDocPointer);

  return () => {
    document.removeEventListener('pointerdown', onDocPointer);
    closeMenu();
  };
}

function disposeTitlebar() {
  titlebarCleanup?.();
  titlebarCleanup = null;
  document.body.classList.remove(BODY_BERANDA_FRAMELESS_CLASS);
  document.querySelector('.beranda-window-titlebar')?.remove();
}

async function mountFramelessTitlebar(appFrame, title = 'App') {
  const api = window.electronAPI;
  if (!api?.windowMinimize || !appFrame) return null;

  let frameless = true;
  if (typeof api.isFrameless === 'function') {
    try {
      frameless = await api.isFrameless();
    } catch (_) {
      frameless = true;
    }
  }
  if (!frameless) return null;

  disposeTitlebar();
  document.body.classList.add(BODY_BERANDA_FRAMELESS_CLASS);

  const bar = document.createElement('header');
  bar.className = 'beranda-window-titlebar';
  bar.setAttribute('role', 'toolbar');
  bar.innerHTML = buildTitlebarHtml(title);

  appFrame.prepend(bar);

  const titleEl = bar.querySelector('.beranda-window-titlebar__title');
  const maxBtn = bar.querySelector('[data-action="maximize"]');
  const detachMenus = attachTitlebarMenus(bar, api);

  const syncTitle = (nextTitle) => {
    if (titleEl && nextTitle) titleEl.textContent = nextTitle;
  };
  syncTitle(title);

  const syncMaximized = async () => {
    try {
      const maximized = await api.windowIsMaximized?.();
      setTitlebarMaximizeIcon(maxBtn, Boolean(maximized));
    } catch (_) {
      /* abaikan */
    }
  };

  bar.addEventListener('dblclick', (event) => {
    if (event.target.closest('.beranda-window-titlebar__controls')
      || event.target.closest('.beranda-window-titlebar__menubar')
      || event.target.closest('.beranda-window-titlebar__nav')
      || event.target.closest('.beranda-window-titlebar__logo')) return;
    api.windowToggleMaximize?.();
  });

  bar.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.getAttribute('data-action');
    if (action === 'minimize') api.windowMinimize?.();
    else if (action === 'maximize') {
      api.windowToggleMaximize?.().then((res) => {
        setTitlebarMaximizeIcon(maxBtn, Boolean(res?.maximized));
      });
    } else if (action === 'close') api.windowClose?.();
    else if (action === 'sidebar') {
      document.dispatchEvent(new CustomEvent('beranda:titlebar-action', { detail: 'sidebar' }));
    } else if (action === 'back') history.back();
    else if (action === 'forward') history.forward();
  });

  const unsubs = [];
  if (typeof api.onWindowMaximizeChanged === 'function') {
    unsubs.push(api.onWindowMaximizeChanged((maximized) => {
      setTitlebarMaximizeIcon(maxBtn, maximized);
    }));
  }

  syncMaximized();

  titlebarCleanup = () => {
    detachMenus?.();
    unsubs.forEach((off) => {
      try {
        off?.();
      } catch (_) {
        /* abaikan */
      }
    });
    bar.remove();
  };

  return { bar, syncTitle };
}

function isBerandaDarkMode() {
  return document.body.classList.contains('dark-mode-grid');
}

function resolveDockviewTheme() {
  return isBerandaDarkMode() ? themeGithubDark : themeGithubLight;
}

function applyDockviewTheme() {
  dockviewInstance?.updateOptions?.({ theme: resolveDockviewTheme() });
}

/** Selaraskan dengan NexaMode + index.html — hanya body, jangan set html. */
function ensureBerandaThemeMode() {
  document.documentElement.classList.remove('dark-mode-grid');

  const saved = localStorage.getItem('darkMode');
  let useDark;
  if (saved !== null) {
    useDark = saved === 'true';
  } else {
    useDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  }
  document.body.classList.toggle('dark-mode-grid', useDark);
  document.documentElement.setAttribute('data-color-mode', useDark ? 'dark' : 'light');

  applyDockviewTheme();
}

function attachBerandaThemeObserver() {
  berandaThemeObserver?.disconnect();
  berandaThemeObserver = new MutationObserver(() => {
    document.documentElement.classList.remove('dark-mode-grid');
    applyDockviewTheme();
  });
  berandaThemeObserver.observe(document.body, {
    attributes: true,
    attributeFilter: ['class'],
  });
  window.addEventListener('storage', onBerandaThemeStorage);
}

function detachBerandaThemeObserver() {
  berandaThemeObserver?.disconnect();
  berandaThemeObserver = null;
  window.removeEventListener('storage', onBerandaThemeStorage);
}

function onBerandaThemeStorage(event) {
  if (event.key === 'darkMode') {
    ensureBerandaThemeMode();
  }
}

/** Grup sidebar (Navigasi saja) — sembunyikan aksi header kustom. */
function isSidebarNavGroup(group) {
  const panels = group?.panels ?? [];
  return panels.length === 1 && panels[0]?.id === 'nav';
}

function isEdgeGroup(group) {
  return group?.api?.location?.type === 'edge';
}

function shouldShowGroupHeaderActions(group) {
  return !isSidebarNavGroup(group) && !isEdgeGroup(group);
}

function shouldShowAddTabButton(group) {
  return shouldShowGroupHeaderActions(group);
}

const HEADER_ICONS = {
  menu: '<svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor"><path d="M2 3.5h12V5H2V3.5zm0 4h12V9H2V7.5zm0 4h12v1.5H2V11.5z"/></svg>',
  popout: '<svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor"><path d="M10 2h4v4h-1.5V4.5H10V2zM6 4v1.5H4.5V12h7.5V10.5H13V13H3V4H6zm4.5 0H13v2.5H14V3h-3.5v1z"/></svg>',
  maximize: '<svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor"><path d="M3 3h10v10H3V3zm1.5 1.5v7h7v-7h-7z"/></svg>',
};

function createHeaderIconButton(label, iconSvg, onClick) {
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'beranda-dockview-header-btn';
  btn.title = label;
  btn.setAttribute('aria-label', label);
  btn.innerHTML = `<span class="beranda-dockview-header-btn__icon" aria-hidden="true">${iconSvg}</span>`;
  btn.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    onClick?.(event, btn);
  });
  return btn;
}

function createHeaderActionsWrapper(className) {
  const element = document.createElement('div');
  element.className = className;
  return {
    element,
    init({ containerApi, group, api }) {
      element.replaceChildren();
      if (!shouldShowGroupHeaderActions(group)) return;
      return { containerApi, group, api };
    },
    dispose() {
      element.replaceChildren();
    },
  };
}

/** Hamburger di kiri tab bar. */
function createPrefixHeaderActions() {
  const shell = createHeaderActionsWrapper('beranda-dockview-header-prefix');
  const baseInit = shell.init.bind(shell);
  shell.init = (params) => {
    baseInit(params);
    if (!shouldShowGroupHeaderActions(params.group)) return;
    shell.element.appendChild(createHeaderIconButton('Menu', HEADER_ICONS.menu, () => {}));
  };
  return shell;
}

/** Popout, maximize di kanan tab bar. */
function createRightHeaderActions() {
  const shell = createHeaderActionsWrapper('beranda-dockview-header-right');
  const baseInit = shell.init.bind(shell);
  shell.init = (params) => {
    baseInit(params);
    if (!shouldShowGroupHeaderActions(params.group)) return;

    const { containerApi, group, api } = params;

    const popoutBtn = createHeaderIconButton('Popout', HEADER_ICONS.popout, () => {
      const item = group.activePanel ?? group.panels[0];
      if (item) containerApi.addPopoutGroup(item);
    });

    const maxBtn = createHeaderIconButton('Maximize', HEADER_ICONS.maximize, () => {
      if (containerApi.hasMaximizedGroup?.()) {
        containerApi.exitMaximizedGroup?.();
        maxBtn.classList.remove('is-active');
      } else {
        api.maximize?.();
        maxBtn.classList.add('is-active');
      }
    });

    shell.element.append(popoutBtn, maxBtn);
  };
  return shell;
}

/** Tombol + setelah daftar tab (dv-left-actions-container). */
function createAddTabHeaderActions() {
  const element = document.createElement('div');
  element.className = 'beranda-dockview-tab-actions';
  let onClick = null;

  return {
    element,
    init({ containerApi, group }) {
      element.replaceChildren();
      if (!shouldShowAddTabButton(group)) return;

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'beranda-dockview-tab-add';
      btn.title = 'Tambah tab';
      btn.setAttribute('aria-label', 'Tambah tab');
      btn.textContent = '+';

      onClick = (event) => {
        event.preventDefault();
        event.stopPropagation();
        const ref = group.activePanel ?? group.panels[0];
        if (!ref) return;

        tabCounter += 1;
        containerApi.addPanel({
          id: `tab_${Date.now()}`,
          component: 'tab',
          title: `Tab ${tabCounter}`,
          position: {
            referencePanel: ref,
            direction: 'within',
          },
        });
      };

      btn.addEventListener('click', onClick);
      element.appendChild(btn);
    },
    dispose() {
      element.replaceChildren();
      onClick = null;
    },
  };
}

function ensureDockviewStyles() {
  if (dockviewCssLoaded || document.querySelector('link[data-dockview-css]')) {
    dockviewCssLoaded = true;
    return;
  }
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = DOCKVIEW_CSS;
  link.dataset.dockviewCss = '1';
  document.head.appendChild(link);
  dockviewCssLoaded = true;
}

function disposeDockview() {
  dockviewResizeCleanup?.();
  dockviewResizeCleanup = null;

  if (!dockviewInstance) return;
  try {
    if (typeof dockviewInstance.dispose === 'function') {
      dockviewInstance.dispose();
    }
  } catch (_) {
    /* abaikan */
  }
  dockviewInstance = null;
}

/** Bersihkan seluruh halaman beranda (route pindah / re-entry). */
function disposeBerandaPage() {
  disposeDockview();
  detachBerandaThemeObserver();
  titlebarActionCleanup?.();
  titlebarActionCleanup = null;
  disposeTitlebar();
  document.body.classList.remove(BODY_BERANDA_CLASS);
  document.body.classList.remove(BODY_BERANDA_FRAMELESS_CLASS);
}

function attachBerandaTitlebarActions() {
  titlebarActionCleanup?.();
  const onAction = (event) => {
    if (!dockviewInstance) return;
    if (event.detail === 'sidebar') {
      dockviewInstance.getPanel('nav')?.api?.setActive();
    }
  };
  document.addEventListener('beranda:titlebar-action', onAction);
  titlebarActionCleanup = () => document.removeEventListener('beranda:titlebar-action', onAction);
}

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max);
}

/** Scroll vertikal gaya dockview (dv-scrollable + dv-scrollbar). */
function attachDockviewVerticalScroll(scrollHost, scrollBody, scrollbar) {
  let scrollOffset = 0;
  let resizeTimer = 0;

  const syncScrollbar = () => {
    const clientSize = scrollHost.clientHeight;
    const scrollSize = scrollBody.scrollHeight;
    const hasScrollbar = scrollSize > clientSize;

    if (!hasScrollbar) {
      scrollbar.style.height = '0px';
      scrollbar.style.top = '0px';
      scrollOffset = 0;
      scrollBody.scrollTop = 0;
      return;
    }

    const thumbSize = clientSize * (clientSize / scrollSize);
    scrollOffset = clamp(scrollOffset, 0, scrollSize - clientSize);
    scrollBody.scrollTop = scrollOffset;

    const ratio = scrollOffset / (scrollSize - clientSize);
    scrollbar.style.height = `${thumbSize}px`;
    scrollbar.style.top = `${(clientSize - thumbSize) * ratio}px`;
  };

  scrollHost.addEventListener('wheel', (event) => {
    scrollOffset += event.deltaY;
    syncScrollbar();
    event.preventDefault();
  }, { passive: false });

  scrollbar.addEventListener('pointerdown', (event) => {
    event.preventDefault();
    scrollHost.classList.add('dv-scrollable-scrolling');
    const startY = event.clientY;
    const startOffset = scrollOffset;

    const onMove = (moveEvent) => {
      const clientSize = scrollHost.clientHeight;
      const scrollSize = scrollBody.scrollHeight;
      const ratio = clientSize / scrollSize;
      scrollOffset = startOffset + (moveEvent.clientY - startY) / ratio;
      syncScrollbar();
    };

    const onEnd = () => {
      scrollHost.classList.remove('dv-scrollable-scrolling');
      document.removeEventListener('pointermove', onMove);
      document.removeEventListener('pointerup', onEnd);
      document.removeEventListener('pointercancel', onEnd);
    };

    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onEnd);
    document.addEventListener('pointercancel', onEnd);
  });

  scrollBody.addEventListener('scroll', () => {
    scrollOffset = scrollBody.scrollTop;
    syncScrollbar();
  });

  const ro = typeof ResizeObserver !== 'undefined'
    ? new ResizeObserver(() => {
        scrollHost.classList.add('dv-scrollable-resizing');
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
          scrollHost.classList.remove('dv-scrollable-resizing');
        }, 500);
        syncScrollbar();
      })
    : null;

  ro?.observe(scrollHost);
  ro?.observe(scrollBody);
  syncScrollbar();

  return () => {
    clearTimeout(resizeTimer);
    ro?.disconnect();
  };
}

function fillPanelContent(name, el, meta = {}) {
  switch (name) {
    case 'welcome':
      el.innerHTML = `
        <div class="main-splash beranda-dockview-panel__welcome">
          <img class="main-splash__icon" src="/assets/images/nx3.png" alt="" />
          <div class="main-splash__headlines">
            <h1 class="main-splash__welcome">Nxdom Framework</h1>
          </div>
          <p class="main-splash__lead">Nusantara eXtreme Development Object Model</p>
          <p class="beranda-dockview-panel__hint">Layout dockview — tarik tab atau resize panel seperti IDE.</p>
        </div>
      `;
      break;
    case 'nav':
      el.innerHTML = `
        <div class="beranda-dockview-panel__nav">
        <p class="site-header__hint">Contoh tautan:</p>
        <nav class="site-header__nav" aria-label="Navigasi utama">
          <ul class="site-header__links">
            <li><a href="/beranda" id="nav-home">Beranda</a></li>
            <li><a href="/about" id="nav-about">About</a></li>
            <li><a href="/blog" id="nav-blog">Blog</a></li>
          </ul>
        </nav>
        </div>
      `;
      break;
    case 'docs':
      el.innerHTML = `
        <div class="beranda-dockview-panel__docs">
          <h2>Dokumentasi</h2>
          <p>Panel ini siap menampilkan README modul atau halaman docs.</p>
          <p class="beranda-dockview-panel__hint">Endpoint contoh: <code>/nexa-module-doc/{label}</code></p>
        </div>
      `;
      break;
    case 'tab': {
      const title = meta.title || 'Tab baru';
      el.innerHTML = `
        <div class="beranda-dockview-panel__tab">
          <h2>${title}</h2>
          <p>Konten tab. Tarik tab untuk pindah grup atau tutup dengan klik kanan.</p>
        </div>
      `;
      break;
    }
    case 'outline':
      el.innerHTML = `
        <div class="beranda-dockview-panel__outline">
          <h2>Outline</h2>
          <ul class="beranda-dockview-outline-list">
            <li>src/App.js</li>
            <li>templates/beranda.js</li>
            <li>assets/css/style.css</li>
            <li>index.html</li>
          </ul>
          <p class="beranda-dockview-panel__hint">Klik tab vertikal di kanan untuk tutup/buka panel.</p>
        </div>
      `;
      break;
    case 'properties':
      el.innerHTML = `
        <div class="beranda-dockview-panel__properties">
          <h2>Properties</h2>
          <dl class="beranda-dockview-props">
            <dt>Framework</dt><dd>Nxdom</dd>
            <dt>Layout</dt><dd>Dockview edge group</dd>
            <dt>Theme</dt><dd>GitHub Light</dd>
          </dl>
        </div>
      `;
      break;
    default:
      el.textContent = name;
  }
}

/** dockview-core v6: renderer wajib punya `element`; init tidak menyediakan containerElement. */
function createPanelRenderer(name) {
  const element = document.createElement('div');
  element.className = 'beranda-dockview-panel';
  element.dataset.dockPanel = name;

  const scrollHost = document.createElement('div');
  scrollHost.className = 'dv-scrollable beranda-dockview-scroll';

  const scrollBody = document.createElement('div');
  scrollBody.className = 'beranda-dockview-scroll__body';

  const scrollbar = document.createElement('div');
  scrollbar.className = 'dv-scrollbar dv-scrollbar-vertical';

  scrollHost.appendChild(scrollBody);
  scrollHost.appendChild(scrollbar);
  element.appendChild(scrollHost);

  let detachScroll = null;

  return {
    element,
    init(params) {
      fillPanelContent(name, scrollBody, { title: params?.title });
      detachScroll?.();
      detachScroll = attachDockviewVerticalScroll(scrollHost, scrollBody, scrollbar);
    },
    dispose() {
      detachScroll?.();
      detachScroll = null;
    },
  };
}

/** Tab demo di grup utama (seperti referensi). */
const MAIN_DEMO_TABS = [
  { id: 'watchlist', title: 'Watchlist' },
  { id: 'price_alert', title: 'Price Alert' },
  { id: 'research', title: 'Research' },
  { id: 'tab_demo', title: 'Tab' },
  { id: 'analytics', title: 'Analytics' },
];

/** Ukuran layout awal — min/max untuk drag sash; lebar tidak dipaksa ulang setelah user resize. */
const VS_LAYOUT = {
  sidebarDefaultWidth: 250,
  sidebarMinWidth: 160,
  sidebarMaxWidth: 520,
  bottomHeight: 220,
  bottomMinHeight: 120,
  bottomMaxHeight: 480,
};

function resolveSidebarWidth() {
  return VS_LAYOUT.sidebarDefaultWidth;
}

/** Lebar panel kanan supaya sidebar nav tetap ~250px (bukan split 50/50). */
function resolveWelcomeInitialWidth(rootEl) {
  const total = Math.max(rootEl?.clientWidth ?? 0, 800);
  const navW = VS_LAYOUT.sidebarDefaultWidth;
  if (total <= navW + 320) return 320;
  return total - navW;
}

function setNavSidebarWidth(dv) {
  const nav = getDockPanel(dv, 'nav');
  nav?.group?.api?.setSize?.({ width: VS_LAYOUT.sidebarDefaultWidth });
}

function setDocsPanelHeight(dv) {
  const docs = getDockPanel(dv, 'docs');
  docs?.group?.api?.setSize?.({ height: VS_LAYOUT.bottomHeight });
}

function applyInitialLayout(dv, rootEl) {
  syncDockviewLayout(dv, rootEl);
  setNavSidebarWidth(dv);
  setDocsPanelHeight(dv);
  dv?.overlayRenderContainer?.updateAllPositions?.();
}

function getDockPanel(dv, id) {
  if (dv?.api?.getPanel) return dv.api.getPanel(id);
  if (typeof dv?.getGroupPanel === 'function') return dv.getGroupPanel(id);
  return null;
}

/** Sinkron lebar/tinggi dockview dengan container Electron. */
function syncDockviewLayout(dv, rootEl) {
  const w = rootEl?.clientWidth ?? 0;
  const h = rootEl?.clientHeight ?? 0;
  if (w > 0 && h > 0 && typeof dv?.layout === 'function') {
    dv.layout(w, h, true);
  }
}

function attachDockviewResize(dv, rootEl) {
  const shell = rootEl?.closest?.('.beranda-dockview-shell') || rootEl;
  let frame = 0;

  const sync = () => {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(() => {
      syncDockviewLayout(dv, rootEl);
      dv?.overlayRenderContainer?.updateAllPositions?.();
    });
  };

  const ro = typeof ResizeObserver !== 'undefined'
    ? new ResizeObserver(sync)
    : null;

  ro?.observe(shell);
  ro?.observe(rootEl);
  window.addEventListener('resize', sync, { passive: true });
  sync();

  return () => {
    cancelAnimationFrame(frame);
    ro?.disconnect();
    window.removeEventListener('resize', sync);
  };
}

/** Ukuran awal sekali setelah layout siap — jangan panggil ulang (menimpa drag user). */
function scheduleInitialLayout(dv, rootEl, navPanel, docsPanel, edgePanels) {
  revealGroupPanel(navPanel);
  revealGroupPanel(docsPanel);
  edgePanels.forEach(revealGroupPanel);

  applyInitialLayout(dv, rootEl);
  requestAnimationFrame(() => {
    applyInitialLayout(dv, rootEl);
    requestAnimationFrame(() => applyInitialLayout(dv, rootEl));
  });
}

function revealGroupPanel(panel) {
  if (!panel?.group?.model?.openPanel) return;
  panel.group.model.openPanel(panel, { skipSetGroupActive: true });
}

/** Panel vertikal di tepi kanan (Outline / Properties) — seperti demo dockview Abyss. */
const EDGE_RIGHT = {
  groupId: 'edge-right',
  collapsedSize: 36,
  initialSize: 280,
  minimumSize: 200,
  maximumSize: 420,
};

const EDGE_RIGHT_PANELS = [
  { id: 'outline', component: 'outline', title: 'Outline' },
  { id: 'properties', component: 'properties', title: 'Properties' },
];

function setupRightEdgeGroup(dv) {
  if (typeof dv.addEdgeGroup !== 'function') return [];

  dv.addEdgeGroup('right', {
    id: EDGE_RIGHT.groupId,
    collapsed: true,
    collapsedSize: EDGE_RIGHT.collapsedSize,
    initialSize: EDGE_RIGHT.initialSize,
    minimumSize: EDGE_RIGHT.minimumSize,
    maximumSize: EDGE_RIGHT.maximumSize,
  });

  const panels = [];
  EDGE_RIGHT_PANELS.forEach((spec, index) => {
    const panel = dv.addPanel({
      id: spec.id,
      component: spec.component,
      title: spec.title,
      position: {
        referenceGroup: EDGE_RIGHT.groupId,
        direction: 'within',
      },
      inactive: index > 0,
    });
    panels.push(panel);
  });

  return panels;
}

function initDockview(root) {
  disposeDockview();

  const sidebarW = resolveSidebarWidth();
  const welcomeW = resolveWelcomeInitialWidth(root);

  dockviewInstance = new DockviewComponent(root, {
    theme: resolveDockviewTheme(),
    /** Sidebar + panel bawah tampil bersamaan — overlay posisi konten di semua grup. */
    defaultRenderer: 'always',
    scrollbars: 'custom',
    createComponent: (options) => createPanelRenderer(options.name),
    createPrefixHeaderActionComponent: () => createPrefixHeaderActions(),
    createLeftHeaderActionComponent: () => createAddTabHeaderActions(),
    createRightHeaderActionComponent: () => createRightHeaderActions(),
  });

  // 1. Sidebar kiri dulu (tepi dock) — lebar initialWidth lebih andal
  const navPanel = dockviewInstance.addPanel({
    id: 'nav',
    component: 'nav',
    title: 'Navigasi',
    position: { direction: 'left' },
    initialWidth: sidebarW,
    minimumWidth: VS_LAYOUT.sidebarMinWidth,
    maximumWidth: VS_LAYOUT.sidebarMaxWidth,
    inactive: true,
  });

  // 2. Beranda di kanan Navigasi — initialWidth besar agar nav tidak split 50/50
  dockviewInstance.addPanel({
    id: 'welcome',
    component: 'welcome',
    title: 'Market Data',
    position: { referencePanel: navPanel, direction: 'right' },
    initialWidth: welcomeW,
  });
  setNavSidebarWidth(dockviewInstance);

  MAIN_DEMO_TABS.forEach(({ id, title }) => {
    dockviewInstance.addPanel({
      id,
      component: 'tab',
      title,
      position: { referencePanel: 'welcome', direction: 'within' },
      inactive: true,
    });
  });

  // 3. Dokumentasi di bawah Beranda
  const docsPanel = dockviewInstance.addPanel({
    id: 'docs',
    component: 'docs',
    title: 'Dokumentasi',
    position: { direction: 'below', referencePanel: 'welcome' },
    initialHeight: VS_LAYOUT.bottomHeight,
    minimumHeight: VS_LAYOUT.bottomMinHeight,
    maximumHeight: VS_LAYOUT.bottomMaxHeight,
    inactive: true,
  });
  setNavSidebarWidth(dockviewInstance);

  const edgePanels = setupRightEdgeGroup(dockviewInstance);
  dockviewResizeCleanup = attachDockviewResize(dockviewInstance, root);

  scheduleInitialLayout(dockviewInstance, root, navPanel, docsPanel, edgePanels);

  attachBerandaTitlebarActions();

  return dockviewInstance;
}

export async function beranda(page, route) {
  route.register(page, async (routeName, container, routeMeta = {
    title: 'Beranda | App',
    description: 'Halaman beranda.',
  }, style, nav = {}) => {
    disposeBerandaPage();
    route.routeMetaByRoute.set(page, routeMeta);
    ensureDockviewStyles();
    ensureBerandaThemeMode();
    attachBerandaThemeObserver();
    document.body.classList.add(BODY_BERANDA_CLASS);

    container.innerHTML = `
      <div class="beranda-app-frame" id="beranda-app-frame">
        <div class="beranda-dockview-shell">
          <div class="beranda-dockview dockview-spaced beranda-dockview-theme" id="beranda-dockview-root"></div>
        </div>
      </div>
    `;

    const appFrame = container.querySelector('#beranda-app-frame');
    await mountFramelessTitlebar(appFrame, routeMeta.title || 'Beranda | App');

    const root = container.querySelector('#beranda-dockview-root');
    if (root) {
      const start = () => {
        if (root.clientWidth < 80) {
          requestAnimationFrame(start);
          return;
        }
        initDockview(root);
      };
      start();
    }
  });
}
