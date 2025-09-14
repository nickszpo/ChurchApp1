/**
 * St. Thomas Aquinas Parish Church Event and Resource Management System - Main JavaScript
 * Handles client-side interactions, form validations, and AJAX requests
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize date pickers
    initDatePickers();
    
    // Initialize time pickers
    initTimePickers();
    
    // Handle form submissions with AJAX
    initAjaxForms();
    
    // Initialize any tooltips or popovers that weren't auto-initialized
    initTooltipsAndPopovers();
    
    // Handle dynamic form fields (like adding/removing items)
    initDynamicForms();
    
    // Initialize any charts if needed
    initCharts();
});

/**
 * Initialize date pickers
 */
function initDatePickers() {
    const dateInputs = document.querySelectorAll('.datepicker');
    if (dateInputs.length > 0 && typeof flatpickr !== 'undefined') {
        flatpickr('.datepicker', {
            dateFormat: 'Y-m-d',
            allowInput: true,
            minDate: 'today',
            disable: [
                function(date) {
                    // Disable Sundays
                    return (date.getDay() === 0);
                }
            ]
        });
    }
}

/**
 * Initialize time pickers
 */
function initTimePickers() {
    const timeInputs = document.querySelectorAll('.timepicker');
    if (timeInputs.length > 0 && typeof flatpickr !== 'undefined') {
        flatpickr('.timepicker', {
            enableTime: true,
            noCalendar: true,
            dateFormat: 'H:i',
            time_24hr: true,
            minuteIncrement: 15,
            minTime: '09:00',
            maxTime: '17:00'
        });
    }
}

/**
 * Handle form submissions with AJAX
 */
function initAjaxForms() {
    const ajaxForms = document.querySelectorAll('.ajax-form');
    
    ajaxForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Disable submit button and show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            
            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else if (data.success) {
                    showAlert('success', data.message || 'Operation completed successfully');
                    
                    // If form has a reset-on-success attribute, reset it
                    if (this.hasAttribute('data-reset-on-success')) {
                        this.reset();
                    }
                    
                    // If there's a callback function to execute on success
                    if (this.hasAttribute('data-success-callback')) {
                        const callback = window[this.getAttribute('data-success-callback')];
                        if (typeof callback === 'function') {
                            callback(data);
                        }
                    }
                } else {
                    showAlert('danger', data.message || 'An error occurred. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred. Please try again.');
            })
            .finally(() => {
                // Re-enable submit button and restore original text
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    });
}

/**
 * Show alert message
 * @param {string} type - Alert type (success, danger, warning, info)
 * @param {string} message - Alert message
 * @param {number} timeout - Time in milliseconds to auto-dismiss (0 for no auto-dismiss)
 */
function showAlert(type, message, timeout = 5000) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // Prepend to the alerts container if it exists, otherwise to the main content
    const alertsContainer = document.getElementById('alerts-container') || document.querySelector('main');
    if (alertsContainer) {
        alertsContainer.insertAdjacentHTML('afterbegin', alertHtml);
        
        // Auto-dismiss after timeout if specified
        if (timeout > 0) {
            setTimeout(() => {
                const alert = alertsContainer.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, timeout);
        }
    }
}

/**
 * Initialize tooltips and popovers
 */
function initTooltipsAndPopovers() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Handle dynamic form fields (add/remove)
 */
function initDynamicForms() {
    // Add item button
    document.addEventListener('click', function(e) {
        if (e.target && e.target.matches('.add-item-btn')) {
            e.preventDefault();
            const container = document.querySelector(e.target.getAttribute('data-container'));
            const template = document.querySelector(e.target.getAttribute('data-template'));
            
            if (container && template) {
                const newItem = template.content.cloneNode(true);
                container.appendChild(newItem);
                
                // Re-initialize any date/time pickers in the new item
                if (typeof flatpickr !== 'undefined') {
                    const datePickers = container.querySelectorAll('.datepicker:not([readonly])');
                    const timePickers = container.querySelectorAll('.timepicker:not([readonly])');
                    
                    if (datePickers.length > 0) initDatePickers();
                    if (timePickers.length > 0) initTimePickers();
                }
                
                // Scroll to the new item
                container.lastElementChild.scrollIntoView({ behavior: 'smooth' });
            }
        }
        
        // Remove item button
        if (e.target && e.target.matches('.remove-item-btn')) {
            e.preventDefault();
            const item = e.target.closest('.item-row, .form-group');
            if (item) {
                item.remove();
            }
        }
    });
}

/**
 * Initialize charts using Chart.js if available
 */
function initCharts() {
    if (typeof Chart === 'undefined') return;
    
    const charts = document.querySelectorAll('[data-chart]');
    charts.forEach(chartEl => {
        const ctx = chartEl.getContext('2d');
        const type = chartEl.getAttribute('data-chart-type') || 'bar';
        const data = JSON.parse(chartEl.getAttribute('data-chart-data'));
        const options = JSON.parse(chartEl.getAttribute('data-chart-options') || '{}');
        
        new Chart(ctx, {
            type: type,
            data: data,
            options: options
        });
    });
}

/**
 * Format date to a readable format
 * @param {string} dateString - Date string to format
 * @param {string} format - Output format (default: 'MMM d, YYYY h:mm A')
 * @returns {string} Formatted date string
 */
function formatDate(dateString, format = 'MMM d, YYYY h:mm A') {
    if (!dateString) return '';
    return dayjs(dateString).format(format);
}

/**
 * Handle file upload preview
 * @param {HTMLElement} input - File input element
 * @param {HTMLElement} previewContainer - Container to show the preview
 */
function handleFileUploadPreview(input, previewContainer) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            if (input.files[0].type.startsWith('image/')) {
                previewContainer.innerHTML = `<img src="${e.target.result}" class="img-fluid" alt="Preview">`;
            } else {
                previewContainer.innerHTML = `<div class="p-3 bg-light rounded">
                    <i class="bi bi-file-earmark-text fs-1 d-block text-center"></i>
                    <p class="text-center mb-0">${input.files[0].name}</p>
                </div>`;
            }
            previewContainer.classList.remove('d-none');
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Make functions available globally
window.showAlert = showAlert;
window.formatDate = formatDate;
