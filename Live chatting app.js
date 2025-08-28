class ChatApp {
    constructor() {
        this.currentUser = null;
        this.activeChat = null;
        this.users = JSON.parse(localStorage.getItem('chatUsers')) || [];
        this.messages = JSON.parse(localStorage.getItem('chatMessages')) || {};
        this.init();
        this.setupDefaultUsers();
    }
    
    init() {
        this.setupEventListeners();
        this.checkLoggedInUser();
    }
    
    setupDefaultUsers() {
        if (this.users.length === 0) {
            const defaultUsers = [
                { id: 1, username: 'alice', email: 'alice@demo.com', password: 'password123', online: true },
                { id: 2, username: 'bob', email: 'bob@demo.com', password: 'password123', online: true },
                { id: 3, username: 'charlie', email: 'charlie@demo.com', password: 'password123', online: false },
                { id: 4, username: 'diana', email: 'diana@demo.com', password: 'password123', online: true }
            ];
            this.users = defaultUsers;
            this.saveUsers();
        }
    }
    
    setupEventListeners() {
        // Auth form listeners
        document.getElementById('showRegister').addEventListener('click', (e) => {
            e.preventDefault();
            this.showRegisterForm();
        });
        
        document.getElementById('showLogin').addEventListener('click', (e) => {
            e.preventDefault();
            this.showLoginForm();
        });
        
        document.getElementById('loginFormElement').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });
        
        document.getElementById('registerFormElement').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleRegister();
        });
        
        // Chat listeners
        document.getElementById('logoutBtn').addEventListener('click', (e) => {
            e.preventDefault();
            this.logout();
        });
        
        document.getElementById('sendBtn').addEventListener('click', () => {
            this.sendMessage();
        });
        
        document.getElementById('messageInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.sendMessage();
            }
        });
        
        document.getElementById('messageInput').addEventListener('input', () => {
            this.handleTyping();
        });
        
        document.getElementById('emojiBtn').addEventListener('click', () => {
            this.toggleEmojiPicker();
        });
        
        // Emoji picker listeners
        document.querySelectorAll('.emoji').forEach(emoji => {
            emoji.addEventListener('click', (e) => {
                this.insertEmoji(e.target.dataset.emoji);
            });
        });
    }
    
    showRegisterForm() {
        document.getElementById('loginForm').style.display = 'none';
        document.getElementById('registerForm').style.display = 'block';
    }
    
    showLoginForm() {
        document.getElementById('registerForm').style.display = 'none';
        document.getElementById('loginForm').style.display = 'block';
    }
    
    handleLogin() {
        const username = document.getElementById('loginUsername').value;
        const password = document.getElementById('loginPassword').value;
        
        const user = this.users.find(u => u.username === username && u.password === password);
        
        if (user) {
            this.currentUser = user;
            user.online = true;
            this.saveUsers();
            localStorage.setItem('currentUser', JSON.stringify(user));
            this.showChatInterface();
            this.showNotification('Login successful!', 'success');
        } else {
            this.showNotification('Invalid credentials!', 'error');
        }
    }
    
    handleRegister() {
        const username = document.getElementById('registerUsername').value;
        const email = document.getElementById('registerEmail').value;
        const password = document.getElementById('registerPassword').value;
        
        if (this.users.find(u => u.username === username)) {
            this.showNotification('Username already exists!', 'error');
            return;
        }
        
        const newUser = {
            id: Date.now(),
            username,
            email,
            password,
            online: true
        };
        
        this.users.push(newUser);
        this.saveUsers();
        this.currentUser = newUser;
        localStorage.setItem('currentUser', JSON.stringify(newUser));
        this.showChatInterface();
        this.showNotification('Registration successful!', 'success');
    }
    
    logout() {
        if (this.currentUser) {
            this.currentUser.online = false;
            this.saveUsers();
        }
        localStorage.removeItem('currentUser');
        this.currentUser = null;
        this.activeChat = null;
        document.getElementById('authContainer').style.display = 'block';
        document.getElementById('chatContainer').style.display = 'none';
        this.showNotification('Logged out successfully!', 'success');
    }
    
    checkLoggedInUser() {
        const savedUser = localStorage.getItem('currentUser');
        if (savedUser) {
            this.currentUser = JSON.parse(savedUser);
            // Update user online status
            const userIndex = this.users.findIndex(u => u.id === this.currentUser.id);
            if (userIndex !== -1) {
                this.users[userIndex].online = true;
                this.saveUsers();
            }
            this.showChatInterface();
        }
    }
    
    showChatInterface() {
        document.getElementById('authContainer').style.display = 'none';
        document.getElementById('chatContainer').style.display = 'block';
        document.getElementById('currentUser').textContent = this.currentUser.username;
        this.renderUserList();
        this.startHeartbeat();
    }
    
    renderUserList() {
        const userList = document.getElementById('userList');
        userList.innerHTML = '';
        
        this.users.filter(u => u.id !== this.currentUser.id).forEach(user => {
            const userElement = document.createElement('div');
            userElement.className = `user-item ${this.activeChat === user.id ? 'active' : ''}`;
            userElement.onclick = () => this.selectUser(user.id);
            
            userElement.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-user-circle fa-2x ${user.online ? 'text-primary' : 'text-muted'}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold">${user.username}</div>
                        <small class="text-muted">
                            ${user.online ? '<span class="online-indicator"></span>Online' : 'Offline'}
                        </small>
                    </div>
                </div>
            `;
            
            userList.appendChild(userElement);
        });
    }
    
    selectUser(userId) {
        this.activeChat = userId;
        const user = this.users.find(u => u.id === userId);
        
        document.getElementById('chatTitle').textContent = user.username;
        document.getElementById('chatStatus').textContent = user.online ? 'Online' : 'Last seen recently';
        
        // Enable message input
        document.getElementById('messageInput').disabled = false;
        document.getElementById('sendBtn').disabled = false;
        document.getElementById('emojiBtn').disabled = false;
        document.getElementById('messageInput').placeholder = `Message ${user.username}...`;
        
        this.renderUserList();
        this.loadMessages();
    }
    
    loadMessages() {
        const chatKey = this.getChatKey(this.currentUser.id, this.activeChat);
        const messages = this.messages[chatKey] || [];
        
        const chatMessages = document.getElementById('chatMessages');
        chatMessages.innerHTML = '';
        
        if (messages.length === 0) {
            chatMessages.innerHTML = `
                <div class="text-center text-muted mt-5">
                    <i class="fas fa-comments fa-3x mb-3 opacity-25"></i>
                    <p>No messages yet. Start the conversation!</p>
                </div>
            `;
            return;
        }
        
        messages.forEach(message => {
            this.renderMessage(message);
        });
        
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    renderMessage(message) {
        const chatMessages = document.getElementById('chatMessages');
        const isOwn = message.senderId === this.currentUser.id;
        
        const messageElement = document.createElement('div');
        messageElement.className = `message ${isOwn ? 'own' : ''} fade-in`;
        
        const senderName = isOwn ? 'You' : this.users.find(u => u.id === message.senderId)?.username || 'Unknown';
        
        messageElement.innerHTML = `
            <div class="message-content">
                <div class="message-text">${this.formatMessage(message.text)}</div>
                <div class="message-time">${this.formatTime(message.timestamp)}</div>
            </div>
        `;
        
        chatMessages.appendChild(messageElement);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    formatMessage(text) {
        // Simple URL detection and emoji support
        return text
            .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" class="text-primary">$1</a>')
            .replace(/\n/g, '<br>');
    }
    
    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) { // Less than 1 minute
            return 'Just now';
        } else if (diff < 3600000) { // Less than 1 hour
            return `${Math.floor(diff / 60000)} min ago`;
        } else if (date.toDateString() === now.toDateString()) { // Same day
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } else {
            return date.toLocaleDateString();
        }
    }
    
    sendMessage() {
        const messageInput = document.getElementById('messageInput');
        const text = messageInput.value.trim();
        
        if (!text || !this.activeChat) return;
        
        const message = {
            id: Date.now(),
            senderId: this.currentUser.id,
            receiverId: this.activeChat,
            text: text,
            timestamp: Date.now()
        };
        
        const chatKey = this.getChatKey(this.currentUser.id, this.activeChat);
        if (!this.messages[chatKey]) {
            this.messages[chatKey] = [];
        }
        
        this.messages[chatKey].push(message);
        this.saveMessages();
        
        this.renderMessage(message);
        messageInput.value = '';
        
        // Simulate response from other user (for demo purposes)
        setTimeout(() => {
            this.simulateResponse();
        }, 1000 + Math.random() * 2000);
    }
    
    simulateResponse() {
        if (!this.activeChat) return;
        
        const responses = [
            "That's interesting! 😊",
            "I agree with you",
            "Really? Tell me more!",
            "Haha, that's funny! 😂",
            "Thanks for sharing that",
            "I see what you mean",
            "That sounds great! 👍",
            "Wow, amazing!",
            "I'm thinking about that too",
            "Good point!"
        ];
        
        const randomResponse = responses[Math.floor(Math.random() * responses.length)];
        
        const message = {
            id: Date.now(),
            senderId: this.activeChat,
            receiverId: this.currentUser.id,
            text: randomResponse,
            timestamp: Date.now()
        };
        
        const chatKey = this.getChatKey(this.currentUser.id, this.activeChat);
        if (!this.messages[chatKey]) {
            this.messages[chatKey] = [];
        }
        
        this.messages[chatKey].push(message);
        this.saveMessages();
        
        this.renderMessage(message);
        this.showTypingIndicator(false);
    }
    
    handleTyping() {
        this.showTypingIndicator(true);
        clearTimeout(this.typingTimeout);
        this.typingTimeout = setTimeout(() => {
            this.showTypingIndicator(false);
        }, 1000);
    }
    
    showTypingIndicator(show) {
        const indicator = document.getElementById('typingIndicator');
        indicator.style.display = show ? 'block' : 'none';
    }
    
    toggleEmojiPicker() {
        const picker = document.getElementById('emojiPicker');
        picker.style.display = picker.style.display === 'block' ? 'none' : 'block';
    }
    
    insertEmoji(emoji) {
        const input = document.getElementById('messageInput');
        input.value += emoji;
        input.focus();
        this.toggleEmojiPicker();
    }
    
    getChatKey(userId1, userId2) {
        return [userId1, userId2].sort().join('-');
    }
    
    saveUsers() {
        localStorage.setItem('chatUsers', JSON.stringify(this.users));
    }
    
    saveMessages() {
        localStorage.setItem('chatMessages', JSON.stringify(this.messages));
    }
    
    showNotification(message, type = 'success') {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.className = `notification ${type === 'error' ? 'bg-danger' : 'bg-success'}`;
        notification.style.display = 'block';
        
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    }
    
    startHeartbeat() {
        // Simulate online status updates
        setInterval(() => {
            // Randomly update user online status for demo
            this.users.forEach(user => {
                if (user.id !== this.currentUser.id) {
                    // 90% chance to stay online
                    user.online = Math.random() > 0.1;
                }
            });
            this.saveUsers();
            
            if (document.getElementById('chatContainer').style.display !== 'none') {
                this.renderUserList();
            }
        }, 10000); // Update every 10 seconds
    }
}

// Initialize the chat application
document.addEventListener('DOMContentLoaded', () => {
    new ChatApp();
});

// Hide emoji picker when clicking outside
document.addEventListener('click', (e) => {
    const emojiPicker = document.getElementById('emojiPicker');
    const emojiBtn = document.getElementById('emojiBtn');
    
    if (!emojiPicker.contains(e.target) && !emojiBtn.contains(e.target)) {
        emojiPicker.style.display = 'none';
    }
});
