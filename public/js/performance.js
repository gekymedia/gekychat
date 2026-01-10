/**
 * Web Performance Monitoring
 * Tracks Core Web Vitals and custom metrics
 */

// Track Core Web Vitals
function trackCoreWebVitals() {
    // LCP - Largest Contentful Paint
    new PerformanceObserver((list) => {
        const entries = list.getEntries();
        const lastEntry = entries[entries.length - 1];
        console.log('LCP:', lastEntry.renderTime || lastEntry.loadTime);
        sendToAnalytics('lcp', lastEntry.renderTime || lastEntry.loadTime);
    }).observe({ entryTypes: ['largest-contentful-paint'] });

    // FID - First Input Delay
    new PerformanceObserver((list) => {
        const entries = list.getEntries();
        entries.forEach((entry) => {
            console.log('FID:', entry.processingStart - entry.startTime);
            sendToAnalytics('fid', entry.processingStart - entry.startTime);
        });
    }).observe({ entryTypes: ['first-input'] });

    // CLS - Cumulative Layout Shift
    let clsScore = 0;
    new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
            if (!entry.hadRecentInput) {
                clsScore += entry.value;
            }
        }
        console.log('CLS:', clsScore);
        sendToAnalytics('cls', clsScore);
    }).observe({ entryTypes: ['layout-shift'] });
}

// Send metrics to analytics
function sendToAnalytics(metric, value) {
    if (typeof gtag !== 'undefined') {
        gtag('event', metric, {
            event_category: 'Web Vitals',
            value: Math.round(value),
            non_interaction: true,
        });
    }
}

// Lazy load images
function lazyLoadImages() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                observer.unobserve(img);
            }
        });
    }, {
        rootMargin: '50px'
    });

    images.forEach(img => imageObserver.observe(img));
}

// Initialize on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        trackCoreWebVitals();
        lazyLoadImages();
    });
} else {
    trackCoreWebVitals();
    lazyLoadImages();
}
