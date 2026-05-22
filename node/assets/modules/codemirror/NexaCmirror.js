/**
 * NexaCmirror - Class untuk mengelola CodeMirror Editor
 * Memudahkan penggunaan dan pengembangan CodeMirror
 */
export class NexaCmirror {
  // Static flag untuk tracking apakah dependencies sudah di-load
  static _dependenciesLoaded = false;
  static _loadingPromise = null;
  constructor(element, options = {}) {

    // Validasi element
    if (!element) {
      throw new Error('Element is required for NexaCmirror');
    }

    // Simpan element untuk digunakan nanti
    this.elementTitle =options?.title ?? '';
    this.elementId = typeof element === 'string' ? element : null;
    this.elementRef = typeof element === 'string' ? null : element;

    // Sembunyikan container editor sampai semua dependencies selesai di-load
    this.hideEditorContainer(element);

    // Cek apakah CodeMirror sudah tersedia, jika belum, load dependencies
    if (typeof CodeMirror === 'undefined') {
      // Jika NXUI tersedia, load dependencies secara async
      if (typeof NXUI !== 'undefined' && NXUI.NexaStylesheet && NXUI.NexaScript) {
        // Load dependencies dan tunggu sampai selesai
        // loadDependencies() sudah memiliki caching, jadi tidak akan load berulang
        NexaCmirror.loadDependencies().then(() => {
          // Tunggu sebentar untuk memastikan semua mode script sudah ter-register
          setTimeout(() => {
            // Setelah dependencies loaded, inisialisasi editor
            this._initializeEditor(element, options);
          }, 100);
        }).catch((error) => {
          console.error('Failed to load CodeMirror dependencies:', error);
          throw new Error('CodeMirror dependencies failed to load. Please ensure NXUI is available and network connection is stable.');
        });
        return; // Return early, editor akan diinisialisasi setelah dependencies loaded
      } else {
        throw new Error('CodeMirror is not loaded. Please either:\n1. Include CodeMirror manually in HTML, or\n2. Call NexaCmirror.loadDependencies() first, or\n3. Ensure NXUI is available for auto-loading');
      }
    }

    // Jika CodeMirror sudah tersedia, langsung inisialisasi
    this._initializeEditor(element, options);
  }

  /**
   * Internal method untuk inisialisasi editor
   * @private
   */
  _initializeEditor(element, options) {

    // Konfigurasi default
    this.defaultOptions = {
      lineNumbers: true,
      mode: 'htmlmixed',
      theme: 'monokai', // Theme akan disesuaikan otomatis berdasarkan mode (SQL=default, HTML/JS/CSS=monokai)
      indentUnit: 2,
      lineWrapping: true,
      autoCloseTags: true,
      autoCloseBrackets: true,
      tabSize: 2,
      indentWithTabs: false,
      smartIndent: true,
      matchBrackets: true,
      autoFocus: false,
      readOnly: false,
      cursorBlinkRate: 530,
      lineWiseCopyCut: true,
      pasteLinesPerSelection: false // Set false untuk menghindari duplikasi saat paste
    };

    // Merge dengan options yang diberikan user
    this.config = { ...this.defaultOptions, ...options };

    // Tentukan theme berdasarkan mode (jika user tidak override)
    if (!options.theme) {
      // Jika mode berupa object (seperti { name: 'javascript', json: true }), cek apakah JSON mode
      let modeName = this.config.mode;
      if (typeof this.config.mode === 'object' && this.config.mode.name) {
        // Jika mode adalah object dengan json: true, ini adalah JSON mode
        if (this.config.mode.json === true) {
          modeName = 'json';
        } else {
          modeName = this.config.mode.name;
        }
      }
      this.config.theme = this.getThemeByMode(modeName);
    }

    // Setup autocomplete dan extraKeys berdasarkan mode (setelah merge)
    this.setupAutocomplete();
    this.setupExtraKeys();

    // Inisialisasi editor
    this.element = typeof element === 'string' ? document.getElementById(element) : element;
    
    if (!this.element) {
      throw new Error('Element not found');
    }

    // Pastikan mode SQL di-set di config sebelum editor dibuat
    const isSQLMode = this.config.mode === 'sql' || (typeof this.config.mode === 'string' && this.config.mode.includes('sql'));
    
    // Jika mode SQL, pastikan mode di-set di config dengan MIME type yang benar
    if (isSQLMode && CodeMirror.modes && CodeMirror.modes.sql) {
      // Gunakan MIME type 'text/x-sql' untuk memastikan mode SQL ter-register dengan benar
      this.config.mode = 'text/x-sql';
    }
    
    // Jika element adalah textarea, gunakan fromTextArea
    if (this.element.tagName === 'TEXTAREA') {
      this.editor = CodeMirror.fromTextArea(this.element, this.config);
    } else {
      // Jika bukan textarea, buat editor di dalam element
      this.editor = CodeMirror(this.element, this.config);
    }

    // Pastikan mode diterapkan dengan benar setelah editor dibuat
    const applyModeWithHighlight = (mode) => {
      try {
        // Jika mode adalah 'sql', konversi ke 'text/x-sql'
        if (mode === 'sql') {
          mode = 'text/x-sql';
        }
        this.editor.setOption('mode', mode);
        this.editor.setOption('theme', this.config.theme);
        
        // Force re-highlight dengan cara yang lebih agresif
        const content = this.editor.getValue();
        if (content) {
          // Clear dan set ulang untuk trigger re-highlight
          this.editor.setValue('');
          setTimeout(() => {
            this.editor.setValue(content);
            this.editor.refresh();
            
            // Force re-highlight dengan cara manual jika perlu
            const currentMode = this.editor.getOption('mode');
            if (currentMode === 'sql' || currentMode === 'text/x-sql') {
              // Pastikan mode SQL benar-benar aktif
              const wrapper = this.editor.getWrapperElement();
              if (wrapper) {
                // Tambahkan class untuk memastikan CSS diterapkan
                wrapper.classList.add('cm-s-default');
                // Force re-render
                this.editor.operation(() => {
                  this.editor.refresh();
                });
              }
            }
            
            // Force re-highlight sekali lagi
            setTimeout(() => {
              this.editor.refresh();
            }, 50);
          }, 10);
        } else {
          this.editor.refresh();
        }
      } catch (e) {
        console.warn('Error applying mode:', e);
        this.editor.refresh();
      }
    };
    
    if (isSQLMode) {
      // Untuk SQL mode, cek apakah mode SQL sudah tersedia
      const applySQLMode = () => {
        if (CodeMirror.modes && CodeMirror.modes.sql) {
          // Set mode SQL dengan MIME type yang benar untuk memastikan highlighting bekerja
          this.editor.setOption('mode', 'text/x-sql');
          this.editor.setOption('theme', this.config.theme);
          
          // Force re-highlight dengan cara yang lebih agresif
          const content = this.editor.getValue();
          if (content) {
            // Gunakan operation untuk batch changes
            this.editor.operation(() => {
              this.editor.setValue('');
              this.editor.setValue(content);
            });
          }
          
          // Refresh beberapa kali untuk memastikan highlighting aktif
          this.editor.refresh();
          setTimeout(() => {
            this.editor.refresh();
            // Jika mode SQL sudah ter-apply, pastikan wrapper memiliki class yang benar
            const wrapper = this.editor.getWrapperElement();
            if (wrapper) {
              wrapper.classList.add('cm-s-default');
            }
            
            // Force re-highlight dengan cara manual
            // Cek apakah ada token dengan class syntax highlighting
            const lines = this.editor.lineCount();
            let hasSyntaxClasses = false;
            for (let i = 0; i < Math.min(lines, 10); i++) {
              const line = this.editor.getLine(i);
              if (line) {
                // Trigger highlighting untuk baris ini
                this.editor.markText(
                  {line: i, ch: 0},
                  {line: i, ch: line.length},
                  {className: 'cm-force-highlight'}
                );
              }
            }
            
            // Force refresh sekali lagi
            this.editor.refresh();
            
            // Cek apakah ada element dengan class syntax highlighting
            setTimeout(() => {
              const codeLines = wrapper.querySelectorAll('.CodeMirror-line span');
              let keywordCount = 0;
              let stringCount = 0;
              codeLines.forEach(span => {
                if (span.classList.contains('cm-keyword')) keywordCount++;
                if (span.classList.contains('cm-string')) stringCount++;
              });
              
              // Jika tidak ada syntax highlighting, coba force dengan cara lain
              if (keywordCount === 0 && stringCount === 0) {
                
                // Coba beberapa metode untuk force highlighting
                // Method 1: Set mode null lalu set ulang
                this.editor.setOption('mode', null);
                setTimeout(() => {
                  this.editor.setOption('mode', 'text/x-sql');
                  
                  // Method 2: Force tokenization dengan cara manual
                  const content = this.editor.getValue();
                  this.editor.setValue('');
                  setTimeout(() => {
                    this.editor.setValue(content);
                    
                    // Method 3: Gunakan operation untuk batch changes
                    this.editor.operation(() => {
                      this.editor.refresh();
                    });
                    
                    // Method 4: Force re-tokenize dengan cara yang lebih agresif
                    setTimeout(() => {
                      // Force refresh dan re-highlight
                      this.editor.refresh();
                      // Coba trigger dengan set cursor
                      const cursor = this.editor.getCursor();
                      this.editor.setCursor(cursor);
                      this.editor.refresh();
                      
                      // Cek lagi setelah beberapa saat
                      setTimeout(() => {
                        const codeLines2 = wrapper.querySelectorAll('.CodeMirror-line span');
                        let keywordCount2 = 0;
                        let stringCount2 = 0;
                        codeLines2.forEach(span => {
                          if (span.classList.contains('cm-keyword')) keywordCount2++;
                          if (span.classList.contains('cm-string')) stringCount2++;
                        });
                        
                        // Jika masih tidak ada, mungkin mode SQL tidak support highlighting
                        // Coba gunakan mode yang mirip atau tambahkan highlighting manual
                        if (keywordCount2 === 0 && stringCount2 === 0) {
                          // Tambahkan highlighting manual dengan regex
                          this.addManualSQLHighlighting();
                        }
                      }, 300);
                    }, 100);
                  }, 50);
                }, 50);
              }
            }, 200);
          }, 100);
          
          // Tambahkan event listener untuk memastikan highlighting aktif setelah content change
          this.editor.on('change', () => {
            this.editor.refresh();
          });
          
          // Tambahkan method untuk manual SQL highlighting
          this.addManualSQLHighlighting = () => {
            const content = this.editor.getValue();
            const wrapper = this.editor.getWrapperElement();
            if (!wrapper) return;
            
            // SQL keywords untuk highlighting (single word dulu, multi-word akan ditangani terpisah)
            const singleKeywords = ['SELECT', 'FROM', 'WHERE', 'HAVING', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP', 'TABLE', 'DATABASE', 'INDEX', 'VIEW', 'AS', 'AND', 'OR', 'NOT', 'IN', 'EXISTS', 'LIKE', 'BETWEEN', 'LIMIT', 'OFFSET', 'JOIN', 'UNION', 'EXCEPT', 'INTERSECT', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END', 'COUNT', 'SUM', 'AVG', 'MAX', 'MIN', 'DISTINCT', 'UPPER', 'LOWER', 'SUBSTRING', 'CONCAT', 'LENGTH', 'TRIM', 'DATE', 'NOW', 'CURDATE', 'CURTIME', 'YEAR', 'MONTH', 'DAY', 'INT', 'VARCHAR', 'TEXT', 'DATETIME', 'TIMESTAMP', 'DECIMAL', 'FLOAT', 'DOUBLE', 'BOOLEAN', 'UNIQUE', 'DEFAULT'];
            const multiKeywords = ['GROUP BY', 'ORDER BY', 'IS NULL', 'IS NOT NULL', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'FULL JOIN', 'CROSS JOIN', 'UNION ALL', 'PRIMARY KEY', 'FOREIGN KEY', 'NOT NULL', 'AUTO_INCREMENT'];
            
            // Split content per line dan apply highlighting
            const lines = content.split('\n');
            const marks = [];
            
            lines.forEach((line, lineIndex) => {
              const upperLine = line.toUpperCase();
              
              // Handle multi-word keywords first
              multiKeywords.forEach(keyword => {
                const upperKeyword = keyword.toUpperCase();
                let index = upperLine.indexOf(upperKeyword);
                while (index !== -1) {
                  // Check if it's a whole word
                  const before = index > 0 ? line[index - 1] : ' ';
                  const after = index + keyword.length < line.length ? line[index + keyword.length] : ' ';
                  if (!/\w/.test(before) && !/\w/.test(after)) {
                    try {
                      const mark = this.editor.markText(
                        {line: lineIndex, ch: index},
                        {line: lineIndex, ch: index + keyword.length},
                        {
                          className: 'cm-keyword',
                          inclusiveLeft: false,
                          inclusiveRight: false,
                          clearOnEnter: false,
                          readOnly: false
                        }
                      );
                      marks.push(mark);
                    } catch (e) {
                      console.warn('Error marking text:', e);
                    }
                  }
                  index = upperLine.indexOf(upperKeyword, index + 1);
                }
              });
              
              // Handle single keywords
              singleKeywords.forEach(keyword => {
                const regex = new RegExp('\\b' + keyword + '\\b', 'gi');
                let match;
                while ((match = regex.exec(line)) !== null) {
                  try {
                    const mark = this.editor.markText(
                      {line: lineIndex, ch: match.index},
                      {line: lineIndex, ch: match.index + match[0].length},
                      {
                        className: 'cm-keyword',
                        inclusiveLeft: false,
                        inclusiveRight: false,
                        clearOnEnter: false,
                        readOnly: false
                      }
                    );
                    marks.push(mark);
                  } catch (e) {
                    console.warn('Error marking text:', e);
                  }
                }
              });
            });
            
            // Simpan marks untuk bisa di-clear nanti jika perlu
            this._sqlMarks = marks;
            
            // Force refresh dan cek apakah marks terlihat
            this.editor.refresh();
            
            // Tunggu sebentar lalu cek apakah marks terlihat di DOM
            setTimeout(() => {
              const wrapper = this.editor.getWrapperElement();
              const marksInDOM = wrapper.querySelectorAll('.CodeMirror-mark.cm-keyword, .cm-keyword, span.cm-keyword');
              
              if (marksInDOM.length > 0) {
                // Verifikasi bahwa CSS sudah diterapkan
                marksInDOM.forEach((markEl) => {
                  const computedStyle = window.getComputedStyle(markEl);
                  const currentColor = computedStyle.color;
                  
                  // Apply inline style sebagai fallback jika perlu
                  if (currentColor === 'rgb(0, 0, 0)' || currentColor === 'black' || 
                      currentColor === 'rgb(51, 51, 51)' || currentColor === '#333') {
                    markEl.style.color = '#0000ff';
                    markEl.style.fontWeight = 'bold';
                  }
                });
              }
            }, 100);
          };
          
          return true;
        }
        return false;
      };
      
      // Coba apply mode SQL langsung
      if (!applySQLMode()) {
        // Jika belum tersedia, coba lagi dengan retry yang lebih agresif
        let retryCount = 0;
        const maxRetries = 20;
        const retryInterval = setInterval(() => {
          retryCount++;
          if (applySQLMode()) {
            clearInterval(retryInterval);
          } else if (retryCount >= maxRetries) {
            clearInterval(retryInterval);
            // Tetap coba set mode SQL meskipun belum terdeteksi
            this.editor.setOption('mode', 'text/x-sql');
            this.editor.setOption('theme', this.config.theme);
            const content = this.editor.getValue();
            if (content) {
              this.editor.operation(() => {
                this.editor.setValue('');
                this.editor.setValue(content);
              });
            }
            this.editor.refresh();
            // Tambahkan event listener
            this.editor.on('change', () => {
              this.editor.refresh();
            });
          }
        }, 100);
      }
    } else {
      // Untuk mode lain, langsung apply
      applyModeWithHighlight(this.config.mode);
    }

    // Terapkan height jika disediakan
    if (this.config.height) {
      this.editor.setSize(null, this.config.height);
    }

    // Terapkan fontSize jika disediakan
    if (this.config.fontSize) {
      this.setFontSize(this.config.fontSize);
    }

    // Event listeners
    this.setupEventListeners();
    this.setupAutocompleteEvents();
    
    // Setup save dan copy buttons jika disediakan
    this.setupSaveAndCopy();
    
    // Setup format button jika disediakan
    this.setupFormatButton();
    
    // Setup mode icon
    this.setupModeIcon();
    
    // Auto format jika format: true di config
    if (this.config.format === true) {
      // Format setelah editor siap (gunakan setTimeout untuk memastikan editor sudah fully initialized)
      setTimeout(() => {
        this.formatCode();
      }, 100);
    }
    
    // Tampilkan editor setelah semua selesai di-setup
    this.showEditor();
  }
  
  /**
   * Menyembunyikan container editor sampai semua dependencies selesai di-load
   * @private
   */
  hideEditorContainer(element) {
    // Cari element
    const el = typeof element === 'string' ? document.getElementById(element) : element;
    if (!el) return;
    
    let container = el;
    
    // Cari parent dengan class NexaCmirror-editorContainer
    while (container && container.parentElement) {
      if (container.classList && container.classList.contains('NexaCmirror-editorContainer')) {
        break;
      }
      container = container.parentElement;
    }
    
    // Jika tidak ditemukan, cari di document
    if (!container || !container.classList || !container.classList.contains('NexaCmirror-editorContainer')) {
      container = document.querySelector('.NexaCmirror-editorContainer');
    }
    
    // Sembunyikan container via inline style (tidak bergantung CSS file)
    if (container) {
      container.classList.remove('NexaCmirror-ready');
      container.style.opacity = '0';
      container.style.visibility = 'hidden';
    }
  }
  
  /**
   * Menampilkan editor setelah semua dependencies selesai di-load
   * @private
   */
  showEditor() {
    // Cari container editor
    let container = this.element;
    
    // Cari parent dengan class NexaCmirror-editorContainer
    while (container && container.parentElement) {
      if (container.classList && container.classList.contains('NexaCmirror-editorContainer')) {
        break;
      }
      container = container.parentElement;
    }
    
    // Jika tidak ditemukan, cari di document
    if (!container || !container.classList || !container.classList.contains('NexaCmirror-editorContainer')) {
      container = document.querySelector('.NexaCmirror-editorContainer');
    }
    
    // Tampilkan container setelah semua selesai
    // Hapus inline style lalu tambah class ready agar transisi berjalan
    if (container) {
      container.style.opacity = '';
      container.style.visibility = '';
      container.classList.add('NexaCmirror-ready');
    }

    // CodeMirror tidak bisa mengukur dimensi saat container tersembunyi.
    // Panggil refresh() setelah container visible agar konten ter-render.
    if (this.editor) {
      setTimeout(() => {
        try { this.editor.refresh(); } catch (_) {}
      }, 0);
    }
  }

  /**
   * Mendapatkan theme berdasarkan mode
   * @param {string} mode - Mode editor
   * @returns {string} Theme yang sesuai dengan mode
   */
  getThemeByMode(mode) {
    // SQL dan JSON mode menggunakan theme default (putih)
    if (mode === 'sql' || mode === 'text/x-sql' || mode.includes('sql') || 
        mode === 'json' || mode.includes('json') || 
        mode === 'application/json' || mode.includes('application/json')) {
      return 'default';
    }
    // HTML, JavaScript, CSS menggunakan theme monokai (gelap)
    if (mode === 'htmlmixed' || mode === 'javascript' || mode === 'css' || 
        mode.includes('html') || mode.includes('javascript') || mode.includes('css')) {
      return 'monokai';
    }
    // Default untuk mode lainnya
    return 'default';
  }

  /**
   * Setup autocomplete berdasarkan mode
   */
  setupAutocomplete() {
    const mode = this.config.mode || 'htmlmixed';
    const instance = this; // Simpan referensi instance untuk digunakan di closure
    
    // Deteksi apakah mode adalah JSON (object dengan json: true)
    const isJsonMode = typeof mode === 'object' && mode.json === true;
    
    // Custom hint function berdasarkan mode
    const hintFunction = (cm) => {
      const cursor = cm.getCursor();
      const line = cm.getLine(cursor.line);
      const textBeforeCursor = line.substring(0, cursor.ch);
      let currentMode = cm.getMode().name || (typeof mode === 'object' ? mode.name : mode);
      
      // Jika mode adalah JSON (object dengan json: true), set currentMode ke 'json'
      if (isJsonMode || (typeof mode === 'object' && mode.json === true)) {
        currentMode = 'json';
      }

      // HTML/XML mode - autocomplete untuk tags dan atribut
      if (currentMode === 'htmlmixed' || currentMode === 'xml' || currentMode.includes('html')) {
        // Deteksi CSS di dalam tag <style> atau atribut style=""
        // Cek apakah cursor berada di dalam <style> tag
        const beforeCursor = cm.getRange({line: 0, ch: 0}, cursor);
        const styleTagOpen = beforeCursor.lastIndexOf('<style');
        const styleTagClose = beforeCursor.lastIndexOf('</style>');
        const isInStyleTag = styleTagOpen !== -1 && (styleTagClose === -1 || styleTagOpen > styleTagClose);
        
        // Cek apakah cursor berada di dalam atribut style=""
        const styleAttrMatch = textBeforeCursor.match(/style\s*=\s*"([^"]*)$/);
        const isInStyleAttr = styleAttrMatch !== null;
        
        // Jika berada di dalam CSS context (tag <style> atau atribut style="")
        if (isInStyleTag || isInStyleAttr) {
          // Gunakan CSS hint
          if (CodeMirror.hint && CodeMirror.hint.css) {
            return CodeMirror.hint.css(cm);
          }
        }
        
        // Autocomplete untuk atribut (seperti <div cl, <div id, dll)
        const attrMatch = textBeforeCursor.match(/<\w+[^>]*\s+([a-zA-Z-]*)$/);
        if (attrMatch) {
          const attrPrefix = attrMatch[1].toLowerCase();
          
          // Daftar atribut HTML umum
          const htmlAttributes = [
            'class', 'id', 'style', 'title', 'lang', 'dir', 'data-', 'aria-',
            'href', 'src', 'alt', 'target', 'rel', 'type', 'name', 'value',
            'placeholder', 'required', 'disabled', 'readonly', 'checked', 'selected',
            'width', 'height', 'colspan', 'rowspan', 'scope', 'headers',
            'role', 'tabindex', 'accesskey', 'contenteditable', 'draggable',
            'hidden', 'spellcheck', 'translate', 'autocomplete', 'autofocus',
            'form', 'formaction', 'formenctype', 'formmethod', 'formnovalidate',
            'formtarget', 'list', 'max', 'min', 'maxlength', 'minlength',
            'pattern', 'step', 'multiple', 'accept', 'capture'
          ];
          
          // Filter atribut berdasarkan prefix
          const matches = htmlAttributes.filter(attr => attr.startsWith(attrPrefix));
          
          if (matches.length > 0) {
            const completions = matches.map(attr => {
              // Jika atribut sudah ada tanda `=`, jangan tambahkan `=""`
              const afterCursor = line.substring(cursor.ch);
              if (afterCursor.match(/^\s*=/)) {
                return attr;
              }
              // Tambahkan `=""` setelah atribut
              return attr + '=""';
            });
            
            return {
              list: completions,
              from: CodeMirror.Pos(cursor.line, cursor.ch - attrPrefix.length),
              to: cursor
            };
          }
        }
        
        // Autocomplete untuk tag (seperti <div, <span, <img, dll)
        const tagMatch = textBeforeCursor.match(/<([a-zA-Z]*)$/);
        if (tagMatch) {
          const tagPrefix = tagMatch[1].toLowerCase();
          
          // Daftar tag HTML lengkap
          const htmlTags = [
            'a', 'abbr', 'address', 'area', 'article', 'aside', 'audio',
            'b', 'base', 'bdi', 'bdo', 'blockquote', 'body', 'br', 'button',
            'canvas', 'caption', 'cite', 'code', 'col', 'colgroup',
            'data', 'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'div', 'dl', 'dt',
            'em', 'embed',
            'fieldset', 'figcaption', 'figure', 'footer', 'form',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hr', 'html',
            'i', 'iframe', 'img', 'input', 'ins',
            'kbd',
            'label', 'legend', 'li', 'link',
            'main', 'map', 'mark', 'meta', 'meter',
            'nav', 'noscript',
            'object', 'ol', 'optgroup', 'option', 'output',
            'p', 'param', 'picture', 'pre', 'progress',
            'q',
            'rp', 'rt', 'ruby',
            's', 'samp', 'script', 'section', 'select', 'small', 'source', 'span', 'strong', 'style', 'sub', 'summary', 'sup',
            'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th', 'thead', 'time', 'title', 'tr', 'track',
            'u', 'ul',
            'var', 'video',
            'wbr'
          ];
          
          // Filter tag berdasarkan prefix
          const matches = htmlTags.filter(tag => tag.startsWith(tagPrefix));
          
          if (matches.length > 0) {
            // Self-closing tags yang tidak perlu closing tag
            const selfClosingTags = ['br', 'hr', 'img', 'input', 'meta', 'link', 'area', 'base', 'col', 'embed', 'source', 'track', 'wbr'];
            
            // Tag dengan atribut default yang umum digunakan
            const tagsWithDefaultAttrs = {
              'img': ' src=""',
              'a': ' href=""',
              'input': ' type=""',
              'link': ' rel="" href=""',
              'script': ' src=""',
              'iframe': ' src=""',
              'video': ' src=""',
              'audio': ' src=""',
              'source': ' src=""',
              'embed': ' src=""',
              'object': ' data=""',
              'form': ' action=""',
              'button': ' type=""',
              'select': ' name=""',
              'textarea': ' name=""',
              'meta': ' name="" content=""',
              'area': ' shape="" coords="" href=""',
              'base': ' href=""',
              'col': ' span=""',
              'track': ' kind="" src=""'
            };
            
            return {
              list: matches.map(tag => {
                // Jika tag memiliki atribut default, tambahkan atribut tersebut
                if (tagsWithDefaultAttrs[tag]) {
                  return tag + tagsWithDefaultAttrs[tag] + '>';
                }
                // Untuk tag lainnya, tambahkan `>` saja
                return tag + '>';
              }),
              from: CodeMirror.Pos(cursor.line, cursor.ch - tagPrefix.length),
              to: cursor
            };
          }
          
          // Fallback ke CodeMirror.hint.html jika ada
          if (CodeMirror.hint && CodeMirror.hint.html) {
            return CodeMirror.hint.html(cm);
          }
        }
        
        // Untuk HTML, juga coba XML hint sebagai fallback
        if (CodeMirror.hint && CodeMirror.hint.xml) {
          return CodeMirror.hint.xml(cm);
        }
      }
      
      // SQL mode - autocomplete lengkap untuk SQL keywords
      if (currentMode === 'sql' || currentMode.includes('sql')) {
        const cursor = cm.getCursor();
        const line = cm.getLine(cursor.line);
        const textBeforeCursor = line.substring(0, cursor.ch);
        const wordMatch = textBeforeCursor.match(/(\w+)$/);
        
        // Ambil instance untuk akses config (prioritaskan instance dari closure, lalu dari editor)
        const nexaInstance = instance || cm.nexaInstance;
        const config = nexaInstance ? nexaInstance.config : {};
        
        // Ambil variabel SQL dari config
        const sqlTables = config.sqlTables || [];
        const sqlColumns = config.sqlColumns || [];
        const sqlTableColumnsMap = config.sqlTableColumnsMap || {};
        const sqlFullAliases = config.sqlFullAliases || []; // Format lengkap: "table.column AS alias"
        
        // Daftar lengkap SQL keywords
        const sqlKeywords = [
          // Data Definition Language (DDL)
          'CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'RENAME',
          // Data Manipulation Language (DML)
          'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'MERGE',
          // Data Control Language (DCL)
          'GRANT', 'REVOKE',
          // Transaction Control Language (TCL)
          'COMMIT', 'ROLLBACK', 'SAVEPOINT',
          // Query clauses
          'FROM', 'WHERE', 'GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT', 'OFFSET',
          'JOIN', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'FULL JOIN', 'CROSS JOIN',
          'ON', 'USING',
          // Operators
          'AND', 'OR', 'NOT', 'IN', 'EXISTS', 'LIKE', 'BETWEEN', 'IS NULL', 'IS NOT NULL',
          // Functions
          'COUNT', 'SUM', 'AVG', 'MAX', 'MIN', 'DISTINCT',
          'UPPER', 'LOWER', 'SUBSTRING', 'CONCAT', 'LENGTH', 'TRIM',
          'DATE', 'NOW', 'CURDATE', 'CURTIME', 'YEAR', 'MONTH', 'DAY',
          // Data types
          'INT', 'VARCHAR', 'TEXT', 'DATE', 'DATETIME', 'TIMESTAMP', 'DECIMAL', 'FLOAT', 'DOUBLE', 'BOOLEAN',
          // Constraints
          'PRIMARY KEY', 'FOREIGN KEY', 'UNIQUE', 'NOT NULL', 'DEFAULT', 'AUTO_INCREMENT',
          // Table operations
          'TABLE', 'DATABASE', 'INDEX', 'VIEW', 'TRIGGER', 'PROCEDURE', 'FUNCTION',
          // Other keywords
          'AS', 'ASC', 'DESC', 'ALL', 'ANY', 'SOME', 'UNION', 'UNION ALL',
          'CASE', 'WHEN', 'THEN', 'ELSE', 'END',
          'IF', 'ELSEIF', 'ELSE', 'END IF',
          'WHILE', 'FOR', 'LOOP', 'REPEAT', 'UNTIL',
          'DECLARE', 'SET', 'BEGIN', 'END',
          'SHOW', 'DESCRIBE', 'EXPLAIN', 'USE'
        ];
        
        // Cek apakah ada pattern "table." untuk menampilkan kolom dari tabel tertentu
        const tableDotMatch = textBeforeCursor.match(/(\w+)\.(\w*)$/);
        if (tableDotMatch) {
          const tableName = tableDotMatch[1];
          const columnPrefix = tableDotMatch[2] || '';
          
          // Cari kolom dari tabel yang disebutkan
          if (sqlTableColumnsMap[tableName]) {
            const tableColumns = sqlTableColumnsMap[tableName].filter(col => 
              col.toLowerCase().startsWith(columnPrefix.toLowerCase())
            );
            if (tableColumns.length > 0) {
              // Return kolom dari tabel spesifik
              return {
                list: tableColumns,
                from: CodeMirror.Pos(cursor.line, cursor.ch - columnPrefix.length),
                to: cursor
              };
            }
          }
        }
        
        if (wordMatch) {
          const wordPrefix = wordMatch[1];
          const wordPrefixUpper = wordPrefix.toUpperCase();
          
          // Gabungkan keywords dengan tabel dan kolom
          let allSuggestions = [];
          
          // Filter keywords berdasarkan prefix (case-insensitive untuk keywords)
          const keywordMatches = sqlKeywords.filter(keyword => 
            keyword.toUpperCase().startsWith(wordPrefixUpper)
          );
          allSuggestions = allSuggestions.concat(keywordMatches);
          
          // Filter tabel berdasarkan prefix (case-insensitive)
          const tableMatches = sqlTables.filter(table => 
            table.toLowerCase().startsWith(wordPrefix.toLowerCase())
          );
          allSuggestions = allSuggestions.concat(tableMatches);
          
          // Filter kolom berdasarkan prefix (case-insensitive)
          const columnMatches = sqlColumns.filter(column => 
            column.toLowerCase().startsWith(wordPrefix.toLowerCase())
          );
          allSuggestions = allSuggestions.concat(columnMatches);
          
          // Filter fullAliases berdasarkan prefix (prioritaskan ini untuk memudahkan)
          // Cek apakah prefix cocok dengan bagian awal dari fullAlias (table.column atau alias)
          const fullAliasMatches = sqlFullAliases.filter(fullAlias => {
            const lowerAlias = fullAlias.toLowerCase();
            const lowerPrefix = wordPrefix.toLowerCase();
            // Cocokkan dengan table.column, column, atau alias
            return lowerAlias.includes(lowerPrefix) && 
                   (lowerAlias.startsWith(lowerPrefix) || 
                    lowerAlias.includes('.' + lowerPrefix) ||
                    lowerAlias.includes(' as ' + lowerPrefix));
          });
          // Tambahkan fullAliases dengan prioritas tinggi (di awal)
          allSuggestions = fullAliasMatches.concat(allSuggestions);
          
          if (allSuggestions.length > 0) {
            // Hapus duplikat dan urutkan
            const uniqueSuggestions = [...new Set(allSuggestions)];
            uniqueSuggestions.sort((a, b) => {
              // Prioritaskan fullAliases, lalu keywords, lalu tabel, lalu kolom
              const aIsFullAlias = sqlFullAliases.includes(a);
              const bIsFullAlias = sqlFullAliases.includes(b);
              if (aIsFullAlias && !bIsFullAlias) return -1;
              if (!aIsFullAlias && bIsFullAlias) return 1;
              
              const aIsKeyword = sqlKeywords.includes(a.toUpperCase());
              const bIsKeyword = sqlKeywords.includes(b.toUpperCase());
              if (aIsKeyword && !bIsKeyword) return -1;
              if (!aIsKeyword && bIsKeyword) return 1;
              
              const aIsTable = sqlTables.includes(a);
              const bIsTable = sqlTables.includes(b);
              if (aIsTable && !bIsTable && !aIsKeyword) return -1;
              if (!aIsTable && bIsTable && !aIsKeyword) return 1;
              
              return a.localeCompare(b);
            });
            
            return {
              list: uniqueSuggestions,
              from: CodeMirror.Pos(cursor.line, cursor.ch - wordPrefix.length),
              to: cursor
            };
          }
        } else {
          // Jika tidak ada wordMatch tapi ada data SQL, tampilkan semua suggestions
          // (hanya jika cursor di posisi yang tepat, misalnya setelah spasi atau di awal baris)
          const isEmptyOrAfterSpace = textBeforeCursor.trim() === '' || textBeforeCursor.endsWith(' ');
          if (isEmptyOrAfterSpace && (sqlTables.length > 0 || sqlColumns.length > 0 || sqlFullAliases.length > 0)) {
            let allSuggestions = [];
            // Tampilkan keywords yang umum digunakan
            const commonKeywords = ['SELECT', 'FROM', 'WHERE', 'JOIN', 'ORDER BY', 'GROUP BY', 'HAVING', 'LIMIT'];
            allSuggestions = allSuggestions.concat(commonKeywords);
            // Prioritaskan fullAliases untuk memudahkan
            if (sqlFullAliases.length > 0) {
              allSuggestions = allSuggestions.concat(sqlFullAliases);
            }
            allSuggestions = allSuggestions.concat(sqlTables);
            allSuggestions = allSuggestions.concat(sqlColumns.slice(0, 20)); // Limit kolom untuk menghindari terlalu banyak
            
            const uniqueSuggestions = [...new Set(allSuggestions)];
            return {
              list: uniqueSuggestions,
              from: cursor,
              to: cursor
            };
          }
        }
        
        // Fallback ke CodeMirror.hint.sql jika ada
        if (CodeMirror.hint && CodeMirror.hint.sql) {
          return CodeMirror.hint.sql(cm);
        }
      }
      
      // JavaScript mode
      if (currentMode === 'javascript' || currentMode.includes('javascript')) {
        if (CodeMirror.hint && CodeMirror.hint.javascript) {
          return CodeMirror.hint.javascript(cm);
        }
      }
      
      // CSS mode
      if (currentMode === 'css' || currentMode.includes('css')) {
        if (CodeMirror.hint && CodeMirror.hint.css) {
          return CodeMirror.hint.css(cm);
        }
      }
      
      // JSON mode
      if (currentMode === 'json' || currentMode.includes('json') || 
          currentMode === 'application/json' || currentMode.includes('application/json')) {
        if (CodeMirror.hint && CodeMirror.hint.json) {
          return CodeMirror.hint.json(cm);
        }
        // Fallback: JSON biasanya menggunakan JavaScript hint untuk autocomplete
        if (CodeMirror.hint && CodeMirror.hint.javascript) {
          return CodeMirror.hint.javascript(cm);
        }
      }

      // Jika tidak ada hint yang cocok, return null (tidak tampilkan autocomplete)
      return null;
    };

    // Setup hintOptions
    if (!this.config.hintOptions) {
      this.config.hintOptions = {};
    }
    
    this.config.hintOptions = {
      completeSingle: false,
      closeOnUnfocus: true,
      hint: hintFunction,
      ...this.config.hintOptions
    };
  }

  /**
   * Setup extraKeys untuk keyboard shortcuts
   */
  setupExtraKeys() {
    // Default extraKeys
    const defaultExtraKeys = {
      // Ctrl+Space untuk trigger autocomplete
      'Ctrl-Space': 'autocomplete',
      
      // Format code (Ctrl+Shift+F, Alt+Shift+F, atau Ctrl+K Ctrl+F)
      'Ctrl-Shift-F': (cm) => {
        const instance = cm.nexaInstance;
        if (instance && instance.formatCode) {
          instance.formatCode();
        }
        return true;
      },
      'Alt-Shift-F': (cm) => {
        const instance = cm.nexaInstance;
        if (instance && instance.formatCode) {
          instance.formatCode();
        }
        return true;
      },
      'Ctrl-K Ctrl-F': (cm) => {
        const instance = cm.nexaInstance;
        if (instance && instance.formatCode) {
          instance.formatCode();
        }
        return true;
      },
      
      // Tab untuk autocomplete atau indent
      'Tab': (cm) => {
        if (cm.somethingSelected()) {
          cm.indentSelection('add');
        } else {
          CodeMirror.commands.autocomplete(cm);
        }
      },
      
      // Enter untuk auto-close tag (HTML mode)
      'Enter': (cm) => {
        const currentMode = cm.getMode().name || this.config.mode;
        
        // Hanya untuk HTML/XML mode
        if (currentMode === 'htmlmixed' || currentMode === 'xml' || currentMode.includes('html')) {
          const cursor = cm.getCursor();
          const line = cm.getLine(cursor.line);
          const textBeforeCursor = line.substring(0, cursor.ch);
          
          // Cek apakah ada tag yang belum ditutup
          const tagMatch = textBeforeCursor.match(/<(\w+)(?:\s[^>]*)?>$/);
          if (tagMatch) {
            const tagName = tagMatch[1];
            // Skip untuk self-closing tags
            const selfClosingTags = ['br', 'hr', 'img', 'input', 'meta', 'link', 'area', 'base', 'col', 'embed', 'source', 'track', 'wbr'];
            if (!selfClosingTags.includes(tagName.toLowerCase())) {
              // Hitung indent dari baris saat ini
              const indentMatch = line.match(/^(\s*)/);
              const currentIndent = indentMatch ? indentMatch[1] : '';
              const indentUnit = cm.getOption('indentUnit') || 2;
              const indentChar = cm.getOption('indentWithTabs') ? '\t' : ' '.repeat(indentUnit);
              const newIndent = currentIndent + indentChar;
              
              // Insert newline dengan indent baru
              cm.replaceSelection('\n' + newIndent);
              // Insert closing tag dengan indent yang sama dengan baris pembuka
              cm.replaceSelection('\n' + currentIndent + '</' + tagName + '>');
              // Pindahkan cursor ke baris baru dengan indent
              const newCursor = {line: cursor.line + 1, ch: newIndent.length};
              cm.setCursor(newCursor);
              return;
            }
          }
        }
        
        // Default behavior untuk Enter
        CodeMirror.commands.newlineAndIndent(cm);
      }
    };

    // Merge: default dulu, lalu user extraKeys (user bisa override)
    this.config.extraKeys = {
      ...defaultExtraKeys,
      ...(this.config.extraKeys || {})
    };
  }

  /**
   * Setup event listeners untuk autocomplete
   */
  setupAutocompleteEvents() {
    // Trigger autocomplete saat mengetik
    this.editor.on('inputRead', (cm, change) => {
      const cursor = cm.getCursor();
      const line = cm.getLine(cursor.line);
      const textBeforeCursor = line.substring(0, cursor.ch);
      const currentMode = cm.getMode().name || this.config.mode;
      
      // Trigger autocomplete untuk HTML/XML mode
      if (currentMode === 'htmlmixed' || currentMode === 'xml' || currentMode.includes('html')) {
        // Deteksi CSS context (di dalam <style> tag atau atribut style="")
        const beforeCursor = cm.getRange({line: 0, ch: 0}, cursor);
        const styleTagOpen = beforeCursor.lastIndexOf('<style');
        const styleTagClose = beforeCursor.lastIndexOf('</style>');
        const isInStyleTag = styleTagOpen !== -1 && (styleTagClose === -1 || styleTagOpen > styleTagClose);
        const isInStyleAttr = textBeforeCursor.match(/style\s*=\s*"([^"]*)$/) !== null;
        
        // Trigger autocomplete untuk CSS di dalam <style> tag atau atribut style=""
        if (isInStyleTag || isInStyleAttr) {
          if (change.text[0] && /^[a-zA-Z0-9-:]/.test(change.text[0])) {
            setTimeout(() => {
              CodeMirror.commands.autocomplete(cm, null, {completeSingle: false});
            }, 200);
          }
        }
        
        // Trigger untuk tag (seperti <div, <img, dll)
        if (change.text[0] === '<' || 
            (textBeforeCursor.match(/<[a-zA-Z]*$/) && /^[a-zA-Z]/.test(change.text[0]))) {
          setTimeout(() => {
            CodeMirror.commands.autocomplete(cm, null, {completeSingle: false});
          }, 100);
        }
        // Trigger untuk tag yang sudah diketik sebagian (seperti <img)
        else if (textBeforeCursor.match(/<\w+$/) && change.text[0] && !change.text[0].match(/[<>\s]/)) {
          setTimeout(() => {
            CodeMirror.commands.autocomplete(cm, null, {completeSingle: false});
          }, 100);
        }
        // Trigger untuk atribut (seperti <div cl, <div id)
        else if (textBeforeCursor.match(/<\w+\s+[a-zA-Z-]*$/) && /^[a-zA-Z-]/.test(change.text[0])) {
          setTimeout(() => {
            CodeMirror.commands.autocomplete(cm, null, {completeSingle: false});
          }, 100);
        }
        // Trigger saat mengetik spasi setelah tag (untuk atribut)
        else if (change.text[0] === ' ' && textBeforeCursor.match(/<\w+$/)) {
          setTimeout(() => {
            CodeMirror.commands.autocomplete(cm, null, {completeSingle: false});
          }, 150);
        }
      }
      
      // Trigger autocomplete untuk mode lain (SQL, JSON, JS, CSS)
      else if (currentMode === 'sql' || currentMode === 'json' || currentMode === 'application/json' || 
               currentMode === 'javascript' || currentMode === 'css' ||
               currentMode.includes('sql') || currentMode.includes('json') || 
               currentMode.includes('application/json') || currentMode.includes('javascript') || currentMode.includes('css')) {
        // Trigger setelah mengetik beberapa karakter
        if (change.text[0] && /^[a-zA-Z0-9_$]/.test(change.text[0])) {
          setTimeout(() => {
            CodeMirror.commands.autocomplete(cm, null, {completeSingle: false});
          }, 200);
        }
      }
    });
    
    // Trigger autocomplete saat mengetik karakter setelah '<' atau spasi (HTML mode)
    this.editor.on('keyHandled', (cm, name, e) => {
      const cursor = cm.getCursor();
      const line = cm.getLine(cursor.line);
      const textBeforeCursor = line.substring(0, cursor.ch);
      const currentMode = cm.getMode().name || this.config.mode;
      
      // Untuk HTML/XML mode
      if (currentMode === 'htmlmixed' || currentMode === 'xml' || currentMode.includes('html')) {
        // Trigger untuk tag (seperti <div)
        if (textBeforeCursor.match(/<[a-zA-Z]*$/) && /^[a-zA-Z0-9]$/.test(e.key)) {
          setTimeout(() => {
            CodeMirror.commands.autocomplete(cm, null, {completeSingle: false});
          }, 150);
        }
        // Trigger untuk atribut (seperti <div cl, <div id)
        else if (textBeforeCursor.match(/<\w+\s+[a-zA-Z-]*$/) && /^[a-zA-Z-]$/.test(e.key)) {
          setTimeout(() => {
            CodeMirror.commands.autocomplete(cm, null, {completeSingle: false});
          }, 150);
        }
      }
    });
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Simpan referensi ke instance
    this.editor.nexaInstance = this;
    
    // Handler untuk paste event - pastikan tidak ada duplikasi atau formatting aneh
    this.editor.on('beforeChange', (cm, change) => {
      // Jika ini adalah paste operation
      if (change.origin === 'paste') {
        const currentMode = cm.getMode().name || this.config.mode;
        if (currentMode === 'sql' || currentMode === 'text/x-sql' || String(currentMode).includes('sql')) {
          // Dapatkan text yang akan di-paste
          const pastedText = change.text.join('\n');
          
          // Dapatkan text yang akan diganti (selection)
          const selectedText = cm.getRange(change.from, change.to);
          
          // Pastikan paste mengganti selection dengan benar
          // Jika ada selection, pastikan text yang di-paste tidak mengandung bagian dari selection
          if (selectedText && selectedText.trim()) {
            // Normalize text yang di-paste
            const normalizedPasted = pastedText.trim();
            const normalizedSelected = selectedText.trim();
            
            // Jika text yang di-paste mengandung bagian dari selection yang akan diganti,
            // pastikan tidak ada duplikasi
            // Tapi kita biarkan CodeMirror menangani ini secara default
          }
          
          // Normalize text yang di-paste: hapus whitespace berlebihan di akhir baris
          // Pastikan text yang di-paste tidak terpotong atau rusak
          const normalizedLines = change.text.map(line => {
            // Hapus whitespace di akhir baris
            let cleaned = line.trimEnd();
            // Pastikan tidak ada karakter aneh di awal atau akhir
            // Tapi jangan hapus whitespace di awal jika memang ada indentasi
            return cleaned;
          });
          
          if (JSON.stringify(normalizedLines) !== JSON.stringify(change.text)) {
            // Update change.text dengan normalized lines
            change.update(change.from, change.to, normalizedLines);
          }
        }
      }
    });
    
    // Handler setelah paste untuk memperbaiki text yang terpotong
    this.editor.on('change', (cm, change) => {
      // Jika ini adalah paste operation
      if (change.origin === 'paste') {
        const currentMode = cm.getMode().name || this.config.mode;
        if (currentMode === 'sql' || currentMode === 'text/x-sql' || String(currentMode).includes('sql')) {
          // Tunggu sebentar untuk memastikan change sudah selesai
          setTimeout(() => {
            const cursor = cm.getCursor();
            const line = cm.getLine(cursor.line);
            
            // Cek apakah ada text yang terpotong (misalnya "petani.n  petani.nama AS nama,")
            // Pattern 1: text terpotong di tengah kata (misalnya "petani.n" diikuti "petani.nama")
            // Pattern: "table.partial  table.full" -> "table.full"
            const brokenPattern1 = /(\w+)\.(\w{1,2})\s+(\1\.\w+)/;
            // Pattern 2: text terpotong di akhir (misalnya "ama AS nama," setelah "petani.nama AS nama,")
            // Pattern: "partial" diikuti "full partial" -> "full"
            const brokenPattern2 = /(\w{1,3})\s+(\w+\.\w+\s+AS\s+\1)/;
            
            // Cek beberapa baris di sekitar cursor untuk memperbaiki text terpotong
            // Perluas cakupan untuk menangani kasus yang lebih kompleks
            const startLine = Math.max(0, cursor.line - 2);
            const endLine = Math.min(cm.lineCount() - 1, cursor.line + 2);
            
            for (let checkLine = startLine; checkLine <= endLine; checkLine++) {
              const checkLineText = cm.getLine(checkLine);
              let fixedLine = checkLineText;
              let lineHasFix = false;
              
              // Perbaiki pattern 1: "petani.n  petani.nama" -> "petani.nama"
              if (brokenPattern1.test(fixedLine)) {
                fixedLine = fixedLine.replace(brokenPattern1, '$3');
                lineHasFix = true;
              }
              
              // Perbaiki pattern 2: "ama AS nama," setelah "petani.nama AS nama," -> hapus yang pertama
              if (brokenPattern2.test(fixedLine)) {
                fixedLine = fixedLine.replace(brokenPattern2, '$2');
                lineHasFix = true;
              }
              
              // Cek juga apakah baris ini adalah sisa dari text terpotong di baris sebelumnya
              if (checkLine > 0 && !lineHasFix) {
                const prevLine = cm.getLine(checkLine - 1);
                // Jika baris sebelumnya berakhir dengan pattern terpotong dan baris ini dimulai dengan sisa
                // Misalnya: baris sebelumnya = "petani.n  petani.nama AS nama,"
                //           baris ini = "ama AS nama,"
                if (prevLine.match(/(\w+\.\w+\s+AS\s+)(\w+),?\s*$/) && fixedLine.match(/^(\w+)\s+AS\s+\1/)) {
                  const prevMatch = prevLine.match(/(\w+\.\w+\s+AS\s+)(\w+),?\s*$/);
                  const currentMatch = fixedLine.match(/^(\w+)\s+AS\s+\1/);
                  if (prevMatch && currentMatch && prevMatch[2] === currentMatch[1]) {
                    // Baris ini adalah duplikat dari baris sebelumnya, hapus
                    fixedLine = '';
                    lineHasFix = true;
                  }
                }
              }
              
              if (lineHasFix && fixedLine !== checkLineText) {
                if (fixedLine === '') {
                  // Hapus baris kosong
                  cm.replaceRange('', {line: checkLine, ch: 0}, {line: checkLine, ch: checkLineText.length});
                  // Jika ada baris berikutnya, gabungkan dengan baris sebelumnya jika perlu
                  if (checkLine + 1 < cm.lineCount()) {
                    const nextLine = cm.getLine(checkLine + 1);
                    const prevLine = checkLine > 0 ? cm.getLine(checkLine - 1) : '';
                    if (prevLine && !prevLine.trim().endsWith(',') && nextLine.trim().match(/^\w+\.\w+\s+AS/)) {
                      // Gabungkan baris berikutnya dengan baris sebelumnya
                      const merged = prevLine.trim() + ' ' + nextLine.trim();
                      cm.replaceRange(merged, {line: checkLine - 1, ch: 0}, {line: checkLine - 1, ch: prevLine.length});
                      cm.replaceRange('', {line: checkLine + 1, ch: 0}, {line: checkLine + 1, ch: nextLine.length});
                    }
                  }
                } else {
                  cm.replaceRange(fixedLine, {line: checkLine, ch: 0}, {line: checkLine, ch: checkLineText.length});
                }
              }
            }
            
            // Restore cursor position setelah perbaikan
            setTimeout(() => {
              const newCursor = cm.getCursor();
              const newLine = cm.getLine(newCursor.line);
              const newCursorPos = Math.min(newCursor.ch, newLine.length);
              cm.setCursor(newCursor.line, newCursorPos);
            }, 5);
          }, 10);
        }
      }
    });
    
    // Event listener untuk memindahkan cursor setelah completion atribut dipilih
    // dan menambahkan `>` setelah tag dipilih
    this.editor.on('change', (cm, change) => {
      const cursor = cm.getCursor();
      const line = cm.getLine(cursor.line);
      const textBeforeCursor = line.substring(0, cursor.ch);
      const currentMode = cm.getMode().name || this.config.mode;
      
      // Cek apakah tag dengan atribut default dipilih (seperti <img src="">)
      if (currentMode === 'htmlmixed' || currentMode === 'xml' || currentMode.includes('html')) {
        if (change.text && change.text.length > 0) {
          setTimeout(() => {
            const currentCursor = cm.getCursor();
            const currentLine = cm.getLine(currentCursor.line);
            const beforeCurrent = currentLine.substring(0, currentCursor.ch);
            
            // Deteksi tag dengan atribut default (seperti <img src="">, <a href="">, dll)
            const tagWithAttrMatch = beforeCurrent.match(/<(\w+)\s+(\w+)=""/);
            if (tagWithAttrMatch) {
              const firstEqualQuote = beforeCurrent.indexOf('=""');
              if (firstEqualQuote !== -1) {
                // Pindahkan cursor ke dalam tanda kutip pertama
                cm.setCursor(currentCursor.line, firstEqualQuote + 2);
              }
            }
          }, 50);
        }
      }
      
      // Cek apakah perubahan menambahkan `=""` (completion atribut)
      if (change.text && change.text.length > 0) {
        const text = change.text[0];
        // Jika text berisi `=""` (completion atribut seperti class="", id="", dll)
        if (text.includes('=""')) {
          setTimeout(() => {
            const cursor = cm.getCursor();
            const line = cm.getLine(cursor.line);
            
            // Cari semua posisi `=""` di baris
            let equalQuotePos = -1;
            let searchStart = 0;
            
            // Cari `=""` yang paling dekat dengan posisi cursor
            while (true) {
              const pos = line.indexOf('=""', searchStart);
              if (pos === -1) break;
              
              // Jika posisi ini setelah atau dekat dengan cursor, gunakan ini
              if (pos >= cursor.ch - 5 || (equalQuotePos === -1 && pos < cursor.ch + 10)) {
                equalQuotePos = pos;
                break;
              }
              searchStart = pos + 1;
            }
            
            if (equalQuotePos !== -1) {
              // Pindahkan cursor ke posisi setelah tanda kutip pertama (di dalam "")
              // equalQuotePos adalah posisi `=`, jadi +2 adalah posisi setelah `"`
              cm.setCursor(cursor.line, equalQuotePos + 2);
            } else {
              // Fallback: cari tanda kutip pertama di sekitar cursor
              const searchStart = Math.max(0, cursor.ch - 10);
              const searchEnd = Math.min(line.length, cursor.ch + 10);
              const searchArea = line.substring(searchStart, searchEnd);
              const localQuotePos = searchArea.indexOf('"');
              
              if (localQuotePos !== -1) {
                cm.setCursor(cursor.line, searchStart + localQuotePos + 1);
              }
            }
          }, 10);
        }
      }
    });
  }

  /**
   * Setup format button secara otomatis
   * @private
   */
  setupFormatButton() {
    // Cek apakah ada tombol format di HTML
    const btnFormat = document.getElementById('btnFormat');
    
    if (btnFormat) {
      btnFormat.addEventListener('click', () => {
        this.formatCode();
      });
    }
  }

  /**
   * Setup save dan copy buttons secara otomatis
   * @private
   */
  setupSaveAndCopy() {
    // Cek status: jika false, sembunyikan tombol save dan copy
    const status = this.config.status !== false; // Default: true jika tidak disediakan
    
    // Setup save button jika ID disediakan
    if (this.config.save) {
      const btnSave = typeof this.config.save === 'string' 
        ? document.getElementById(this.config.save) 
        : this.config.save;
      
      if (btnSave) {
        // Tampilkan atau sembunyikan tombol berdasarkan status
        if (!status) {
          btnSave.style.display = 'none';
        } else {
          btnSave.style.display = '';
          
          // Setup save functionality hanya jika status true
          // Buat atau dapatkan notification element
          let saveNotification = document.getElementById('saveNotification');
          if (!saveNotification) {
            saveNotification = document.createElement('div');
            saveNotification.id = 'saveNotification';
            saveNotification.className = 'NexaCmirror-save-notification';
            saveNotification.textContent = '✓ Berhasil disimpan!';
            document.body.appendChild(saveNotification);
          }

          // Fungsi untuk menampilkan notifikasi save
          const showSaveNotification = (message = '✓ Berhasil disimpan!') => {
            // Cek apakah notifikasi diaktifkan
            if (this.config.notification === false) {
              return;
            }
            saveNotification.textContent = message;
            saveNotification.classList.add('NexaCmirror-show');
            setTimeout(() => {
              saveNotification.classList.remove('NexaCmirror-show');
            }, 2000);
          };

          // Fungsi save
          const saveContent = () => {
            try {
              const content = this.getValue();
              localStorage.setItem('codemirror_content', content);
              // Simpan ke textarea jika menggunakan fromTextArea
              if (this.element && this.element.tagName === 'TEXTAREA') {
                this.editor.save();
              }
              showSaveNotification('✓ Berhasil disimpan!');
              
              // Panggil callback onSave jika disediakan
              if (typeof this.config.onSave === 'function') {
                this.config.onSave(content);
              }
            } catch (error) {
              console.error('Error saving content:', error);
              showSaveNotification('✗ Gagal menyimpan!');
            }
          };

          // Event listener untuk tombol save
          btnSave.addEventListener('click', saveContent);

          // Keyboard shortcut Ctrl+S atau Cmd+S untuk save
          // Gunakan once: false agar bisa digunakan berkali-kali
          const saveKeyHandler = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
              e.preventDefault();
              saveContent();
            }
          };
          document.addEventListener('keydown', saveKeyHandler);
          // Simpan handler untuk bisa di-remove nanti jika diperlukan
          this._saveKeyHandler = saveKeyHandler;
        }
      }
    }

    // Setup copy button jika ID disediakan
    if (this.config.copy) {
      const btnCopy = typeof this.config.copy === 'string' 
        ? document.getElementById(this.config.copy) 
        : this.config.copy;
      
      if (btnCopy) {
        // Tampilkan atau sembunyikan tombol berdasarkan status
        if (!status) {
          btnCopy.style.display = 'none';
        } else {
          btnCopy.style.display = '';
          
          // Setup copy functionality hanya jika status true
          // Buat atau dapatkan notification element
          let copyNotification = document.getElementById('copyNotification');
          if (!copyNotification) {
            copyNotification = document.createElement('div');
            copyNotification.id = 'copyNotification';
            copyNotification.className = 'NexaCmirror-copy-notification';
            copyNotification.textContent = '✓ Berhasil disalin!';
            document.body.appendChild(copyNotification);
          }

          // Fungsi untuk menampilkan notifikasi copy
          const showCopyNotification = (message = '✓ Berhasil disalin!') => {
            // Cek apakah notifikasi diaktifkan
            if (this.config.notification === false) {
              return;
            }
            copyNotification.textContent = message;
            copyNotification.classList.add('NexaCmirror-show');
            setTimeout(() => {
              copyNotification.classList.remove('NexaCmirror-show');
            }, 2000);
          };

          // Fungsi copy
          const copyContent = async () => {
            let content = this.editor.getSelection() || this.getValue();

            // Coba execCommand dulu (kompatibel Electron & semua browser)
            try {
              const textArea = document.createElement('textarea');
              textArea.value = content;
              textArea.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
              document.body.appendChild(textArea);
              textArea.focus();
              textArea.select();
              const ok = document.execCommand('copy');
              document.body.removeChild(textArea);
              if (ok) {
                showCopyNotification('✓ Berhasil disalin ke clipboard!');
                if (typeof this.config.onCopy === 'function') this.config.onCopy(content);
                return;
              }
            } catch (_) {}

            // Fallback: Clipboard API (butuh HTTPS / permission)
            try {
              await navigator.clipboard.writeText(content);
              showCopyNotification('✓ Berhasil disalin ke clipboard!');
              if (typeof this.config.onCopy === 'function') this.config.onCopy(content);
            } catch (err) {
              showCopyNotification('✗ Gagal menyalin!');
            }
          };

          // Event listener untuk tombol copy
          btnCopy.addEventListener('click', copyContent);
        }
      }
    }
  }

  /**
   * Setup mode icon berdasarkan mode editor
   * @private
   */
  setupModeIcon() {
    // Cari element icon dan text
    const iconElement = document.getElementById('modeIcon');
    const textElement = document.getElementById('modeText');
    
    if (!iconElement) return;
    
    // Pastikan Font Awesome CSS sudah di-load
    this.ensureFontAwesomeLoaded();
    
    // Update icon berdasarkan mode saat ini
    this.updateModeIcon(this.config.mode);
  }

  /**
   * Memastikan Font Awesome CSS sudah di-load
   * @private
   */
  ensureFontAwesomeLoaded() {
    // Cek apakah Font Awesome CSS sudah di-load dengan mencari link tag
    const existingLink = document.querySelector('link[href*="fontawesome"], link[href*="font-awesome"]');
    
    // Jika belum ada, load Font Awesome CSS
    if (!existingLink && typeof NXUI !== 'undefined' && NXUI.NexaStylesheet) {
      // Load Font Awesome CSS dari local assets
      NXUI.NexaStylesheet.Dom(['{font/fontawesome/css/all.min.css}']).catch(err => {
        console.warn('Failed to load Font Awesome CSS:', err);
      });
    }
  }

  /**
   * Format/Beautify kode HTML/XML
   * Merapikan kode yang berantakan dengan menambahkan indentasi dan line breaks
   */
  formatCode() {
    try {
      const content = this.getValue();
      if (!content || content.trim() === '') {
        return this;
      }
      
      const currentMode = this.config.mode;
      
      // Deteksi mode
      let modeName = currentMode;
      if (typeof currentMode === 'object' && currentMode.name) {
        modeName = currentMode.name;
      }
      
      // Format untuk HTML/XML mode
      if (modeName === 'htmlmixed' || modeName === 'html' || modeName === 'xml' || 
          String(modeName).includes('html') || String(modeName).includes('xml')) {
        const formatted = this.formatHTML(content);
        if (formatted && formatted !== content) {
          // Simpan cursor position
          const cursor = this.getCursor();
          this.setValue(formatted);
          // Restore cursor position
          try {
            this.setCursor(cursor.line, cursor.ch);
          } catch (e) {
            // If cursor position invalid, go to start
            this.setCursor(0, 0);
          }
          this.refresh();
        }
        return this;
      }
      
      // Format untuk SQL mode
      if (modeName === 'sql' || String(modeName).includes('sql')) {
        const formatted = this.formatSQL(content);
        if (formatted && formatted !== content) {
          // Simpan cursor position
          const cursor = this.getCursor();
          this.setValue(formatted);
          // Restore cursor position
          try {
            this.setCursor(cursor.line, cursor.ch);
          } catch (e) {
            // If cursor position invalid, go to start
            this.setCursor(0, 0);
          }
          this.refresh();
        }
        return this;
      }
      
      // Untuk mode lain, gunakan indentasi sederhana
      if (this.editor && typeof this.editor.autoFormatRange === 'function') {
        this.editor.autoFormatRange(
          {line: 0, ch: 0},
          {line: this.editor.lineCount() - 1, ch: this.editor.getLine(this.editor.lineCount() - 1).length}
        );
      }
      
      return this;
    } catch (error) {
      console.error('Error formatting code:', error);
      return this;
    }
  }

  /**
   * Format SQL code dengan indentasi dan line breaks
   * @param {string} sql - SQL code yang akan di-format
   * @returns {string} Formatted SQL
   * @private
   */
  formatSQL(sql) {
    if (!sql || typeof sql !== 'string') {
      return sql;
    }

    const indentSize = this.config.indentUnit || 2;
    const indentChar = this.config.indentWithTabs ? '\t' : ' '.repeat(indentSize);
    
    // Normalize: replace multiple spaces dengan single space, tapi preserve newlines
    sql = sql.replace(/[ \t]+/g, ' ').replace(/\n\s*/g, ' ').trim();
    
    // SQL keywords yang memerlukan line break sebelum (main clauses)
    const mainClauses = ['FROM', 'WHERE', 'GROUP BY', 'ORDER BY', 'HAVING', 
                         'JOIN', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'FULL JOIN', 'CROSS JOIN',
                         'UNION', 'UNION ALL', 'EXCEPT', 'INTERSECT', 'LIMIT', 'OFFSET'];
    
    let formatted = '';
    let indent = 0;
    let i = 0;
    const len = sql.length;
    let inQuotes = false;
    let quoteChar = null;
    let inSelectClause = false; // Track apakah sedang di SELECT clause
    let afterSelect = false; // Track apakah baru saja menemukan SELECT
    
    while (i < len) {
      const char = sql[i];
      
      // Handle quotes (untuk string literals)
      if ((char === '"' || char === "'" || char === '`') && (i === 0 || sql[i-1] !== '\\')) {
        if (!inQuotes) {
          inQuotes = true;
          quoteChar = char;
        } else if (char === quoteChar) {
          inQuotes = false;
          quoteChar = null;
        }
        formatted += char;
        i++;
        continue;
      }
      
      // Jika dalam quotes, tambahkan karakter apa adanya
      if (inQuotes) {
        formatted += char;
        i++;
        continue;
      }
      
      // Handle keywords - cek apakah ada keyword di posisi ini
      if (char.match(/[A-Za-z]/)) {
        const wordInfo = this.getNextWord(sql, i);
        
        // Validasi: pastikan wordInfo valid dan memiliki word
        if (!wordInfo || !wordInfo.word || wordInfo.length === 0) {
          // Jika tidak ada word yang valid, tambahkan karakter biasa
          formatted += char;
          i++;
          continue;
        }
        
        const word = wordInfo.word;
        const wordLength = wordInfo.length;
        const upperWord = word.toUpperCase();
        
        // Handle SELECT khusus - tambahkan line break setelah SELECT
        if (upperWord === 'SELECT') {
          formatted += 'SELECT';
          i += wordLength;
          afterSelect = true;
          inSelectClause = true;
          
          // Skip whitespace setelah SELECT
          while (i < len && sql[i] === ' ') {
            i++;
          }
          
          // Tambahkan line break setelah SELECT jika ada kolom
          if (i < len && sql[i] !== '*') {
            formatted += '\n' + indentChar.repeat(indent + 1);
          }
          continue;
        }
        
        // Handle AS keyword - jangan pisahkan dari kolom sebelumnya
        if (upperWord === 'AS' && inSelectClause) {
          // Pastikan tidak ada line break sebelum AS
          // Jika formatted berakhir dengan newline, hapus newline dan tambahkan spasi
          if (formatted.endsWith('\n')) {
            // Hapus newline terakhir dan indent
            formatted = formatted.replace(/\n\s*$/, '');
            formatted += ' ';
          } else if (!formatted.endsWith(' ')) {
            // Tambahkan spasi sebelum AS jika belum ada
            formatted += ' ';
          }
          formatted += 'AS';
          i += wordLength;
          
          // Skip whitespace setelah AS
          while (i < len && sql[i] === ' ') {
            i++;
          }
          
          // Tambahkan spasi setelah AS jika ada identifier berikutnya
          if (i < len && sql[i].match(/[A-Za-z0-9_]/)) {
            formatted += ' ';
          }
          continue;
        }
        
        // Cek apakah ini main clause yang perlu line break
        if (mainClauses.includes(upperWord)) {
          // Jika sebelumnya di SELECT clause, reset flag
          if (inSelectClause) {
            inSelectClause = false;
            // Pastikan tidak ada baris kosong ganda setelah SELECT clause
            // Hapus semua newline di akhir, lalu tambahkan satu newline
            formatted = formatted.replace(/\n+$/, '');
            formatted += '\n';
          }
          
          // Handle LIMIT - cek apakah berikutnya adalah OFFSET
          if (upperWord === 'LIMIT') {
            // Cek apakah setelah LIMIT ada OFFSET
            let j = i + wordLength;
            // Skip whitespace
            while (j < len && sql[j] === ' ') {
              j++;
            }
            // Cek apakah berikutnya adalah OFFSET
            const nextWordInfo = this.getNextWord(sql, j);
            if (nextWordInfo && nextWordInfo.word && nextWordInfo.word.toUpperCase() === 'OFFSET') {
              // Jika berikutnya adalah OFFSET, jangan tambahkan line break setelah LIMIT
              // Tambahkan line break sebelum LIMIT
              if (formatted && !formatted.endsWith('\n')) {
                formatted += '\n';
              }
              formatted = formatted.replace(/\n+$/, '\n');
              formatted += indentChar.repeat(indent);
              formatted += upperWord;
              i += wordLength;
              
              // Tambahkan spasi setelah LIMIT
              while (i < len && sql[i] === ' ') {
                formatted += ' ';
                i++;
              }
              continue; // OFFSET akan di-handle di iterasi berikutnya
            }
          }
          
          // Handle OFFSET - gabungkan dengan LIMIT di satu baris jika berurutan
          if (upperWord === 'OFFSET') {
            // Cek apakah sebelumnya adalah LIMIT (bisa di akhir baris atau di tengah)
            const lastLine = formatted.split('\n').pop() || '';
            if (lastLine.trim().toUpperCase().endsWith('LIMIT')) {
              // Jika sebelumnya adalah LIMIT, tambahkan spasi saja (tidak line break)
              formatted += ' ' + upperWord;
              i += wordLength;
              
              // Skip whitespace setelah OFFSET
              while (i < len && sql[i] === ' ') {
                i++;
              }
              continue;
            }
          }
          
          // Tambahkan line break sebelum keyword (pastikan hanya satu newline)
          if (formatted && !formatted.endsWith('\n')) {
            formatted += '\n';
          }
          // Pastikan tidak ada baris kosong ganda
          formatted = formatted.replace(/\n+$/, '\n');
          
          formatted += indentChar.repeat(indent);
          formatted += upperWord;
          i += wordLength;
          
          // Tambahkan spasi setelah keyword jika ada
          while (i < len && sql[i] === ' ') {
            formatted += ' ';
            i++;
          }
          continue;
        }
        
        // Tambahkan keyword biasa
        formatted += word;
        i += wordLength;
        continue;
      }
      
      // Handle comma - tambahkan line break setelah comma untuk SELECT columns
      if (char === ',') {
        formatted += char;
        i++;
        // Skip whitespace setelah comma
        while (i < len && sql[i] === ' ') {
          i++;
        }
        // Tambahkan line break dan indent untuk kolom berikutnya (hanya di SELECT clause)
        if (i < len) {
          if (inSelectClause) {
            formatted += '\n' + indentChar.repeat(indent + 1);
          } else {
            formatted += ' ';
          }
        }
        continue;
      }
      
      // Handle spasi
      if (char === ' ') {
        // Skip multiple spaces
        let spaceCount = 1;
        while (i + spaceCount < len && sql[i + spaceCount] === ' ') {
          spaceCount++;
        }
        i += spaceCount;
        
        // Cek apakah setelah spasi ada keyword AS (di SELECT clause)
        if (i < len && sql[i].match(/[A-Za-z]/) && inSelectClause) {
          const nextWordInfo = this.getNextWord(sql, i);
          if (nextWordInfo && nextWordInfo.word && nextWordInfo.word.toUpperCase() === 'AS') {
            // Jika berikutnya adalah AS, tambahkan spasi saja (tidak line break)
            if (formatted && !formatted.endsWith(' ') && !formatted.endsWith('\n')) {
              formatted += ' ';
            }
            continue; // Skip spasi ini, AS akan di-handle di bagian keyword
          }
        }
        
        // Jika setelah SELECT dan belum ada line break, tambahkan line break
        if (afterSelect && !formatted.endsWith('\n')) {
          formatted += '\n' + indentChar.repeat(indent + 1);
          afterSelect = false;
        } else if (formatted && !formatted.endsWith('\n') && !formatted.endsWith(' ')) {
          // Tambahkan spasi jika tidak di akhir baris
          formatted += ' ';
        }
        continue;
      }
      
      // Karakter lainnya
      formatted += char;
      i++;
    }
    
    // Clean up: remove trailing spaces dan baris kosong ganda
    formatted = formatted.replace(/[ \t]+$/gm, '');
    formatted = formatted.replace(/\n\n+/g, '\n'); // Hapus baris kosong ganda
    formatted = formatted.replace(/\n\s*\n/g, '\n'); // Hapus baris kosong di antara konten
    
    return formatted.trim();
  }
  
  /**
   * Helper function untuk mendapatkan next word dari SQL
   * @param {string} sql - SQL string
   * @param {number} start - Start position
   * @returns {Object} {word: string, length: number} - Next word dan panjangnya
   * @private
   */
  getNextWord(sql, start) {
    let i = start;
    const len = sql.length;
    const startPos = i;
    
    // Skip whitespace
    while (i < len && /\s/.test(sql[i])) {
      i++;
    }
    
    if (i >= len) return { word: '', length: 0 };
    
    // Get word (alphanumeric and underscore)
    let word = '';
    while (i < len && /[A-Za-z0-9_]/.test(sql[i])) {
      word += sql[i];
      i++;
    }
    
    // Check for multi-word keywords (GROUP BY, ORDER BY, etc.)
    const upperWord = word.toUpperCase();
    if (upperWord === 'GROUP' || upperWord === 'ORDER' || 
        upperWord === 'INNER' || upperWord === 'LEFT' ||
        upperWord === 'RIGHT' || upperWord === 'FULL' ||
        upperWord === 'CROSS' || upperWord === 'UNION') {
      // Skip whitespace
      while (i < len && /\s/.test(sql[i])) {
        i++;
      }
      // Get next word
      let nextWord = '';
      while (i < len && /[A-Za-z0-9_]/.test(sql[i])) {
        nextWord += sql[i];
        i++;
      }
      if (nextWord) {
        const upperNext = nextWord.toUpperCase();
        if ((upperWord === 'GROUP' || upperWord === 'ORDER') && upperNext === 'BY') {
          word += ' ' + nextWord;
        } else if ((upperWord === 'INNER' || upperWord === 'LEFT' || upperWord === 'RIGHT' || 
                   upperWord === 'FULL' || upperWord === 'CROSS') && upperNext === 'JOIN') {
          word += ' ' + nextWord;
        } else if (upperWord === 'UNION' && upperNext === 'ALL') {
          word += ' ' + nextWord;
        }
      }
    }
    
    // Calculate actual length (including skipped whitespace)
    const actualLength = i - startPos;
    
    return { word: word, length: actualLength };
  }

  /**
   * Format HTML code dengan indentasi
   * @param {string} html - HTML code yang akan di-format
   * @returns {string} Formatted HTML
   * @private
   */
  formatHTML(html) {
    if (!html || typeof html !== 'string') {
      return html;
    }

    const indentSize = this.config.indentUnit || 2;
    const indentChar = this.config.indentWithTabs ? '\t' : ' '.repeat(indentSize);
    
    // Self-closing tags
    const selfClosingTags = ['br', 'hr', 'img', 'input', 'meta', 'link', 'area', 'base', 
                            'col', 'embed', 'source', 'track', 'wbr'];
    
    // Normalize: remove whitespace between tags
    html = html.replace(/>\s+</g, '><').trim();
    
    let formatted = '';
    let indent = 0;
    let i = 0;
    const len = html.length;
    
    // Helper function untuk get tag name
    const getTagName = (tag) => {
      const match = tag.match(/^<\/?(\w+)/i);
      return match ? match[1].toLowerCase() : '';
    };
    
    // Helper function untuk check self-closing
    const isSelfClosingTag = (tag) => {
      const tagName = getTagName(tag);
      return selfClosingTags.includes(tagName) || tag.endsWith('/>') || tag.match(/^<!/i);
    };
    
    while (i < len) {
      // Skip whitespace
      while (i < len && /\s/.test(html[i])) {
        i++;
      }
      if (i >= len) break;
      
      // Check for tag
      if (html[i] === '<') {
        // Find end of tag (handle quotes in attributes)
        let tagEnd = i + 1;
        let inQuotes = false;
        let quoteChar = null;
        
        while (tagEnd < len) {
          const char = html[tagEnd];
          if (!inQuotes && (char === '"' || char === "'")) {
            inQuotes = true;
            quoteChar = char;
          } else if (inQuotes && char === quoteChar) {
            inQuotes = false;
            quoteChar = null;
          } else if (!inQuotes && char === '>') {
            tagEnd++;
            break;
          }
          tagEnd++;
        }
        
        if (tagEnd > len) {
          // Incomplete tag
          formatted += html.substring(i);
          break;
        }
        
        const tag = html.substring(i, tagEnd);
        const isClosing = tag.startsWith('</');
        const isComment = tag.startsWith('<!--');
        const isDoctype = tag.match(/^<!doctype/i);
        
        // Add newline before tag (except first tag)
        if (formatted && !formatted.endsWith('\n')) {
          formatted += '\n';
        }
        
        if (isClosing) {
          // Closing tag - decrease indent first
          indent = Math.max(0, indent - 1);
          formatted += indentChar.repeat(indent);
          formatted += tag;
        } else if (isComment || isDoctype) {
          // Comment or DOCTYPE - keep at current indent
          formatted += indentChar.repeat(indent);
          formatted += tag;
        } else {
          // Opening tag
          formatted += indentChar.repeat(indent);
          formatted += tag;
          
          // Increase indent if not self-closing
          if (!isSelfClosingTag(tag)) {
            indent++;
          }
        }
        
        i = tagEnd;
      } else {
        // Text content
        let textStart = i;
        // Find next tag
        let nextTag = html.indexOf('<', i);
        if (nextTag === -1) {
          nextTag = len;
        }
        
        const text = html.substring(textStart, nextTag);
        const trimmed = text.trim();
        
        if (trimmed) {
          // Add newline before text if previous was tag
          if (formatted && !formatted.endsWith('\n')) {
            formatted += '\n';
          }
          formatted += indentChar.repeat(indent);
          formatted += trimmed;
        }
        
        i = nextTag;
      }
    }
    
    return formatted.trim();
  }

  /**
   * Update icon berdasarkan mode
   * @param {string|object} mode - Mode editor
   */
  updateModeIcon(mode) {
    const iconElement = document.getElementById('modeIcon');
    const textElement = document.getElementById('modeText');
    
    if (!iconElement) return;
    
    // Deteksi mode name
    let modeName = mode;
    if (typeof mode === 'object' && mode.name) {
      if (mode.json === true) {
        modeName = 'json';
      } else {
        modeName = mode.name;
      }
    }
    
    // Mapping mode ke Font Awesome icon (Font Awesome 5.x menggunakan fas/fab)
    const modeIcons = {
      'htmlmixed': { class: 'fab', icon: 'fa-html5', color: '#e34c26' },
      'html': { class: 'fab', icon: 'fa-html5', color: '#e34c26' },
      'xml': { class: 'fas', icon: 'fa-code', color: '#ff6600' },
      'javascript': { class: 'fab', icon: 'fa-js', color: '#f7df1e' },
      'js': { class: 'fab', icon: 'fa-js', color: '#f7df1e' },
      'css': { class: 'fab', icon: 'fa-css3-alt', color: '#264de4' },
      'sql': { class: 'fas', icon: 'fa-database', color: '#336791' },
      'json': { class: 'fas', icon: 'fa-file-code', color: '#000000' },
      'application/json': { class: 'fas', icon: 'fa-file-code', color: '#000000' }
    };
    
    // Mapping mode ke text label
    const modeLabels = {
      'htmlmixed': 'HTML',
      'html': 'HTML',
      'xml': 'XML',
      'javascript': 'JavaScript',
      'js': 'JavaScript',
      'css': 'CSS',
      'sql': 'SQL',
      'json': 'JSON',
      'application/json': 'JSON'
    };
    
    // Cari icon yang sesuai
    let iconConfig = { class: 'fas', icon: 'fa-code', color: '#333' }; // Default
    let label = 'Code'; // Default
    
    // Normalize modeName untuk matching
    const normalizedMode = String(modeName).toLowerCase().trim();
    
    // Cek exact match dulu
    if (modeIcons[normalizedMode]) {
      iconConfig = modeIcons[normalizedMode];
      label = modeLabels[normalizedMode] || modeName;
    } else {
      // Cek partial match
      for (const [key, config] of Object.entries(modeIcons)) {
        if (normalizedMode.includes(key) || key.includes(normalizedMode)) {
          iconConfig = config;
          label = modeLabels[key] || modeName;
          break;
        }
      }
    }
    
    // Update icon class (Font Awesome 5.x menggunakan fas/fab)
    iconElement.className = `${iconConfig.class} ${iconConfig.icon}`;
    
    // Update warna icon
    if (iconConfig.color) {
      iconElement.style.color = iconConfig.color;
    }
    
    // Update text jika element ada
    if (textElement) {
      textElement.textContent =this.elementTitle ? label+" Type "+this.elementTitle:label;
    }
  }

  /**
   * Mendapatkan konten editor
   * @returns {string} Konten editor
   */
  getValue() {
    return this.editor.getValue();
  }

  /**
   * Mengatur konten editor
   * @param {string} content - Konten yang akan di-set
   */
  setValue(content) {
    this.editor.setValue(content || '');
    return this;
  }

  /**
   * Mendapatkan instance CodeMirror
   * @returns {CodeMirror} Instance CodeMirror
   */
  getEditor() {
    return this.editor;
  }

  /**
   * Mengatur mode editor
   * @param {string} mode - Mode editor (htmlmixed, javascript, css, sql, dll)
   */
  setMode(mode) {
    this.editor.setOption('mode', mode);
    this.config.mode = mode;
    // Setup ulang autocomplete untuk mode baru
    this.setupAutocomplete();
    this.editor.setOption('hintOptions', this.config.hintOptions);
    // Ubah theme berdasarkan mode baru
    const newTheme = this.getThemeByMode(mode);
    this.config.theme = newTheme;
    this.editor.setOption('theme', newTheme);
    // Update icon berdasarkan mode baru
    this.updateModeIcon(mode);
    return this;
  }

  /**
   * Mengatur theme editor
   * @param {string} theme - Theme editor (monokai, default, dll)
   */
  setTheme(theme) {
    this.editor.setOption('theme', theme);
    return this;
  }

  /**
   * Mengatur ukuran font editor
   * @param {string|number} fontSize - Ukuran font (contoh: '14px', '16px', 14)
   */
  setFontSize(fontSize) {
    // Simpan ke config
    this.config.fontSize = fontSize;
    
    // Konversi ke string dengan 'px' jika hanya angka
    const fontSizeStr = typeof fontSize === 'number' ? `${fontSize}px` : fontSize;
    
    // Terapkan ke editor element
    if (this.editor && this.editor.getWrapperElement) {
      const wrapper = this.editor.getWrapperElement();
      if (wrapper) {
        wrapper.style.fontSize = fontSizeStr;
        
        // Juga terapkan ke CodeMirror element
        const cmElement = wrapper.querySelector('.CodeMirror');
        if (cmElement) {
          cmElement.style.fontSize = fontSizeStr;
        }
        
        // Terapkan ke textarea/input CodeMirror
        const cmLines = wrapper.querySelector('.CodeMirror-lines');
        if (cmLines) {
          cmLines.style.fontSize = fontSizeStr;
        }
      }
    }
    
    // Refresh editor untuk menerapkan perubahan
    if (this.editor) {
      this.editor.refresh();
    }
    
    return this;
  }

  /**
   * Mengatur read-only mode
   * @param {boolean} readOnly - True untuk read-only
   */
  setReadOnly(readOnly) {
    this.editor.setOption('readOnly', readOnly);
    return this;
  }

  /**
   * Focus ke editor
   */
  focus() {
    this.editor.focus();
    return this;
  }

  /**
   * Refresh editor (untuk resize atau perubahan layout)
   */
  refresh() {
    this.editor.refresh();
    return this;
  }

  /**
   * Mengatur ukuran editor
   * @param {string|number} width - Lebar editor (null untuk auto)
   * @param {string|number} height - Tinggi editor
   */
  setSize(width, height) {
    this.editor.setSize(width, height);
    return this;
  }

  /**
   * Mengatur tinggi editor
   * @param {string|number} height - Tinggi editor (contoh: '700px', 700)
   */
  setHeight(height) {
    this.editor.setSize(null, height);
    return this;
  }

  /**
   * Mengatur lebar editor
   * @param {string|number} width - Lebar editor (contoh: '100%', 800)
   */
  setWidth(width) {
    this.editor.setSize(width, null);
    return this;
  }

  /**
   * Mendapatkan posisi cursor
   * @returns {Object} {line, ch}
   */
  getCursor() {
    return this.editor.getCursor();
  }

  /**
   * Mengatur posisi cursor
   * @param {number} line - Nomor baris
   * @param {number} ch - Posisi karakter
   */
  setCursor(line, ch) {
    this.editor.setCursor(line, ch);
    return this;
  }

  /**
   * Mendapatkan text yang ter-select
   * @returns {string} Text yang ter-select
   */
  getSelection() {
    return this.editor.getSelection();
  }

  /**
   * Mengganti text yang ter-select
   * @param {string} text - Text pengganti
   */
  replaceSelection(text) {
    this.editor.replaceSelection(text);
    return this;
  }

  /**
   * Menambahkan event listener
   * @param {string} event - Nama event (change, focus, blur, dll)
   * @param {Function} callback - Callback function
   */
  on(event, callback) {
    this.editor.on(event, callback);
    return this;
  }

  /**
   * Menghapus event listener
   * @param {string} event - Nama event
   * @param {Function} callback - Callback function
   */
  off(event, callback) {
    this.editor.off(event, callback);
    return this;
  }

  /**
   * Undo
   */
  undo() {
    this.editor.undo();
    return this;
  }

  /**
   * Redo
   */
  redo() {
    this.editor.redo();
    return this;
  }

  /**
   * Mendapatkan jumlah baris
   * @returns {number} Jumlah baris
   */
  lineCount() {
    return this.editor.lineCount();
  }

  /**
   * Mendapatkan text di baris tertentu
   * @param {number} line - Nomor baris
   * @returns {string} Text di baris tersebut
   */
  getLine(line) {
    return this.editor.getLine(line);
  }

  /**
   * Mengatur text di baris tertentu
   * @param {number} line - Nomor baris
   * @param {string} text - Text baru
   */
  setLine(line, text) {
    this.editor.setLine(line, text);
    return this;
  }

  /**
   * Menghapus baris
   * @param {number} line - Nomor baris yang akan dihapus
   */
  removeLine(line) {
    this.editor.removeLine(line);
    return this;
  }

  /**
   * Menyimpan konten ke textarea (jika menggunakan fromTextArea)
   */
  save() {
    if (this.element.tagName === 'TEXTAREA') {
      this.editor.save();
    }
    return this;
  }

  /**
   * Menghancurkan instance editor
   */
  destroy() {
    if (this.editor) {
      this.editor.toTextArea();
      this.editor = null;
    }
    return this;
  }

  /**
   * Static method untuk membuat instance baru
   * @param {string|HTMLElement} element - Element atau ID element
   * @param {Object} options - Konfigurasi
   * @returns {NexaCmirror} Instance NexaCmirror
   */
  static create(element, options) {
    return new NexaCmirror(element, options);
  }

  /**
   * Static method untuk memuat semua dependencies CodeMirror (CSS & JS)
   * Menggunakan NexaStylesheet dan NexaScript untuk dynamic loading
   * Dengan caching untuk menghindari load berulang
   * @returns {Promise<void>}
   */
  static async loadDependencies() {
    // Cek apakah CodeMirror sudah tersedia (sudah di-load sebelumnya)
    if (typeof CodeMirror !== 'undefined' && NexaCmirror._dependenciesLoaded) {
      // Dependencies sudah di-load, langsung return
      return Promise.resolve();
    }

    // Jika sedang dalam proses loading, return promise yang sama (hindari multiple loads)
    if (NexaCmirror._loadingPromise) {
      return NexaCmirror._loadingPromise;
    }

    // Pastikan NXUI tersedia
    if (typeof NXUI === 'undefined') {
      throw new Error('NXUI is required. Please ensure NXUI is loaded before calling NexaCmirror.loadDependencies()');
    }

    // Buat promise untuk loading
    NexaCmirror._loadingPromise = (async () => {

    // Daftar CSS dari local assets
    // Note: Theme default sudah termasuk dalam codemirror.css, tidak perlu load terpisah
    const cssFiles = [
      'codemirror/css/codemirror.css',
      'codemirror/css/theme/monokai.css',
      'codemirror/css/addon/hint/show-hint.css'
    ];

    // Core CodeMirror harus di-load terlebih dahulu
    const coreJsFile = 'codemirror/js/codemirror.js';
    
    // Daftar JavaScript lainnya (harus di-load setelah codemirror.js)
    // Note: json.js adalah bagian dari javascript.js, jadi tidak perlu di-load terpisah
    const jsFiles = [
      'codemirror/js/mode/xml/xml.js',
      'codemirror/js/mode/css/css.js',
      'codemirror/js/mode/javascript/javascript.js',
      'codemirror/js/mode/htmlmixed/htmlmixed.js',
      'codemirror/js/mode/sql/sql.js',
      // json.js tidak perlu di-load terpisah karena sudah termasuk dalam javascript.js
      // Mode JSON menggunakan { name: 'javascript', json: true }
      'codemirror/js/addon/edit/closetag.js',
      'codemirror/js/addon/edit/closebrackets.js',
      'codemirror/js/addon/hint/show-hint.js',
      'codemirror/js/addon/hint/xml-hint.js',
      'codemirror/js/addon/hint/html-hint.js',
      'codemirror/js/addon/hint/javascript-hint.js',
      'codemirror/js/addon/hint/css-hint.js',
      'codemirror/js/addon/hint/sql-hint.js'
    ];

    try {
      // Load CSS files terlebih dahulu (path lokal, jadi gunakan false)
      await NXUI.NexaStylesheet.Dom(cssFiles, false);
      
      // Load custom CSS untuk NexaCmirror
      await NXUI.NexaStylesheet.Dom(['Cmirror.css']);

      // Load core CodeMirror terlebih dahulu (WAJIB sebelum script lainnya)
      // Gunakan identifier unik berdasarkan full path (replace / dengan - untuk identifier yang valid)
      const coreIdentifier = coreJsFile.replace(/[^a-zA-Z0-9]/g, '-');
      const coreScript = new NXUI.NexaScript(coreJsFile, false, coreIdentifier);
      await coreScript.loadAsScript();

      // Pastikan CodeMirror sudah tersedia sebelum load script lainnya
      if (typeof CodeMirror === 'undefined') {
        throw new Error('CodeMirror core failed to load. Please check if the file exists in the assets folder.');
      }

      // Load JavaScript files lainnya secara sequential untuk memastikan dependencies terpenuhi
      // Gunakan sequential loading untuk menghindari race condition
      // Gunakan identifier unik untuk setiap script (replace karakter khusus dengan -)
      for (const jsFile of jsFiles) {
        try {
          // Buat identifier unik dari path (replace karakter khusus dengan -)
          const identifier = jsFile.replace(/[^a-zA-Z0-9]/g, '-');
          const script = new NXUI.NexaScript(jsFile, false, identifier);
          
          // Cek apakah script sudah di-load sebelumnya
          if (!script.isLoaded()) {
            await script.loadAsScript();
            // Khusus untuk SQL mode script, verifikasi bahwa mode sudah ter-register
            if (jsFile.includes('mode/sql/sql.js')) {
              // Tunggu sebentar untuk memastikan mode ter-register
              await new Promise(resolve => setTimeout(resolve, 100));
              // Verifikasi bahwa mode SQL ter-register
              if (CodeMirror.modes && CodeMirror.modes.sql) {
                console.log('✅ SQL mode registered successfully');
              } else {
                console.warn('⚠️ SQL mode not registered, trying to register manually');
                // Coba register mode SQL secara manual jika belum ter-register
                if (typeof CodeMirror !== 'undefined' && CodeMirror.defineMode) {
                  // Mode SQL seharusnya sudah ter-register oleh file sql.js
                  console.log('CodeMirror.defineMode available, SQL mode should be registered');
                }
              }
            }
          }
        } catch (error) {
          console.warn(`Failed to load script: ${jsFile}`, error);
          // Continue loading other scripts even if one fails
          // Some scripts might be optional or already loaded
        }
      }

      // Verifikasi final
      if (typeof CodeMirror === 'undefined') {
        throw new Error('CodeMirror failed to load. Please check if the files exist in the assets folder.');
      }
      
      // Pastikan mode SQL sudah ter-load (jika diperlukan)
      // Tunggu sebentar untuk memastikan semua mode script sudah ter-register
      await new Promise(resolve => setTimeout(resolve, 50));
      
      // Mark dependencies sebagai sudah di-load
      NexaCmirror._dependenciesLoaded = true;
      
      // Clear loading promise setelah selesai
      NexaCmirror._loadingPromise = null;
    } catch (error) {
      // Clear loading promise jika error
      NexaCmirror._loadingPromise = null;
      
      console.error('Error loading CodeMirror dependencies:', error);
      throw error;
    }
    })();

    return NexaCmirror._loadingPromise;
  }

  /**
   * Reset cache dependencies (untuk testing atau force reload)
   * @static
   */
  static resetDependenciesCache() {
    NexaCmirror._dependenciesLoaded = false;
    NexaCmirror._loadingPromise = null;
  }
}

