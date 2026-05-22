/**
 * NexaAI - AI Chat Module for Terminal
 * Handles AI chat functionality using Gemini API
 */

export class NexaAI {
    constructor() {
        this.chatConfig = null;
        this.initialized = false;
        this.conversationHistory = []; // Store conversation history
    }

    /**
     * Initialize chat configuration
     * @returns {Promise<Object>} chatConfig
     */
    async init() {
        if (this.initialized && window.chatConfig) {
            this.chatConfig = window.chatConfig;
            return this.chatConfig;
        }

        try {
            // Check if NexaUI is available
            if (typeof window.NexaUI === 'undefined' || typeof window.NexaUI !== 'function') {
                throw new Error('NexaUI is not available');
            }
            
            const nexaUI = window.NexaUI();
            
            if (!nexaUI) {
                throw new Error('NexaUI initialization failed');
            }
            
            const init = await nexaUI.Storage().package("Account").Chat({
                Authorization: true,
            });
            
            const crypto = nexaUI.Crypto("NexaChatV1");
            this.chatConfig = crypto.decode(init.data.token);
            window.chatConfig = this.chatConfig;
            this.initialized = true;
            
            return this.chatConfig;
        } catch (error) {
            console.error('Error initializing AI chat:', error);
            throw error;
        }
    }

    /**
     * Get AI configuration
     * @returns {Object|null} AI config with token and url
     */
    getAIConfig() {
        if (!this.chatConfig || !this.chatConfig.setting || !this.chatConfig.setting.Ai) {
            return null;
        }
        return this.chatConfig.setting.Ai;
    }

    /**
     * Check if AI is configured
     * @returns {boolean}
     */
    isConfigured() {
        const aiConfig = this.getAIConfig();
        return aiConfig && aiConfig.token && aiConfig.url;
    }

    /**
     * Send question to AI and get response
     * @param {string} question - The question to ask AI
     * @returns {Promise<string>} AI response
     */
    async ask(question) {
        if (!this.isConfigured()) {
            throw new Error('AI configuration not available. Please initialize first.');
        }

        const aiConfig = this.getAIConfig();
        const apiUrl = aiConfig.url;
        const apiToken = aiConfig.token;

        // Prepare request to Gemini API with Indonesian language instruction
        const promptWithLanguage = `Jawab dalam bahasa Indonesia. ${question}`;
        
        // Build contents array with conversation history
        const contents = [];
        
        // Add conversation history with proper role (user or model)
        // Gemini API requires role for all messages
        this.conversationHistory.forEach(msg => {
            // Ensure role is valid ('user' or 'model')
            const validRole = (msg.role === 'user' || msg.role === 'model') ? msg.role : 'user';
            contents.push({
                role: validRole,
                parts: [{
                    text: msg.text
                }]
            });
        });
        
        // Add current question with user role (always use role)
        contents.push({
            role: 'user',
            parts: [{
                text: promptWithLanguage
            }]
        });
        
        const requestBody = {
            contents: contents
        };

        // Send request to Gemini API using header (same format as NexaAIAssistant)
        const myHeaders = new Headers();
        myHeaders.append('Content-Type', 'application/json');
        myHeaders.append('X-goog-api-key', apiToken);

        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: myHeaders,
            body: JSON.stringify(requestBody)
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error?.message || `API error: ${response.status} ${response.statusText}`);
        }

        const data = await response.json();

        // Extract response text from Gemini API response
        let aiResponse = '';
        if (data.candidates && data.candidates[0] && data.candidates[0].content) {
            aiResponse = data.candidates[0].content.parts[0].text || 'No response from AI';
        } else {
            aiResponse = 'No response from AI';
        }

        // Add to conversation history (add user question and model response)
        // Note: User question is added here, but it's also in the request
        // We add it to history after getting response to maintain order
        this.addToHistory('user', promptWithLanguage);
        this.addToHistory('model', aiResponse);

        return aiResponse;
    }

    /**
     * Get chat configuration
     * @returns {Object|null} Full chat config
     */
    getConfig() {
        return this.chatConfig;
    }

    /**
     * Check if question is asking for user list
     * @param {string} question - The question to check
     * @returns {boolean}
     */
    isAskingForUserList(question) {
        if (!question) return false;
        const lowerQuestion = question.toLowerCase();
        const keywords = [
            'tampilkan daftar user',
            'tampilkan daftar pengguna',
            'daftar user',
            'daftar pengguna',
            'list user',
            'list pengguna',
            'show user list',
            'show users',
            'tampilkan user',
            'tampilkan pengguna',
            'siapa saja user',
            'siapa saja pengguna'
        ];
        return keywords.some(keyword => lowerQuestion.includes(keyword));
    }

    /**
     * Get list of friends/users from chatConfig
     * @returns {Array} Array of user objects
     */
    getFriendsList() {
        if (!this.chatConfig || !this.chatConfig.frend || !this.chatConfig.frend.userChat) {
            return [];
        }
        return this.chatConfig.frend.userChat || [];
    }

    /**
     * Get current user info
     * @returns {Object|null} Current user object
     */
    getCurrentUser() {
        if (!this.chatConfig || !this.chatConfig.frend || !this.chatConfig.frend.userOn) {
            return null;
        }
        return this.chatConfig.frend.userOn;
    }

    /**
     * Add message to conversation history
     * @param {string} role - 'user' or 'model'
     * @param {string} text - Message text
     */
    addToHistory(role, text) {
        this.conversationHistory.push({
            role: role,
            text: text,
            timestamp: new Date().toISOString()
        });
        
        // Limit history to last 20 messages to avoid token limit
        if (this.conversationHistory.length > 20) {
            this.conversationHistory = this.conversationHistory.slice(-20);
        }
    }

    /**
     * Clear conversation history
     */
    clearHistory() {
        this.conversationHistory = [];
    }

    /**
     * Get conversation history
     * @returns {Array} Conversation history
     */
    getHistory() {
        return this.conversationHistory;
    }
}

