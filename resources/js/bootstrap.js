/**
 * resources/js/bootstrap.js
 * 
 * Bootstrap 5 initialization for Laravel + Vite
 */

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// Initialize Bootstrap components when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Auto-initialize all Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-initialize all Bootstrap popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    const popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    console.log('✅ Bootstrap initialized successfully');
});

// Global Bootstrap helper functions
window.showModal = function(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
};

window.hideModal = function(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        }
    }
};

export { bootstrap };