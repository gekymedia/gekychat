/**
 * BlackTask Quick Commands for GekyChat Web
 */
class BlackTaskCommands {
    constructor(messageInputId, sendMessageCallback) {
        this.messageInput = document.getElementById(messageInputId);
        this.sendMessage = sendMessageCallback;
        this.init();
    }

    init() {
        this.createCommandsPanel();
    }

    createCommandsPanel() {
        const panel = document.createElement('div');
        panel.id = 'blacktask-commands-panel';
        panel.className = 'blacktask-commands-panel hidden';
        panel.innerHTML = `
            <div class="commands-header">
                <div class="commands-title">
                    <i class="fas fa-tasks"></i>
                    <span>BlackTask Quick Actions</span>
                </div>
                <button class="close-btn" onclick="blackTaskCommands.togglePanel()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="commands-grid">
                <button class="command-btn" data-command="Add task ">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Task</span>
                </button>
                <button class="command-btn" data-command="Show my tasks">
                    <i class="fas fa-list"></i>
                    <span>My Tasks</span>
                </button>
                <button class="command-btn" data-command="Complete task ">
                    <i class="fas fa-check-circle"></i>
                    <span>Complete</span>
                </button>
                <button class="command-btn" data-command="Task statistics">
                    <i class="fas fa-chart-bar"></i>
                    <span>Statistics</span>
                </button>
                <button class="command-btn" data-command="todo help">
                    <i class="fas fa-question-circle"></i>
                    <span>Help</span>
                </button>
            </div>
            <div class="commands-examples">
                <div class="example-section">
                    <strong>💡 Quick Tips:</strong>
                    <ul>
                        <li>Use natural dates: tomorrow, next week, Monday</li>
                        <li>Add priority: urgent, important, low</li>
                        <li>Example: "Add task Buy milk tomorrow urgent"</li>
                    </ul>
                </div>
            </div>
        `;

        // Append to chat container or body
        const chatContainer = document.querySelector('.chat-container') || document.body;
        chatContainer.appendChild(panel);

        // Attach event listeners
        panel.querySelectorAll('.command-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const command = btn.dataset.command;
                this.executeCommand(command);
            });
        });
    }

    togglePanel() {
        const panel = document.getElementById('blacktask-commands-panel');
        if (panel) {
            panel.classList.toggle('hidden');
        }
    }

    executeCommand(command) {
        if (this.messageInput) {
            this.messageInput.value = command;
            this.messageInput.focus();
            
            // If command is complete (doesn't end with space), send it
            if (!command.endsWith(' ')) {
                if (typeof this.sendMessage === 'function') {
                    this.sendMessage(command);
                }
            }
        }
    }

    showHelp() {
        const helpModal = `
            <div class="blacktask-help-modal">
                <div class="help-modal-content">
                    <div class="help-header">
                        <h3>📋 BlackTask Commands</h3>
                        <button onclick="this.closest('.blacktask-help-modal').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="help-body">
                        <div class="help-section">
                            <h4>📝 Create Tasks</h4>
                            <ul>
                                <li>Add task Buy groceries tomorrow</li>
                                <li>Create todo Call John on Friday</li>
                                <li>New reminder Meeting at 3pm urgent</li>
                            </ul>
                        </div>
                        <div class="help-section">
                            <h4>👀 View Tasks</h4>
                            <ul>
                                <li>Show my tasks</li>
                                <li>List my todos</li>
                            </ul>
                        </div>
                        <div class="help-section">
                            <h4>✅ Complete Tasks</h4>
                            <ul>
                                <li>Complete task 5</li>
                                <li>Mark task 3 as done</li>
                            </ul>
                        </div>
                        <div class="help-section">
                            <h4>📊 Statistics</h4>
                            <ul>
                                <li>Task statistics</li>
                                <li>Show my task stats</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', helpModal);
    }
}

// Initialize when DOM is ready
let blackTaskCommands;
document.addEventListener('DOMContentLoaded', () => {
    // Initialize with your message input ID and send function
    // Example: blackTaskCommands = new BlackTaskCommands('message-input', sendMessageFunction);
});
