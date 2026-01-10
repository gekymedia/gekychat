@props(['currentLocale' => app()->getLocale()])

<div class="language-switcher" x-data="{ open: false }">
    <button @click="open = !open" class="language-button">
        <svg class="language-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
        </svg>
        <span class="language-label">{{ strtoupper($currentLocale) }}</span>
    </button>
    
    <div x-show="open" @click.away="open = false" class="language-dropdown">
        @foreach(config('app.supported_locales') as $locale)
            <a href="{{ url()->current() }}?lang={{ $locale }}" 
               class="language-option {{ $locale === $currentLocale ? 'active' : '' }}"
               onclick="setLocale('{{ $locale }}')">
                <span class="flag">{{ $flags[$locale] ?? '🌐' }}</span>
                <span class="name">{{ $localeNames[$locale] ?? $locale }}</span>
            </a>
        @endforeach
    </div>
</div>

@php
$flags = [
    'en' => '🇬🇧',
    'es' => '🇪🇸',
    'fr' => '🇫🇷',
    'de' => '🇩🇪',
    'ar' => '🇸🇦',
    'zh' => '🇨🇳',
    'ja' => '🇯🇵',
    'pt' => '🇵🇹',
    'ru' => '🇷🇺',
    'hi' => '🇮🇳',
];

$localeNames = [
    'en' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'ar' => 'العربية',
    'zh' => '中文',
    'ja' => '日本語',
    'pt' => 'Português',
    'ru' => 'Русский',
    'hi' => 'हिन्दी',
];
@endphp

<style>
.language-switcher {
    position: relative;
    display: inline-block;
}

.language-button {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: transparent;
    border: 1px solid #ddd;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
}

.language-button:hover {
    background: #f5f5f5;
}

.language-icon {
    width: 1.25rem;
    height: 1.25rem;
}

.language-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0.5rem;
    background: white;
    border: 1px solid #ddd;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    min-width: 200px;
    z-index: 1000;
}

.language-option {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    text-decoration: none;
    color: #333;
    transition: background 0.2s;
}

.language-option:hover {
    background: #f5f5f5;
}

.language-option.active {
    background: #008069;
    color: white;
}

.language-option .flag {
    font-size: 1.5rem;
}

/* RTL Support */
[dir="rtl"] .language-dropdown {
    right: auto;
    left: 0;
}
</style>

<script>
function setLocale(locale) {
    if (typeof window.i18n !== 'undefined') {
        window.i18n.setLocale(locale);
    }
    
    // Store in localStorage
    localStorage.setItem('locale', locale);
    
    // Update server-side locale
    fetch('/api/user/locale', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ locale })
    });
}
</script>
