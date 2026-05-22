import { NexaTree } from "./NexaTree.js";
import { standarMenu } from "./buildViewNodes.js";
export async function navigation(data) {
    const safeData = data && typeof data === "object" ? data : {};

    const workspaceStoreRaw = await NXUI.ref.get("bucketsStore", "workspace");
    const workspaceStore = workspaceStoreRaw && typeof workspaceStoreRaw === "object"
      ? workspaceStoreRaw
      : {};
    const persistedOpenBranches = workspaceStore?.treeState?.openBranches && typeof workspaceStore.treeState.openBranches === "object"
      ? workspaceStore.treeState.openBranches
      : {};
    const incomingOpenBranches = safeData?.treeState?.openBranches && typeof safeData.treeState.openBranches === "object"
      ? safeData.treeState.openBranches
      : {};
    const openBranches = { ...persistedOpenBranches, ...incomingOpenBranches };

    if (globalThis.window !== undefined) {
      globalThis.__nxWorkspaceOpenBranchesCache = { ...openBranches };
      if (typeof globalThis.nxWorkspaceTreeToggle !== "function") {
        globalThis.nxWorkspaceTreeToggle = async (detailsEl) => {
          try {
            const treeKey = detailsEl?.dataset?.treeKey;
            if (!treeKey) return;

            const cacheOpenBranches = globalThis.__nxWorkspaceOpenBranchesCache && typeof globalThis.__nxWorkspaceOpenBranchesCache === "object"
              ? globalThis.__nxWorkspaceOpenBranchesCache
              : {};

            const nextOpenBranches = {
              ...cacheOpenBranches,
              [treeKey]: Boolean(detailsEl.open),
            };

            const currentWorkspaceRaw = await NXUI.ref.get("bucketsStore", "workspace");
            const currentWorkspace = currentWorkspaceRaw && typeof currentWorkspaceRaw === "object"
              ? currentWorkspaceRaw
              : {};
            const currentTreeState = currentWorkspace.treeState && typeof currentWorkspace.treeState === "object"
              ? currentWorkspace.treeState
              : {};

            globalThis.__nxWorkspaceOpenBranchesCache = nextOpenBranches;

            await NXUI.ref.set("bucketsStore", {
              id: "workspace",
              ...currentWorkspace,
              treeState: {
                ...currentTreeState,
                openBranches: nextOpenBranches,
              },
            });
          } catch (error) {
            console.error("Gagal menyimpan state tree workspace:", error);
          }
        };
      }
    }

    const sourceItems = Array.isArray(safeData?.menuData)
      ? safeData.menuData
      : standarMenu;

    const menuData = sourceItems.map((item) => ({
      ...item,
      appName: item?.appname || item?.appName || item?.label || "",
      id: item?.id || "",
      icons: item?.icons || "inventory_2",
      status: item?.dev || item?.status || "development",
      deskripsi: item?.deskripsi || ""
    }));

    const menuTree = new NexaTree(menuData, { openState: openBranches });
      const styles = `<style>
  .tabel-view-theme {
    --tv-canvas: var(--color-canvas-subtle, #f6f8fa);
    --tv-surface: var(--color-canvas-default, #ffffff);
    --tv-surface-muted: var(--color-canvas-inset, #f6f8fa);
    --tv-border: var(--color-border-default, #d0d7de);
    --tv-border-muted: var(--color-border-muted, #d8dee4);
    --tv-text: var(--color-fg-default, #1f2328);
    --tv-text-muted: var(--color-fg-muted, #656d76);
    --tv-accent: var(--color-accent-fg, #0969da);
    --tv-accent-muted: var(--color-accent-subtle, #ddf4ff);
    --tv-success: var(--color-success-fg, #1a7f37);
    --tv-shadow: var(--color-shadow-small, 0 1px 0 rgba(31,35,40,.04));
    --tv-panel-shadow: var(--color-shadow-medium, 0 3px 6px rgba(140,149,159,.15));
    color: var(--tv-text);
  }
  body.dark-mode-grid .tabel-view-theme,
  html.dark-mode-grid body .tabel-view-theme {
    --tv-canvas: var(--dm-bg-main, #0d1117);
    --tv-surface: var(--dm-bg-card, #161b22);
    --tv-surface-muted: var(--dm-bg-surface, #21262d);
    --tv-border: var(--dm-border, #30363d);
    --tv-border-muted: var(--dm-border-dark, #3d444d);
    --tv-text: var(--dm-text-main, #e6edf3);
    --tv-text-muted: var(--dm-text-secondary, #8b949e);
    --tv-accent: var(--dm-blue-accent, #58a6ff);
    --tv-accent-muted: rgba(56,139,253,.15);
    --tv-success: var(--dm-success, #3fb950);
    --tv-shadow: none;
    --tv-panel-shadow: 0 0 0 1px rgba(48,54,61,.4);
  }
  .nexa-tree-root { list-style:none; margin:0; padding:0; }
  .nexa-tree-root ul { list-style:none; margin:0; padding-left:18px; }
  .nexa-tree-branch, .nexa-tree-leaf { position:relative; }
  .nexa-tree-children { margin-top:1px; border-left:1px solid var(--tv-border-muted); margin-left:7px; }
  .nexa-tree-details[open] > .nexa-tree-row .nexa-tree-caret { transform:rotate(90deg); }
  .nexa-tree-details summary { list-style:none; }
  .nexa-tree-details summary::-webkit-details-marker { display:none; }
  .nexa-tree-row {
    display:flex; gap:8px; align-items:center; min-height:26px;
    border-radius:6px; padding:3px 8px;
  }
  .nexa-tree-row.is-branch { cursor:pointer; }
  .nexa-tree-row.is-item-branch { justify-content:flex-start; }
  .nexa-tree-row.is-leaf {
    align-items:center; background:transparent; border:1px solid transparent;
    gap:4px; width:100%; text-align:left;
  }
  .nexa-tree-action { appearance:none; cursor:pointer; color:inherit; }
  .nexa-tree-action:hover { border-color:var(--tv-border); background:var(--tv-surface-muted); }
  .nexa-tree-action:focus-visible { outline:2px solid var(--tv-accent); outline-offset:2px; }
  .nexa-tree-caret {
    width:0; height:0;
    border-top:6px solid transparent; border-bottom:6px solid transparent;
    border-left:7px solid var(--tv-text-muted);
    transition:transform .2s ease;
  }
  .nexa-tree-label { font-size:14px; font-weight:700; color:var(--tv-text); text-transform:capitalize; }
  .nexa-tree-branch-icon, .nexa-tree-leaf-icon, .nexa-tree-operation-icon {
    min-width:18px; height:18px; display:inline-flex; align-items:center;
    justify-content:center; color:var(--tv-text-muted);
    font-size:15px; font-variation-settings:"FILL" 0,"wght" 400,"GRAD" 0,"opsz" 16;
    flex:0 0 18px;
  }
  .nexa-tree-leaf-icon { font-size:14px; }
  .nexa-tree-count {
    margin-left:auto; padding:0 6px; border-radius:999px;
    background:var(--tv-accent-muted); color:var(--tv-accent);
    font-size:11px; font-weight:700; line-height:20px;
  }
  .nexa-tree-leaf-content { min-width:0; display:flex; align-items:center; }
  .nexa-tree-leaf-title { font-size:14px; font-weight:400; color:var(--tv-text); }
  .nexa-tree-row.is-operation, .nexa-tree-row.is-operation-group {
    min-height:24px; padding:2px 8px; border-radius:6px;
  }
  .nexa-tree-row.is-operation-group { color:var(--tv-text-muted); }
  .nexa-tree-row.is-operation { color:var(--tv-text-muted); }
  .nexa-tree-operation-action {
    appearance:none; width:100%; border:1px solid transparent;
    background:transparent; cursor:pointer; text-align:left; color:inherit;
  }
  .nexa-tree-row.is-operation:hover,
  .nexa-tree-row.is-operation-group:hover { background:var(--tv-surface-muted); }
  .nexa-tree-item-branch > .nexa-tree-details > .nexa-tree-children > .nexa-tree-operation-leaf > .nexa-tree-row.is-operation,
  .nexa-tree-item-branch > .nexa-tree-details > .nexa-tree-children > .nexa-tree-operation-leaf > .nexa-tree-operation-action {
    padding-left:19px;
  }
  .nexa-tree-operation-label { min-width:0; font-size:12px; line-height:1.4; color:inherit; word-break:break-word; }
  .nexa-tree-operation-meta {
    margin-left:auto; padding-left:8px; font-size:11px;
    color:var(--tv-text-muted); white-space:nowrap;
  }
  .nexa-tree-empty { padding:10px; font-size:12px; color:var(--tv-text-muted); }
  /* active highlight */
  .nexa-tree-item-branch.vw-tree-active > .nexa-tree-details > summary,
  .nexa-tree-leaf.vw-tree-active > .nexa-tree-row,
  .nexa-tree-leaf.vw-tree-active > button {
    background: #ddf4ff00;
    font-weight: 600;
    border-color: #0969da00;
  }
  .vw-tree-wrap { padding:6px 0; }
</style>`;

  return `<div id="Operasi" class="nx-row tabel-view-theme">${styles}${menuTree.render()}</div>`;

}

nx.vwShowDetail  = async function (key) {
	console.log('key:', key);
}