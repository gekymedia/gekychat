/**
 * GekyChat Theme Manager
 * Manages Golden/White themes with Light/Dark modes for web
 */
class ThemeManager {
    constructor() {
        this.STORAGE_KEY = 'gekychat_theme_mode';
        this.themes = {
            golden_light: {
                name: 'Golden Light',
                primary: '#D4AF37',
                background: '#FFFAF0',
                surface: '#FFF8DC',
                text: '#8B7500',
                icon: 'gold_with_text',
                isDark: false,
                isGolden: true
            },
            golden_dark: {
                name: 'Golden Dark',
                primary: '#D4AF37',
                background: '#0F0D0A',
                surface: '#1A1410',
                text: '#D4AF37',
                icon: 'gold_with_text',
                isDark: true,
                isGolden: true
            },
            white_light: {
                name: 'Classic Light',
                primary: '#008069',
                background: '#F0F2F5',
                surface: '#FFFFFF',
                text: '#111827',
                icon: 'white_with_text',
                isDark: false,
                isGolden: false
            },
            white_dark: {
                name: 'Classic Dark',
                primary: '#008069',
                background: '#111B21',
                surface: '#202C33',
                text: '#E5E7EB',
                icon: 'white_with_text',
                isDark: true,
                isGolden: false
            }
        };
        
        this.currentTheme = this.loadTheme();
        this.applyTheme(this.currentTheme);
    }

    /**
     * Load theme from localStorage
     */
    loadTheme() {
        const saved = localStorage.getItem(this.STORAGE_KEY);
        return saved && this.themes[saved] ? saved : 'white_light';
    }

    /**
     * Save theme to localStorage
     */
    saveTheme(themeKey) {
        localStorage.setItem(this.STORAGE_KEY, themeKey);
        this.currentTheme = themeKey;
    }

    /**
     * Apply theme to document
     */
    applyTheme(themeKey) {
        const theme = this.themes[themeKey];
        if (!theme) return;

        const root = document.documentElement;
        
        // Set CSS variables
        root.style.setProperty('--primary-color', theme.primary);
        root.style.setProperty('--background-color', theme.background);
        root.style.setProperty('--surface-color', theme.surface);
        root.style.setProperty('--text-color', theme.text);
        
        // Update body class - check if body exists first
        if (document.body) {
            document.body.classList.remove('theme-golden-light', 'theme-golden-dark', 'theme-white-light', 'theme-white-dark');
            document.body.classList.add(`theme-${themeKey.replace('_', '-')}`);
            
            // Update data attributes
            document.body.dataset.theme = themeKey;
            document.body.dataset.brightness = theme.isDark ? 'dark' : 'light';
            document.body.dataset.colorScheme = theme.isGolden ? 'golden' : 'white';
        } else {
            // If body doesn't exist yet, wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.applyTheme(themeKey));
            }
        }
        
        // Update favicon
        this.updateFavicon(theme.icon);
        
        // Dispatch event for other components
        window.dispatchEvent(new CustomEvent('themeChanged', { 
            detail: { themeKey, theme } 
        }));
    }

    /**
     * Update favicon based on theme
     */
    updateFavicon(iconFolder) {
        const sizes = [16, 32, 48, 96];
        
        // Remove old favicons
        document.querySelectorAll('link[rel*="icon"]').forEach(link => link.remove());
        
        // Add new favicons
        sizes.forEach(size => {
            const link = document.createElement('link');
            link.rel = 'icon';
            link.type = 'image/png';
            link.sizes = `${size}x${size}`;
            link.href = `/icons/theme/${iconFolder}/${size}x${size}.png`;
            document.head.appendChild(link);
        });
    }

    /**
     * Set theme
     */
    setTheme(themeKey) {
        if (!this.themes[themeKey]) return;
        
        this.saveTheme(themeKey);
        this.applyTheme(themeKey);
    }

    /**
     * Toggle brightness (light/dark)
     */
    toggleBrightness() {
        const current = this.themes[this.currentTheme];
        const newTheme = current.isDark
            ? (current.isGolden ? 'golden_light' : 'white_light')
            : (current.isGolden ? 'golden_dark' : 'white_dark');
        
        this.setTheme(newTheme);
    }

    /**
     * Toggle color scheme (golden/white)
     */
    toggleColorScheme() {
        const current = this.themes[this.currentTheme];
        const newTheme = current.isGolden
            ? (current.isDark ? 'white_dark' : 'white_light')
            : (current.isDark ? 'golden_dark' : 'golden_light');
        
        this.setTheme(newTheme);
    }

    /**
     * Get current theme info
     */
    getCurrentTheme() {
        return {
            key: this.currentTheme,
            ...this.themes[this.currentTheme]
        };
    }

    /**
     * Get all themes
     */
    getAllThemes() {
        return Object.entries(this.themes).map(([key, theme]) => ({
            key,
            ...theme
        }));
    }
}

// Initialize theme manager when DOM is ready
let themeManager;

function initializeThemeManager() {
    if (document.body) {
        try {
            themeManager = new ThemeManager();
            window.themeManager = themeManager;
            // Dispatch event so other scripts know theme manager is ready
            window.dispatchEvent(new CustomEvent('themeManagerReady'));
        } catch (error) {
            console.error('Failed to initialize theme manager:', error);
            // Retry after a short delay
            setTimeout(initializeThemeManager, 100);
        }
    } else {
        // If body doesn't exist yet, wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeThemeManager);
        } else {
            // If readyState is interactive or complete but body is still null, try again soon
            setTimeout(initializeThemeManager, 50);
        }
    }
}

// Start initialization
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeThemeManager);
} else {
    // DOM is already ready
    initializeThemeManager();
}
