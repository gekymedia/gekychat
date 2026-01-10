/**
 * Simple i18n implementation for JavaScript
 */

const translations = {
    en: {},
    es: {},
    fr: {},
    de: {},
    ar: {},
    zh: {},
    ja: {},
    pt: {},
    ru: {},
    hi: {},
};

let currentLocale = 'en';

// Initialize locale from HTML lang attribute or localStorage
function initLocale() {
    const htmlLang = document.documentElement.lang;
    const storedLocale = localStorage.getItem('locale');
    
    currentLocale = storedLocale || htmlLang || 'en';
    document.documentElement.lang = currentLocale;
}

// Translate function
function t(key, params = {}) {
    let translation = translations[currentLocale]?.[key] || translations['en']?.[key] || key;
    
    // Replace parameters
    Object.keys(params).forEach(param => {
        translation = translation.replace(`:${param}`, params[param]);
    });
    
    return translation;
}

// Set locale
function setLocale(locale) {
    if (translations[locale]) {
        currentLocale = locale;
        document.documentElement.lang = locale;
        localStorage.setItem('locale', locale);
        
        // Update page direction for RTL languages
        if (['ar', 'he', 'fa', 'ur'].includes(locale)) {
            document.documentElement.dir = 'rtl';
        } else {
            document.documentElement.dir = 'ltr';
        }
        
        // Trigger locale change event
        window.dispatchEvent(new CustomEvent('localechange', { detail: { locale } }));
    }
}

// Get current locale
function getLocale() {
    return currentLocale;
}

// Load translations from server
async function loadTranslations(locale) {
    try {
        const response = await fetch(`/lang/${locale}.json`);
        if (response.ok) {
            translations[locale] = await response.json();
        }
    } catch (error) {
        console.error(`Failed to load translations for ${locale}:`, error);
    }
}

// Initialize
initLocale();

// Export functions
window.i18n = {
    t,
    setLocale,
    getLocale,
    loadTranslations,
};
