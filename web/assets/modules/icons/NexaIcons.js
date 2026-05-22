/**
 * Universal icon resolver for NexaFloating
 * @param {string} type - Field type (e.g. 'name', 'email', ...)
 * @param {string} iconType - Icon set: 'octicon', 'awesome', 'material'
 * @returns {string} Icon class or name for the requested set
 */
export function getIconByTypeUniversal(type, iconType = 'material',customIcon='') {
    // Jika customIcon diisi dan bukan "attach_file", langsung gunakan customIcon
    // Contoh: getIconByTypeUniversal('text', 'material', 'my_custom_icon') => 'my_custom_icon'
    if (customIcon && customIcon !== "attach_file") return customIcon;

    const iconMap = {
        maps: { octicon: 'octicon octicon-location-16', awesome: 'fa-solid fa-location-dot', material: 'distance' },
        currency:  { octicon: 'octicon octicon-credit-card-16', awesome: 'fa-solid fa-money-bill', material: 'attach_money' },
        url:      { octicon: 'octicon octicon-link-16', awesome: 'fa-solid fa-link', material: 'link' },
        time:     { octicon: 'octicon octicon-clock-16', awesome: 'fa-solid fa-clock', material: 'access_time' },
        sesi:      { octicon: 'octicon octicon-clock-16', awesome: 'fa-solid fa-clock', material: 'schedule' },
        name:      { octicon: 'octicon octicon-person-16', awesome: 'fa-solid fa-user', material: 'person' },
        email:     { octicon: 'octicon octicon-mail-16', awesome: 'fa-solid fa-envelope', material: 'mail' },
        phone:     { octicon: 'octicon octicon-device-mobile-16', awesome: 'fa-solid fa-mobile-screen', material: 'smartphone' },
        tel:     { octicon: 'octicon octicon-device-mobile-16', awesome: 'fa-solid fa-mobile-screen', material: 'smartphone' },
        password:  { octicon: 'octicon octicon-lock-16', awesome: 'fa-solid fa-lock', material: 'lock' },
        date:      { octicon: 'octicon octicon-calendar-16', awesome: 'fa-solid fa-calendar-days', material: 'calendar_month' },
        category:  { octicon: 'octicon octicon-list-unordered-16', awesome: 'fa-solid fa-list', material: 'category' },
        amount:    { octicon: 'octicon octicon-hash-16', awesome: 'fa-solid fa-hashtag', material: 'tag' },
        gender:    { octicon: 'octicon octicon-circle-16', awesome: 'fa-regular fa-circle', material: 'radio_button_unchecked' },
        interests: { octicon: 'octicon octicon-check-circle-16', awesome: 'fa-regular fa-circle-check', material: 'task_alt' },
        file:      { octicon: 'octicon octicon-upload-16', awesome: 'fa-solid fa-upload', material: 'drive_folder_upload' },
        color:     { octicon: 'octicon octicon-paintbrush-16', awesome: 'fa-solid fa-palette', material: 'palette' },
        range:     { octicon: 'octicon octicon-filter-16', awesome: 'fa-solid fa-sliders', material: 'tune' },
        select:    { octicon: 'octicon octicon-list-unordered-16', awesome: 'fa-solid fa-list', material: 'list' },
        select2:   { octicon: 'octicon octicon-tag-16', awesome: 'fa-solid fa-tags', material: 'sell' },
        populate: { octicon: 'octicon octicon-search-16', awesome: 'fa-solid fa-magnifying-glass', material: 'manage_search' },
        instansi:  { octicon: 'octicon octicon-organization-16', awesome: 'fa-solid fa-building', material: 'business' },
        note:      { octicon: 'octicon octicon-note-16', awesome: 'fa-regular fa-note-sticky', material: 'edit_note' },
        eye:       { octicon: 'octicon octicon-eye-16', awesome: 'fa-regular fa-eye', material: 'visibility' },
        eye_off:   { octicon: 'octicon octicon-eye-closed-16', awesome: 'fa-regular fa-eye-slash', material: 'visibility_off' },
        // fallback
        text:      { octicon: 'octicon octicon-edit-16', awesome: 'fa-solid fa-pen', material: 'edit' },
        hidden:    { octicon: 'octicon octicon-visibility-off-16', awesome: 'fa-regular fa-eye-slash', material: 'visibility_off' },
        // add more as needed
    };
    let entry = iconMap[type];
    if (!entry) {
        // Fallback: use getIconByType (material style) and warn in dev
        if (typeof window !== 'undefined' && window.console && window.location && window.location.hostname === 'localhost') {
            console.warn(`NexaIcons: Icon type '${type}' not registered in getIconByTypeUniversal, fallback to getIconByType.`);
        }
        // Use getIconByType for fallback (material style)
        const fallbackIcon = getIconByType(type);
        // Return as material style (for compatibility)
        return fallbackIcon;
    }
    return entry[iconType] || entry.material;
}
export function getIconByType(type, customIcon = null) {
  // Jika customIcon diisi dan bukan "attach_file", langsung gunakan customIcon
  // Contoh: getIconByType('text', 'my_custom_icon') => 'my_custom_icon'
  if (customIcon && customIcon !== "attach_file") return customIcon;

  // Mapping tipe field ke Octicon names (format: name-16)
  const iconMap = {
    text: "edit_note", // Icon untuk field text biasa
    hidden: "visibility_off", // Icon untuk field hidden
    email: "email", // Icon untuk field email
    password: "lock", // Icon untuk field password
    number: "numbers", // Icon untuk field number
    tel: "phone", // Icon untuk field telepon
    url: "link", // Icon untuk field URL
    search: "search", // Icon untuk field search
    populate: "manage_search", // Lookup / autocomplete dari index
    date: "calendar_today", // Icon untuk field date
    "datetime-local": "schedule", // Icon untuk field datetime-local
    time: "access_time", // Icon untuk field time
    textarea: "description", // Icon untuk field textarea
    select: "event_list", // Icon untuk field select
    radio: "radio_button_checked", // Icon untuk field radio
    checkbox: "check_box", // Icon untuk field checkbox
    switch: "split_scene_right", // Icon untuk field switch
    file: "attach_file", // Icon untuk field file
    range: "tune", // Icon untuk field range
    color: "palette", // Icon untuk field color
    flag: "flag", // Icon untuk field flag
    currency: "attach_money", // Icon untuk field currency
    approval: "approval", // Icon untuk field currency
    slug:"dataset_linked",
    keyup:"action_key",
    tags:"label",
    maps:'distance',
    instansi: "business",
    avatar: "account_circle",
  };

  return iconMap[type] || "question-16";
}

/**
 * Mengembalikan HTML span untuk Octicon
 * @param {string} type - Tipe field
 * @param {string|null} customIcon - Custom octicon name (opsional, tanpa prefix "octicon-")
 * @returns {string} HTML string: <span class="octicon octicon-{name}"></span>
 */
export function getIconHTML(type, customIcon = null) {
  const name = getIconByType(type, customIcon);
  if (!name) return '';
  return `<span class="octicon octicon-${name}"></span>`;
}
