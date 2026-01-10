/**
 * World Feed Search with history and suggestions
 */
class WorldSearchManager {
    constructor() {
        this.searchInput = document.getElementById('world-search-input');
        this.suggestionsContainer = document.getElementById('world-search-suggestions');
        this.resultsContainer = document.getElementById('world-search-results');
        this.currentQuery = '';
        this.init();
    }

    init() {
        if (!this.searchInput) return;
        
        this.searchInput.addEventListener('input', (e) => this.onSearchInput(e));
        this.searchInput.addEventListener('focus', () => this.showSuggestions());
        
        // Close suggestions when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.world-search-container')) {
                this.hideSuggestions();
            }
        });
    }

    async onSearchInput(e) {
        const query = e.target.value.trim();
        this.currentQuery = query;
        
        if (query.length === 0) {
            this.loadRecentSearches();
        } else if (query.length >= 2) {
            this.loadSuggestions(query);
        }
    }

    async loadRecentSearches() {
        const history = this.getSearchHistory();
        this.renderSuggestions(history.slice(0, 10).map(q => ({
            text: q,
            type: 'recent',
            icon: 'history'
        })));
    }

    async loadSuggestions(query) {
        try {
            const response = await fetch(`/api/world/search/suggestions?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            this.renderSuggestions(data.suggestions);
        } catch (error) {
            console.error('Failed to load suggestions:', error);
            this.loadLocalSuggestions(query);
        }
    }

    loadLocalSuggestions(query) {
        const history = this.getSearchHistory();
        const matches = history.filter(q => 
            q.toLowerCase().includes(query.toLowerCase())
        ).slice(0, 10);
        
        this.renderSuggestions(matches.map(q => ({
            text: q,
            type: 'history',
            icon: 'search'
        })));
    }

    renderSuggestions(suggestions) {
        if (!this.suggestionsContainer) return;
        
        if (suggestions.length === 0) {
            this.hideSuggestions();
            return;
        }
        
        this.suggestionsContainer.innerHTML = suggestions.map(s => `
            <div class="world-search-suggestion" data-query="${this.escapeHtml(s.text)}">
                <i class="material-icons">${s.icon || 'search'}</i>
                <span>${this.escapeHtml(s.text)}</span>
                ${s.score ? `<span class="suggestion-score">${s.score}</span>` : ''}
            </div>
        `).join('');
        
        // Attach click handlers
        this.suggestionsContainer.querySelectorAll('.world-search-suggestion').forEach(el => {
            el.addEventListener('click', () => {
                const query = el.dataset.query;
                this.searchInput.value = query;
                this.performSearch(query);
            });
        });
        
        this.showSuggestions();
    }

    async performSearch(query) {
        if (!query.trim()) return;
        
        this.saveSearchQuery(query);
        this.hideSuggestions();
        this.currentQuery = query;
        
        // Show loading
        this.resultsContainer.innerHTML = '<div class="loading-spinner"></div>';
        
        try {
            const response = await fetch('/api/world/search', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ query })
            });
            
            const data = await response.json();
            this.renderResults(data);
        } catch (error) {
            console.error('Search failed:', error);
            this.resultsContainer.innerHTML = '<div class="error">Search failed. Please try again.</div>';
        }
    }

    renderResults(data) {
        // Create tabs
        const tabs = `
            <div class="world-search-tabs">
                <button class="tab active" data-tab="top">Top</button>
                <button class="tab" data-tab="users">Users</button>
                <button class="tab" data-tab="videos">Videos</button>
                <button class="tab" data-tab="hashtags">Hashtags</button>
            </div>
        `;
        
        const content = `
            <div class="world-search-tab-content active" data-content="top">
                ${this.renderTopResults(data.top || [])}
            </div>
            <div class="world-search-tab-content" data-content="users">
                ${this.renderUserResults(data.users || [])}
            </div>
            <div class="world-search-tab-content" data-content="videos">
                ${this.renderVideoResults(data.videos || [])}
            </div>
            <div class="world-search-tab-content" data-content="hashtags">
                ${this.renderHashtagResults(data.hashtags || [])}
            </div>
        `;
        
        this.resultsContainer.innerHTML = tabs + content;
        
        // Tab switching
        this.resultsContainer.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const tabName = tab.dataset.tab;
                
                // Update active tab
                this.resultsContainer.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // Update active content
                this.resultsContainer.querySelectorAll('.world-search-tab-content').forEach(c => c.classList.remove('active'));
                this.resultsContainer.querySelector(`[data-content="${tabName}"]`).classList.add('active');
            });
        });
    }

    renderTopResults(results) {
        if (results.length === 0) return '<div class="no-results">No results found</div>';
        
        return `<div class="world-search-grid">${results.map(item => this.renderCard(item)).join('')}</div>`;
    }

    renderVideoResults(videos) {
        if (videos.length === 0) return '<div class="no-results">No videos found</div>';
        
        return `<div class="world-search-grid">${videos.map(video => this.renderCard(video)).join('')}</div>`;
    }

    renderUserResults(users) {
        if (users.length === 0) return '<div class="no-results">No users found</div>';
        
        return `<div class="world-search-list">${users.map(user => `
            <div class="world-search-user-item" data-user-id="${user.id}">
                <img src="${user.avatar || '/images/default-avatar.png'}" alt="${this.escapeHtml(user.name)}" class="user-avatar">
                <div class="user-info">
                    <div class="user-name">${this.escapeHtml(user.name)}</div>
                    <div class="user-stats">${user.followers_count || 0} followers</div>
                </div>
                <button class="btn-follow" data-user-id="${user.id}" onclick="worldSearch.followUser(${user.id})">
                    ${user.is_following ? 'Following' : 'Follow'}
                </button>
            </div>
        `).join('')}</div>`;
    }

    renderHashtagResults(hashtags) {
        if (hashtags.length === 0) return '<div class="no-results">No hashtags found</div>';
        
        return `<div class="world-search-list">${hashtags.map(tag => `
            <div class="world-search-hashtag-item" data-hashtag="${this.escapeHtml(tag.name)}">
                <i class="material-icons">tag</i>
                <div class="hashtag-info">
                    <div class="hashtag-name">#${this.escapeHtml(tag.name)}</div>
                    <div class="hashtag-stats">${tag.posts_count || 0} posts</div>
                </div>
            </div>
        `).join('')}</div>`;
    }

    renderCard(item) {
        return `
            <div class="world-search-card" data-id="${item.id}" data-type="${item.type}" onclick="worldSearch.onResultClick('${item.type}', ${item.id})">
                <div class="card-thumbnail">
                    <img src="${item.thumbnail || '/images/video-placeholder.jpg'}" alt="${this.escapeHtml(item.title || '')}">
                    <i class="material-icons play-icon">play_circle_outline</i>
                </div>
                <div class="card-info">
                    <div class="card-title">${this.escapeHtml(item.title || 'Untitled')}</div>
                    <div class="card-stats">
                        <i class="material-icons">play_arrow</i>
                        ${this.formatNumber(item.views || 0)} views
                    </div>
                </div>
            </div>
        `;
    }

    async followUser(userId) {
        try {
            const response = await fetch(`/api/world/users/${userId}/follow`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            if (response.ok) {
                const button = document.querySelector(`button[data-user-id="${userId}"]`);
                if (button) {
                    button.textContent = button.textContent === 'Follow' ? 'Following' : 'Follow';
                }
            }
        } catch (error) {
            console.error('Follow failed:', error);
        }
    }

    onResultClick(type, id) {
        // Track click
        this.saveSearchClick(this.currentQuery, type, id);
        
        // Navigate
        if (type === 'video') {
            window.location.href = `/world/videos/${id}`;
        } else if (type === 'user') {
            window.location.href = `/world/users/${id}`;
        }
    }

    // Local storage helpers
    getSearchHistory() {
        const history = localStorage.getItem('world_search_history');
        return history ? JSON.parse(history) : [];
    }

    saveSearchQuery(query) {
        let history = this.getSearchHistory();
        history = history.filter(q => q !== query);
        history.unshift(query);
        history = history.slice(0, 50);
        localStorage.setItem('world_search_history', JSON.stringify(history));
    }

    saveSearchClick(query, type, id) {
        fetch('/api/world/search/clicks', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ query, type, id })
        }).catch(err => console.error('Failed to track click:', err));
    }

    showSuggestions() {
        if (this.suggestionsContainer) {
            this.suggestionsContainer.style.display = 'block';
        }
    }

    hideSuggestions() {
        if (this.suggestionsContainer) {
            this.suggestionsContainer.style.display = 'none';
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatNumber(num) {
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
        return num.toString();
    }
}

// Initialize
let worldSearch;
document.addEventListener('DOMContentLoaded', () => {
    worldSearch = new WorldSearchManager();
});
