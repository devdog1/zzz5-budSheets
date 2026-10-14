<?php
/**
 * Budgets Management View
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

if (!has_permission('budsheets_admin')) {
    die('Access Denied: Admin permissions required.');
}

$message = '';
$error = '';
$systemUsers = budsheets_get_all_system_users();
$managedFys = budsheets_get_managed_fiscal_years();
$currentFy = budsheets_get_fiscal_year();
$filterFyParam = $_GET['fy'] ?? null;
$selectedFy = ($filterFyParam === 'all') ? null : (isset($filterFyParam) && $filterFyParam !== '' ? (int)$filterFyParam : $currentFy);
$currentUserId = $_SESSION['user_id'] ?? 0;

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('validate_csrf')) {
        validate_csrf();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_lob' || $action === 'update_lob') {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $fiscal_year = (int)($_POST['fiscal_year'] ?? $currentFy);
        $assigned_user_ids = $_POST['assigned_user_ids'] ?? [];
        $user_access_levels = $_POST['user_access_levels'] ?? [];

        $user_assignments = [];
        foreach ($assigned_user_ids as $uid) {
            $uid = (int)$uid;
            $level = $user_access_levels[$uid] ?? 'full';
            $user_assignments[$uid] = $level;
        }

        if (empty($name)) {
            $error = 'Budget name is required.';
        } else {
            if ($action === 'create_lob') {
                if (budsheets_add_lob($name, $code, $description, $user_assignments, $fiscal_year)) {
                    $message = 'Budget created successfully.';
                } else {
                    $error = 'Failed to create Budget.';
                }
            } else {
                $lob_id = (int)($_POST['lob_id'] ?? 0);
                if (budsheets_update_lob($lob_id, $name, $code, $description, $user_assignments, $fiscal_year)) {
                    $message = 'Budget updated successfully.';
                } else {
                    $error = 'Failed to update Budget.';
                }
            }
        }
    } elseif ($action === 'clone_lob') {
        $source_id = (int)($_POST['source_lob_id'] ?? 0);
        $target_name = trim($_POST['target_name'] ?? '');
        $target_code = trim($_POST['target_code'] ?? '');
        $target_fy = (int)($_POST['target_fiscal_year'] ?? $currentFy);
        $clone_type = ($_POST['clone_type'] ?? 'full') === 'soft' ? 'soft' : 'full';

        if (!$source_id || empty($target_name) || !$target_fy) {
            $error = 'Source budget, target name, and target financial year are required.';
        } else {
            $clonedId = budsheets_clone_budget($source_id, $target_name, $target_code, $target_fy, $clone_type);
            if ($clonedId) {
                $message = ($clone_type === 'full') ? "Budget cloned successfully with items and financial schedules for FY{$target_fy}." : "Budget soft-cloned successfully with items (without financials) for FY{$target_fy}.";
            } else {
                $error = 'Failed to clone Budget.';
            }
        }
    } elseif ($action === 'delete_lob') {
        $lob_id = (int)($_POST['lob_id'] ?? 0);
        if ($lob_id) {
            if (budsheets_delete_lob($lob_id)) {
                $message = 'Budget deleted successfully.';
            } else {
                $error = 'Failed to delete Budget.';
            }
        }
    } elseif ($action === 'add_comment') {
        $lob_id = (int)($_POST['lob_id'] ?? 0);
        $commentText = trim($_POST['comment_text'] ?? '');
        if ($lob_id && !empty($commentText)) {
            budsheets_add_comment('budget', $lob_id, $commentText);
            $message = 'Budget comment added successfully.';
        } else {
            $error = 'Comment text cannot be empty.';
        }
    } elseif ($action === 'delete_comment') {
        $commentId = (int)($_POST['comment_id'] ?? 0);
        if ($commentId) {
            budsheets_delete_comment($commentId);
            $message = 'Comment removed.';
        }
    }
}

$lobs = budsheets_get_lobs(null, $selectedFy);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-sitemap me-2 text-primary"></i>Budgets</h2>
            <p class="text-muted small mb-0">Manage organizational budgets assigned to single financial years, clone budgets, post comments, and configure user access permissions.</p>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <!-- Filter Bar for Financial Year -->
            <form method="GET" class="d-flex align-items-center me-2">
                <input type="hidden" name="route" value="budsheets_lobs">
                <label for="fy_filter" class="form-label mb-0 me-2 fw-semibold text-muted small"><i class="fa-solid fa-calendar-days me-1"></i>Financial Year:</label>
                <select name="fy" id="fy_filter" class="form-select form-select-sm fw-bold border-primary text-primary" onchange="this.form.submit()">
                    <option value="all" <?= $filterFyParam === 'all' ? 'selected' : '' ?>>-- All Financial Years --</option>
                    <?php
                    $fyYears = array_column($managedFys, 'year');
                    if (!in_array($currentFy, $fyYears)) {
                        $fyYears[] = $currentFy;
                    }
                    rsort($fyYears);
                    foreach (array_unique($fyYears) as $y):
                    ?>
                        <option value="<?= $y ?>" <?= ($selectedFy !== null && $y === $selectedFy) ? 'selected' : '' ?>>
                            FY<?= $y ?><?= $y === $currentFy ? ' (Current)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLobModal">
                <i class="fa-solid fa-plus me-1"></i> Add Budget
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= e($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Code</th>
                            <th>Budget Name</th>
                            <th>Financial Year</th>
                            <th>Description</th>
                            <th>Assigned Users</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lobs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No Budgets found. Click "Add Budget" to create one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lobs as $lob):
                                $assignedUserAssignments = budsheets_get_lob_user_assignments($lob['id']);
                                $lobFy = !empty($lob['fiscal_year']) ? (int)$lob['fiscal_year'] : $currentFy;
                                $budgetComments = budsheets_get_comments('budget', $lob['id']);
                            ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-muted">#<?= (int)$lob['id'] ?></td>
                                    <td><span class="badge bg-secondary"><?= e($lob['code'] ?: 'N/A') ?></span></td>
                                    <td class="fw-semibold text-dark"><?= e($lob['name']) ?></td>
                                    <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">FY<?= $lobFy ?></span></td>
                                    <td class="text-muted small"><?= e($lob['description'] ?: '—') ?></td>
                                    <td>
                                        <?php if (empty($assignedUserAssignments)): ?>
                                            <span class="badge bg-light text-muted border">All Admins / Open</span>
                                        <?php else: ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                <i class="fa-solid fa-users me-1"></i><?= count($assignedUserAssignments) ?> Assigned User(s)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <!-- Comments Modal Trigger -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#commentsLobModal<?= (int)$lob['id'] ?>" title="View & Post Comments">
                                            <i class="fa-solid fa-comments me-1"></i>Comments (<?= count($budgetComments) ?>)
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#cloneLobModal<?= (int)$lob['id'] ?>" title="Clone Budget">
                                            <i class="fa-solid fa-copy me-1"></i>Clone
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editLobModal<?= (int)$lob['id'] ?>" title="Edit Budget">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Budget? Associated items will be deleted.');">
                                            <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_lob">
                                            <input type="hidden" name="lob_id" value="<?= (int)$lob['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Budget">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Budget Comments Modal -->
                                <div class="modal fade" id="commentsLobModal<?= (int)$lob['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold"><i class="fa-solid fa-comments me-2 text-primary"></i>Comments & Discussions - <?= e($lob['name']) ?> (FY<?= $lobFy ?>)</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <?php if (empty($budgetComments)): ?>
                                                    <div class="text-muted small py-3 text-center border-bottom mb-3">No comments on this budget yet. Post a comment below.</div>
                                                <?php else: ?>
                                                    <div class="list-group list-group-flush border-bottom mb-3" style="max-height: 250px; overflow-y: auto;">
                                                        <?php foreach ($budgetComments as $bc): ?>
                                                            <div class="list-group-item px-0 py-2">
                                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                                    <div>
                                                                        <span class="fw-bold text-dark me-2"><i class="fa-solid fa-user-circle me-1 text-secondary"></i><?= e($bc['author_name']) ?></span>
                                                                        <span class="text-muted small"><?= e($bc['created_at']) ?></span>
                                                                    </div>
                                                                    <?php if (has_permission('budsheets_admin') || (int)($bc['created_by'] ?? 0) === (int)$currentUserId): ?>
                                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this comment?');">
                                                                            <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                                            <input type="hidden" name="action" value="delete_comment">
                                                                            <input type="hidden" name="comment_id" value="<?= (int)$bc['id'] ?>">
                                                                            <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none p-0" title="Delete Comment">
                                                                                <i class="fa-solid fa-trash-can"></i>
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="text-secondary small" style="white-space: pre-wrap;"><?= e($bc['comment_text']) ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <form method="POST">
                                                    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="add_comment">
                                                    <input type="hidden" name="lob_id" value="<?= (int)$lob['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold small">Post a Comment for <?= e($lob['name']) ?></label>
                                                        <textarea name="comment_text" class="form-control form-control-sm" rows="3" placeholder="Type your comment, rationale, or internal note here..." required></textarea>
                                                    </div>
                                                    <div class="text-end">
                                                        <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-paper-plane me-1"></i> Post Comment</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editLobModal<?= (int)$lob['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_lob">
                                                <input type="hidden" name="lob_id" value="<?= (int)$lob['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Budget</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Budget Name <span class="text-danger">*</span></label>
                                                        <input type="text" name="name" class="form-control" value="<?= e($lob['name']) ?>" required>
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-8">
                                                            <label class="form-label fw-semibold">Code / Abbreviation</label>
                                                            <input type="text" name="code" class="form-control" value="<?= e($lob['code']) ?>" placeholder="e.g. IT, HR, MKT">
                                                        </div>
                                                        <div class="col-4">
                                                            <label class="form-label fw-semibold">Financial Year <span class="text-danger">*</span></label>
                                                            <select name="fiscal_year" class="form-select" required>
                                                                <?php foreach ($managedFys as $fy): ?>
                                                                    <option value="<?= (int)$fy['year'] ?>" <?= $lobFy === (int)$fy['year'] ? 'selected' : '' ?>>
                                                                        FY<?= (int)$fy['year'] ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Description</label>
                                                        <textarea name="description" class="form-control" rows="2"><?= e($lob['description']) ?></textarea>
                                                    </div>
                                                    <?php if (!empty($systemUsers)): ?>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">User Access Assignments & Permissions</label>
                                                            <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                                                <?php foreach ($systemUsers as $u):
                                                                    $isAssigned = isset($assignedUserAssignments[$u['id']]);
                                                                    $currLevel = $assignedUserAssignments[$u['id']] ?? 'full';
                                                                ?>
                                                                    <div class="row align-items-center mb-2 pb-1 border-bottom">
                                                                        <div class="col-6">
                                                                            <div class="form-check mb-0">
                                                                                <input class="form-check-input" type="checkbox" name="assigned_user_ids[]" value="<?= (int)$u['id'] ?>" id="edit_u_check_<?= (int)$lob['id'] ?>_<?= (int)$u['id'] ?>" <?= $isAssigned ? 'checked' : '' ?>>
                                                                                <label class="form-check-label small fw-semibold text-truncate d-block" for="edit_u_check_<?= (int)$lob['id'] ?>_<?= (int)$u['id'] ?>" title="<?= e($u['name']) ?>">
                                                                                    <?= e($u['name']) ?>
                                                                                </label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <select name="user_access_levels[<?= (int)$u['id'] ?>]" class="form-select form-select-sm">
                                                                                <option value="full" <?= $currLevel === 'full' ? 'selected' : '' ?>>Full Access</option>
                                                                                <option value="read_only" <?= $currLevel === 'read_only' ? 'selected' : '' ?>>Read-Only</option>
                                                                                <option value="hide_costs" <?= $currLevel === 'hide_costs' ? 'selected' : '' ?>>Read-Only (Hide Costs)</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="form-text">Configure authorized users and their access permission level for this Budget.</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Clone Budget Modal -->
                                <div class="modal fade" id="cloneLobModal<?= (int)$lob['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="clone_lob">
                                                <input type="hidden" name="source_lob_id" value="<?= (int)$lob['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-copy me-2 text-info"></i>Clone Budget</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">New Budget Name <span class="text-danger">*</span></label>
                                                        <input type="text" name="target_name" class="form-control" value="<?= e($lob['name']) ?> (FY<?= $lobFy + 1 ?>)" required>
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-6">
                                                            <label class="form-label fw-semibold">Code / Abbreviation</label>
                                                            <input type="text" name="target_code" class="form-control" value="<?= e($lob['code']) ?>">
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label fw-semibold">Target Financial Year <span class="text-danger">*</span></label>
                                                            <select name="target_fiscal_year" class="form-select" required>
                                                                <?php foreach ($managedFys as $fy): ?>
                                                                    <option value="<?= (int)$fy['year'] ?>" <?= ($lobFy + 1) === (int)$fy['year'] ? 'selected' : '' ?>>
                                                                        FY<?= (int)$fy['year'] ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Cloning Method <span class="text-danger">*</span></label>
                                                        <div class="border rounded p-3 bg-light">
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input" type="radio" name="clone_type" id="clone_full_<?= (int)$lob['id'] ?>" value="full" checked>
                                                                <label class="form-check-label fw-semibold" for="clone_full_<?= (int)$lob['id'] ?>">
                                                                    Full Clone
                                                                    <div class="text-muted small fw-normal">Copy Budget, all items, recurring costs, AND monthly schedule breakdown amounts into the target Financial Year.</div>
                                                                </label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="clone_type" id="clone_soft_<?= (int)$lob['id'] ?>" value="soft">
                                                                <label class="form-check-label fw-semibold" for="clone_soft_<?= (int)$lob['id'] ?>">
                                                                    Soft Clone
                                                                    <div class="text-muted small fw-normal">Copy Budget and item specifications (vendors, products, classes, descriptions) WITHOUT financial costs (costs set to $0.00).</div>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-info text-white"><i class="fa-solid fa-copy me-1"></i> Clone Budget</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add LOB Modal -->
<div class="modal fade" id="addLobModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_lob">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-sitemap me-2"></i>Add Budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Budget Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Information Technology 2026" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label fw-semibold">Code / Abbreviation</label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. IT, HR, MKT">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Financial Year <span class="text-danger">*</span></label>
                            <select name="fiscal_year" class="form-select" required>
                                <?php foreach ($managedFys as $fy): ?>
                                    <option value="<?= (int)$fy['year'] ?>" <?= ($selectedFy !== null ? $selectedFy : $currentFy) === (int)$fy['year'] ? 'selected' : '' ?>>
                                        FY<?= (int)$fy['year'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief description of this budget..."></textarea>
                    </div>
                    <?php if (!empty($systemUsers)): ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">User Access Assignments & Permissions</label>
                            <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($systemUsers as $u): ?>
                                    <div class="row align-items-center mb-2 pb-1 border-bottom">
                                        <div class="col-6">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" name="assigned_user_ids[]" value="<?= (int)$u['id'] ?>" id="add_u_check_<?= (int)$u['id'] ?>">
                                                <label class="form-check-label small fw-semibold text-truncate d-block" for="add_u_check_<?= (int)$u['id'] ?>" title="<?= e($u['name']) ?>">
                                                    <?= e($u['name']) ?>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <select name="user_access_levels[<?= (int)$u['id'] ?>]" class="form-select form-select-sm">
                                                <option value="full" selected>Full Access</option>
                                                <option value="read_only">Read-Only</option>
                                                <option value="hide_costs">Read-Only (Hide Costs)</option>
                                            </select>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text">Configure authorized users and their access permission level for this Budget.</div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Budget</button>
                </div>
            </form>
        </div>
    </div>
</div>
