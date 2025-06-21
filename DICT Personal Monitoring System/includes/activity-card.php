<?php
/**
 * Activity Card Template
 * 
 * @param array $activity Activity data
 * @param string $type Type of activity card ('upcoming' or 'in-progress')
 * @return string HTML for the activity card
 */
function get_activity_card_html($activity, $type = 'upcoming') {
    $start_date = new DateTime($activity['start_date']);
    $end_date = !empty($activity['end_date']) ? new DateTime($activity['end_date']) : null;
    $now = new DateTime();
    $now->setTime(0, 0, 0); // Reset time part for accurate day comparison
    
    // Calculate days until start/end
    $interval_to_start = $now->diff($start_date);
    $days_until_start = $interval_to_start->days * ($interval_to_start->invert ? -1 : 1);
    
    $days_until_due = $days_until_start;
    $is_overdue = false;
    
    if ($end_date) {
        $interval_to_end = $now->diff($end_date);
        $days_until_end = $interval_to_end->days * ($interval_to_end->invert ? -1 : 1);
        
        // If we're within the activity period
        if ($days_until_start <= 0 && $days_until_end >= 0) {
            $days_until_due = $days_until_end;
        } else {
            $days_until_due = $days_until_start;
        }
        
        $is_overdue = $days_until_end < 0;
    } else {
        $is_overdue = $days_until_start < 0;
    }
    
    // Format date range for better readability (date only)
    $now = new DateTime();
    $today = clone $now;
    $today->setTime(0, 0, 0);
    $tomorrow = (clone $today)->modify('+1 day');
    $in_one_week = (clone $today)->modify('+7 days');
    
    $date_format_long = 'M j, Y';
    
    $format_date = function($date) use ($today, $tomorrow, $in_one_week, $date_format_long) {
        $date_only = clone $date;
        $date_only->setTime(0, 0, 0);
        
        if ($date_only == $today) {
            return '<strong>Today</strong>';
        } elseif ($date_only == $tomorrow) {
            return '<strong>Tomorrow</strong>';
        } elseif ($date_only >= $today && $date_only <= $in_one_week) {
            return '<strong>' . $date->format('l') . '</strong>';
        } else {
            return $date->format($date_format_long);
        }
    };
    
    // Format the date range (date only)
    $date_range = '';
    if ($end_date && $start_date->format('Y-m-d') !== $end_date->format('Y-m-d')) {
        // Multi-day event
        $date_range = $format_date($start_date) . ' to ' . $format_date($end_date);
    } else {
        // Single day event
        $date_range = $format_date($start_date);
    }
    
    // Determine time indicator class and text
    $time_indicator = '';
    $time_class = '';
    
    if ($is_overdue) {
        $overdue_days = abs($end_date ? $days_until_end : $days_until_start);
        $time_indicator = $overdue_days > 0 ? "$overdue_days day" . ($overdue_days > 1 ? 's' : '') . ' overdue' : 'Overdue';
        $time_class = 'bg-danger text-white';
    } elseif ($days_until_due === 0) {
        $time_indicator = $type === 'in-progress' ? 'Due Today' : 'Today';
        $time_class = 'bg-warning text-dark';
    } elseif ($days_until_due === 1) {
        $time_indicator = $type === 'in-progress' ? 'Due Tomorrow' : 'Tomorrow';
        $time_class = 'bg-info text-white';
    } else {
        $time_indicator = $type === 'in-progress' 
            ? 'Due in ' . $days_until_due . ' day' . ($days_until_due > 1 ? 's' : '') 
            : 'In ' . $days_until_due . ' day' . ($days_until_due > 1 ? 's' : '');
        $time_class = 'bg-success text-white';
    }
    
    // Determine status class for the card
    $status_class = '';
    if ($is_overdue) {
        $status_class = 'overdue';
    } elseif ($days_until_due === 0) {
        $status_class = 'due-today';
    } elseif ($days_until_due === 1) {
        $status_class = 'due-tomorrow';
    } else {
        $status_class = 'upcoming';
    }
    
    ob_start();
    ?>
    <div class="activity-card <?= $status_class ?>" data-activity-id="<?= htmlspecialchars($activity['id']) ?>">
        <button class="edit-activity-btn" data-activity-id="<?= htmlspecialchars($activity['id']) ?>" title="Edit Activity">
            <i class="bi bi-pencil"></i>
        </button>
        <div class="d-flex justify-content-between align-items-start mb-2">
            <h6 class="activity-title mb-0"><?= htmlspecialchars($activity['title']) ?></h6>
            <?php if (!empty($activity['project_title'])): ?>
                <span class="badge project-badge ms-2">
                    <i class="bi bi-folder2-open me-1"></i><?= htmlspecialchars($activity['project_title']) ?>
                </span>
            <?php endif; ?>
        </div>
        
        <div class="activity-meta d-flex flex-wrap align-items-center gap-2">
            <div class="date-display d-flex align-items-center" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars($start_date->format('F j, Y' . ($end_date ? ' \t\o ' . $end_date->format('F j, Y') : ''))) ?>">
                <i class="bi bi-calendar-event me-2"></i>
                <span class="date-text"><?= $date_range ?></span>
            </div>
            <span class="badge rounded-pill <?= $time_class ?> text-uppercase fw-normal status-badge">
                <i class="bi bi-<?= $is_overdue ? 'exclamation-triangle' : 'clock' ?> me-1"></i> <?= $time_indicator ?>
            </span>
        </div>
        
        <?php if (!empty($activity['description'])): ?>
            <p class="activity-description"><?= nl2br(htmlspecialchars($activity['description'])) ?></p>
        <?php endif; ?>
        
        <?php if ($type === 'in-progress'): ?>
            <div class="activity-footer">
                <!-- Additional footer content for in-progress activities if needed -->
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
