/**
 * Enhanced comments with likes and threaded replies
 */
class WorldCommentsManager {
    constructor(postId, containerId) {
        this.postId = postId;
        this.container = document.getElementById(containerId);
        this.replyingTo = null;
        this.init();
    }

    init() {
        if (!this.container) return;
        
        this.loadComments();
        this.attachEventListeners();
    }

    attachEventListeners() {
        // Submit comment
        const submitBtn = this.container.querySelector('.comment-submit-btn');
        const input = this.container.querySelector('.comment-input');
        
        if (submitBtn && input) {
            submitBtn.addEventListener('click', () => this.submitComment());
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.submitComment();
                }
            });
        }
    }

    async loadComments() {
        try {
            const response = await fetch(`/api/world/posts/${this.postId}/comments`);
            const data = await response.json();
            this.renderComments(data.comments || []);
        } catch (error) {
            console.error('Failed to load comments:', error);
        }
    }

    renderComments(comments) {
        const commentsHtml = comments.map(comment => this.renderComment(comment, false)).join('');
        const commentsContainer = this.container.querySelector('.comments-list');
        
        if (commentsContainer) {
            commentsContainer.innerHTML = commentsHtml;
            this.attachCommentEventListeners();
        }
    }

    renderComment(comment, isReply) {
        const repliesHtml = comment.replies && comment.replies.length > 0
            ? `<div class="comment-replies">${comment.replies.map(r => this.renderComment(r, true)).join('')}</div>`
            : '';
        
        return `
            <div class="comment-item ${isReply ? 'comment-reply' : ''}" data-comment-id="${comment.id}">
                <img src="${comment.user.avatar || '/images/default-avatar.png'}" alt="${this.escapeHtml(comment.user.name)}" class="comment-avatar">
                <div class="comment-content">
                    <div class="comment-header">
                        <span class="comment-author">${this.escapeHtml(comment.user.name)}</span>
                        <span class="comment-time">${this.formatTime(comment.created_at)}</span>
                    </div>
                    <div class="comment-text">${this.escapeHtml(comment.text)}</div>
                    <div class="comment-actions">
                        <button class="comment-like-btn ${comment.is_liked ? 'liked' : ''}" data-comment-id="${comment.id}">
                            <i class="material-icons">${comment.is_liked ? 'favorite' : 'favorite_border'}</i>
                            ${comment.likes_count > 0 ? `<span>${comment.likes_count}</span>` : ''}
                        </button>
                        ${!isReply ? `
                            <button class="comment-reply-btn" data-comment-id="${comment.id}" data-author="${this.escapeHtml(comment.user.name)}">
                                <i class="material-icons">reply</i>
                                <span>Reply</span>
                            </button>
                        ` : ''}
                    </div>
                </div>
                ${repliesHtml}
            </div>
        `;
    }

    attachCommentEventListeners() {
        // Like buttons
        this.container.querySelectorAll('.comment-like-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const commentId = btn.dataset.commentId;
                this.toggleLike(commentId, btn);
            });
        });
        
        // Reply buttons
        this.container.querySelectorAll('.comment-reply-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const commentId = btn.dataset.commentId;
                const author = btn.dataset.author;
                this.startReply(commentId, author);
            });
        });
    }

    async toggleLike(commentId, button) {
        try {
            const response = await fetch(`/api/world/comments/${commentId}/like`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                const icon = button.querySelector('i');
                const countSpan = button.querySelector('span');
                
                if (data.is_liked) {
                    button.classList.add('liked');
                    icon.textContent = 'favorite';
                } else {
                    button.classList.remove('liked');
                    icon.textContent = 'favorite_border';
                }
                
                if (data.likes_count > 0) {
                    if (countSpan) {
                        countSpan.textContent = data.likes_count;
                    } else {
                        button.innerHTML += `<span>${data.likes_count}</span>`;
                    }
                } else if (countSpan) {
                    countSpan.remove();
                }
            }
        } catch (error) {
            console.error('Failed to toggle like:', error);
        }
    }

    startReply(commentId, authorName) {
        this.replyingTo = commentId;
        
        const input = this.container.querySelector('.comment-input');
        const replyIndicator = this.container.querySelector('.reply-indicator');
        
        if (replyIndicator) {
            replyIndicator.innerHTML = `
                <i class="material-icons">reply</i>
                <span>Replying to ${this.escapeHtml(authorName)}</span>
                <button class="cancel-reply-btn">
                    <i class="material-icons">close</i>
                </button>
            `;
            replyIndicator.style.display = 'flex';
            
            const cancelBtn = replyIndicator.querySelector('.cancel-reply-btn');
            cancelBtn.addEventListener('click', () => this.cancelReply());
        }
        
        if (input) {
            input.focus();
            input.placeholder = `Reply to ${authorName}...`;
        }
    }

    cancelReply() {
        this.replyingTo = null;
        
        const input = this.container.querySelector('.comment-input');
        const replyIndicator = this.container.querySelector('.reply-indicator');
        
        if (replyIndicator) {
            replyIndicator.style.display = 'none';
        }
        
        if (input) {
            input.placeholder = 'Add a comment...';
        }
    }

    async submitComment() {
        const input = this.container.querySelector('.comment-input');
        const text = input.value.trim();
        
        if (!text) return;
        
        try {
            const response = await fetch(`/api/world/posts/${this.postId}/comments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    text: text,
                    parent_id: this.replyingTo
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                input.value = '';
                this.cancelReply();
                this.loadComments(); // Reload to show new comment
            }
        } catch (error) {
            console.error('Failed to submit comment:', error);
        }
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return 'Just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
        
        return `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize comments when needed
window.initWorldComments = (postId, containerId) => {
    return new WorldCommentsManager(postId, containerId);
};
