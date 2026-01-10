@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-2" style="color: var(--text-color)">
                Theme Settings
            </h1>
            <p style="color: var(--text-secondary)">
                Customize your GekyChat experience
            </p>
        </div>

        <!-- Color Scheme Section -->
        <div class="card mb-6 p-6">
            <h2 class="text-xl font-semibold mb-4" style="color: var(--text-color)">
                Color Scheme
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Golden Theme -->
                <div class="theme-option" id="theme-golden" onclick="selectColorScheme('golden')">
                    <div class="flex items-center mb-3">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center mr-3" style="background-color: #D4AF37;">
                            <i class="fas fa-star text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--text-color)">Golden</h3>
                            <p class="text-sm" style="color: var(--text-secondary)">Elegant gold theme</p>
                        </div>
                    </div>
                    <div class="preview-box" style="background: linear-gradient(135deg, #FFF8DC 0%, #D4AF37 100%); height: 60px; border-radius: 8px;"></div>
                </div>

                <!-- Classic Theme -->
                <div class="theme-option" id="theme-white" onclick="selectColorScheme('white')">
                    <div class="flex items-center mb-3">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center mr-3" style="background-color: #008069;">
                            <i class="fas fa-chat-bubble text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--text-color)">Classic</h3>
                            <p class="text-sm" style="color: var(--text-secondary)">WhatsApp-style green</p>
                        </div>
                    </div>
                    <div class="preview-box" style="background: linear-gradient(135deg, #F0F2F5 0%, #008069 100%); height: 60px; border-radius: 8px;"></div>
                </div>
            </div>
        </div>

        <!-- Brightness Section -->
        <div class="card mb-6 p-6">
            <h2 class="text-xl font-semibold mb-4" style="color: var(--text-color)">
                Brightness
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Light Mode -->
                <div class="theme-option" id="brightness-light" onclick="selectBrightness('light')">
                    <div class="flex items-center mb-3">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center mr-3" style="background-color: var(--primary-color);">
                            <i class="fas fa-sun text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--text-color)">Light Mode</h3>
                            <p class="text-sm" style="color: var(--text-secondary)">Bright and clean</p>
                        </div>
                    </div>
                </div>

                <!-- Dark Mode -->
                <div class="theme-option" id="brightness-dark" onclick="selectBrightness('dark')">
                    <div class="flex items-center mb-3">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center mr-3" style="background-color: var(--primary-color);">
                            <i class="fas fa-moon text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold" style="color: var(--text-color)">Dark Mode</h3>
                            <p class="text-sm" style="color: var(--text-secondary)">Easy on the eyes</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview Section -->
        <div class="card p-6">
            <h2 class="text-xl font-semibold mb-4" style="color: var(--text-color)">
                Preview
            </h2>
            <div class="preview-container p-4 rounded-lg" style="background-color: var(--background-color);">
                <div class="flex items-start mb-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3" style="background-color: var(--primary-color);">
                        <i class="fas fa-user text-white"></i>
                    </div>
                    <div class="flex-1">
                        <div class="font-semibold mb-1" style="color: var(--text-color)">John Doe</div>
                        <div class="message-bubble" style="background-color: var(--surface-color); padding: 12px; border-radius: 8px;">
                            <p style="color: var(--text-color)">This is how your messages will look!</p>
                            <div class="flex items-center justify-end mt-2">
                                <i class="fas fa-check-double text-xs mr-1" style="color: var(--primary-color);"></i>
                                <span class="text-xs" style="color: var(--text-secondary);">12:30 PM</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-start justify-end">
                    <div class="message-bubble sent" style="background-color: var(--primary-color); padding: 12px; border-radius: 8px; max-width: 70%;">
                        <p class="text-white">Great! I love this theme!</p>
                        <div class="flex items-center justify-end mt-2">
                            <i class="fas fa-check-double text-xs mr-1 text-white opacity-75"></i>
                            <span class="text-xs text-white opacity-75">12:31 PM</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Access Buttons -->
        <div class="mt-6 flex gap-4">
            <button onclick="themeManager.toggleBrightness()" class="btn-primary flex-1">
                <i class="fas fa-adjust mr-2"></i>
                Toggle Brightness
            </button>
            <button onclick="themeManager.toggleColorScheme()" class="btn-primary flex-1">
                <i class="fas fa-palette mr-2"></i>
                Switch Color Scheme
            </button>
        </div>
    </div>
</div>

<script>
// Update UI based on current theme
function updateThemeUI() {
    const current = themeManager.getCurrentTheme();
    
    // Update color scheme selection
    document.querySelectorAll('[id^="theme-"]').forEach(el => {
        el.classList.remove('selected');
    });
    document.getElementById(`theme-${current.isGolden ? 'golden' : 'white'}`).classList.add('selected');
    
    // Update brightness selection
    document.querySelectorAll('[id^="brightness-"]').forEach(el => {
        el.classList.remove('selected');
    });
    document.getElementById(`brightness-${current.isDark ? 'dark' : 'light'}`).classList.add('selected');
}

function selectColorScheme(scheme) {
    const current = themeManager.getCurrentTheme();
    const newTheme = current.isDark 
        ? `${scheme}_dark` 
        : `${scheme}_light`;
    themeManager.setTheme(newTheme);
    updateThemeUI();
}

function selectBrightness(brightness) {
    const current = themeManager.getCurrentTheme();
    const newTheme = current.isGolden 
        ? `golden_${brightness}` 
        : `white_${brightness}`;
    themeManager.setTheme(newTheme);
    updateThemeUI();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', updateThemeUI);

// Listen for theme changes
window.addEventListener('themeChanged', updateThemeUI);
</script>
@endsection
