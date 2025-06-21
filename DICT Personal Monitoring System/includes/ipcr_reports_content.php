<?php
// Get list of years with targets (for filter dropdown)
try {
    $year_stmt = $pdo->prepare("
        SELECT DISTINCT YEAR(target_date) as year 
        FROM ipcr_targets 
        WHERE user_id = ? OR ? = 1 
        ORDER BY year DESC
    ");
    $year_stmt->execute([$user_id, $is_admin ? 1 : 0]);
    $years = $year_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no years found, add current year
    if (empty($years)) {
        $years = [$current_year];
    }
    
    // Get categories for filter
    $category_stmt = $pdo->query("SELECT * FROM ipcr_categories ORDER BY name");
    $categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare base query
    $query = "
        SELECT 
            t.*, 
            c.name as category_name,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.employee_id,
            u.position,
            u.department,
            (t.quantity_accomplished / t.target_quantity * 100) as progress
        FROM ipcr_targets t
        JOIN ipcr_categories c ON t.category_id = c.id
        JOIN users u ON t.user_id = u.id
        WHERE (YEAR(t.target_date) = :year OR :year IS NULL)
    ";
    
    $params = [':year' => $selected_year];
    
    // Add quarter filter
    if ($selected_quarter > 0) {
        $start_month = (($selected_quarter - 1) * 3) + 1;
        $end_month = $start_month + 2;
        $query .= " AND MONTH(t.target_date) BETWEEN :start_month AND :end_month";
        $params[':start_month'] = $start_month;
        $params[':end_month'] = $end_month;
    }
    
    // Add status filter
    if ($selected_status !== 'all') {
        $query .= " AND t.status = :status";
        $params[':status'] = $selected_status;
    }
    
    // Add category filter
    if ($selected_category !== 'all') {
        $query .= " AND t.category_id = :category_id";
        $params[':category_id'] = $selected_category;
    }
    
    // Add user filter for non-admin users
    if (!$is_admin) {
        $query .= " AND t.user_id = :user_id";
        $params[':user_id'] = $user_id;
    }
    
    // Add sorting
    $query .= " ORDER BY t.target_date DESC, t.priority DESC, t.created_at DESC";
    
    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary statistics
    $summary = [
        'total_targets' => count($targets),
        'completed' => 0,
        'in_progress' => 0,
        'not_started' => 0,
        'on_hold' => 0,
        'cancelled' => 0,
        'average_progress' => 0,
        'by_category' => [],
        'by_quarter' => [
            'Q1' => ['total' => 0, 'completed' => 0],
            'Q2' => ['total' => 0, 'completed' => 0],
            'Q3' => ['total' => 0, 'completed' => 0],
            'Q4' => ['total' => 0, 'completed' => 0]
        ],
        'by_status' => [
            'Not Started' => 0,
            'In Progress' => 0,
            'Completed' => 0,
            'On Hold' => 0,
            'Cancelled' => 0
        ]
    ];
    
    // Process targets for summary
    foreach ($targets as $target) {
        $status = $target['status'];
        
        // Count by status
        if (isset($summary['by_status'][$status])) {
            $summary['by_status'][$status]++;
        }
        
        // Update status counts
        switch ($status) {
            case 'Completed':
                $summary['completed']++;
                break;
            case 'In Progress':
                $summary['in_progress']++;
                break;
            case 'Not Started':
                $summary['not_started']++;
                break;
            case 'On Hold':
                $summary['on_hold']++;
                break;
            case 'Cancelled':
                $summary['cancelled']++;
                break;
        }
        
        // Group by category
        $category_id = $target['category_id'];
        if (!isset($summary['by_category'][$category_id])) {
            $summary['by_category'][$category_id] = [
                'name' => $target['category_name'],
                'total' => 0,
                'completed' => 0,
                'in_progress' => 0,
                'not_started' => 0,
                'on_hold' => 0,
                'cancelled' => 0
            ];
        }
        $summary['by_category'][$category_id]['total']++;
        
        // Update category status counts
        switch ($status) {
            case 'Completed':
                $summary['by_category'][$category_id]['completed']++;
                break;
            case 'In Progress':
                $summary['by_category'][$category_id]['in_progress']++;
                break;
            case 'Not Started':
                $summary['by_category'][$category_id]['not_started']++;
                break;
            case 'On Hold':
                $summary['by_category'][$category_id]['on_hold']++;
                break;
            case 'Cancelled':
                $summary['by_category'][$category_id]['cancelled']++;
                break;
        }
        
        // Calculate progress
        $summary['average_progress'] += (float)$target['progress'];
        
        // Group by quarter
        $month = (int)date('n', strtotime($target['target_date']));
        $quarter = ceil($month / 3);
        $quarter_key = 'Q' . $quarter;
        
        if (isset($summary['by_quarter'][$quarter_key])) {
            $summary['by_quarter'][$quarter_key]['total']++;
            if ($status === 'Completed') {
                $summary['by_quarter'][$quarter_key]['completed']++;
            }
        }
    }
    
    // Calculate average progress
    if ($summary['total_targets'] > 0) {
        $summary['average_progress'] = round($summary['average_progress'] / $summary['total_targets'], 2);
    }
    
} catch (PDOException $e) {
    error_log("Error in IPCR reports: " . $e->getMessage());
    $error = "An error occurred while generating the report. Please try again later.";
}
?>

<!-- Filters Card -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter me-1"></i>
        Report Filters
    </div>
    <div class="card-body">
        <form method="get" action="" class="row g-3">
            <div class="col-md-3">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" id="year" name="year">
                    <?php foreach ($years as $year): ?>
                        <option value="<?= $year ?>" <?= $year == $selected_year ? 'selected' : '' ?>><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="quarter" class="form-label">Quarter</label>
                <select class="form-select" id="quarter" name="quarter">
                    <option value="0" <?= $selected_quarter == 0 ? 'selected' : '' ?>>All Quarters</option>
                    <option value="1" <?= $selected_quarter == 1 ? 'selected' : '' ?>>Q1 (Jan - Mar)</option>
                    <option value="2" <?= $selected_quarter == 2 ? 'selected' : '' ?>>Q2 (Apr - Jun)</option>
                    <option value="3" <?= $selected_quarter == 3 ? 'selected' : '' ?>>Q3 (Jul - Sep)</option>
                    <option value="4" <?= $selected_quarter == 4 ? 'selected' : '' ?>>Q4 (Oct - Dec)</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="all" <?= $selected_status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="Not Started" <?= $selected_status === 'Not Started' ? 'selected' : '' ?>>Not Started</option>
                    <option value="In Progress" <?= $selected_status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="Completed" <?= $selected_status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="On Hold" <?= $selected_status === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                    <option value="Cancelled" <?= $selected_status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="all" <?= $selected_category === 'all' ? 'selected' : '' ?>>All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= $selected_category == $category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sync-alt me-1"></i> Generate Report
                </button>
                <button type="button" class="btn btn-success" id="exportPdf">
                    <i class="fas fa-file-pdf me-1"></i> Export to PDF
                </button>
                <button type="button" class="btn btn-success" id="exportExcel">
                    <i class="fas fa-file-excel me-1"></i> Export to Excel
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php else: ?>
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Targets</h6>
                            <h2 class="mt-2 mb-0"><?= number_format($summary['total_targets']) ?></h2>
                        </div>
                        <i class="fas fa-bullseye fa-3x opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="#targetsTable">View Details</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Completed</h6>
                            <h2 class="mt-2 mb-0"><?= number_format($summary['completed']) ?></h2>
                        </div>
                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <span class="small text-white"><?= $summary['total_targets'] > 0 ? round(($summary['completed'] / $summary['total_targets']) * 100) : 0 ?>% of total</span>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-dark mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">In Progress</h6>
                            <h2 class="mt-2 mb-0"><?= number_format($summary['in_progress']) ?></h2>
                        </div>
                        <i class="fas fa-spinner fa-3x opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <span class="small text-dark"><?= $summary['total_targets'] > 0 ? round(($summary['in_progress'] / $summary['total_targets']) * 100) : 0 ?>% of total</span>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Average Progress</h6>
                            <h2 class="mt-2 mb-0"><?= number_format($summary['average_progress'], 1) ?>%</h2>
                        </div>
                        <i class="fas fa-chart-line fa-3x opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <span class="small text-white">Across all targets</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Status Distribution -->
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    Status Distribution
                </div>
                <div class="card-body">
                    <canvas id="statusChart" width="100%" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Quarterly Performance -->
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    Quarterly Performance (<?= $selected_year ?>)
                </div>
                <div class="card-body">
                    <canvas id="quarterlyChart" width="100%" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Category Performance -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-chart-bar me-1"></i>
            Performance by Category
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="categoryTable">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Total</th>
                            <th>Completed</th>
                            <th>In Progress</th>
                            <th>Not Started</th>
                            <th>On Hold</th>
                            <th>Cancelled</th>
                            <th>Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary['by_category'] as $category): ?>
                            <tr>
                                <td><?= htmlspecialchars($category['name']) ?></td>
                                <td><?= $category['total'] ?></td>
                                <td><?= $category['completed'] ?></td>
                                <td><?= $category['in_progress'] ?></td>
                                <td><?= $category['not_started'] ?></td>
                                <td><?= $category['on_hold'] ?></td>
                                <td><?= $category['cancelled'] ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <?php 
                                        $completion_rate = $category['total'] > 0 
                                            ? round(($category['completed'] / $category['total']) * 100) 
                                            : 0; 
                                        ?>
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?= $completion_rate ?>%" 
                                             aria-valuenow="<?= $completion_rate ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?= $completion_rate ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Targets Table -->
    <div class="card mb-4" id="targetsTable">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            IPCR Targets
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="targetsTableData" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Target Date</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Priority</th>
                            <?php if ($is_admin): ?>
                                <th>Assigned To</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($targets as $target): 
                            $target_date = new DateTime($target['target_date']);
                            $now = new DateTime();
                            $is_overdue = $now > $target_date && $target['status'] !== 'Completed';
                            
                            // Status badge class
                            $status_class = [
                                'Not Started' => 'bg-secondary',
                                'In Progress' => 'bg-primary',
                                'Completed' => 'bg-success',
                                'On Hold' => 'bg-warning',
                                'Cancelled' => 'bg-danger'
                            ][$target['status']] ?? 'bg-secondary';
                            
                            // Priority badge class
                            $priority_class = [
                                'Low' => 'bg-success',
                                'Medium' => 'bg-warning',
                                'High' => 'bg-danger'
                            ][$target['priority']] ?? 'bg-secondary';
                            
                            // Progress bar class
                            $progress = (float)$target['progress'];
                            $progress_class = 'bg-success';
                            if ($progress < 33) {
                                $progress_class = 'bg-danger';
                            } elseif ($progress < 66) {
                                $progress_class = 'bg-warning';
                            }
                        ?>
                            <tr class="<?= $is_overdue ? 'table-danger' : '' ?>">
                                <td>#<?= $target['id'] ?></td>
                                <td>
                                    <a href="#" class="text-decoration-none" 
                                       onclick="viewTargetDetails(<?= $target['id'] ?>); return false;">
                                        <?= htmlspecialchars($target['title']) ?>
                                    </a>
                                    <?php if ($is_overdue): ?>
                                        <span class="badge bg-danger ms-1">Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($target['category_name']) ?></td>
                                <td><?= $target_date->format('M j, Y') ?></td>
                                <td>
                                    <span class="badge <?= $status_class ?>">
                                        <?= $target['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated <?= $progress_class ?>" 
                                             role="progressbar" 
                                             style="width: <?= $progress ?>%" 
                                             aria-valuenow="<?= $progress ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?= round($progress) ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $priority_class ?>">
                                        <?= $target['priority'] ?>
                                    </span>
                                </td>
                                <?php if ($is_admin): ?>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-2">
                                                <?php 
                                                $initials = '';
                                                $name_parts = explode(' ', $target['user_name']);
                                                foreach ($name_parts as $part) {
                                                    $initials .= strtoupper(substr($part, 0, 1));
                                                }
                                                ?>
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                                                    <?= substr($initials, 0, 2) ?>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-medium"><?= htmlspecialchars($target['user_name']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($target['employee_id']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="viewTargetDetails(<?= $target['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($target['user_id'] == $user_id || $is_admin): ?>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick="editTarget(<?= $target['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Initialize DataTable and Charts -->
<script>
$(document).ready(function() {
    // Initialize DataTable
    const targetsTable = $('#targetsTableData').DataTable({
        responsive: true,
        order: [[3, 'asc']], // Sort by target date by default
        dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search targets...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No entries found",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: '❯',
                previous: '❮'
            }
        },
        columnDefs: [
            { orderable: false, targets: -1 } // Disable sorting on actions column
        ]
    });
    
    // Initialize category table
    $('#categoryTable').DataTable({
        responsive: true,
        order: [[0, 'asc']],
        dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search categories...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No entries found",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: '❯',
                previous: '❮'
            }
        }
    });
    
    // Status Distribution Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: [
                'Completed', 
                'In Progress', 
                'Not Started', 
                'On Hold', 
                'Cancelled'
            ],
            datasets: [{
                data: [
                    <?= $summary['by_status']['Completed'] ?? 0 ?>,
                    <?= $summary['by_status']['In Progress'] ?? 0 ?>,
                    <?= $summary['by_status']['Not Started'] ?? 0 ?>,
                    <?= $summary['by_status']['On Hold'] ?? 0 ?>,
                    <?= $summary['by_status']['Cancelled'] ?? 0 ?>
                ],
                backgroundColor: [
                    '#28a745', // Green for Completed
                    '#007bff', // Blue for In Progress
                    '#6c757d', // Gray for Not Started
                    '#ffc107', // Yellow for On Hold
                    '#dc3545'  // Red for Cancelled
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Quarterly Performance Chart
    const quarterlyCtx = document.getElementById('quarterlyChart').getContext('2d');
    const quarterlyChart = new Chart(quarterlyCtx, {
        type: 'bar',
        data: {
            labels: ['Q1', 'Q2', 'Q3', 'Q4'],
            datasets: [
                {
                    label: 'Total Targets',
                    data: [
                        <?= $summary['by_quarter']['Q1']['total'] ?? 0 ?>,
                        <?= $summary['by_quarter']['Q2']['total'] ?? 0 ?>,
                        <?= $summary['by_quarter']['Q3']['total'] ?? 0 ?>,
                        <?= $summary['by_quarter']['Q4']['total'] ?? 0 ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Completed',
                    data: [
                        <?= $summary['by_quarter']['Q1']['completed'] ?? 0 ?>,
                        <?= $summary['by_quarter']['Q2']['completed'] ?? 0 ?>,
                        <?= $summary['by_quarter']['Q3']['completed'] ?? 0 ?>,
                        <?= $summary['by_quarter']['Q4']['completed'] ?? 0 ?>
                    ],
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    type: 'bar'
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Targets'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Quarter'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        afterLabel: function(context) {
                            const dataset = context.dataset;
                            const total = dataset.data[context.dataIndex];
                            const completed = context.datasetIndex === 1 
                                ? total 
                                : context.chart.data.datasets[1].data[context.dataIndex];
                            
                            if (datasetIndex === 0) {
                                const completionRate = total > 0 ? Math.round((completed / total) * 100) : 0;
                                return `Completion Rate: ${completionRate}%`;
                            }
                            return '';
                        }
                    }
                },
                legend: {
                    position: 'top',
                }
            }
        }
    });
    
    // Export to PDF
    $('#exportPdf').on('click', function() {
        // This would be implemented using jsPDF and html2canvas
        alert('PDF export functionality will be implemented here');
    });
    
    // Export to Excel
    $('#exportExcel').on('click', function() {
        // This would be implemented using SheetJS
        alert('Excel export functionality will be implemented here');
    });
});

// Function to view target details
function viewTargetDetails(targetId) {
    // This would open the target details modal
    console.log('Viewing target:', targetId);
    // Implementation would go here
}

// Function to edit target
function editTarget(targetId) {
    // This would open the edit target modal
    console.log('Editing target:', targetId);
    // Implementation would go here
}
</script>
