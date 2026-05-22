/**
 * Example: Using NexaHighlig in NexaJS Routes
 *
 * Contoh integrasi NexaHighlig ke dalam struktur NexaJS
 * Dapatkan di: templates/code-demo.js
 */

import NexaHighlig from "../../assets/modules/Highlight/NexaHighlig.js";

export default {
  init() {
    // Setup global instance jika belum ada
    if (!window.NexaHighlig) {
      window.NexaHighlig = new NexaHighlig({
        theme: "atom-one-dark",
        lineNumbers: true,
        copyButton: true,
      });
    }

    return `
      <div class="nexaHighlig-demo">
        <h1>Code Highlighting Demo</h1>
        
        <div class="controls">
          <select id="langSelect">
            <option value="javascript">JavaScript</option>
            <option value="php">PHP</option>
            <option value="python">Python</option>
            <option value="html">HTML</option>
            <option value="css">CSS</option>
            <option value="json">JSON</option>
          </select>
          
          <textarea id="codeInput" placeholder="Masukkan kode...">function hello() {
  console.log('Hello, NexaJS!');
}</textarea>
          
          <button onclick="nx.highlightCode()">Highlight</button>
          
          <select id="themeSelect">
            <option value="atom-one-dark">Dark</option>
            <option value="atom-one-light">Light</option>
            <option value="dracula">Dracula</option>
            <option value="github-dark">GitHub Dark</option>
            <option value="monokai">Monokai</option>
          </select>
        </div>
        
        <div id="output" class="output"></div>
        
        <style>
          .nexaHighlig-demo {
            padding: 2rem;
            max-width: 900px;
          }
          
          .controls {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f5f5f5;
            border-radius: 6px;
          }
          
          .controls select,
          .controls textarea,
          .controls button {
            padding: 0.5rem;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
          }
          
          .controls button {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
          }
          
          .controls button:hover {
            background: #0056b3;
          }
          
          .output {
            margin-top: 2rem;
          }
        </style>
      </div>
    `;
  },

  async afterRender() {
    // Handler untuk highlight button
    nx.highlightCode = () => {
      const code = document.getElementById("codeInput").value;
      const language = document.getElementById("langSelect").value;
      const theme = document.getElementById("themeSelect").value;
      const output = document.getElementById("output");

      try {
        // Set theme jika berbeda
        if (window.NexaHighlig.config.theme !== theme) {
          window.NexaHighlig.setTheme(theme);
        }

        // Highlight code
        const block = window.NexaHighlig.renderCodeBlock(code, language);
        output.innerHTML = "";
        output.appendChild(block);
      } catch (error) {
        output.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
      }
    };

    // Handler untuk theme change
    document.getElementById("themeSelect").addEventListener("change", () => {
      nx.highlightCode();
    });

    // Initial highlight
    setTimeout(() => nx.highlightCode(), 100);
  },
};
