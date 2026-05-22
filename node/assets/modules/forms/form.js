export async function Depform(items) {
  const iconTypeRaw = String(items?.iconType || "material").toLowerCase().trim();
  const iconType = iconTypeRaw.replace(/^#?sym:/, "");

  const iconMap = {
    name: { octicon: "octicon octicon-person-16", awesome: "fa-solid fa-user", material: "person" },
    email: { octicon: "octicon octicon-mail-16", awesome: "fa-solid fa-envelope", material: "mail" },
    phone: { octicon: "octicon octicon-device-mobile-16", awesome: "fa-solid fa-mobile-screen", material: "smartphone" },
    password: { octicon: "octicon octicon-lock-16", awesome: "fa-solid fa-lock", material: "lock" },
    date: { octicon: "octicon octicon-calendar-16", awesome: "fa-solid fa-calendar-days", material: "calendar_month" },
    category: { octicon: "octicon octicon-list-unordered-16", awesome: "fa-solid fa-list", material: "category" },
    amount: { octicon: "octicon octicon-hash-16", awesome: "fa-solid fa-hashtag", material: "tag" },
    gender: { octicon: "octicon octicon-circle-16", awesome: "fa-regular fa-circle", material: "radio_button_unchecked" },
    interests: { octicon: "octicon octicon-check-circle-16", awesome: "fa-regular fa-circle-check", material: "task_alt" },
    file: { octicon: "octicon octicon-upload-16", awesome: "fa-solid fa-upload", material: "upload" },
    color: { octicon: "octicon octicon-paintbrush-16", awesome: "fa-solid fa-palette", material: "palette" },
    range: { octicon: "octicon octicon-filter-16", awesome: "fa-solid fa-sliders", material: "tune" },
    select: { octicon: "octicon octicon-list-unordered-16", awesome: "fa-solid fa-list", material: "list" },
    select2: { octicon: "octicon octicon-tag-16", awesome: "fa-solid fa-tags", material: "sell" },
    note: { octicon: "octicon octicon-note-16", awesome: "fa-regular fa-note-sticky", material: "edit_note" },
    eye: { octicon: "octicon octicon-eye-16", awesome: "fa-regular fa-eye", material: "visibility" },
    eye_off: { octicon: "octicon octicon-eye-closed-16", awesome: "fa-regular fa-eye-slash", material: "visibility_off" }
  };

  const getIcon = (key) => {
    const conf = iconMap[key] || iconMap.note;
    if (iconType === "awesome") {
      return `<i class="${conf.awesome} form-label-icon" aria-hidden="true"></i>`;
    }
    if (iconType === "material-icons") {
      return `<span class="material-icons form-label-icon" aria-hidden="true">${conf.material}</span>`;
    }
    if (iconType === "material" || iconType === "material-symbols" || iconType === "material-symbols-outlined") {
      return `<span class="material-symbols-outlined form-label-icon" aria-hidden="true">${conf.material}</span>`;
    }
    return `<span class="${conf.octicon} form-label-icon" aria-hidden="true"></span>`;
  };

  const getInputIconButton = (key, label) => {
    const icon = getIcon(key);
    return `<div class="input-group-button"><button class="btn" type="button" tabindex="-1" aria-label="${label}">${icon}</button></div>`;
  };

  const getPasswordToggleButton = (inputId, label) => {
    const iconShow = getIcon("eye");
    const iconHide = getIcon("eye_off");
    return `<div class="input-group-button"><button class="btn" type="button" data-target="${inputId}" data-password-visible="false" onclick="togglePasswordVisibility(this)" aria-label="${label}"><span class="pwd-eye-on">${iconShow}</span><span class="pwd-eye-off" style="display:none">${iconHide}</span></button></div>`;
  };

  const innerHTML = `
    <div>
      <h2 class="h3 mb-1">Contact and identity form</h2>
      <p class="note mb-3">Contoh field HTML lengkap: text, email, radio, checkbox, select, file upload, color, range, dan textarea.</p>

      <form id="myForm" class="nx-row">
        <div class="nx-col-12">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="nama">${getIcon("name")}Full name</label>
              </dt>
              <dd class="form-group-body">
                <div class="input-group">
                  <input class="form-control input-block" id="nama" name="nama" type="text" placeholder="Masukkan nama lengkap" required />
                  ${getInputIconButton("name", "Full name")}
                </div>
                <p class="note">Nama ini akan ditampilkan pada profil.</p>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="email">${getIcon("email")}Email address</label>
              </dt>
              <dd class="form-group-body">
                <div class="input-group">
                  <input class="form-control input-block" id="email" name="email" type="email" placeholder="nama@email.com" required />
                  ${getInputIconButton("email", "Email address")}
                </div>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="telepon">${getIcon("phone")}Phone number</label>
              </dt>
              <dd class="form-group-body">
                <div class="input-group">
                  <input class="form-control input-block" id="telepon" name="telepon" type="tel" placeholder="08xxxxxxxxxx" required />
                  ${getInputIconButton("phone", "Phone number")}
                </div>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="password">${getIcon("password")}Password</label>
              </dt>
              <dd class="form-group-body">
                <div class="input-group">
                  <input class="form-control input-block" id="password" name="password" type="password" placeholder="Masukkan password" required />
                  ${getPasswordToggleButton("password", "Tampilkan password")}
                </div>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="tanggal">${getIcon("date")}Birth date</label>
              </dt>
              <dd class="form-group-body">
                <div class="input-group">
                  <input class="form-control input-block" id="tanggal" name="tanggal" type="date" required />
                  ${getInputIconButton("date", "Birth date")}
                </div>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="kategori">${getIcon("category")}Category</label>
              </dt>
              <dd class="form-group-body">
                <select class="form-select" id="kategori" name="kategori" required>
                  <option value="">Pilih kategori</option>
                  <option>Umum</option>
                  <option>Support</option>
                  <option>Kerjasama</option>
                </select>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="frameworkSelect">${getIcon("select")}Framework (Select)</label>
              </dt>
              <dd class="form-group-body">
                <select class="form-select" id="frameworkSelect" name="framework" required>
                  <option value="">Pilih framework</option>
                  <option value="nexa">NexaUI</option>
                  <option value="react">React</option>
                  <option value="vue">Vue</option>
                  <option value="angular">Angular</option>
                </select>
                <p class="note">Contoh native select dari modul select.</p>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="tagsInput">${getIcon("select2")}Technology Tags (NexaTags)</label>
              </dt>
              <dd class="form-group-body">
                <div id="tagsInput" name="tags" data-label="Technology Tags" class="form-nexa-control"></div>
                <p class="note">Contoh input tags NexaTags.js (multiple, autocomplete, styled).</p>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="levelSelect2">${getIcon("select2")}Level (Select2 Standard)</label>
              </dt>
              <dd class="form-group-body">
                <select id="levelSelect2" class="js-select2" name="level" required data-placeholder="Pilih level" data-allow-clear="true" style="width:100%">
                  <option value=""></option>
                  <option value="junior">Junior</option>
                  <option value="mid">Mid</option>
                  <option value="senior">Senior</option>
                  <option value="lead">Lead</option>
                </select>
                <p class="note">Contoh select2 standar (single select + clear).</p>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="jumlah">${getIcon("amount")}Amount</label>
              </dt>
              <dd class="form-group-body">
                <div class="input-group">
                  <input class="form-control input-block" id="jumlah" name="jumlah" type="number" min="1" max="100" value="1" />
                  ${getInputIconButton("amount", "Amount")}
                </div>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label>${getIcon("gender")}Gender</label>
              </dt>
              <dd class="form-group-body">
                <div class="form-radio"><label><input type="radio" name="gender" value="pria" required /> Pria</label></div>
                <div class="form-radio"><label><input type="radio" name="gender" value="wanita" /> Wanita</label></div>
                <div class="form-radio"><label><input type="radio" name="gender" value="lainnya" /> Lainnya</label></div>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label>${getIcon("interests")}Interests</label>
              </dt>
              <dd class="form-group-body">
                <div class="form-checkbox"><label><input type="checkbox" name="hobi" value="membaca" /> Membaca</label></div>
                <div class="form-checkbox"><label><input type="checkbox" name="hobi" value="musik" /> Musik</label></div>
                <div class="form-checkbox"><label><input type="checkbox" name="hobi" value="olahraga" /> Olahraga</label></div>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="foto-input">${getIcon("file")}Profile image</label>
              </dt>
              <dd class="form-group-body">
                <label for="foto-input" class="file-upload-area" id="foto-drop">
                  <input id="foto-input" name="foto" type="file" accept="image/*" style="display:none" required onchange="fileUploadUpdate(this, 'foto-label', 'foto-drop')">
                  <span class="file-upload-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                  </span>
                  <span class="file-upload-text"><strong>Klik untuk memilih gambar</strong> atau seret &amp; lepas di sini</span>
                  <span class="file-upload-hint" id="foto-label">JPG, PNG, GIF, WEBP</span>
                </label>
                <p class="note">Pilih satu gambar profil.</p>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="warna">${getIcon("color")}Accent color</label>
              </dt>
              <dd class="form-group-body">
                <div class="input-group">
                  <input class="form-control" id="warna" name="warna" type="color" value="#0969da" />
                  ${getInputIconButton("color", "Accent color")}
                </div>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12 nx-col-md-6">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="range">${getIcon("range")}Satisfaction</label>
              </dt>
              <dd class="form-group-body">
                <input id="range" name="range" type="range" class="form-range" min="0" max="100" value="75" />
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12">
          <div class="form-group">
            <dl>
              <dt class="form-group-header">
                <label for="pesan">${getIcon("note")}Bio / message</label>
              </dt>
              <dd class="form-group-body">
                <textarea class="form-control input-block" id="pesan" name="pesan" rows="5" placeholder="Tulis pesan di sini..." required></textarea>
              </dd>
            </dl>
          </div>
        </div>

        <div class="nx-col-12">
          <div class="form-actions">
            <button onclick="setForm('myForm');" class="btn btn-primary" type="button">Update profile</button>
            <button onclick="setForm('Reset');" class="btn" type="button">Reset</button>
          </div>
        </div>
      </form>
    </div>
  `;

  setTimeout(() => {
    if (typeof globalThis.NexaTags === "function") {
      new globalThis.NexaTags({
        targetId: "tagsInput",
        data: [
          { failed: "JavaScript" },
          { failed: "TypeScript" },
          { failed: "PHP" },
          { failed: "Python" },
          { failed: "Go" },
          { failed: "Rust" }
        ],
        value: [], // bisa diisi default value array
        validasi: 6, // maksimal 6 tags
        close: true,
        onChange: (data) => {
          // Update hidden input jika perlu
          let hidden = document.getElementById("tagsInput_value");
          if (hidden) {
            hidden.name = "tags";
          }
        }
      });
    }
    if (typeof globalThis.initDeploymentFormSelects === "function") {
      globalThis.initDeploymentFormSelects("myForm");
    }
  }, 0);

  return innerHTML;
}

export const form = Depform;

if (globalThis.window !== undefined && typeof globalThis.fileUploadUpdate !== "function") {
  globalThis.fileUploadUpdate = function fileUploadUpdate(input, labelId, dropId) {
    const labelEl = document.getElementById(labelId);
    const dropEl = document.getElementById(dropId);
    if (!input || !labelEl || !dropEl) return;

    const count = input.files ? input.files.length : 0;
    if (count <= 0) {
      labelEl.textContent = "JPG, PNG, GIF, WEBP";
      dropEl.classList.remove("has-file");
      return;
    }

    labelEl.textContent = count === 1 ? input.files[0].name : `${count} file dipilih`;
    dropEl.classList.add("has-file");
  };
}

if (globalThis.window !== undefined && typeof globalThis.togglePasswordVisibility !== "function") {
  globalThis.togglePasswordVisibility = function togglePasswordVisibility(buttonEl) {
    const targetId = buttonEl?.dataset?.target;
    const inputEl = targetId ? document.getElementById(targetId) : null;
    if (!inputEl) return;

    const show = inputEl.type === "password";
    inputEl.type = show ? "text" : "password";
    buttonEl.dataset.passwordVisible = show ? "true" : "false";
    buttonEl.setAttribute("aria-label", show ? "Sembunyikan password" : "Tampilkan password");

    const eyeOn = buttonEl.querySelector(".pwd-eye-on");
    const eyeOff = buttonEl.querySelector(".pwd-eye-off");
    if (eyeOn && eyeOff) {
      eyeOn.style.display = show ? "none" : "inline-flex";
      eyeOff.style.display = show ? "inline-flex" : "none";
    }
  };
}

if (globalThis.window !== undefined && typeof globalThis.initDeploymentFormSelects !== "function") {
  globalThis.initDeploymentFormSelects = function initDeploymentFormSelects(formId = "myForm") {
    const root = document.getElementById(formId);
    if (!root) return false;

    const selects = root.querySelectorAll(".js-select2");
    if (!selects.length) return false;

    selects.forEach((el) => {
      const selector = `#${el.id}`;
      const placeholder = String(el.dataset.placeholder || (el.multiple ? "Pilih tags" : "Pilih opsi"));
      const allowClear = String(el.dataset.allowClear || "false") === "true";
      const opts = {
        placeholder,
        allowClear,
        width: "100%"
      };

      if (globalThis.NXUI && typeof globalThis.NXUI.initSelect2 === "function") {
        globalThis.NXUI.initSelect2(selector, opts);
      } else if (typeof globalThis.$?.fn?.select2 === "function") {
        globalThis.$(selector).select2(opts);
      }
    });

    return true;
  };
}

globalThis.setForm = async function (id) {
  if (id === "Reset") {
    NXUI.NexaWild.clear("myForm");
    document.getElementById("myForm")?.reset();
    return;
  }

  const noValidationBy = ["nama", "telpon"];

  const res = await new NXUI.NexaWild({
    type: "insert",
    elementFormId: id,
    getValidationBy: ["name"],
    ...(noValidationBy?.length ? { noValidationBy } : {}),
  });

  if (res.status) {
    console.log("res:", res);
  }
};
