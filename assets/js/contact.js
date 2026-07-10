/**
 * SIXandCO Contact Form Handler
 * Premium validation and AJAX submission
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // ============================================
    // DOM REFERENCES
    // ============================================
    
    const form = document.getElementById('contactForm');
    if (!form) return;

    const submitBtn = document.getElementById('submitBtn');
    const loadingEl = form.querySelector('.loading');
    const errorEl = form.querySelector('.error-message');
    const sentEl = form.querySelector('.sent-message');

    // CSRF Token
    const csrfInput = document.getElementById('csrf_token');
    if (csrfInput && !csrfInput.value) {
        csrfInput.value = generateCsrfToken();
    }

    // ============================================
    // HELPERS
    // ============================================
    
    function generateCsrfToken() {
        return Math.random().toString(36).substring(2, 15) + 
               Math.random().toString(36).substring(2, 15) + 
               Date.now().toString(36);
    }

    function showError(input, message) {
        const wrapper = input.closest('.input-wrapper');
        if (!wrapper) return;
        const errorEl = wrapper.querySelector('.field-error');
        if (!errorEl) return;
        errorEl.textContent = message;
        errorEl.style.display = 'block';
        input.style.borderColor = '#ef4444';
    }

    function hideError(input) {
        const wrapper = input.closest('.input-wrapper');
        if (!wrapper) return;
        const errorEl = wrapper.querySelector('.field-error');
        if (!errorEl) return;
        errorEl.style.display = 'none';
        input.style.borderColor = '';
    }

    function clearAllErrors() {
        document.querySelectorAll('.field-error').forEach(function(el) {
            el.style.display = 'none';
        });
        document.querySelectorAll('input, textarea').forEach(function(el) {
            el.style.borderColor = '';
        });
    }

    // ============================================
    // VALIDATORS
    // ============================================
    
    const validators = {
        name: function(value) {
            const trimmed = value.trim();
            if (trimmed.length < 2) return 'Please enter your full name (minimum 2 characters).';
            if (!/^[a-zA-Z\s\-']+$/.test(trimmed)) return 'Name can only contain letters, spaces, hyphens, and apostrophes.';
            return null;
        },
        email: function(value) {
            const trimmed = value.trim();
            if (!trimmed) return 'Email address is required.';
            const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
            if (!emailRegex.test(trimmed)) return 'Please enter a valid email address (e.g., name@domain.com).';
            return null;
        },
        phone: function(value) {
            const trimmed = value.trim();
            if (!trimmed) return null;
            const phoneRegex = /^(\+?\d{1,4}[\s\-]?)?(\(?\d{1,4}\)?[\s\-]?)?[\d\s\-]{5,15}$/;
            if (!phoneRegex.test(trimmed)) return 'Please enter a valid phone number (e.g., +27 62 031 6488).';
            return null;
        },
        subject: function(value) {
            const trimmed = value.trim();
            if (trimmed.length < 3) return 'Please enter a subject (minimum 3 characters).';
            if (trimmed.length > 200) return 'Subject is too long (maximum 200 characters).';
            return null;
        },
        message: function(value) {
            const trimmed = value.trim();
            if (trimmed.length < 10) return 'Please enter a detailed message (minimum 10 characters).';
            if (trimmed.length > 5000) return 'Message is too long (maximum 5000 characters).';
            return null;
        }
    };

    // ============================================
    // FIELD VALIDATION (Real-time)
    // ============================================
    
    const fields = ['name', 'email', 'phone', 'subject', 'message'];
    
    fields.forEach(function(fieldName) {
        const input = document.getElementById(fieldName);
        if (!input) return;
        
        // On blur - validate
        input.addEventListener('blur', function() {
            const validator = validators[fieldName];
            if (!validator) return;
            const error = validator(this.value);
            if (error) {
                showError(this, error);
            } else {
                hideError(this);
            }
        });

        // On input - clear error if valid
        input.addEventListener('input', function() {
            const errorEl = this.closest('.input-wrapper').querySelector('.field-error');
            if (!errorEl) return;
            if (errorEl.style.display === 'block') {
                const validator = validators[fieldName];
                if (!validator) return;
                const error = validator(this.value);
                if (!error) {
                    hideError(this);
                }
            }
        });
    });

    // ============================================
    // FORM SUBMISSION
    // ============================================
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Reset messages
        errorEl.style.display = 'none';
        sentEl.style.display = 'none';
        clearAllErrors();

        // Validate all fields
        let hasErrors = false;
        
        fields.forEach(function(fieldName) {
            const input = document.getElementById(fieldName);
            if (!input) return;
            
            const validator = validators[fieldName];
            if (!validator) return;
            
            const error = validator(input.value);
            if (error) {
                showError(input, error);
                hasErrors = true;
            }
        });

        if (hasErrors) {
            errorEl.textContent = 'Please fix the errors highlighted above.';
            errorEl.style.display = 'block';
            errorEl.style.background = '#fee2e2';
            errorEl.style.color = '#dc2626';
            errorEl.style.padding = '12px 16px';
            errorEl.style.borderRadius = '8px';
            errorEl.style.marginBottom = '16px';
            
            // Scroll to first error
            const firstError = document.querySelector('.field-error[style*="display: block"]');
            if (firstError) {
                firstError.closest('.input-group-custom').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }
            return;
        }

        // Update CSRF token
        if (csrfInput) {
            csrfInput.value = generateCsrfToken();
        }

        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>Sending...</span> <i class="bi bi-arrow-right-circle-fill"></i>';
        loadingEl.style.display = 'block';
        loadingEl.style.background = 'var(--section-gradient-light)';
        loadingEl.style.padding = '12px 16px';
        loadingEl.style.borderRadius = '8px';
        loadingEl.style.marginBottom = '16px';
        loadingEl.style.color = 'var(--default-color)';

        // Prepare form data
        const formData = new FormData(form);

        // Send AJAX request
        fetch('forms/contact.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loadingEl.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>Send Message</span> <i class="bi bi-arrow-right-circle-fill"></i>';

            if (data.success) {
                sentEl.style.display = 'block';
                sentEl.style.background = '#dcfce7';
                sentEl.style.color = '#16a34a';
                sentEl.style.padding = '12px 16px';
                sentEl.style.borderRadius = '8px';
                sentEl.style.marginBottom = '16px';
                sentEl.textContent = data.message || 'Your message has been sent successfully. We\'ll get back to you within 24 hours.';
                form.reset();
                // Clear any visible errors
                clearAllErrors();
            } else {
                // Show field-specific errors if available
                if (data.data && typeof data.data === 'object') {
                    let fieldErrors = data.data;
                    let firstErrorField = null;
                    for (let fieldName in fieldErrors) {
                        const input = document.getElementById(fieldName);
                        if (input) {
                            showError(input, fieldErrors[fieldName]);
                            if (!firstErrorField) firstErrorField = input;
                        }
                    }
                    if (firstErrorField) {
                        firstErrorField.closest('.input-group-custom').scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                    }
                }
                
                errorEl.style.display = 'block';
                errorEl.style.background = '#fee2e2';
                errorEl.style.color = '#dc2626';
                errorEl.style.padding = '12px 16px';
                errorEl.style.borderRadius = '8px';
                errorEl.style.marginBottom = '16px';
                errorEl.textContent = data.message || 'Something went wrong. Please try again or call us directly.';
            }
        })
        .catch(error => {
            loadingEl.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>Send Message</span> <i class="bi bi-arrow-right-circle-fill"></i>';
            
            errorEl.style.display = 'block';
            errorEl.style.background = '#fee2e2';
            errorEl.style.color = '#dc2626';
            errorEl.style.padding = '12px 16px';
            errorEl.style.borderRadius = '8px';
            errorEl.style.marginBottom = '16px';
            errorEl.textContent = 'Network error. Please check your connection and try again.';
        });
    });

}); // End DOMContentLoaded