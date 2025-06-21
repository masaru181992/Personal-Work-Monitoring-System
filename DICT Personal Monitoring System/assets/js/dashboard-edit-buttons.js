/**
 * Dashboard Edit Buttons
 * Handles the edit functionality for activity cards on the dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Handle edit button clicks using event delegation
    document.addEventListener('click', function(e) {
        // Check if the click was on an edit button or its icon
        const editBtn = e.target.closest('.edit-activity-btn');
        const icon = e.target.closest('.edit-activity-btn i');
        
        // Get the edit button element
        const targetBtn = editBtn || (icon ? icon.closest('.edit-activity-btn') : null);
        
        if (targetBtn) {
            e.preventDefault();
            e.stopPropagation();
            const activityId = targetBtn.getAttribute('data-activity-id');
            if (activityId) {
                editActivity(activityId);
            }
        }
    });

    // Function to handle editing an activity
    async function editActivity(activityId) {
        try {
            console.log('editActivity called with ID:', activityId);
            
            // Get modal elements
            const editModal = document.getElementById('editActivityModal');
            if (!editModal) {
                console.error('Edit modal element not found');
                showToast('Error', 'Could not load edit form', 'danger');
                return;
            }
            
            console.log('Modal element found, initializing...');
            
            // Make sure Bootstrap is available
            if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                console.error('Bootstrap Modal is not available');
                showToast('Error', 'Bootstrap is not loaded', 'danger');
                return;
            }
            
            // Initialize modal
            let modal = null;
            try {
                modal = new bootstrap.Modal(editModal);
                console.log('New Bootstrap Modal instance created');
            } catch (error) {
                console.error('Error creating modal instance:', error);
                showToast('Error', 'Failed to initialize edit form', 'danger');
                return;
            }
            
            const modalTitle = editModal.querySelector('.modal-title');
            const modalBody = editModal.querySelector('.modal-body');
            
            if (!modalTitle || !modalBody) {
                console.error('Modal title or body not found');
                showToast('Error', 'Form elements not found', 'danger');
                return;
            }
            
            // Show loading state
            modalTitle.textContent = 'Loading Activity...';
            modalBody.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading activity details...</p>
                </div>
            `;
            
            // Add event listener for when modal is hidden
            const handleModalHidden = () => {
                window.location.reload();
                editModal.removeEventListener('hidden.bs.modal', handleModalHidden);
            };
            
            // Show the modal
            console.log('Showing modal...');
            try {
                editModal.addEventListener('hidden.bs.modal', handleModalHidden);
                modal.show();
                console.log('Modal show method called');
            } catch (error) {
                console.error('Error showing modal:', error);
                // Fallback to manual show
                editModal.style.display = 'block';
                editModal.classList.add('show');
                document.body.classList.add('modal-open');
                
                // Add backdrop
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(backdrop);
                console.log('Modal shown with fallback method');
                
                // Add event listener for form submission using event delegation
                document.body.addEventListener('submit', function(e) {
                    if (e.target && e.target.matches('#editActivityForm')) {
                        handleFormSubmit(e);
                    }
                });
            }
            
            // Fetch activity details
            try {
                console.log('Fetching activity details...');
                const response = await fetch(`api/get_activity.php?id=${activityId}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.message || 'Failed to load activity');
                }
                
                console.log('Activity data loaded:', result.data);
                
                // Format dates for input fields (date only, no time)
                const formatDateForInput = (dateString) => {
                    if (!dateString) return '';
                    const date = new Date(dateString);
                    // Return date in YYYY-MM-DD format
                    return date.toISOString().split('T')[0];
                };
                
                // Populate the form
                modalTitle.textContent = 'Edit Activity';
                modalBody.innerHTML = `
                    <form id="editActivityForm">
                        <input type="hidden" id="activity_id" name="activity_id" value="${result.data.id}">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" value="${escapeHtml(result.data.title || '')}" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3">${escapeHtml(result.data.description || '')}</textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="${formatDateForInput(result.data.start_date)}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date (Optional)</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="${formatDateForInput(result.data.end_date)}">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="not started" ${result.data.status.toLowerCase() === 'not started' ? 'selected' : ''}>Not Started</option>
                                    <option value="in progress" ${result.data.status.toLowerCase() === 'in progress' ? 'selected' : ''}>In Progress</option>
                                    <option value="completed" ${result.data.status.toLowerCase() === 'completed' ? 'selected' : ''}>Completed</option>
                                    <option value="on hold" ${result.data.status.toLowerCase() === 'on hold' ? 'selected' : ''}>On Hold</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="project_id" class="form-label">Project (Optional)</label>
                                <select class="form-select" id="project_id" name="project_id">
                                    <option value="">-- Select Project --</option>
                                    ${result.projects ? result.projects.map(project => 
                                        `<option value="${project.id}" ${result.data.project_id == project.id ? 'selected' : ''}>
                                            ${escapeHtml(project.title || 'Untitled Project')}
                                        </option>`
                                    ).join('') : ''}
                                </select>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" id="cancelEditBtn" class="btn btn-secondary">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                Save Changes
                            </button>
                        </div>
                    </form>
                `;
                
                // Add form submission handler
                document.getElementById('editActivityForm').addEventListener('submit', handleFormSubmit);
                
                // Add click handler for cancel button
                document.getElementById('cancelEditBtn').addEventListener('click', function() {
                    window.location.reload();
                });
                
            } catch (error) {
                console.error('Error loading activity:', error);
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <h5 class="alert-heading">Error</h5>
                        <p>${escapeHtml(error.message || 'Failed to load activity details. Please try again.')}</p>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                `;
            }
            
        } catch (error) {
            console.error('Error editing activity:', error);
            showToast('Error', error.message || 'Failed to load activity details', 'danger');
            
            // Close the modal if it's open
            const editModal = document.getElementById('editActivityModal');
            if (editModal) {
                const modal = bootstrap.Modal.getInstance(editModal);
                if (modal) {
                    modal.hide();
                } else {
                    // If no instance exists, just hide it directly
                    editModal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                }
            }
        }
    }
    
    // Function to handle form submission
    async function handleFormSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        const spinner = submitButton.querySelector('.spinner-border');
        
        try {
            // Show loading state
            submitButton.disabled = true;
            spinner.classList.remove('d-none');
            
            // Log form data for debugging
            const formDataObj = {};
            formData.forEach((value, key) => formDataObj[key] = value);
            console.log('Submitting form data:', formDataObj);
            
            // Send the request with proper headers for AJAX verification
            const response = await fetch('api/save_activity.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            
            const result = await response.json();
            console.log('Save response:', result);
            
            if (!response.ok || !result.success) {
                throw new Error(result.message || `HTTP error! status: ${response.status}`);
            }
            
            // Show success message
            showToast('Success', 'Activity updated successfully!', 'success');
            
            // Close the modal
            const modalElement = document.getElementById('editActivityModal');
            if (modalElement) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                } else {
                    // Fallback if modal instance not found
                    modalElement.style.display = 'none';
                    document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                }
            }
            
            // Reload the page to reflect changes
            setTimeout(() => {
                window.location.reload();
            }, 1000);
            
        } catch (error) {
            console.error('Error saving activity:', error);
            
            // Show error message
            const errorMessage = error.message || 'Failed to save activity. Please try again.';
            showToast('Error', errorMessage, 'danger');
            
            // Scroll to top of form
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
        } finally {
            // Reset button state
            submitButton.disabled = false;
            spinner.classList.add('d-none');
        }
    }
    
    // Helper function to show toast notifications
    function showToast(title, message, type = 'info') {
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.position = 'fixed';
            toastContainer.style.top = '20px';
            toastContainer.style.right = '20px';
            toastContainer.style.zIndex = '1100';
            toastContainer.style.maxWidth = '350px';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast element
        const toastId = 'toast-' + Date.now();
        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = `toast show mb-3`;
        toast.role = 'alert';
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        // Set toast content based on type
        const typeClass = {
            'success': 'bg-success text-white',
            'error': 'bg-danger text-white',
            'warning': 'bg-warning text-dark',
            'info': 'bg-info text-white'
        }[type] || 'bg-light text-dark';
        
        toast.innerHTML = `
            <div class="toast-header ${typeClass}">
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body bg-white text-dark">
                ${message}
            </div>
        `;
        
        // Add to container
        toastContainer.appendChild(toast);
        
        // Auto-remove after delay
        setTimeout(() => {
            toast.classList.remove('show');
            toast.classList.add('hide');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 5000);
        
        // Handle close button
        const closeBtn = toast.querySelector('[data-bs-dismiss="toast"]');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                toast.classList.remove('show');
                toast.classList.add('hide');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            });
        }
    }
    
    // Helper function to escape HTML
    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return '';
        return unsafe
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
