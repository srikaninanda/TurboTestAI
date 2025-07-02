// Test Management Framework - Main Application JavaScript

// Global application state
const App = {
    currentUser: null,
    currentProject: null,
    notifications: [],
    
    // Initialize the application
    init() {
        this.bindEvents();
        this.loadUserData();
        this.setupNotifications();
        this.initializeTooltips();
    },
    
    // Bind global event listeners
    bindEvents() {
        // Global search functionality
        const searchInput = document.querySelector('#global-search');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(this.handleGlobalSearch, 300));
        }
        
        // Auto-dismiss alerts
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.classList.contains('alert-success')) {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 3000);
            }
        });
        
        // Form validation
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', this.validateForm);
        });
        
        // AJAX form submissions
        const ajaxForms = document.querySelectorAll('form[data-ajax]');
        ajaxForms.forEach(form => {
            form.addEventListener('submit', this.handleAjaxForm);
        });
    },
    
    // Load current user data
    loadUserData() {
        // This would typically fetch from session or API
        this.currentUser = window.currentUser || null;
    },
    
    // Setup notification system
    setupNotifications() {
        this.checkForUpdates();
        setInterval(() => this.checkForUpdates(), 30000); // Check every 30 seconds
    },
    
    // Initialize Bootstrap tooltips
    initializeTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    },
    
    // Debounce function for search
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Handle global search
    handleGlobalSearch(event) {
        const query = event.target.value.trim();
        if (query.length >= 2) {
            // Implement search functionality
            console.log('Searching for:', query);
        }
    },
    
    // Validate forms
    validateForm(event) {
        const form = event.target;
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
            }
        });
        
        // Email validation
        const emailFields = form.querySelectorAll('input[type="email"]');
        emailFields.forEach(field => {
            if (field.value && !this.isValidEmail(field.value)) {
                field.classList.add('is-invalid');
                isValid = false;
            }
        });
        
        if (!isValid) {
            event.preventDefault();
            this.showAlert('Please fill in all required fields correctly.', 'danger');
        }
    },
    
    // Handle AJAX form submissions
    handleAjaxForm(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Show loading state
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        }
        
        fetch(form.action, {
            method: form.method,
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert(data.message || 'Operation completed successfully.', 'success');
                if (data.redirect) {
                    setTimeout(() => window.location.href = data.redirect, 1000);
                }
            } else {
                this.showAlert(data.message || 'An error occurred.', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showAlert('An unexpected error occurred.', 'danger');
        })
        .finally(() => {
            // Reset button state
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalText || 'Submit';
            }
        });
    },
    
    // Check for real-time updates
    checkForUpdates() {
        // Implementation for real-time notifications
        // This could fetch from an API endpoint for new activities, bugs, etc.
    },
    
    // Show alert messages
    showAlert(message, type = 'info') {
        const alertContainer = document.getElementById('alert-container') || document.body;
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        alertContainer.insertBefore(alert, alertContainer.firstChild);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
    },
    
    // Utility functions
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },
    
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    },
    
    // AJAX utility functions
    async apiCall(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const mergedOptions = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(url, mergedOptions);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API call failed:', error);
            throw error;
        }
    },
    
    // Loading state management
    showLoading(element) {
        if (element) {
            element.classList.add('loading');
            element.style.opacity = '0.6';
            element.style.pointerEvents = 'none';
        }
    },
    
    hideLoading(element) {
        if (element) {
            element.classList.remove('loading');
            element.style.opacity = '1';
            element.style.pointerEvents = 'auto';
        }
    },
    
    // Project management utilities
    setCurrentProject(projectId) {
        this.currentProject = projectId;
        localStorage.setItem('currentProject', projectId);
    },
    
    getCurrentProject() {
        return this.currentProject || localStorage.getItem('currentProject');
    }
};

// AI Integration Helper
const AIHelper = {
    // Show AI analysis modal
    showAnalysisModal(content, title = 'AI Analysis') {
        const modal = new bootstrap.Modal(document.getElementById('ai-modal') || this.createAIModal());
        document.querySelector('#ai-modal .modal-title').textContent = title;
        document.querySelector('#ai-modal .modal-body').innerHTML = content;
        modal.show();
    },
    
    // Create AI modal if it doesn't exist
    createAIModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'ai-modal';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">AI Analysis</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        return modal;
    },
    
    // Process AI request
    async processAIRequest(endpoint, data) {
        try {
            App.showLoading(document.body);
            const response = await App.apiCall(endpoint, {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            if (response.error) {
                App.showAlert(response.error, 'warning');
                return null;
            }
            
            return response;
        } catch (error) {
            App.showAlert('AI service is temporarily unavailable.', 'warning');
            return null;
        } finally {
            App.hideLoading(document.body);
        }
    }
};

// Initialize the application when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});

// Export for use in other modules
window.App = App;
window.AIHelper = AIHelper;
