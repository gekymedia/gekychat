{{-- resources/views/chat/shared/emoji_picker.blade.php --}}
<div id="emoji-picker-container" class="emoji-picker-container" style="display: none;">
  <div class="emoji-picker-header d-flex justify-content-between align-items-center p-2 border-bottom">
    <div class="emoji-categories d-flex gap-1">
      <button type="button" class="category-btn active" data-category="recent" aria-label="Recent emojis">
        <i class="bi bi-clock"></i>
      </button>
      <button type="button" class="category-btn" data-category="smileys" aria-label="Smileys & People">
        <i class="bi bi-emoji-smile"></i>
      </button>
      <button type="button" class="category-btn" data-category="animals" aria-label="Animals & Nature">
        <i class="bi bi-flower1"></i>
      </button>
      <button type="button" class="category-btn" data-category="food" aria-label="Food & Drink">
        <i class="bi bi-cup-straw"></i>
      </button>
      <button type="button" class="category-btn" data-category="travel" aria-label="Travel & Places">
        <i class="bi bi-airplane"></i>
      </button>
      <button type="button" class="category-btn" data-category="activities" aria-label="Activities">
        <i class="bi bi-balloon"></i>
      </button>
      <button type="button" class="category-btn" data-category="objects" aria-label="Objects">
        <i class="bi bi-lightbulb"></i>
      </button>
      <button type="button" class="category-btn" data-category="symbols" aria-label="Symbols">
        <i class="bi bi-heart"></i>
      </button>
      <button type="button" class="category-btn" data-category="flags" aria-label="Flags">
        <i class="bi bi-flag"></i>
      </button>
    </div>
    <button type="button" class="btn btn-sm btn-ghost" id="close-emoji-picker" aria-label="Close emoji picker">
      <i class="bi bi-x"></i>
    </button>
  </div>
  
  <div class="emoji-search-container p-2 border-bottom">
    <div class="input-group input-group-sm">
      <span class="input-group-text bg-transparent border-end-0">
        <i class="bi bi-search"></i>
      </span>
      <input type="text" 
             class="form-control border-start-0" 
             id="emoji-search" 
             placeholder="Search emojis..."
             aria-label="Search emojis">
    </div>
  </div>

  <div class="skin-tones-container p-2 border-bottom" style="display: none;">
    <div class="d-flex gap-1 justify-content-center">
      <button type="button" class="skin-tone-btn" data-tone="default" aria-label="Default skin tone">👋</button>
      <button type="button" class="skin-tone-btn" data-tone="light" aria-label="Light skin tone">👋🏻</button>
      <button type="button" class="skin-tone-btn" data-tone="medium-light" aria-label="Medium-light skin tone">👋🏼</button>
      <button type="button" class="skin-tone-btn" data-tone="medium" aria-label="Medium skin tone">👋🏽</button>
      <button type="button" class="skin-tone-btn" data-tone="medium-dark" aria-label="Medium-dark skin tone">👋🏾</button>
      <button type="button" class="skin-tone-btn" data-tone="dark" aria-label="Dark skin tone">👋🏿</button>
    </div>
  </div>

  <div class="emoji-content">
    <div class="emoji-section" id="recent-section" data-category="recent">
      <div class="emoji-section-header small fw-semibold p-2">Recently Used</div>
      <div class="emoji-grid p-2" id="recent-grid"></div>
    </div>
    
    <div class="emoji-section" id="search-results-section" data-category="search" style="display: none;">
      <div class="emoji-section-header small fw-semibold p-2">Search Results</div>
      <div class="emoji-grid p-2" id="search-results-grid"></div>
    </div>

    <div class="emoji-sections-container" id="category-sections">
      <!-- Category sections will be populated by JavaScript -->
    </div>
  </div>
</div>

<style>
.emoji-picker-container {
  position: fixed;
  bottom: 80px;
  right: 16px;
  width: 350px;
  height: 400px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  z-index: 1060;
  display: flex;
  flex-direction: column;
  animation: slideUpFade 0.2s ease-out;
}

@keyframes slideUpFade {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.emoji-picker-header {
  background: var(--card);
  border-radius: 12px 12px 0 0;
  flex-shrink: 0;
}

.emoji-categories {
  flex: 1;
  overflow-x: auto;
}

.category-btn {
  background: none;
  border: none;
  border-radius: 6px;
  padding: 6px 8px;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.15s ease;
  color: var(--text-muted);
}

.category-btn:hover {
  background: var(--primary-bg-subtle);
  color: var(--text);
}

.category-btn.active {
  background: var(--primary);
  color: white;
}

.emoji-search-container {
  flex-shrink: 0;
}

.emoji-content {
  flex: 1;
  overflow-y: auto;
  position: relative;
}

.emoji-section {
  display: none;
}

.emoji-section.active {
  display: block;
}

.emoji-section-header {
  background: var(--card);
  position: sticky;
  top: 0;
  z-index: 1;
  border-bottom: 1px solid var(--border);
}

.emoji-grid {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 4px;
}

.emoji-btn {
  background: none;
  border: none;
  border-radius: 8px;
  padding: 8px;
  font-size: 1.2rem;
  cursor: pointer;
  transition: all 0.15s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}

.emoji-btn:hover {
  background: var(--primary-bg-subtle);
  transform: scale(1.1);
}

.emoji-btn:focus {
  outline: 2px solid var(--primary);
  outline-offset: 1px;
}

.skin-tones-container {
  flex-shrink: 0;
  animation: fadeIn 0.2s ease;
}

.skin-tone-btn {
  background: none;
  border: none;
  border-radius: 6px;
  padding: 4px 6px;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.15s ease;
}

.skin-tone-btn:hover {
  background: var(--primary-bg-subtle);
  transform: scale(1.05);
}

.skin-tone-btn.active {
  background: var(--primary);
}

.no-emojis {
  text-align: center;
  padding: 2rem;
  color: var(--text-muted);
  font-style: italic;
}

/* Scrollbar styling */
.emoji-content::-webkit-scrollbar {
  width: 6px;
}

.emoji-content::-webkit-scrollbar-track {
  background: transparent;
}

.emoji-content::-webkit-scrollbar-thumb {
  background: color-mix(in srgb, var(--text) 30%, transparent);
  border-radius: 3px;
}

.emoji-content::-webkit-scrollbar-thumb:hover {
  background: color-mix(in srgb, var(--text) 50%, transparent);
}

/* Context-specific styling */
[data-context="group"] .emoji-picker-container {
  border-color: var(--group-accent);
}

[data-context="group"] .emoji-btn:hover {
  background: color-mix(in srgb, var(--group-accent) 10%, transparent);
}

/* Responsive Design */
@media (max-width: 768px) {
  .emoji-picker-container {
    right: 8px;
    left: 8px;
    width: auto;
    bottom: 70px;
    height: 380px;
  }

  .emoji-grid {
    grid-template-columns: repeat(6, 1fr);
  }
}

@media (max-width: 576px) {
  .emoji-picker-container {
    height: 350px;
  }

  .emoji-grid {
    grid-template-columns: repeat(5, 1fr);
  }
  
  .emoji-categories {
    gap: 0.5rem;
  }
  
  .category-btn {
    padding: 4px 6px;
    font-size: 0.8rem;
  }
}

/* Animations */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.emoji-fade-in {
  animation: fadeIn 0.2s ease;
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
  .emoji-picker-container,
  .emoji-btn,
  .skin-tone-btn,
  .emoji-fade-in {
    animation: none;
    transition: none;
  }
}
</style>

<script>
class AdvancedEmojiPicker {
  constructor() {
    this.container = document.getElementById('emoji-picker-container');
    this.emojiContent = document.querySelector('.emoji-content');
    this.categorySections = document.getElementById('category-sections');
    this.closeButton = document.getElementById('close-emoji-picker');
    this.emojiButton = document.getElementById('emoji-btn');
    this.messageInput = document.getElementById('message-input');
    this.searchInput = document.getElementById('emoji-search');
    
    this.isVisible = false;
    this.currentCategory = 'recent';
    this.currentSkinTone = 'default';
    this.recentEmojis = this.getRecentEmojis();
    this.emojiData = this.getEmojiData();
    this.searchTimeout = null;
    
    this.skinToneModifiers = {
      'light': '🏻',
      'medium-light': '🏼',
      'medium': '🏽',
      'medium-dark': '🏾',
      'dark': '🏿'
    };
    
    this.init();
  }

  init() {
    this.setupCategories();
    this.setupEventListeners();
    this.loadCategory('recent');
    console.log('Advanced emoji picker initialized');
  }

  getRecentEmojis() {
    try {
      const recent = localStorage.getItem('emoji-recent');
      return recent ? JSON.parse(recent) : [];
    } catch (e) {
      console.warn('Could not load recent emojis:', e);
      return [];
    }
  }

  saveRecentEmoji(emoji) {
    // Remove if already exists
    this.recentEmojis = this.recentEmojis.filter(e => e !== emoji);
    
    // Add to beginning
    this.recentEmojis.unshift(emoji);
    
    // Keep only last 20
    this.recentEmojis = this.recentEmojis.slice(0, 20);
    
    try {
      localStorage.setItem('emoji-recent', JSON.stringify(this.recentEmojis));
    } catch (e) {
      console.warn('Could not save recent emojis:', e);
    }
  }

  getEmojiData() {
    return {
      smileys: [
        '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚',
        '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣',
        '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '😥', '😓', '🤗',
        '🤔', '🤭', '🤫', '🤥', '😶', '😐', '😑', '😬', '🙄', '😯', '😦', '😧', '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐'
      ],
      animals: [
        '🐵', '🐒', '🦍', '🦧', '🐶', '🐕', '🦮', '🐩', '🐺', '🦊', '🦝', '🐱', '🐈', '🦁', '🐯', '🐅', '🐆', '🐴', '🐎', '🦄',
        '🦓', '🦌', '🐮', '🐂', '🐃', '🐄', '🐷', '🐖', '🐗', '🐽', '🐏', '🐑', '🐐', '🐪', '🐫', '🦙', '🦒', '🐘', '🦏', '🦛',
        '🐭', '🐁', '🐀', '🐹', '🐰', '🐇', '🐿️', '🦔', '🦇', '🐻', '🐨', '🐼', '🦥', '🦦', '🦨', '🦘', '🦡', '🐾', '🦃', '🐔',
        '🐓', '🐣', '🐤', '🐥', '🐦', '🐧', '🕊️', '🦅', '🦆', '🦢', '🦉', '🦩', '🦚', '🦜', '🐸', '🐊', '🐢', '🦎', '🐍', '🐲'
      ],
      food: [
        '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🫐', '🍈', '🍒', '🍑', '🥭', '🍍', '🥥', '🥝', '🍅', '🍆', '🥑', '🥦',
        '🥬', '🥒', '🌶️', '🫑', '🌽', '🥕', '🫒', '🧄', '🧅', '🥔', '🍠', '🥐', '🥯', '🍞', '🥖', '🥨', '🧀', '🥚', '🍳', '🧇',
        '🥞', '🧈', '🍤', '🍗', '🍖', '🦴', '🌭', '🍔', '🍟', '🍕', '🫓', '🥪', '🥙', '🧆', '🌮', '🌯', '🫔', '🥗', '🥘', '🫕'
      ],
      travel: [
        '🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚐', '🛻', '🚚', '🚛', '🚜', '🏍️', '🛵', '🚲', '🛴', '🛹', '🛼',
        '🚁', '✈️', '🛩️', '🛫', '🛬', '🪂', '💺', '🚀', '🛸', '🚉', '🚊', '🚝', '🚞', '🚋', '🚃', '🚠', '🚡', '🚢', '⛵', '🛶',
        '🚤', '🛳️', '⛴️', '🛥️', '🚧', '⚓', '⛽', '🚏', '🚦', '🚥', '🗺️', '🧭', '🏖️', '🏝️', '🏜️', '🌋', '🗻', '⛰️', '🏔️', '🛤️'
      ],
      activities: [
        '⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🪀', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏', '🪃', '🥅', '⛳',
        '🪁', '🎣', '🤿', '🎽', '🎿', '🛷', '🥌', '🎯', '🎱', '🔫', '🎮', '🕹️', '🎲', '♟️', '🎭', '🩰', '🎨', '🎪', '🎤', '🎧'
      ],
      objects: [
        '💡', '🔦', '🏮', '🪔', '📔', '📕', '📖', '📗', '📘', '📙', '📚', '📓', '📒', '📃', '📜', '📄', '📰', '🗞️', '📑', '🔖',
        '🏷️', '💰', '🪙', '💴', '💵', '💶', '💷', '💸', '💳', '🧾', '✉️', '📧', '📨', '📩', '📤', '📥', '📦', '📫', '📪', '📬'
      ],
      symbols: [
        '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮️',
        '✝️', '☪️', '🕉️', '☸️', '✡️', '🔯', '🕎', '☯️', '☦️', '🛐', '⛎', '♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐'
      ],
      flags: [
        '🏳️', '🏴', '🏁', '🚩', '🏳️‍🌈', '🏳️‍⚧️', '🏴‍☠️', '🇦🇫', '🇦🇱', '🇩🇿', '🇦🇸', '🇦🇩', '🇦🇴', '🇦🇮', '🇦🇶', '🇦🇬', '🇦🇷', '🇦🇲', '🇦🇼', '🇦🇺',
        '🇦🇹', '🇦🇿', '🇧🇸', '🇧🇭', '🇧🇩', '🇧🇧', '🇧🇾', '🇧🇪', '🇧🇿', '🇧🇯', '🇧🇲', '🇧🇹', '🇧🇴', '🇧🇦', '🇧🇼', '🇧🇷', '🇮🇴', '🇻🇬', '🇧🇳', '🇧🇬'
      ]
    };
  }

  setupCategories() {
    // Create category sections
    Object.keys(this.emojiData).forEach(category => {
      const section = document.createElement('div');
      section.className = 'emoji-section';
      section.id = `${category}-section`;
      section.dataset.category = category;
      
      const header = document.createElement('div');
      header.className = 'emoji-section-header small fw-semibold p-2';
      header.textContent = this.getCategoryName(category);
      
      const grid = document.createElement('div');
      grid.className = 'emoji-grid p-2';
      grid.id = `${category}-grid`;
      
      section.appendChild(header);
      section.appendChild(grid);
      this.categorySections.appendChild(section);
    });
  }

  getCategoryName(category) {
    const names = {
      'recent': 'Recently Used',
      'smileys': 'Smileys & People',
      'animals': 'Animals & Nature',
      'food': 'Food & Drink',
      'travel': 'Travel & Places',
      'activities': 'Activities',
      'objects': 'Objects',
      'symbols': 'Symbols',
      'flags': 'Flags'
    };
    return names[category] || category;
  }

  setupEventListeners() {
    // Close button
    if (this.closeButton) {
      this.closeButton.addEventListener('click', (e) => {
        e.stopPropagation();
        this.hide();
      });
    }

    // Category buttons
    document.querySelectorAll('.category-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const category = btn.dataset.category;
        this.switchCategory(category);
      });
    });

    // Search input
    if (this.searchInput) {
      this.searchInput.addEventListener('input', (e) => {
        this.handleSearch(e.target.value);
      });
      
      this.searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          e.target.value = '';
          this.handleSearch('');
          this.hide();
        }
      });
    }

    // Skin tone buttons
    document.querySelectorAll('.skin-tone-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.setSkinTone(btn.dataset.tone);
      });
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
      if (this.isVisible && 
          !this.container.contains(e.target) && 
          e.target !== this.emojiButton && 
          !this.emojiButton?.contains(e.target)) {
        this.hide();
      }
    });

    // Close with Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.isVisible) {
        this.hide();
      }
    });

    // Prevent closing when clicking inside the picker
    if (this.container) {
      this.container.addEventListener('click', (e) => {
        e.stopPropagation();
      });
    }
  }

  switchCategory(category) {
    // Update active category button
    document.querySelectorAll('.category-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.category === category);
    });

    this.currentCategory = category;
    this.loadCategory(category);
    
    // Show/hide skin tones for smileys category
    const skinTonesContainer = document.querySelector('.skin-tones-container');
    if (skinTonesContainer) {
      skinTonesContainer.style.display = category === 'smileys' ? 'block' : 'none';
    }
  }

  loadCategory(category) {
    // Hide all sections
    document.querySelectorAll('.emoji-section').forEach(section => {
      section.classList.remove('active');
    });

    let section, emojis;

    if (category === 'recent') {
      section = document.getElementById('recent-section');
      emojis = this.recentEmojis;
    } else {
      section = document.getElementById(`${category}-section`);
      emojis = this.emojiData[category] || [];
    }

    if (section) {
      section.classList.add('active');
      const grid = section.querySelector('.emoji-grid');
      this.renderEmojis(grid, emojis, category === 'recent');
    }
  }

  renderEmojis(container, emojis, isRecent = false) {
    if (!container) return;

    if (emojis.length === 0) {
      container.innerHTML = `
        <div class="no-emojis" style="grid-column: 1 / -1;">
          ${isRecent ? 'No recent emojis' : 'No emojis found'}
        </div>
      `;
      return;
    }

    container.innerHTML = emojis.map(emoji => `
      <button type="button" class="emoji-btn emoji-fade-in" data-emoji="${emoji}" aria-label="Emoji ${emoji}">
        ${this.applySkinTone(emoji)}
      </button>
    `).join('');

    // Add click handlers
    container.querySelectorAll('.emoji-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const emoji = btn.getAttribute('data-emoji');
        this.insertEmoji(emoji);
      });
    });
  }

  applySkinTone(emoji) {
    if (this.currentSkinTone === 'default' || !this.skinToneModifiers[this.currentSkinTone]) {
      return emoji;
    }

    // Simple skin tone application (you can extend this with more complex logic)
    const toneModifier = this.skinToneModifiers[this.currentSkinTone];
    
    // Apply to common emojis that support skin tones
    const skinToneEmojis = {
      '👋': `👋${toneModifier}`,
      '👍': `👍${toneModifier}`,
      '👎': `👎${toneModifier}`,
      '👌': `👌${toneModifier}`,
      '✌️': `✌️${toneModifier}`,
      '🤞': `🤞${toneModifier}`,
      '🤟': `🤟${toneModifier}`,
      '🤘': `🤘${toneModifier}`,
      '👆': `👆${toneModifier}`,
      '👇': `👇${toneModifier}`,
    };

    return skinToneEmojis[emoji] || emoji;
  }

  setSkinTone(tone) {
    this.currentSkinTone = tone;
    
    // Update active skin tone button
    document.querySelectorAll('.skin-tone-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tone === tone);
    });

    // Re-render current category if it's smileys
    if (this.currentCategory === 'smileys') {
      this.loadCategory('smileys');
    }
  }

  handleSearch(query) {
    if (!query.trim()) {
      // Show current category when search is empty
      this.switchCategory(this.currentCategory);
      return;
    }

    // Hide all category sections
    document.querySelectorAll('.emoji-section').forEach(section => {
      section.classList.remove('active');
    });

    // Show search results section
    const searchSection = document.getElementById('search-results-section');
    const searchGrid = document.getElementById('search-results-grid');
    
    if (searchSection && searchGrid) {
      searchSection.classList.add('active');
      
      // Search through all emojis
      const allEmojis = Object.values(this.emojiData).flat();
      const searchResults = allEmojis.filter(emoji => 
        emoji.toLowerCase().includes(query.toLowerCase())
      );

      this.renderEmojis(searchGrid, searchResults);
    }
  }

  insertEmoji(emoji) {
    if (!this.messageInput) return;
    
    const finalEmoji = this.applySkinTone(emoji);
    
    const cursorPos = this.messageInput.selectionStart;
    const textBefore = this.messageInput.value.substring(0, cursorPos);
    const textAfter = this.messageInput.value.substring(cursorPos);
    
    this.messageInput.value = textBefore + finalEmoji + textAfter;
    
    // Update cursor position
    const newCursorPos = cursorPos + finalEmoji.length;
    this.messageInput.setSelectionRange(newCursorPos, newCursorPos);
    
    // Focus back to input
    this.messageInput.focus();
    
    // Save to recent emojis
    this.saveRecentEmoji(emoji);
    
    // Trigger input event for typing indicators and validation
    this.messageInput.dispatchEvent(new Event('input', { bubbles: true }));
    
    // Keep picker open so user can select multiple emojis
    // Picker will only close when user clicks close button, clicks outside, or presses Escape
  }

 show() {
  if (!this.container) return;
  
  this.container.style.display = 'flex';
  this.isVisible = true;
  
  // Clear search
  if (this.searchInput) {
    this.searchInput.value = '';
  }
  
  // Load recent emojis
  this.recentEmojis = this.getRecentEmojis();
  this.loadCategory(this.currentCategory);
  
  // Position the picker above the composer
  const composer = document.getElementById('chat-form');
  if (composer) {
    const composerRect = composer.getBoundingClientRect();
    this.container.style.bottom = `${window.innerHeight - composerRect.top + 10}px`;
  }
  
  // Focus search input
  setTimeout(() => {
    if (this.searchInput) {
      this.searchInput.focus(); // FIXED: Complete the method call
    }
  }, 100);
}
  hide() {
    if (!this.container) return;
    
    this.container.style.display = 'none';
    this.isVisible = false;
    
    // Clear search when hiding
    if (this.searchInput) {
      this.searchInput.value = '';
    }
    
    // Reset to recent category
    this.switchCategory('recent');
  }

  toggle() {
    if (this.isVisible) {
      this.hide();
    } else {
      this.show();
    }
  }

  // Public method to update the message input reference
  setMessageInput(inputElement) {
    this.messageInput = inputElement;
  }

  // Public method to update the emoji button reference
  setEmojiButton(buttonElement) {
    this.emojiButton = buttonElement;
    
    // Add click event to the emoji button if it exists
    if (this.emojiButton) {
      this.emojiButton.addEventListener('click', (e) => {
        e.stopPropagation();
        this.toggle();
      });
    }
  }

  // Method to handle dynamic context changes
  setContext(context) {
    if (this.container) {
      this.container.setAttribute('data-context', context);
    }
  }

  // Cleanup method
  destroy() {
    document.removeEventListener('click', this.boundHandleOutsideClick);
    document.removeEventListener('keydown', this.boundHandleEscape);
    
    if (this.emojiButton) {
      this.emojiButton.removeEventListener('click', this.boundHandleEmojiButtonClick);
    }
    
    if (this.container) {
      this.container.remove();
    }
  }
}

// Initialize the emoji picker when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  window.emojiPicker = new AdvancedEmojiPicker();
  
  // Auto-attach to common message input and emoji button if they exist
  const messageInput = document.getElementById('message-input');
  const emojiButton = document.getElementById('emoji-btn');
  
  if (messageInput) {
    window.emojiPicker.setMessageInput(messageInput);
  }
  
  if (emojiButton) {
    window.emojiPicker.setEmojiButton(emojiButton);
  }
  
  // Support for dynamic chat interfaces
  const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      if (!window.emojiPicker.messageInput) {
        const newMessageInput = document.getElementById('message-input');
        if (newMessageInput) {
          window.emojiPicker.setMessageInput(newMessageInput);
        }
      }
      
      if (!window.emojiPicker.emojiButton) {
        const newEmojiButton = document.getElementById('emoji-btn');
        if (newEmojiButton) {
          window.emojiPicker.setEmojiButton(newEmojiButton);
        }
      }
    });
  });
  
  // Start observing
  observer.observe(document.body, {
    childList: true,
    subtree: true
  });
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
  module.exports = AdvancedEmojiPicker;
}
</script>