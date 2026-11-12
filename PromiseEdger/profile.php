<?php
// File: profile.php - own profile (edit) or view another user (?id=)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$csrf = csrf_token();
$viewer = (int)getCurrentUserId();
$id = isset($_GET['id']) ? (int)$_GET['id'] : $viewer;
$isOwn = ($id === $viewer);
$viewerIsAdmin = isAdmin($conn);

$profile = db_one($conn, "SELECT id, name, email, role, profile_picture, created_at FROM users WHERE id = ?", "i", [$id]);
if (!$profile) {
    $_SESSION['error'] = t('err_user_not_found');
    header("Location: dashboard.php");
    exit();
}

$avatarDir = __DIR__ . '/uploads/avatars';
function deleteAvatarFile($path, $dir) {
    if ($path) {
        $file = $dir . '/' . basename($path);
        if (is_file($file)) @unlink($file);
    }
}

// ========== ACTIONS (sirf apni profile) ==========
if ($isOwn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $_SESSION['error'] = t('err_invalid_request');
        header("Location: profile.php?tab=settings");
        exit();
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $picture = $profile['profile_picture'];
        $err = '';
        if ($name === '' || mb_strlen($name) > 100) $err = t('err_name_required');

        if (!$err && !empty($_FILES['avatar']['name'])) {
            $f = $_FILES['avatar'];
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $err = t('err_upload_failed');
            } elseif ($f['size'] > 2 * 1024 * 1024) {
                $err = t('err_image_size');
            } else {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                if (!isset($allowed[$mime])) {
                    $err = t('err_image_type');
                } else {
                    if (!is_dir($avatarDir)) {
                        mkdir($avatarDir, 0755, true);
                        file_put_contents($avatarDir . '/.htaccess', "<FilesMatch \"\\.(php|phtml|phar)$\">\n  Require all denied\n</FilesMatch>\n");
                    }
                    $newName = $id . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
                    if (move_uploaded_file($f['tmp_name'], $avatarDir . '/' . $newName)) {
                        deleteAvatarFile($picture, $avatarDir);
                        $picture = 'uploads/avatars/' . $newName;
                    } else {
                        $err = t('err_save_image');
                    }
                }
            }
        }
        if ($err) {
            $_SESSION['error'] = $err;
        } else {
            db_exec($conn, "UPDATE users SET name = ?, profile_picture = ? WHERE id = ?", "ssi", [$name, $picture, $id]);
            $_SESSION['user']['name'] = $name;
            $_SESSION['success'] = t('profile_updated');
        }
        header("Location: profile.php?tab=settings");
        exit();
    }

    if ($action === 'remove_avatar') {
        deleteAvatarFile($profile['profile_picture'], $avatarDir);
        db_exec($conn, "UPDATE users SET profile_picture = NULL WHERE id = ?", "i", [$id]);
        $_SESSION['success'] = t('photo_removed');
        header("Location: profile.php?tab=settings");
        exit();
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $row = db_one($conn, "SELECT password FROM users WHERE id = ?", "i", [$id]);

        if (!$row || !password_verify($current, $row['password'])) {
            $_SESSION['error'] = t('err_current_password');
        } elseif (strlen($new) < 6) {
            $_SESSION['error'] = t('err_password_short');
        } elseif ($new !== $confirm) {
            $_SESSION['error'] = t('err_password_mismatch');
        } else {
            db_exec($conn, "UPDATE users SET password = ? WHERE id = ?", "si", [password_hash($new, PASSWORD_DEFAULT), $id]);
            session_regenerate_id(true);
            $_SESSION['success'] = t('password_changed');
        }
        header("Location: profile.php?tab=settings");
        exit();
    }
}

// ========== DATA ==========
$tab = $_GET['tab'] ?? 'promises';
if (!in_array($tab, ['promises', 'followers', 'following', 'settings'], true) || ($tab === 'settings' && !$isOwn)) $tab = 'promises';

$publicOnly = (!$isOwn && !$viewerIsAdmin);
$stats = getUserStats($conn, $id, $publicOnly);
$iFollow = (!$isOwn && isFollowing($conn, $viewer, $id));

$page = max(1, (int)($_GET['page'] ?? 1));
$per = 8;
$ctx = ['uid' => $viewer, 'isAdmin' => $viewerIsAdmin, 'csrf' => $csrf];

if ($tab === 'promises') {
    $rows = queryPromises($conn, $viewer, ['feed' => 'user', 'user_id' => $id, 'limit' => $per + 1, 'offset' => ($page - 1) * $per, 'all_visible' => $viewerIsAdmin]);
    $hasMore = count($rows) > $per;
    $rows = array_slice($rows, 0, $per);
    $x = loadCardExtras($conn, $rows, $viewer);
    if (isset($_GET['partial'])) {
        render_promise_cards($rows, $x, $ctx);
        if ($hasMore) echo '<div class="more-marker" data-url="profile.php?id=' . $id . '&page=' . ($page + 1) . '&partial=1"></div>';
        exit();
    }
}

$people = [];
if ($tab === 'followers' || $tab === 'following') {
    $join = $tab === 'followers' ? "f.follower_id" : "f.following_id";
    $where = $tab === 'followers' ? "f.following_id" : "f.follower_id";
    $people = db_all($conn, "SELECT u.id, u.name, u.profile_picture,
        (SELECT COUNT(*) FROM promises p WHERE p.user_id = u.id AND p.visibility = 'public') AS pc,
        (SELECT COUNT(*) FROM promises p WHERE p.user_id = u.id AND p.visibility = 'public' AND p.status = 'done') AS dc,
        EXISTS(SELECT 1 FROM follows m WHERE m.follower_id = ? AND m.following_id = u.id) AS i_follow
        FROM follows f JOIN users u ON u.id = $join WHERE $where = ? ORDER BY f.created_at DESC LIMIT 100", "ii", [$viewer, $id]);
}

// Achievements
$badges = [
    ['done1', 'bi-check2-circle', $stats['done'] >= 1],
    ['done5', 'bi-award', $stats['done'] >= 5],
    ['done10', 'bi-trophy', $stats['done'] >= 10],
    ['streak3', 'bi-fire', $stats['best_streak'] >= 3],
    ['streak7', 'bi-lightning-charge', $stats['best_streak'] >= 7],
    ['streak30', 'bi-gem', $stats['best_streak'] >= 30],
];

page_head($profile['name'] . ' - PromiseEdger');
page_nav($isOwn ? 'profile' : '');
$tabUrl = fn($t) => 'profile.php?' . ($isOwn && !isset($_GET['id']) ? '' : 'id=' . $id . '&') . 'tab=' . $t;
?>
<main class="container py-4 py-lg-5">
    <?php page_flash(); ?>

    <div class="card-x p-4 p-md-5 mb-4 profile-hero anim-up">
        <div class="d-flex align-items-center gap-4 flex-wrap">
            <?php echo avatar_html($profile, 96); ?>
            <div class="flex-grow-1">
                <h1 class="h3 page-title mb-1"><?php echo escapeHtml($profile['name']); ?>
                    <?php if (($profile['role'] ?? 'user') === 'admin'): ?><span class="pill pill-admin align-middle"><i class="bi bi-shield-check"></i> <?php echo t('admin'); ?></span><?php endif; ?>
                </h1>
                <?php if ($isOwn || $viewerIsAdmin): ?><div class="text-secondary"><i class="bi bi-envelope"></i> <?php echo escapeHtml($profile['email']); ?></div><?php endif; ?>
                <div class="text-secondary"><i class="bi bi-calendar3"></i> <?php echo t('joined'); ?> <?php echo escapeHtml(fmtDate($profile['created_at'])); ?></div>
            </div>
            <?php if (!$isOwn): ?>
                <button type="button" class="follow-btn lg <?php echo $iFollow ? 'on' : ''; ?>" data-user="<?php echo $id; ?>" data-following="<?php echo $iFollow ? 1 : 0; ?>">
                    <i class="bi <?php echo $iFollow ? 'bi-check2' : 'bi-plus-lg'; ?>"></i> <span><?php echo $iFollow ? t('following') : t('follow'); ?></span>
                </button>
            <?php endif; ?>
        </div>

        <div class="profile-stats mt-4">
            <div><b data-count="<?php echo $stats['promises']; ?>">0</b><span><?php echo t('promises'); ?></span></div>
            <div><b data-count="<?php echo $stats['done']; ?>">0</b><span><?php echo t('st_done'); ?></span></div>
            <div><b data-count="<?php echo $stats['best_streak']; ?>">0</b><span><?php echo t('best_streak_short'); ?></span></div>
            <a href="<?php echo $tabUrl('followers'); ?>"><b data-count="<?php echo $stats['followers']; ?>">0</b><span><?php echo t('followers'); ?></span></a>
            <a href="<?php echo $tabUrl('following'); ?>"><b data-count="<?php echo $stats['following']; ?>">0</b><span><?php echo t('following'); ?></span></a>
            <div><b data-count="<?php echo $stats['reactions']; ?>">0</b><span><?php echo t('reactions'); ?></span></div>
        </div>

        <div class="achievements mt-4">
            <?php foreach ($badges as $b): ?>
                <span class="ach <?php echo $b[2] ? 'on' : ''; ?>" title="<?php echo escapeHtml(t('badge_' . $b[0] . '_d')); ?>"><i class="bi <?php echo $b[1]; ?>"></i> <?php echo t('badge_' . $b[0]); ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tabs-x mb-4">
        <a class="<?php echo $tab === 'promises' ? 'active' : ''; ?>" href="<?php echo $tabUrl('promises'); ?>"><i class="bi bi-bookmark-heart"></i> <?php echo t('promises'); ?></a>
        <a class="<?php echo $tab === 'followers' ? 'active' : ''; ?>" href="<?php echo $tabUrl('followers'); ?>"><i class="bi bi-people"></i> <?php echo t('followers'); ?></a>
        <a class="<?php echo $tab === 'following' ? 'active' : ''; ?>" href="<?php echo $tabUrl('following'); ?>"><i class="bi bi-person-check"></i> <?php echo t('following'); ?></a>
        <?php if ($isOwn): ?><a class="<?php echo $tab === 'settings' ? 'active' : ''; ?>" href="<?php echo $tabUrl('settings'); ?>"><i class="bi bi-gear"></i> <?php echo t('settings'); ?></a><?php endif; ?>
    </div>

    <?php if ($tab === 'settings' && $isOwn): ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card-x p-4 h-100">
                <h5 class="mb-3"><?php echo t('edit_profile'); ?></h5>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3"><label class="form-label"><?php echo t('name'); ?></label>
                        <input type="text" name="name" class="form-control" maxlength="100" required value="<?php echo escapeHtml($profile['name']); ?>"></div>
                    <div class="mb-3"><label class="form-label"><?php echo t('profile_picture'); ?> <small class="text-secondary">(JPG/PNG/WEBP, max 2MB)</small></label>
                        <input type="file" name="avatar" class="form-control" accept="image/jpeg,image/png,image/webp"></div>
                    <button class="btn btn-brand"><?php echo t('save'); ?></button>
                </form>
                <?php if (!empty($profile['profile_picture'])): ?>
                    <form method="POST" class="mt-2"><input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="remove_avatar">
                        <button class="btn btn-sm btn-ghost"><?php echo t('remove_photo'); ?></button></form>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card-x p-4 h-100">
                <h5 class="mb-3"><?php echo t('change_password'); ?></h5>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3"><label class="form-label"><?php echo t('current_password'); ?></label><input type="password" name="current_password" class="form-control" dir="ltr" required></div>
                    <div class="mb-3"><label class="form-label"><?php echo t('new_password'); ?></label><input type="password" name="new_password" class="form-control" minlength="6" dir="ltr" required></div>
                    <div class="mb-3"><label class="form-label"><?php echo t('confirm_new_password'); ?></label><input type="password" name="confirm_password" class="form-control" minlength="6" dir="ltr" required></div>
                    <button class="btn btn-brand"><?php echo t('update_password'); ?></button>
                </form>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'promises'): ?>
        <div class="d-flex flex-column gap-4" id="feed"><?php render_promise_cards($rows, $x, $ctx); ?></div>
        <?php if (!$rows): ?><div class="card-x empty"><i class="bi bi-bookmark-heart"></i><h5 class="mt-3"><?php echo t('no_promises_yet'); ?></h5></div><?php endif; ?>
        <div class="text-center mt-4 <?php echo $hasMore ? '' : 'd-none'; ?>" id="loadMoreWrap">
            <button type="button" class="btn btn-ghost" id="loadMore" data-target="#feed" data-url="profile.php?id=<?php echo $id; ?>&page=<?php echo $page + 1; ?>&partial=1"><i class="bi bi-arrow-down-circle"></i> <?php echo t('load_more'); ?></button>
        </div>

    <?php else: ?>
        <?php if (!$people): ?><div class="card-x empty"><i class="bi bi-people"></i><h5 class="mt-3"><?php echo $tab === 'followers' ? t('no_followers') : t('no_following'); ?></h5></div><?php endif; ?>
        <div class="row g-3">
            <?php foreach ($people as $i => $u): ?>
                <div class="col-md-6"><div class="card-x person anim-up" style="--i:<?php echo min($i, 8); ?>">
                    <?php echo avatar_html($u, 46); ?>
                    <div class="flex-grow-1"><a class="fw-semibold" href="profile.php?id=<?php echo (int)$u['id']; ?>"><?php echo escapeHtml($u['name']); ?></a>
                        <div class="small text-secondary"><?php echo t('n_promises_done', (int)$u['pc'], (int)$u['dc']); ?></div></div>
                    <?php if ((int)$u['id'] !== $viewer): ?>
                        <button type="button" class="follow-btn <?php echo $u['i_follow'] ? 'on' : ''; ?>" data-user="<?php echo (int)$u['id']; ?>" data-following="<?php echo $u['i_follow'] ? 1 : 0; ?>">
                            <i class="bi <?php echo $u['i_follow'] ? 'bi-check2' : 'bi-plus-lg'; ?>"></i> <span><?php echo $u['i_follow'] ? t('following') : t('follow'); ?></span></button>
                    <?php endif; ?>
                </div></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php page_foot(); ?>
