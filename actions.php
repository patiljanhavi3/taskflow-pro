<?php
// ============================================================
// actions.php — AJAX task handler
// ============================================================
require_once 'auth.php';
require_once 'db.php';
requireLogin();

header('Content-Type: application/json');

$user   = currentUser();
$uid    = (int)$user['id'];
$db     = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF check for state-modifying actions
$mutating = ['add','delete','complete','reopen','update_order','update_theme'];
if (in_array($action, $mutating)) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
}

try {
    switch ($action) {

        // --- ADD TASK ---
        case 'add':
            $title    = trim($_POST['title'] ?? '');
            $desc     = trim($_POST['description'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $due      = $_POST['due_date'] ?? null;

            if (empty($title)) { echo json_encode(['error' => 'Title required.']); exit; }
            if (!in_array($priority, ['low','medium','high'])) $priority = 'medium';
            if ($due === '') $due = null;

            $stmt = $db->prepare("INSERT INTO tasks (user_id, title, description, priority, due_date, sort_order)
                                  VALUES (?, ?, ?, ?, ?, (SELECT COALESCE(MAX(t2.sort_order),0)+1 FROM tasks t2 WHERE t2.user_id=?))");
            $stmt->execute([$uid, $title, $desc ?: null, $priority, $due, $uid]);
            $newId = $db->lastInsertId();
            $task  = $db->prepare("SELECT * FROM tasks WHERE id=? AND user_id=?");
            $task->execute([$newId, $uid]);
            echo json_encode(['success' => true, 'task' => $task->fetch()]);
            break;

        // --- DELETE TASK ---
        case 'delete':
            $id   = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM tasks WHERE id=? AND user_id=?");
            $stmt->execute([$id, $uid]);
            echo json_encode(['success' => true]);
            break;

        // --- COMPLETE TASK ---
        case 'complete':
            $id   = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare("UPDATE tasks SET status='completed', completed_at=NOW() WHERE id=? AND user_id=?");
            $stmt->execute([$id, $uid]);
            echo json_encode(['success' => true]);
            break;

        // --- REOPEN TASK ---
        case 'reopen':
            $id   = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare("UPDATE tasks SET status='pending', completed_at=NULL WHERE id=? AND user_id=?");
            $stmt->execute([$id, $uid]);
            echo json_encode(['success' => true]);
            break;

        // --- GET TASKS ---
        case 'get':
            $filter = $_GET['filter'] ?? 'all';
            $search = trim($_GET['search'] ?? '');
            $sort   = $_GET['sort'] ?? 'recent';

            $where  = ['user_id = ?'];
            $params = [$uid];

            if ($filter === 'pending')   { $where[] = "status = 'pending'"; }
            if ($filter === 'completed') { $where[] = "status = 'completed'"; }

            if ($search !== '') {
                $where[]  = "(title LIKE ? OR description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            $orderBy = match($sort) {
                'priority' => "FIELD(priority,'high','medium','low'), created_at DESC",
                'due'      => "due_date IS NULL, due_date ASC",
                default    => "sort_order ASC, created_at DESC",
            };

            $sql  = "SELECT * FROM tasks WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['tasks' => $stmt->fetchAll()]);
            break;

        // --- UPDATE SORT ORDER (drag & drop) ---
        case 'update_order':
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            if (!is_array($ids)) { echo json_encode(['error' => 'Invalid.']); exit; }
            $upd = $db->prepare("UPDATE tasks SET sort_order=? WHERE id=? AND user_id=?");
            foreach ($ids as $i => $taskId) {
                $upd->execute([$i + 1, (int)$taskId, $uid]);
            }
            echo json_encode(['success' => true]);
            break;

        // --- UPDATE THEME ---
        case 'update_theme':
            $theme = $_POST['theme'] ?? 'dark';
            if (!in_array($theme, ['dark','light'])) $theme = 'dark';
            $stmt = $db->prepare("UPDATE users SET theme=? WHERE id=?");
            $stmt->execute([$theme, $uid]);
            $_SESSION['theme'] = $theme;
            echo json_encode(['success' => true]);
            break;

        // --- ANALYTICS ---
        case 'analytics':
            $total     = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id=?");
            $total->execute([$uid]);

            $completed = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id=? AND status='completed'");
            $completed->execute([$uid]);

            $byPriority = $db->prepare("SELECT priority, COUNT(*) as cnt FROM tasks WHERE user_id=? GROUP BY priority");
            $byPriority->execute([$uid]);

            $weekly = $db->prepare("SELECT DATE(created_at) as day, COUNT(*) as cnt
                                    FROM tasks WHERE user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                    GROUP BY DATE(created_at) ORDER BY day ASC");
            $weekly->execute([$uid]);

            $streak = $db->prepare("SELECT DATE(completed_at) as day FROM tasks
                                    WHERE user_id=? AND status='completed' AND completed_at IS NOT NULL
                                    GROUP BY DATE(completed_at) ORDER BY day DESC LIMIT 30");
            $streak->execute([$uid]);

            $totalCount     = (int)$total->fetchColumn();
            $completedCount = (int)$completed->fetchColumn();

            echo json_encode([
                'total'       => $totalCount,
                'completed'   => $completedCount,
                'pending'     => $totalCount - $completedCount,
                'by_priority' => $byPriority->fetchAll(),
                'weekly'      => $weekly->fetchAll(),
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action.']);
    }
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'A database error occurred.']);
}