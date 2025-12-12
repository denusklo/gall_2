// Load jQuery
window.$ = window.jQuery = require('jquery');

// Load Popper.js (required for Bootstrap)
require('popper.js');

// Load Bootstrap and make it globally available
window.bootstrap = require('bootstrap');

// Load iziToast
window.iziToast = require('izitoast');
require('izitoast/dist/css/iziToast.min.css');

// Fix for Bootstrap dropdowns on Vue pages
// Ensure Bootstrap's dropdown event listeners work properly after Vue mounts
document.addEventListener('DOMContentLoaded', function() {
    // Re-bind jQuery dropdown events after Vue apps might have interfered
    setTimeout(function() {
        // Re-initialize jQuery dropdown functionality
        $('[data-toggle="dropdown"]').dropdown();
    }, 100);

    // Also reinitialize on window load
    window.addEventListener('load', function() {
        $('[data-toggle="dropdown"]').dropdown();
    });
});

// Function to manually refresh dropdowns for Vue components
window.initializeBootstrapDropdowns = function() {
    // Use jQuery to reinitialize dropdowns
    $('[data-toggle="dropdown"]').dropdown();
};
