<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

$page_title = 'Manage IPCR Categories';
$page_description = 'Manage IPCR categories and their settings';

// Include header
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">IPCR Categories</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">IPCR Categories</li>
    </ol>
    
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-tags me-1"></i>
                    IPCR Categories
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus me-1"></i> Add Category
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="categoriesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addCategoryForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                        <div class="invalid-feedback">Please provide a category name.</div>
                    </div>
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCategoryForm">
                <input type="hidden" id="editCategoryId" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editCategoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editCategoryName" name="name" required>
                        <div class="invalid-feedback">Please provide a category name.</div>
                    </div>
                    <div class="mb-3">
                        <label for="editCategoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editCategoryDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteCategoryModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this category?</p>
                <p class="mb-0"><strong>Note:</strong> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Category</button>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>

<!-- Page level plugins -->
<link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    const table = $('#categoriesTable').DataTable({
        ajax: {
            url: 'manage_ipcr_categories.php',
            dataSrc: 'data'
        },
        columns: [
            { data: 'id' },
            { data: 'name' },
            { data: 'description', defaultContent: 'N/A' },
            { 
                data: 'created_at',
                render: function(data) {
                    return new Date(data).toLocaleDateString();
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return `
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary edit-category" 
                                    data-id="${row.id}" 
                                    data-name="${row.name}" 
                                    data-description="${row.description || ''}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger delete-category" 
                                    data-id="${row.id}" 
                                    data-name="${row.name}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        order: [[0, 'desc']],
        responsive: true
    });

    // Handle add category form submission
    $('#addCategoryForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalBtnText = submitBtn.html();
        
        // Disable submit button and show loading state
        submitBtn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...'
        );
        
        // Reset validation
        form.find('.is-invalid').removeClass('is-invalid');
        
        // Submit form data via AJAX
        $.ajax({
            url: 'manage_ipcr_categories.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                name: $('#categoryName').val(),
                description: $('#categoryDescription').val()
            }),
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showToast('success', 'Success', 'Category added successfully');
                    
                    // Reset form and close modal
                    form.trigger('reset');
                    $('#addCategoryModal').modal('hide');
                    
                    // Reload table data
                    table.ajax.reload();
                } else {
                    // Show error message
                    showToast('error', 'Error', response.message || 'Failed to add category');
                    
                    // Show validation errors if any
                    if (response.errors) {
                        Object.keys(response.errors).forEach(field => {
                            $(`#${field}`).addClass('is-invalid');
                            $(`#${field} + .invalid-feedback`).text(response.errors[field]);
                        });
                    }
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while adding the category';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    console.error('Error parsing error response:', e);
                }
                showToast('error', 'Error', errorMessage);
            },
            complete: function() {
                // Re-enable submit button and restore original text
                submitBtn.prop('disabled', false).html(originalBtnText);
            }
        });
    });
    
    // Handle edit button click
    $('#categoriesTable').on('click', '.edit-category', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const description = $(this).data('description');
        
        // Set form values
        $('#editCategoryId').val(id);
        $('#editCategoryName').val(name);
        $('#editCategoryDescription').val(description || '');
        
        // Show modal
        $('#editCategoryModal').modal('show');
    });
    
    // Handle edit category form submission
    $('#editCategoryForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalBtnText = submitBtn.html();
        
        // Disable submit button and show loading state
        submitBtn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...'
        );
        
        // Reset validation
        form.find('.is-invalid').removeClass('is-invalid');
        
        // Submit form data via AJAX
        $.ajax({
            url: 'manage_ipcr_categories.php',
            type: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({
                id: $('#editCategoryId').val(),
                name: $('#editCategoryName').val(),
                description: $('#editCategoryDescription').val()
            }),
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showToast('success', 'Success', 'Category updated successfully');
                    
                    // Close modal
                    $('#editCategoryModal').modal('hide');
                    
                    // Reload table data
                    table.ajax.reload();
                } else {
                    // Show error message
                    showToast('error', 'Error', response.message || 'Failed to update category');
                    
                    // Show validation errors if any
                    if (response.errors) {
                        Object.keys(response.errors).forEach(field => {
                            $(`#edit${field.charAt(0).toUpperCase() + field.slice(1)}`).addClass('is-invalid');
                            $(`#edit${field.charAt(0).toUpperCase() + field.slice(1)} + .invalid-feedback`).text(response.errors[field]);
                        });
                    }
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while updating the category';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    console.error('Error parsing error response:', e);
                }
                showToast('error', 'Error', errorMessage);
            },
            complete: function() {
                // Re-enable submit button and restore original text
                submitBtn.prop('disabled', false).html(originalBtnText);
            }
        });
    });
    
    // Handle delete button click
    let categoryToDelete = null;
    
    $('#categoriesTable').on('click', '.delete-category', function() {
        categoryToDelete = {
            id: $(this).data('id'),
            name: $(this).data('name')
        };
        
        // Update modal content
        $('#deleteCategoryModal .modal-body p:first-child')
            .html(`Are you sure you want to delete the category <strong>"${categoryToDelete.name}"</strong>?`);
        
        // Show modal
        $('#deleteCategoryModal').modal('show');
    });
    
    // Handle confirm delete button click
    $('#confirmDeleteBtn').on('click', function() {
        if (!categoryToDelete) return;
        
        const btn = $(this);
        const originalBtnText = btn.html();
        
        // Disable button and show loading state
        btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...'
        );
        
        // Send delete request
        $.ajax({
            url: 'manage_ipcr_categories.php',
            type: 'DELETE',
            contentType: 'application/json',
            data: JSON.stringify({ id: categoryToDelete.id }),
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showToast('success', 'Success', 'Category deleted successfully');
                    
                    // Close modal
                    $('#deleteCategoryModal').modal('hide');
                    
                    // Reload table data
                    table.ajax.reload();
                } else {
                    // Show error message
                    showToast('error', 'Error', response.message || 'Failed to delete category');
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while deleting the category';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    console.error('Error parsing error response:', e);
                }
                showToast('error', 'Error', errorMessage);
            },
            complete: function() {
                // Re-enable button and restore original text
                btn.prop('disabled', false).html(originalBtnText);
                
                // Reset the category to delete
                categoryToDelete = null;
            }
        });
    });
    
    // Reset form when modal is closed
    $('.modal').on('hidden.bs.modal', function() {
        $(this).find('form').trigger('reset');
        $(this).find('.is-invalid').removeClass('is-invalid');
    });
    
    // Helper function to show toast notifications
    function showToast(type, title, message) {
        const toast = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}</strong><br>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        const $toast = $(toast);
        $('#toastContainer').append($toast);
        
        const toastBootstrap = new bootstrap.Toast($toast[0], { autohide: true, delay: 5000 });
        toastBootstrap.show();
        
        // Remove toast from DOM after it's hidden
        $toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
});
</script>

<!-- Toast container -->
<div id="toastContainer" class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <!-- Toasts will be inserted here -->
</div>
