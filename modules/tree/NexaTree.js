
export class NexaTree {
	constructor(items = [], options = {}) {
		this.openState = options.openState && typeof options.openState === 'object'
			? options.openState
			: {};
		this.items = items.map(item => this.normalizeItem(item));
	}

	escapeHtml(value = '') {
		return String(value)
			.replaceAll('&',  '&amp;')
			.replaceAll('<',  '&lt;')
			.replaceAll('>',  '&gt;')
			.replaceAll('"',  '&quot;')
			.replaceAll("'",  '&#39;');
	}

	normalizeItem(item = {}) {
		const itemChildren = Array.isArray(item.children) ? item.children : [];

		return {
			...item,
			appName:  item.viewName || item.appName || item.id || '',
			id:       item.viewName || item.id      || '',
			icons:    item.icons    || 'view_list',
			status:   item.status   || 'Development',
			children: itemChildren,
		};
	}

	getAll()        { return [...this.items]; }
	getById(id)     { return this.items.find(i => i.id === id) || null; }
	add(item)       { const m = this.normalizeItem(item); this.items.push(m); return m; }
	setItems(items) { this.items = items.map(i => this.normalizeItem(i)); return this.getAll(); }

	buildTree() {
		const groups = this.items.reduce((acc, item) => {
			const key = item.status || 'Development';
			if (!acc[key]) acc[key] = [];
			acc[key].push(item);
			return acc;
		}, {});

		return {
			type:     'root',
			label:    'Applications',
			icon:     'bathroom',
			count:    this.items.length,
			children: Object.entries(groups).map(([status, items]) => ({
				type:     'group',
				label:    status,
				count:    items.length,
				children: items.map(item => ({ type: 'item', ...item })),
			})),
		};
	}

	getBranchIcon(node) {
		if (node.type === 'root')  return node.icon || 'apps';
		if (node.type === 'group') {
			return String(node.label).toLowerCase() === 'development' ? 'integration_instructions' : 'folder';
		}
		return 'folder';
	}

	isBranchOpen(branchKey, fallback = false) {
		if (!branchKey) return fallback;
		return Object.hasOwn(this.openState, branchKey)
			? Boolean(this.openState[branchKey])
			: fallback;
	}

	// ─── Item node (view name row with expand/collapse) ───────────────────────
	renderItemNode(node, level = 0) {
		const appName    = this.escapeHtml(node.appName);
		const icons      = this.escapeHtml(node.icons);
		const id         = this.escapeHtml(node.id);
		const childMarkup = (node.children || [])
			.map(child => this.renderNode(child, level + 1, node.id))
			.join('');

		if (childMarkup) {
			const branchKey = `item:${node.id}`;
			const isOpen    = this.isBranchOpen(branchKey, false) ? ' open' : '';
			return `
        <li class="nexa-tree-branch nexa-tree-item-branch" data-level="${level}" data-id="${id}">
					<details class="nexa-tree-details" data-tree-key="${this.escapeHtml(branchKey)}" ontoggle="nxWorkspaceTreeToggle(this)"${isOpen}>
						<summary class="nexa-tree-row is-leaf is-item-branch nexa-tree-action" data-action="${id}" title="${appName}">
              <span class="nexa-tree-caret"></span>
              <span class="nexa-tree-leaf-icon material-symbols-outlined">${icons}</span>
              <div class="nexa-tree-leaf-content">
                <div class="nexa-tree-leaf-title">${appName}</div>
              </div>
            </summary>
            <ul class="nexa-tree-children">
              ${childMarkup}
            </ul>
          </details>
        </li>`;
		}

		return `
      <li class="nexa-tree-leaf" data-level="${level}" data-id="${id}">
				<button type="button" class="nexa-tree-row is-leaf nexa-tree-action" data-action="${id}" title="${appName}" onclick="${this.escapeHtml(`nx.vwShowDetail(${JSON.stringify(node.id)})`)}">
          <span class="nexa-tree-leaf-icon material-symbols-outlined">${icons}</span>
          <div class="nexa-tree-leaf-content">
            <div class="nexa-tree-leaf-title">${appName}</div>
          </div>
        </button>
      </li>`;
	}

	// ─── Operation group (Field Dipilih / Oprasi / Limit …) ──────────────────
	renderOperationGroupNode(node, level = 0, parentItemId = '') {
		const label = this.escapeHtml(node.label);
		const icon  = this.escapeHtml(node.icon || 'subdirectory_arrow_right');
		const childMarkup = (node.children || [])
			.map(child => this.renderNode(child, level + 1, parentItemId))
			.join('');

		let countValue = '';
		if (typeof node.countLabel === 'string') countValue = node.countLabel;
		else if (typeof node.count === 'number') countValue = String(node.count);
		const count = countValue
			? `<span class="nexa-tree-count">${this.escapeHtml(countValue)}</span>`
			: '';

		const branchKey = `operation:${parentItemId}:${node.label}`;
		const isOpen    = this.isBranchOpen(branchKey, Boolean(node.open)) ? ' open' : '';

		return `
      <li class="nexa-tree-branch nexa-tree-operation-group" data-level="${level}">
				<details class="nexa-tree-details" data-tree-key="${this.escapeHtml(branchKey)}" ontoggle="nxWorkspaceTreeToggle(this)"${isOpen}>
          <summary class="nexa-tree-row is-operation-group">
            <span class="nexa-tree-caret"></span>
            <span class="nexa-tree-operation-icon material-symbols-outlined">${icon}</span>
            <span class="nexa-tree-operation-label">${label}</span>
            ${count}
          </summary>
          <ul class="nexa-tree-children">
            ${childMarkup}
          </ul>
        </details>
      </li>`;
	}

	// ─── Operation leaf (column name / Settings / Delete) ────────────────────
	renderOperationLeafNode(node, level = 0) {
		const label  = this.escapeHtml(node.label);
		const icon   = this.escapeHtml(node.icon || 'subdirectory_arrow_right');
		const meta   = node.meta
			? `<span class="nexa-tree-operation-meta">${this.escapeHtml(node.meta)}</span>`
			: '';
		const actionName = this.escapeHtml(node.actionName || '');
		const actionId   = this.escapeHtml(node.actionId   || '');

		if (node.actionName && node.actionId) {
			const handler = node.actionName === 'settings'
				? `nx.vwShowDetail(${JSON.stringify(node.actionId)})`
				: `nx.vwDelete(${JSON.stringify(node.actionId)})`;

			return `
        <li class="nexa-tree-leaf nexa-tree-operation-leaf" data-level="${level}">
          <button type="button" class="nexa-tree-row is-operation nexa-tree-operation-action" data-operation-action="${actionName}" data-operation-id="${actionId}" onclick="${this.escapeHtml(handler)}">
            <span class="nexa-tree-operation-icon material-symbols-outlined">${icon}</span>
            <span class="nexa-tree-operation-label">${label}</span>
            ${meta}
          </button>
        </li>`;
		}

		return `
      <li class="nexa-tree-leaf nexa-tree-operation-leaf" data-level="${level}">
        <div class="nexa-tree-row is-operation">
          <span class="nexa-tree-operation-icon material-symbols-outlined">${icon}</span>
          <span class="nexa-tree-operation-label">${label}</span>
          ${meta}
        </div>
      </li>`;
	}

	// ─── Branch node (root / group) ───────────────────────────────────────────
	renderBranchNode(node, level = 0, parentItemId = '') {
		const label = this.escapeHtml(node.label);
		const icon  = this.escapeHtml(this.getBranchIcon(node));
		const childMarkup = (node.children || [])
			.map(child => this.renderNode(child, level + 1, parentItemId))
			.join('');
		const hideCount = ['applications', 'development'].includes(String(node.label).toLowerCase());
		const count = !hideCount && typeof node.count === 'number'
			? `<span class="nexa-tree-count">${node.count}</span>`
			: '';
		const branchKey = node.type === 'root' ? `root:${node.label}` : `group:${node.label}`;
		const isOpen    = this.isBranchOpen(branchKey, level <= 1) ? ' open' : '';

		return `
      <li class="nexa-tree-branch" data-level="${level}">
				<details class="nexa-tree-details" data-tree-key="${this.escapeHtml(branchKey)}" ontoggle="nxWorkspaceTreeToggle(this)"${isOpen}>
          <summary class="nexa-tree-row is-branch">
            <span class="nexa-tree-caret"></span>
            <span class="nexa-tree-branch-icon material-symbols-outlined">${icon}</span>
            <span class="nexa-tree-label">${label}</span>
            ${count}
          </summary>
          <ul class="nexa-tree-children">
            ${childMarkup}
          </ul>
        </details>
      </li>`;
	}

	renderNode(node, level = 0, parentItemId = '') {
		if (node.type === 'item')            return this.renderItemNode(node, level);
		if (node.type === 'operation-group') return this.renderOperationGroupNode(node, level, parentItemId);
		if (node.type === 'operation-leaf')  return this.renderOperationLeafNode(node, level);
		return this.renderBranchNode(node, level, parentItemId);
	}

	render() {
		if (this.items.length === 0) {
			return '<div class="nexa-tree-empty">Belum ada view tersimpan.</div>';
		}
		const tree = this.buildTree();
		return `<ul class="nexa-tree-root">${this.renderNode(tree)}</ul>`;
	}

	toJSON() { return this.getAll(); }
}

export const nexaTreeMenu = new NexaTree();
