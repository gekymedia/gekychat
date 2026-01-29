/**
 * Mentions functionality for GekyChat Web App
 * Handles @mentions in message input and display
 */

export class MentionManager {
    constructor() {
        this.mentionPattern = /@(\w{3,30})/g;
    }

    /**
     * Parse @mentions from text
     */
    parseMentions(text) {
        const mentions = [];
        let match;
        while ((match = this.mentionPattern.exec(text)) !== null) {
            mentions.push({
                username: match[1],
                start: match.index,
                end: match.index + match[0].length,
                text: match[0]
            });
        }
        return mentions;
    }

    /**
     * Highlight @mentions in text with HTML
     */
    highlightMentions(text, mentions = null) {
        if (!mentions || mentions.length === 0) {
            mentions = this.parseMentions(text);
        }

        if (mentions.length === 0) {
            return this.escapeHtml(text);
        }

        // Sort mentions by position
        mentions.sort((a, b) => a.start - b.start);

        let result = '';
        let lastIndex = 0;

        mentions.forEach(mention => {
            // Add text before mention
            result += this.escapeHtml(text.substring(lastIndex, mention.start));
            
            // Add highlighted mention
            const username = mention.mentioned_user?.username || mention.username;
            const userId = mention.mentioned_user?.id || '';
            result += `<span class="mention" data-user-id="${userId}">@${username}</span>`;
            
            lastIndex = mention.end;
        });

        // Add remaining text
        result += this.escapeHtml(text.substring(lastIndex));

        return result;
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Get current mention being typed
     */
    getCurrentMention(text, cursorPosition) {
        if (cursorPosition <= 0 || cursorPosition > text.length) return null;

        // Find the last @ before cursor
        let atIndex = -1;
        for (let i = cursorPosition - 1; i >= 0; i--) {
            if (text[i] === '@') {
                atIndex = i;
                break;
            }
            // Stop if we hit a space or newline
            if (text[i] === ' ' || text[i] === '\n') {
                break;
            }
        }

        if (atIndex === -1) return null;

        // Check if there's a space or newline right before the @
        if (atIndex > 0 && text[atIndex - 1] !== ' ' && text[atIndex - 1] !== '\n') {
            return null;
        }

        // Extract the partial username
        const partial = text.substring(atIndex + 1, cursorPosition);

        // Validate it's a valid username pattern
        if (!/^\w*$/.test(partial)) return null;

        return {
            query: partial,
            start: atIndex,
            end: cursorPosition
        };
    }

    /**
     * Insert mention into text
     */
    insertMention(text, cursorPosition, username) {
        const currentMention = this.getCurrentMention(text, cursorPosition);
        
        if (!currentMention) {
            return {
                text: text + ` @${username} `,
                cursorPosition: text.length + username.length + 3
            };
        }

        const before = text.substring(0, currentMention.start);
        const after = text.substring(cursorPosition);
        const newText = `${before}@${username} ${after}`;
        const newCursorPosition = currentMention.start + username.length + 2;

        return {
            text: newText,
            cursorPosition: newCursorPosition
        };
    }

    /**
     * Initialize mention autocomplete for an input element
     */
    initAutocomplete(inputElement, members, onSelect) {
        const dropdown = document.createElement('div');
        dropdown.className = 'mention-autocomplete';
        dropdown.style.display = 'none';
        document.body.appendChild(dropdown);

        let currentMention = null;

        const updateAutocomplete = () => {
            const text = inputElement.value;
            const cursorPosition = inputElement.selectionStart;
            currentMention = this.getCurrentMention(text, cursorPosition);

            if (!currentMention) {
                dropdown.style.display = 'none';
                return;
            }

            // Filter members
            const query = currentMention.query.toLowerCase();
            const filtered = members.filter(member => 
                member.name.toLowerCase().includes(query) ||
                (member.username && member.username.toLowerCase().includes(query))
            );

            if (filtered.length === 0) {
                dropdown.style.display = 'none';
                return;
            }

            // Update dropdown content
            dropdown.innerHTML = filtered.map(member => `
                <div class="mention-item" data-user-id="${member.id}" data-username="${member.username || this.generateUsername(member.name)}">
                    <img src="${member.avatar_url || '/images/default-avatar.png'}" alt="${member.name}" class="mention-avatar">
                    <div class="mention-info">
                        <div class="mention-name">${member.name}</div>
                        <div class="mention-username">@${member.username || this.generateUsername(member.name)}</div>
                    </div>
                </div>
            `).join('');

            // Position dropdown
            const rect = inputElement.getBoundingClientRect();
            dropdown.style.left = rect.left + 'px';
            dropdown.style.top = (rect.top - dropdown.offsetHeight - 5) + 'px';
            dropdown.style.display = 'block';

            // Add click handlers
            dropdown.querySelectorAll('.mention-item').forEach(item => {
                item.addEventListener('click', () => {
                    const username = item.dataset.username;
                    const result = this.insertMention(inputElement.value, cursorPosition, username);
                    inputElement.value = result.text;
                    inputElement.setSelectionRange(result.cursorPosition, result.cursorPosition);
                    dropdown.style.display = 'none';
                    onSelect && onSelect(username);
                    inputElement.focus();
                });
            });
        };

        inputElement.addEventListener('input', updateAutocomplete);
        inputElement.addEventListener('keyup', updateAutocomplete);

        // Close dropdown on click outside
        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && e.target !== inputElement) {
                dropdown.style.display = 'none';
            }
        });

        return () => {
            dropdown.remove();
        };
    }

    /**
     * Generate username from name
     */
    generateUsername(name) {
        return name.toLowerCase()
            .replace(/[^\w]/g, '_')
            .replace(/_+/g, '_')
            .replace(/^_|_$/g, '');
    }
}

// Export singleton instance
export const mentionManager = new MentionManager();
