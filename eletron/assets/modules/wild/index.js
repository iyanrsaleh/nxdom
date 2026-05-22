
export async function Depform(items) {
 const innerHTML = `
    <div class="container-sm py-4">
      <h2 class="h3 mb-1">Contact and identity form</h2>
      <p class="note mb-3">Contoh field HTML lengkap: text, email, radio, checkbox, select, file upload, color, range, dan textarea.</p>

      <form id="myForm">

        <div class="form-group">
          <dl>
            <dt class="form-group-header"><label for="nama">Full name</label></dt>
            <dd class="form-group-body">
              <input class="form-control input-block" id="nama" name="nama" type="text" placeholder="Masukkan nama lengkap" required />
              <p class="note">Nama ini akan ditampilkan pada profil.</p>
            </dd>
          </dl>
        </div>

        <div class="hfields">
          <div class="form-group">
            <dl>
              <dt class="form-group-header"><label for="email">Email address</label></dt>
              <dd class="form-group-body">
                <input class="form-control input-block" id="email" name="email" type="email" placeholder="nama@email.com" required />
              </dd>
            </dl>
          </div>
          <div class="form-group">
            <dl>
              <dt class="form-group-header"><label for="telepon">Phone number</label></dt>
              <dd class="form-group-body">
                <input class="form-control input-block" id="telepon" name="telepon" type="tel" placeholder="08xxxxxxxxxx" required />
              </dd>
            </dl>
          </div>
        </div>

        <div class="hfields">
          <div class="form-group">
            <dl>
              <dt class="form-group-header"><label for="password">Password</label></dt>
              <dd class="form-group-body">
                <input class="form-control input-block" id="password" name="password" type="password" placeholder="Masukkan password" required />
              </dd>
            </dl>
          </div>
          <div class="form-group">
            <dl>
              <dt class="form-group-header"><label for="tanggal">Birth date</label></dt>
              <dd class="form-group-body">
                <input class="form-control input-block" id="tanggal" name="tanggal" type="date" required />
              </dd>
            </dl>
          </div>
        </div>

        <div class="hfields">
          <div class="form-group">
            <dl>
              <dt class="form-group-header"><label for="kategori">Category</label></dt>
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
          <div class="form-group">
            <dl>
              <dt class="form-group-header"><label for="jumlah">Amount</label></dt>
              <dd class="form-group-body">
                <input class="form-control input-block" id="jumlah" name="jumlah" type="number" min="1" max="100" value="1" />
              </dd>
            </dl>
          </div>
        </div>

        <div class="hfields">
          <div class="form-group">
            <dl>
              <dt class="form-group-header"><label>Gender</label></dt>
              <dd class="form-group-body">
                <div class="form-checkbox">
                  <label><input type="radio" name="gender" value="pria" required /> Pria</label>
                </div>
                <div class="form-checkbox">
                  <label><input type="radio" name="gender" value="wanita" /> Wanita</label>
                </div>
                <div class="form-checkbox">
                  <label><input type="radio" name="gender" value="lainnya" /> Lainnya</label>
                </div>
              </dd>
            </dl>
          </div>
          <div class="form-group">
            <dl>
              <dt class="form-group-header"><label>Interests</label></dt>
              <dd class="form-group-body">
                <div class="form-checkbox">
                  <label><input type="checkbox" name="hobi" value="membaca" /> Membaca</label>
                </div>
                <div class="form-checkbox">
                  <label><input type="checkbox" name="hobi" value="musik" /> Musik</label>
                </div>
                <div class="form-checkbox">
                  <label><input type="checkbox" name="hobi" value="olahraga" /> Olahraga</label>
                </div>
              </dd>
            </dl>
          </div>
        </div>

        <div class="form-group">
          <dl>
            <dt class="form-group-header"><label for="foto">Profile image</label></dt>
            <dd class="form-group-body">
              <label for="foto-input" class="file-upload-area" id="foto-drop">
                <input id="foto-input" name="foto" type="file" accept="image/*" style="display:none" required onchange="fileUploadUpdate(this,'foto-label','foto-drop')">
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

        <div class="hfields">
          <div class="form-group">
            <dl>
              <dt class="form-group-header"><label for="warna">Accent color</label></dt>
              <dd class="form-group-body">
                <input class="form-control" id="warna" name="warna" type="color" value="#0969da" />
              </dd>
            </dl>
          </div>
          <div class="form-group">
            <dl>
              <dt class="form-group-header"><label for="range">Satisfaction</label></dt>
              <dd class="form-group-body">
                <input id="range" name="range" type="range" min="0" max="100" value="75" />
              </dd>
            </dl>
          </div>
        </div>

        <div class="form-group">
          <dl>
            <dt class="form-group-header"><label for="pesan">Bio / message</label></dt>
            <dd class="form-group-body">
              <textarea class="form-control input-block" id="pesan" name="pesan" rows="5" placeholder="Tulis pesan di sini..." required></textarea>
            </dd>
          </dl>
        </div>

        <div class="form-actions">
          <button onclick="setForm('myForm');" class="btn btn-primary" type="button">Update profile</button>
          <button onclick="setForm('Reset');" class="btn" type="button">Reset</button>
        </div>

      </form>
    </div>
  `;
  return innerHTML;
}
export const form = Depform;

if (typeof window !== 'undefined' && typeof window.fileUploadUpdate !== 'function') {
  window.fileUploadUpdate = function fileUploadUpdate(input, labelId, dropId) {
    const labelEl = document.getElementById(labelId);
    const dropEl = document.getElementById(dropId);
    if (!input || !labelEl || !dropEl) return;

    const count = input.files ? input.files.length : 0;
    if (count <= 0) {
      labelEl.textContent = 'JPG, PNG, GIF, WEBP';
      dropEl.classList.remove('has-file');
      return;
    }

    labelEl.textContent =
      count === 1 ? input.files[0].name : `${count} file dipilih`;
    dropEl.classList.add('has-file');
  };
}
window.setForm = async function (id) {
  if (id === "Reset") {
    NXUI.NexaWild.clear("myForm");
    document.getElementById("myForm")?.reset();
    return;
  }

  // Opsional: field yang dilewati validasi. Hapus atau kosongkan untuk validasi semua field.
  const noValidationBy = ["nama", "telpon"];

  const res = await new NXUI.NexaWild({
    type: "insert",
    elementFormId: id,
    getValidationBy: ["name"],
    ...(noValidationBy?.length ? { noValidationBy } : {}),
  });

  if (res.status) 
   console.log("res:", res);
};