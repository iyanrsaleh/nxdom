class NexaFilter {
  /**
   * Parse filter string into array of filters with arguments
   */
  parseFilters(filterString) {
    const filters = [];
    const parts = filterString.split("|");

    for (const part of parts) {
      const filter = part.trim();
      if (!filter) continue;

      // Parse filter name and arguments
      if (filter.includes(":")) {
        const [name, argsStr] = filter.split(":", 2);
        // Improved argument parsing - handle empty args and trim each arg
        const args = argsStr ? argsStr.split(",").map((arg) => arg.trim()) : [];
        filters.push({ name: name.trim(), args });
      } else {
        filters.push({ name: filter, args: [] });
      }
    }
    return filters;
  }

  /**
   * Apply filter to value
   */
  Filter(value, filterName, args = []) {
    switch (filterName.toLowerCase()) {
      case "upper":
        return String(value || "").toUpperCase();
      case "lower":
        return String(value || "").toLowerCase();
      case "truncate":
        const length = args[0] ? parseInt(args[0]) : 100;
        const stringValue = String(value || "");
        if (length <= 0) return stringValue;
        return stringValue.length > length
          ? stringValue.substring(0, length) + "..."
          : stringValue;
      case "more":
        const moreLength = args[0] ? parseInt(args[0]) : 100;
        const moreStringValue = String(value || "");
        if (moreLength <= 0) return moreStringValue;
        return moreStringValue.length > moreLength
          ? moreStringValue.substring(0, moreLength) + "..."
          : moreStringValue;
      case "date":
        const format = args[0] || "YYYY-MM-DD";
        return this.formatDate(new Date(value), format);
      case "time_ago":
        return this.timeAgo(new Date(value).getTime());
      case "number_format":
        const decimals = args[0] ? parseInt(args[0]) : 0;
        return new Intl.NumberFormat("id-ID", {
          minimumFractionDigits: decimals,
          maximumFractionDigits: decimals,
        }).format(value);
      case "currency":
        const currency = args[0] || "IDR";
        return new Intl.NumberFormat("id-ID", {
          style: "currency",
          currency: currency,
        }).format(value);
      case "capitalize":
        const capValue = String(value || "");
        return capValue.charAt(0).toUpperCase() + capValue.slice(1);
      case "slug":
        return this.createSlug(String(value || ""));
      case "nl2br":
        return String(value || "").replace(/\n/g, "<br>");
      case "strip_tags":
        return String(value || "").replace(/<[^>]*>/g, "");
      case "round":
        const precision = args[0] ? parseInt(args[0]) : 0;
        const numValue = Number(value) || 0;
        return numValue.toFixed(precision);
      case "percent":
        const percentDecimals = args[0] ? parseInt(args[0]) : 0;
        const percentValue = Number(value) || 0;
        return `${percentValue.toFixed(percentDecimals)}%`;
      case "filesize":
        return this.formatFileSize(Number(value));
      case "json_encode":
        try {
          return JSON.stringify(value);
        } catch (error) {
          console.error("JSON encode error:", error);
          return String(value || "");
        }
      case "json_decode":
        try {
          return JSON.parse(value);
        } catch (error) {
          console.error("JSON decode error:", error);
          return String(value || "");
        }
      case "escape":
        return String(value || "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#039;");
      case "html_decode":
        return String(value || "")
          .replace(/&amp;/g, "&")
          .replace(/&lt;/g, "<")
          .replace(/&gt;/g, ">")
          .replace(/&quot;/g, '"')
          .replace(/&#039;/g, "'")
          .replace(/&#x27;/g, "'")
          .replace(/&#x2F;/g, "/")
          .replace(/&#x60;/g, "`")
          .replace(/&#x3D;/g, "=");
      case "url_encode":
        return encodeURIComponent(String(value || ""));
      case "base64_encode":
      case "encode":
        return btoa(String(value || ""));
      case "base64_decode":
      case "decode":
        try {
          return atob(String(value || ""));
        } catch (error) {
          console.error("Base64 decode error:", error);
          return String(value || "");
        }
      case "md5":
        return this.md5(String(value || ""));
      case "phone":
        return this.formatPhoneNumber(String(value || ""));
      case "mask":
        const maskChar = args[0] || "*";
        const start = args[1] ? parseInt(args[1]) : 4;
        const end = args[2] ? parseInt(args[2]) : 4;
        return this.maskString(String(value || ""), maskChar, start, end);
      case "trim":
        return String(value || "").trim();
      case "ellipsis":
      case "truncate_auto":
        // Filter untuk truncate otomatis sesuai lebar kolom dengan CSS
        // Mengembalikan wrapper dengan class untuk CSS ellipsis
        const ellipsisLines = args[0] ? parseInt(args[0]) : 2;
        const ellipsisValue = String(value || "");
        const uniqueId = `ellipsis-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        // Return HTML dengan class untuk CSS truncate + data attribute untuk read more
        return `<span class="nexa-ellipsis" data-ellipsis-id="${uniqueId}" data-full-text="${ellipsisValue.replace(/"/g, '&quot;').replace(/'/g, '&#39;')}" data-lines="${ellipsisLines}" style="display: -webkit-box; -webkit-line-clamp: ${ellipsisLines}; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; word-wrap: break-word;">${ellipsisValue}</span>`;
      case "replace":
        const search = args[0] || "";
        const replace = args[1] || "";
        return String(value || "").replace(new RegExp(search, "g"), replace);
      case "decimal_to_rupiah":
        const rupiahValue = Number(value) || 0;
        return new Intl.NumberFormat("id-ID", {
          style: "currency",
          currency: "IDR",
          minimumFractionDigits: 0,
          maximumFractionDigits: 0,
        }).format(rupiahValue);
      case "join":
        const separator = args[0] || ", ";
        return Array.isArray(value)
          ? value.join(separator)
          : String(value || "");
      case "split":
        const delimiter = args[0] || ",";
        return String(value || "").split(delimiter);
      case "indonesian_date":
        return this.formatIndonesianDate(new Date(value));
      case "indonesian_date_full":
      case "indonesian_date_with_day":
        return this.formatIndonesianDateWithDay(new Date(value));
      case "age":
        return this.calculateAge(new Date(value));
      case "relative_time":
        return this.getRelativeTime(new Date(value).getTime());
      case "timeago":
        return this.timeAgo(new Date(value).getTime());
      case "dayname":
        return this.getDayName(new Date(value));
      case "badge":
        return this.createBadge(value);
      case "yesno":
        const yesText = args[0] || "Yes";
        const noText = args[1] || "No";
        return value ? yesText : noText;
      case "default":
        const defaultValue = args[0] || "";
        return value || defaultValue;
      case "first":
        return Array.isArray(value) ? value[0] : value;
      case "last":
        return Array.isArray(value) ? value[value.length - 1] : value;
      case "length":
        return Array.isArray(value) ? value.length : String(value || "").length;
      case "number":
        return this.formatNumber(value);
      case "percentage":
        return this.formatPercentage(value);
      case "icon":
      case "material_icon":
        const iconName = args[0] || value || "circle";
        const iconSize = args[1] || "24px";
        const iconClass = args[2] || "material-symbols-outlined";
        const iconColor = args[3] || "";
        const style = iconColor ? `style="font-size: ${iconSize}; color: ${iconColor};"` : `style="font-size: ${iconSize};"`;
        return `<span class="${iconClass}" ${style}>${iconName}</span>`;
      case "icon_inline":
        const iconNameInline = args[0] || value || "circle";
        const iconClassInline = args[1] || "material-symbols-outlined";
        return `<span class="${iconClassInline}">${iconNameInline}</span>`;
      default:
        return value;
    }
  }

  /**
   * NEW: Process ternary expressions in template
   * Syntax: {field === 'value' ? 'true_result' : 'false_result'}
   * @param {string} template - Template string
   * @param {Object} data - Data object
   * @returns {string} Processed template
   */
  processTernary(template, data) {
    // Pattern untuk mendeteksi ternary expressions
    // {field operator 'value' ? 'true_result' : 'false_result'}
    const ternaryPattern =
      /{([^}]+\s*(?:===|!==|==|!=|>=|<=|>|<)\s*[^}]+\s*\?\s*[^}]+\s*:\s*[^}]+)}/g;

    return template.replace(ternaryPattern, (match, expression) => {
      try {
        return this._processTernaryExpression(expression, data);
      } catch (error) {
        console.warn("Error processing ternary expression:", error, match);
        return "";
      }
    });
  }

  /**
   * NEW: Process individual ternary expression
   * @private
   */
  _processTernaryExpression(expression, data) {
    // Parse ternary expression: field operator value ? true_result : false_result
    const ternaryRegex =
      /^(.+?)\s*(===|!==|==|!=|>=|<=|>|<)\s*['"]?([^'"?]+)['"]?\s*\?\s*['"]?([^'":]*)['"]?\s*:\s*['"]?([^'"]*)['"]?$/;

    const match = expression.trim().match(ternaryRegex);
    if (!match) {
      console.warn("Invalid ternary syntax:", expression);
      return "";
    }

    const [, fieldPath, operator, compareValue, trueResult, falseResult] =
      match;

    // Get value dari data
    const fieldValue = this._getNestedValue(data, fieldPath.trim());

    // Clean results dari quotes
    const cleanTrueResult = trueResult.replace(/^['"]|['"]$/g, "");
    const cleanFalseResult = falseResult.replace(/^['"]|['"]$/g, "");

    // Evaluate condition
    const result = this._evaluateCondition(
      fieldValue,
      operator,
      compareValue.trim()
    );

    return result ? cleanTrueResult : cleanFalseResult;
  }

  /**
   * NEW: Evaluate condition untuk ternary expression
   * @private
   */
  _evaluateCondition(leftValue, operator, rightValue) {
    // Convert values untuk comparison
    const left = String(leftValue || "");
    const right = String(rightValue || "").replace(/^['"]|['"]$/g, ""); // Remove quotes

    switch (operator) {
      case "===":
        return left === right;
      case "!==":
        return left !== right;
      case "==":
        return left == right;
      case "!=":
        return left != right;
      case ">":
        return Number(left) > Number(right);
      case "<":
        return Number(left) < Number(right);
      case ">=":
        return Number(left) >= Number(right);
      case "<=":
        return Number(left) <= Number(right);
      default:
        return false;
    }
  }

  /**
   * NEW: Main template processor - process both ternary and regular placeholders
   * @param {string} template - Template string
   * @param {Object} data - Data object
   * @returns {string} Processed template
   */
  processTemplate(template, data) {
    // First process ternary expressions
    let result = this.processTernary(template, data);

    // Then process switch statements
    result = this.processSwitch(result, data);

    // Finally process regular placeholders dengan filters
    const placeholderPattern = /{([^}|]+)(?:\|([^}]+))?}/g;

    result = result.replace(placeholderPattern, (match, propPath, filters) => {
      try {
        // Skip jika sudah diproses sebagai ternary atau switch
        if (
          match.includes("?") ||
          match.includes("===") ||
          match.includes("!==") ||
          match.includes("==") ||
          match.includes("!=") ||
          match.includes(">=") ||
          match.includes("<=") ||
          match.includes(">") ||
          match.includes("<")
        ) {
          return match;
        }

        // Get value dari data
        let value = this._getNestedValue(data, propPath.trim());

        // Apply filters jika ada
        if (filters) {
          try {
            const filtersList = this.parseFilters(filters);
            filtersList.forEach((filterItem) => {
              value = this.Filter(value, filterItem.name, filterItem.args);
            });
          } catch (filterError) {
            console.warn("Error applying filters:", filterError, filters);
          }
        }

        return value !== undefined && value !== null ? value : "";
      } catch (error) {
        console.error("Error processing placeholder:", error, match);
        return "";
      }
    });

    return result;
  }

  /**
   * Helper methods
   */
  createSlug(text) {
    if (!text) return "";
    return String(text)
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/(^-|-$)+/g, "");
  }

  formatFileSize(bytes) {
    const units = ["B", "KB", "MB", "GB", "TB"];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) {
      bytes /= 1024;
      i++;
    }
    return `${bytes.toFixed(2)} ${units[i]}`;
  }

  formatPhoneNumber(number) {
    number = number.replace(/\D/g, "");
    if (number.startsWith("62") || number.startsWith("0")) {
      number = number.replace(/^62|^0/, "+62 ");
      return number.replace(/(\d{4})/g, "$1 ").trim();
    }
    return number;
  }

  maskString(str, maskChar = "*", start = 4, end = 4) {
    if (str.length <= start + end) return str;
    const masked = maskChar.repeat(str.length - start - end);
    return str.substring(0, start) + masked + str.substring(str.length - end);
  }

  timeAgo(timestamp) {
    const diff = Date.now() - timestamp;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (diff < 60000) return "baru saja";
    if (minutes < 60) return `${minutes} menit lalu`;
    if (hours < 24) return `${hours} jam lalu`;
    return `${days} hari lalu`;
  }

  calculateAge(birthDate) {
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();

    if (
      monthDiff < 0 ||
      (monthDiff === 0 && today.getDate() < birth.getDate())
    ) {
      age--;
    }

    return age;
  }

  formatIndonesianDate(date) {
    const months = [
      "Januari",
      "Februari",
      "Maret",
      "April",
      "Mei",
      "Juni",
      "Juli",
      "Agustus",
      "September",
      "Oktober",
      "November",
      "Desember",
    ];
    return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
  }

  formatIndonesianDateWithDay(date) {
    const days = [
      "Minggu",
      "Senin",
      "Selasa",
      "Rabu",
      "Kamis",
      "Jumat",
      "Sabtu",
    ];
    const months = [
      "Januari",
      "Februari",
      "Maret",
      "April",
      "Mei",
      "Juni",
      "Juli",
      "Agustus",
      "September",
      "Oktober",
      "November",
      "Desember",
    ];
    const dayName = days[date.getDay()];
    return `${dayName}, ${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
  }

  getRelativeTime(timestamp) {
    const diff = Date.now() - timestamp;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    const months = Math.floor(diff / 2592000000);
    const years = Math.floor(diff / 31536000000);

    if (diff < 60000) return "baru saja";
    if (minutes < 60) return `${minutes} menit yang lalu`;
    if (hours < 24) return `${hours} jam yang lalu`;
    if (days < 30) return `${days} hari yang lalu`;
    if (months < 12) return `${months} bulan yang lalu`;
    return `${years} tahun yang lalu`;
  }

  formatDate(date, format) {
    const pad = (num) => String(num).padStart(2, "0");

    const formats = {
      YYYY: date.getFullYear(),
      MM: pad(date.getMonth() + 1),
      DD: pad(date.getDate()),
      HH: pad(date.getHours()),
      mm: pad(date.getMinutes()),
      ss: pad(date.getSeconds()),
    };

    return format.replace(/YYYY|MM|DD|HH|mm|ss/g, (match) => formats[match]);
  }

  // Simple MD5 implementation (for demo purposes - in production use a proper crypto library)
  md5(string) {
    return string; // Placeholder - use proper MD5 implementation in production
  }

  // Test method untuk verify filters (development only)
  testFilters() {
    const testValue =
      "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.";

    console.log("Testing NexaFilter filters:");
    console.log("Original:", testValue);
    console.log("truncate:50:", this.Filter(testValue, "truncate", ["50"]));
    console.log("more:30:", this.Filter(testValue, "more", ["30"]));
    console.log("upper:", this.Filter("hello world", "upper", []));
    console.log("capitalize:", this.Filter("hello world", "capitalize", []));

    // Test with null/undefined
    console.log("truncate null:", this.Filter(null, "truncate", ["10"]));
    console.log("more undefined:", this.Filter(undefined, "more", ["10"]));

    return "All filters tested - check console for results";
  }

  /**
   * Process switch statements in template
   * @param {string} template - Template string
   * @param {Object} data - Data object
   * @returns {string} Processed template
   */
  processSwitch(template, data) {
    // Pattern untuk mendeteksi switch blocks: {switch variable} ... {/switch}
    const switchPattern = /{switch\s+([^}]+)}(.*?){\/switch}/gs;

    return template.replace(switchPattern, (match, variable, content) => {
      try {
        // Get value dari data
        const switchValue = this._getNestedValue(data, variable.trim());

        // Parse case statements dalam switch block
        const result = this._processSwitchContent(content, switchValue, data);

        return result;
      } catch (error) {
        console.warn("Error processing switch statement:", error);
        return "";
      }
    });
  }

  /**
   * Process content dalam switch block
   * @private
   */
  _processSwitchContent(content, switchValue, data) {
    // Clean up content dari extra whitespace
    content = content.trim();

    // Pattern yang lebih robust untuk case statements dengan penutup {/case}
    // Menangani: {case 'value'} content {/case}
    const casePattern = /{case\s+['"]([^'"]+)['"]}\s*(.*?)\s*{\/case}/gs;

    // Pattern untuk default case: {default} content {/default}
    const defaultPattern = /{default}\s*(.*?)\s*{\/default}/gs;

    let result = "";
    let caseMatched = false;

    // Reset regex untuk multiple matches
    casePattern.lastIndex = 0;

    // Process semua case statements
    let caseMatch;
    while ((caseMatch = casePattern.exec(content)) !== null) {
      const [fullMatch, caseValue, caseContent] = caseMatch;

      if (String(switchValue) === String(caseValue)) {
        result = caseContent.trim();
        caseMatched = true;

        // Process nested placeholders dalam case content
        result = this._processNestedPlaceholders(result, data);
        break;
      }
    }

    // Jika tidak ada case yang match, cari default
    if (!caseMatched) {
      defaultPattern.lastIndex = 0;
      const defaultMatch = defaultPattern.exec(content);
      if (defaultMatch) {
        result = defaultMatch[1].trim();

        // Process nested placeholders dalam default content
        result = this._processNestedPlaceholders(result, data);
      }
    }

    return result;
  }

  /**
   * Process nested placeholders in content
   * @private
   */
  _processNestedPlaceholders(content, data) {
    // First process ternary expressions dalam nested content
    content = this.processTernary(content, data);

    // Pattern untuk placeholders: {key|filter}
    const placeholderPattern = /{([^}|]+)(?:\|([^}]+))?}/g;

    return content.replace(placeholderPattern, (match, propPath, filters) => {
      try {
        // Skip jika sudah diproses sebagai ternary
        if (
          match.includes("?") ||
          match.includes("===") ||
          match.includes("!==") ||
          match.includes("==") ||
          match.includes("!=") ||
          match.includes(">=") ||
          match.includes("<=") ||
          match.includes(">") ||
          match.includes("<")
        ) {
          return match;
        }

        // Get value dari data
        let value = this._getNestedValue(data, propPath.trim());

        // Apply filters jika ada
        if (filters) {
          try {
            const filtersList = this.parseFilters(filters);
            filtersList.forEach((filterItem) => {
              value = this.Filter(value, filterItem.name, filterItem.args);
            });
          } catch (filterError) {
            console.warn(
              "Error applying nested filters:",
              filterError,
              filters
            );
          }
        }

        return value !== undefined && value !== null ? value : "";
      } catch (error) {
        console.error("Error processing nested placeholder:", error, match);
        return "";
      }
    });
  }

  /**
   * Get nested value from object using dot notation
   * @private
   */
  _getNestedValue(obj, path) {
    if (!path) return undefined;

    const parts = path.split(".");
    let value = obj;

    for (const part of parts) {
      if (value === undefined || value === null) return undefined;
      value = value[part];
    }

    return value;
  }

  // Helper methods untuk filter baru
  getDayName(date) {
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    return days[date.getDay()];
  }

  createBadge(value) {
    if (!value) return '';
    const badgeClass = this.getBadgeClass(value);
    return `<span class="badge ${badgeClass}">${value}</span>`;
  }

  getBadgeClass(status) {
    const statusMap = {
      'active': 'badge-success',
      'inactive': 'badge-secondary',
      'pending': 'badge-warning',
      'completed': 'badge-success',
      'cancelled': 'badge-danger',
      'draft': 'badge-secondary',
      'published': 'badge-success',
      'archived': 'badge-dark'
    };
    
    const statusLower = String(status).toLowerCase();
    return statusMap[statusLower] || 'badge-primary';
  }

  formatNumber(value) {
    const numValue = Number(value) || 0;
    return new Intl.NumberFormat('id-ID').format(numValue);
  }

  formatPercentage(value) {
    const numValue = Number(value) || 0;
    return `${numValue}%`;
  }
}

export default NexaFilter;
