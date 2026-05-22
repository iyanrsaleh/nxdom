class Nexautility {
  /**
   * Daftar utility classes dan properti CSS-nya
   */
  static utilities = {
    tx: "text-align",
    position: "position",
    pos: "position",
    top: "top",
    bottom: "bottom",
    left: "left",
    right: "right",
    w: "width",
    h: "height",
    "min-w": "min-width",
    "min-h": "min-height",
    "max-w": "max-width",
    "max-h": "max-height",

    // Margin
    m: "margin",
    mt: "margin-top",
    mb: "margin-bottom",
    ml: "margin-left",
    mr: "margin-right",
    mx: "margin-left margin-right",
    my: "margin-top margin-bottom",

    // Padding
    p: "padding",
    pt: "padding-top",
    pb: "padding-bottom",
    pl: "padding-left",
    pr: "padding-right",
    px: "padding-left padding-right",
    py: "padding-top padding-bottom",

    // Font
    fs: "font-size",
    fw: "font-weight",
    lh: "line-height",

    // Border
    br: "border-radius",
    bw: "border-width",

    // Opacity & Z-index
    op: "opacity",
    z: "z-index",

    // Background & Text color
    bg: "background-color",
    text: "color",

    // Display
    hidden: "display",
    block: "display",
    inline: "display",
    flex: "display",

    // Font Weight
    bold: "font-weight",
    normal: "font-weight",
    thin: "font-weight",
    light: "font-weight",
    medium: "font-weight",
    semibold: "font-weight",
    extrabold: "font-weight",

    // Float
    "pull-right": "float",
    "pull-left": "float",
  };

  /**
   * Parse class utility patterns dan konversi ke inline style
   * @param {string} content Konten yang akan diparsing
   * @return {string} Hasil parsing
   */
  static transform(content) {
    // Process NexaDom elements (hide them)
    content = this.processNexaDomElements(content);

    // Pattern untuk mencocokkan class warna hex
    content = content.replace(
      /class=(['"])(b?#[0-9a-f]{3,6})\1/gi,
      (match, quote, hexClass) => {
        const colorMatch = hexClass.match(/^(b?)#([0-9a-f]{3,6})$/i);
        if (colorMatch) {
          const isBackground = colorMatch[1] === "b";
          const hexColor = "#" + colorMatch[2];
          const property = isBackground ? "background-color" : "color";
          return `style="${property}:${hexColor}"`;
        }
        return match;
      }
    );

    // Pattern untuk mencocokkan seluruh tag HTML dengan atribut class
    const pattern = /<([a-zA-Z0-9]+)([^>]*?class=(['"])(.*?)\3[^>]*?)>/gi;

    return content.replace(
      pattern,
      (match, tag, fullAttributes, quote, classValue) => {
        // Ekstrak atribut kecuali class
        const attributes = fullAttributes.replace(/\bclass=(['"])(.*?)\1/, "");

        const classes = classValue.split(" ");
        const styles = {};
        const remainingClasses = [];

        for (const cls of classes) {
          const className = cls.trim();
          if (!className) continue;

          let matched = false;

          // Border width, style, color (bw-1-solid-#ccc atau bw-1-#ccc)
          let bwMatch = className.match(
            /^bw-([0-9]+)(?:-([a-z]+))?(?:-(#[0-9a-fA-F]{3,6}))?$/
          );
          if (bwMatch) {
            const bw = bwMatch[1] + "px";
            const bs = bwMatch[2] && bwMatch[2] ? bwMatch[2] : "solid";
            const bc = bwMatch[3] && bwMatch[3] ? bwMatch[3] : null;
            styles["border-width"] = bw;
            styles["border-style"] = bs;
            if (bc) styles["border-color"] = bc;
            matched = true;
            continue;
          }

          // Border-top
          let btwMatch = className.match(
            /^btw-([0-9]+)(?:-([a-z]+))?(?:-(#[0-9a-fA-F]{3,6}))?$/
          );
          if (btwMatch) {
            const bw = btwMatch[1] + "px";
            const bs = btwMatch[2] && btwMatch[2] ? btwMatch[2] : "solid";
            const bc = btwMatch[3] && btwMatch[3] ? btwMatch[3] : null;
            styles["border-top-width"] = bw;
            styles["border-top-style"] = bs;
            if (bc) styles["border-top-color"] = bc;
            matched = true;
            continue;
          }

          // Border-bottom
          let bbwMatch = className.match(
            /^bbw-([0-9]+)(?:-([a-z]+))?(?:-(#[0-9a-fA-F]{3,6}))?$/
          );
          if (bbwMatch) {
            const bw = bbwMatch[1] + "px";
            const bs = bbwMatch[2] && bbwMatch[2] ? bbwMatch[2] : "solid";
            const bc = bbwMatch[3] && bbwMatch[3] ? bbwMatch[3] : null;
            styles["border-bottom-width"] = bw;
            styles["border-bottom-style"] = bs;
            if (bc) styles["border-bottom-color"] = bc;
            matched = true;
            continue;
          }

          // Border-left
          let blwMatch = className.match(
            /^blw-([0-9]+)(?:-([a-z]+))?(?:-(#[0-9a-fA-F]{3,6}))?$/
          );
          if (blwMatch) {
            const bw = blwMatch[1] + "px";
            const bs = blwMatch[2] && blwMatch[2] ? blwMatch[2] : "solid";
            const bc = blwMatch[3] && blwMatch[3] ? blwMatch[3] : null;
            styles["border-left-width"] = bw;
            styles["border-left-style"] = bs;
            if (bc) styles["border-left-color"] = bc;
            matched = true;
            continue;
          }

          // Border-right
          let brwMatch = className.match(
            /^brw-([0-9]+)(?:-([a-z]+))?(?:-(#[0-9a-fA-F]{3,6}))?$/
          );
          if (brwMatch) {
            const bw = brwMatch[1] + "px";
            const bs = brwMatch[2] && brwMatch[2] ? brwMatch[2] : "solid";
            const bc = brwMatch[3] && brwMatch[3] ? brwMatch[3] : null;
            styles["border-right-width"] = bw;
            styles["border-right-style"] = bs;
            if (bc) styles["border-right-color"] = bc;
            matched = true;
            continue;
          }

          // Check for custom color classes (e.g., #ff0000, b#00ff00)
          const colorMatch = className.match(/^(b?)#([0-9a-f]{3,6})$/i);
          if (colorMatch) {
            const isBackground = colorMatch[1] === "b";
            const hexColor = "#" + colorMatch[2];
            const property = isBackground ? "background-color" : "color";
            styles[property] = hexColor;
            matched = true;
            continue;
          }

          // Check for special display classes
          if (["hidden", "block", "inline", "flex"].includes(className)) {
            const displayValue = className === "hidden" ? "none" : className;
            styles["display"] = displayValue;
            matched = true;
            continue;
          }

          // Check for special float classes
          if (className === "pull-right") {
            styles["float"] = "right";
            matched = true;
            continue;
          }
          if (className === "pull-left") {
            styles["float"] = "left";
            matched = true;
            continue;
          }

          // Check for special font weight classes
          if (
            [
              "bold",
              "normal",
              "thin",
              "light",
              "medium",
              "semibold",
              "extrabold",
            ].includes(className)
          ) {
            const fontWeightMap = {
              thin: "100",
              light: "300",
              normal: "400",
              medium: "500",
              semibold: "600",
              bold: "700",
              extrabold: "800",
            };
            styles["font-weight"] = fontWeightMap[className];
            matched = true;
            continue;
          }

          if (matched) continue;

          // Check utility classes
          for (const [prefix, properties] of Object.entries(this.utilities)) {
            const regex = new RegExp(
              `^${prefix.replace(
                /[.*+?^${}()|[\]\\]/g,
                "\\$&"
              )}-([a-zA-Z0-9#\\.]+)(?:-(.*?))?$`
            );
            const matches = className.match(regex);

            if (matches) {
              let value = matches[1];

              if (properties === "text-align") {
                const alignValues = {
                  center: "center",
                  left: "left",
                  right: "right",
                };
                value = alignValues[value] || value;
              } else {
                const unit = matches[2] || this.getDefaultUnit(properties);
                if (!isNaN(value)) {
                  value += unit;
                }
              }

              const props = properties.split(" ");
              for (const prop of props) {
                styles[prop] = value;
              }
              matched = true;
              break;
            }
          }

          if (!matched) {
            remainingClasses.push(className);
          }
        }

        // Build output tag
        let output = `<${tag}`;

        if (remainingClasses.length > 0) {
          output += ` class=${quote}${remainingClasses.join(" ")}${quote}`;
        }

        if (Object.keys(styles).length > 0) {
          let styleString = "";
          for (const [prop, value] of Object.entries(styles)) {
            styleString += `${prop}:${value};`;
          }

          if (attributes.includes("style=")) {
            const newAttributes = attributes.replace(
              /style=(['"])(.*?)\1/,
              `style="$2${styleString}"`
            );
            output += newAttributes;
          } else {
            output += ` style="${styleString}"${attributes}`;
          }
        } else {
          output += attributes;
        }

        output += ">";
        return output;
      }
    );
  }

  /**
   * Mendapatkan unit default berdasarkan properti
   * @param {string} property Properti CSS
   * @return {string} Unit default
   */
  static getDefaultUnit(property) {
    const timeProperties = [
      "transition",
      "animation",
      "animation-duration",
      "transition-duration",
    ];
    const unitlessProperties = [
      "opacity",
      "z-index",
      "font-weight",
      "flex",
      "order",
      "scale",
    ];

    if (timeProperties.includes(property)) {
      return "ms";
    }
    if (unitlessProperties.includes(property)) {
      return "";
    }
    return "px";
  }

  /**
   * Handle NexaDom attribute - hide elements and remove content
   * @param {string} content Konten yang akan diproses
   * @return {string} Konten yang telah diproses
   */
  static processNexaDomElements(content) {
    // Simply add display:none style to elements with NexaDom attribute
    content = content.replace(
      /<([a-zA-Z0-9]+)([^>]*?\bNexaDom\b[^>]*?)>/gi,
      (match, tag, attributes) => {
        // Check if style attribute already exists
        const styleMatch = attributes.match(/style=(['"])(.*?)\1/);
        if (styleMatch) {
          const quote = styleMatch[1];
          const existingStyle = styleMatch[2];
          const newStyle = existingStyle.replace(/;$/, "") + ";display:none;";
          const newAttributes = attributes.replace(
            /style=(['"])(.*?)\1/,
            `style=${quote}${newStyle}${quote}`
          );
          return `<${tag}${newAttributes}>`;
        } else {
          return `<${tag}${attributes} style="display:none">`;
        }
      }
    );

    return content;
  }
}

// Export for Node.js environments
if (typeof module !== "undefined" && module.exports) {
  module.exports = Nexautility;
}

// Export for ES6 modules
if (typeof window !== "undefined") {
  window.Nexautility = Nexautility;
}
