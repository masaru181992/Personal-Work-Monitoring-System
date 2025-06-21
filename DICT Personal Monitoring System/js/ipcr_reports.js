/**
 * IPCR Reports JavaScript
 * Handles the interactive functionality for the IPCR Reports page
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Export to PDF
    if (document.getElementById('exportPdf')) {
        document.getElementById('exportPdf').addEventListener('click', exportToPdf);
    }
    
    // Export to Excel
    if (document.getElementById('exportExcel')) {
        document.getElementById('exportExcel').addEventListener('click', exportToExcel);
    }
    
    // Initialize any other event listeners
    initializeEventListeners();
});

/**
 * Initialize event listeners for the reports page
 */
function initializeEventListeners() {
    // Add any additional event listeners here
    console.log('IPCR Reports initialized');
}

/**
 * Export the current report to PDF
 */
function exportToPdf() {
    // Show loading state
    const button = document.getElementById('exportPdf');
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Generating PDF...';
    
    // Simulate API call or processing
    setTimeout(() => {
        // Reset button state
        button.disabled = false;
        button.innerHTML = originalHtml;
        
        // Show success message
        showToast('Success', 'PDF generated successfully!', 'success');
        
        // In a real implementation, this would trigger the download
        console.log('PDF export would happen here');
        
        // Example of how you might implement the actual PDF generation:
        /*
        // You would need to include these libraries in your project
        const { jsPDF } = window.jspdf;
        
        const doc = new jsPDF();
        const title = 'IPCR Report - ' + new Date().toLocaleDateString();
        
        // Add title
        doc.setFontSize(18);
        doc.text(title, 14, 22);
        
        // Add a simple table (you would customize this with your actual data)
        doc.autoTable({
            head: [['ID', 'Title', 'Status', 'Progress']],
            body: [
                [1, 'Sample Target 1', 'In Progress', '50%'],
                [2, 'Sample Target 2', 'Completed', '100%'],
            ],
            startY: 30,
        });
        
        // Save the PDF
        doc.save('ipcr-report.pdf');
        */
    }, 1500);
}

/**
 * Export the current report to Excel
 */
function exportToExcel() {
    // Show loading state
    const button = document.getElementById('exportExcel');
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Generating Excel...';
    
    // Simulate API call or processing
    setTimeout(() => {
        // Reset button state
        button.disabled = false;
        button.innerHTML = originalHtml;
        
        // Show success message
        showToast('Success', 'Excel file generated successfully!', 'success');
        
        // In a real implementation, this would trigger the download
        console.log('Excel export would happen here');
        
        // Example of how you might implement the actual Excel export:
        /*
        // You would need to include the SheetJS library (xlsx) in your project
        const XLSX = window.XLSX;
        
        // Get the table data
        const table = document.getElementById('targetsTableData');
        const ws = XLSX.utils.table_to_sheet(table);
        
        // Create workbook and add the worksheet
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'IPCR Report');
        
        // Generate Excel file and trigger download
        XLSX.writeFile(wb, 'ipcr-report.xlsx');
        */
    }, 1500);
}

/**
 * Show a toast notification
 * @param {string} title - The title of the toast
 * @param {string} message - The message to display
 * @param {string} type - The type of toast (success, error, warning, info)
 */
function showToast(title, message, type = 'info') {
    // Check if toast container exists, if not create it
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.position = 'fixed';
        toastContainer.style.top = '20px';
        toastContainer.style.right = '20px';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast show align-items-center text-white bg-${type} border-0`;
    toast.role = 'alert';
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    // Add close button
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}</strong><br>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    // Add to container
    toastContainer.appendChild(toast);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        toast.classList.add('hide');
        
        // Remove from DOM after animation
        setTimeout(() => {
            toast.remove();
            
            // Remove container if no more toasts
            if (toastContainer && toastContainer.children.length === 0) {
                toastContainer.remove();
            }
        }, 500);
    }, 5000);
}

// Make functions available globally
window.viewTargetDetails = viewTargetDetails;
window.editTarget = editTarget;
