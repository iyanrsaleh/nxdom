/**
 * Terminal Component
 * Handler untuk context menu item "Terminal"
 */
export async function nexaChatAssistant(route) {
  try {

  let userData = null;
  if (window.NXUI && window.NXUI.ref) {
    try {
      userData = await NXUI.ref.get("userData", "uniqueId");
    } catch (error) {
      console.error('❌ Error getting userData:', error);
    }
  }
  const varBuckets = await NXUI.ref.get("userData","token");   
  if (varBuckets) {
     const modalID="nexaChatAssistant_"+varBuckets?.key;
     NXUI.modalHTML({
         elementById: modalID,
         styleClass: "w-500px",
         minimize: true,
         label: `AI Assistant`,
         onclick: false,
         content: `
    <script src="https://cdn.jsdelivr.net/npm/marked@9.1.6/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <div class="assistant-chat-container">
      <div class="assistant-chat-header">
        <div>
          <h2>Chat Assistant</h2>
          <p class="assistant-status"><i data-feather="database"></i> AI dengan Memory - Mengingat percakapan Anda</p>
        </div>
        <div class="assistant-header-actions">
          <button id="assistant-copy-all-button" class="assistant-copy-all-button" title="Copy Semua Percakapan">
            <i data-feather="copy"></i>
          </button>
          <button id="assistant-clear-chat" class="assistant-clear-chat" title="Hapus Riwayat Chat">
            <i data-feather="trash-2"></i>
          </button>
        </div>
      </div>
      <div class="assistant-chat-messages" id="assistant-chat-messages"></div>
      <div class="assistant-typing-indicator" id="assistant-typing-indicator">
        💬 AI sedang mengetik... (Click untuk skip)
      </div>
      <div class="assistant-chat-input-form">
        <button id="assistant-attachment-button" class="assistant-attachment-button" title="Upload Gambar">
          <i data-feather="paperclip"></i>
        </button>
        <input
          type="text"
          id="assistant-message-input"
          class="assistant-message-input"
          placeholder="Ketik pesan Anda atau upload gambar..."
        />
        <button id="assistant-voice-button" class="assistant-voice-button" title="Baca suara pesan AI terakhir">
          <i data-feather="mic"></i>
        </button>
        <input
          type="file"
          id="assistant-file-input"
          accept="image/*,.pdf,.txt,.csv,.json"
          style="display: none"
        />
      </div>
    </div>
         `,
       });
       NXUI.nexaModal.open(modalID);
       NXUI.id("body_"+modalID).setStyle("padding", "0px")
      window.autoInitNexaAI = false;
      const nexaAI = new NXUI.NexaAIAssistant({
        token: varBuckets.Ai.token,
        linkAPIGemini: varBuckets.Ai.url,
        photo: {
          assistant: NEXA.url + "/assets/drive/images/wanita.png",
          users: userData?.avatar || NEXA.url + "/assets/drive/images/pria.png",
        },
        containerSelector: ".assistant-chat-container",
        welcomeMessage:
          "Halo! Saya siap membantu Anda. Ada yang bisa saya bantu hari ini?",
        // Voice settings - Sederhana dan langsung
        responsiveVoiceKey: "16D1IGr5", // Sama dengan key di HTML
        voiceLanguage: "Indonesian Female",
        voiceRate: 1.0,
        voicePitch: 1,
        voiceVolume: 0.8,
      });
    } else {}

    // return { success: true };
  } catch (error) {
    // console.error('❌ Error opening terminal:', error);
    return { success: false, error: error.message };
  }
}

