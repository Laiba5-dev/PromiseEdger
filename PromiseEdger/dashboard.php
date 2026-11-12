<?php
// File: dashboard.php - Main app feed (login required)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$csrf = csrf_token();
$user = getCurrentUser($conn);
if (!$user) { session_destroy(); header("Location: login.php"); exit(); }

$uid = (int)$user['id'];
$isAdmin = isAdmin($conn);

$feed = $_GET['feed'] ?? 'all';
if (!in_array($feed, ['all', 'following', 'mine'], true)) $feed = 'all';
$cat = array_key_exists($_GET['cat'] ?? '', PE_CATEGORIES) ? $_GET['cat'] : '';
$status = in_array($_GET['status'] ?? '', PE_STATUSES, true) ? $_GET['status'] : '';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 8;

function dash_url(array $over = []): string {
    global $feed, $cat, $status, $q;
    $a = array_filter(array_merge(['feed' => $feed === 'all' ? '' : $feed, 'cat' => $cat, 'status' => $status, 'q' => $q], $over), fn($v) => $v !== '' && $v !== null);
    return 'dashboard.php' . ($a ? '?' . http_build_query($a) : '');
}

$rows = queryPromises($conn, $uid, ['feed' => $feed, 'category' => $cat, 'status' => $status, 'q' => $q, 'limit' => $per + 1, 'offset' => ($page - 1) * $per]);
$hasMore = count($rows) > $per;
$rows = array_slice($rows, 0, $per);
$x = loadCardExtras($conn, $rows, $uid);
$ctx = ['uid' => $uid, 'isAdmin' => $isAdmin, 'csrf' => $csrf];

// "Load more" ke liye sirf cards bhejo
if (isset($_GET['partial'])) {
    render_promise_cards($rows, $x, $ctx);
    if ($hasMore) echo '<div class="more-marker" data-url="' . escapeHtml(dash_url(['page' => $page + 1, 'partial' => 1])) . '"></div>';
    exit();
}

$stats = getUserStats($conn, $uid);
$dueSoon = db_all($conn, "SELECT id, title, due_date, status FROM promises WHERE user_id = ? AND status = 'in_progress' AND due_date IS NOT NULL
    AND due_date <= CURDATE() + INTERVAL 7 DAY ORDER BY due_date ASC LIMIT 4", "i", [$uid]);
$trending = $conn->query("SELECT p.id, p.title, COUNT(r.id) AS rc FROM promises p LEFT JOIN reactions r ON r.promise_id = p.id
    WHERE p.visibility = 'public' GROUP BY p.id, p.title ORDER BY rc DESC, p.created_at DESC LIMIT 4")->fetch_all(MYSQLI_ASSOC);

$hour = (int)date('G');
$greet = $hour < 12 ? t('good_morning') : ($hour < 18 ? t('good_afternoon') : t('good_evening'));

page_head(t('nav_dashboard') . ' - PromiseEdger');
page_nav('dashboard');
?>
<main class="container py-4 py-lg-5">
    <?php page_flash(); ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <h1 class="h2 page-title mb-0 anim-up"><?php echo $greet; ?>, <span class="grad-text"><?php echo escapeHtml(explode(' ', trim($user['name']))[0]); ?></span></h1>
        <a href="add_promise.php" class="btn btn-brand"><i class="bi bi-plus-lg"></i> <?php echo t('nav_new_promise'); ?></a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="tabs-x mb-3 anim-up">
                <a class="<?php echo $feed === 'all' ? 'active' : ''; ?>" href="<?php echo dash_url(['feed' => '', 'page' => '']); ?>"><i class="bi bi-compass"></i> <?php echo t('feed_all'); ?></a>
                <a class="<?php echo $feed === 'following' ? 'active' : ''; ?>" href="<?php echo dash_url(['feed' => 'following', 'page' => '']); ?>"><i class="bi bi-people"></i> <?php echo t('feed_following'); ?></a>
                <a class="<?php echo $feed === 'mine' ? 'active' : ''; ?>" href="<?php echo dash_url(['feed' => 'mine', 'page' => '']); ?>"><i class="bi bi-person"></i> <?php echo t('feed_mine'); ?></a>
            </div>

            <form method="GET" class="d-flex flex-wrap gap-2 mb-3 anim-up" style="--i:1">
                <?php if ($feed !== 'all'): ?><input type="hidden" name="feed" value="<?php echo $feed; ?>"><?php endif; ?>
                <?php if ($cat): ?><input type="hidden" name="cat" value="<?php echo $cat; ?>"><?php endif; ?>
                <div class="search-wrap flex-grow-1" style="min-width:200px;">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" class="form-control" placeholder="<?php echo escapeHtml(t('search')); ?>" value="<?php echo escapeHtml($q); ?>">
                </div>
                <select name="status" class="form-select w-auto" onchange="this.form.submit()">
                    <option value=""><?php echo t('all_statuses'); ?></option>
                    <?php foreach (PE_STATUSES as $s): ?><option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo t('st_' . $s); ?></option><?php endforeach; ?>
                </select>
            </form>

            <div class="chips mb-4 anim-up" style="--i:2">
                <a class="chip <?php echo $cat === '' ? 'active' : ''; ?>" href="<?php echo dash_url(['cat' => '', 'page' => '']); ?>"><?php echo t('all'); ?></a>
                <?php foreach (PE_CATEGORIES as $k => $icon): ?>
                    <a class="chip <?php echo $cat === $k ? 'active' : ''; ?>" href="<?php echo dash_url(['cat' => $k, 'page' => '']); ?>"><i class="bi <?php echo $icon; ?>"></i> <?php echo t('cat_' . $k); ?></a>
                <?php endforeach; ?>
            </div>

            <div class="d-flex flex-column gap-4" id="feed">
                <?php render_promise_cards($rows, $x, $ctx); ?>
            </div>

            <?php if (!$rows): ?>
                <div class="card-x empty">
                    <i class="bi <?php echo $feed === 'following' ? 'bi-people' : 'bi-bookmark-heart'; ?>"></i>
                    <h5 class="mt-3"><?php echo $feed === 'following' ? t('following_empty_title') : t('nothing_found'); ?></h5>
                    <p class="text-secondary"><?php echo $feed === 'following' ? t('following_empty_hint') : t('try_different'); ?></p>
                    <?php if ($feed === 'following'): ?><a href="leaderboard.php" class="btn btn-brand"><i class="bi bi-trophy"></i> <?php echo t('discover_people'); ?></a>
                    <?php elseif ($feed === 'all' && !$cat && !$status && $q === ''): ?><a href="add_promise.php" class="btn btn-brand"><?php echo t('make_one'); ?></a><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4 <?php echo $hasMore ? '' : 'd-none'; ?>" id="loadMoreWrap">
                <button type="button" class="btn btn-ghost" id="loadMore" data-target="#feed" data-url="<?php echo escapeHtml(dash_url(['page' => $page + 1, 'partial' => 1])); ?>">
                    <i class="bi bi-arrow-down-circle"></i> <?php echo t('load_more'); ?>
                </button>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="side-sticky">
                <div class="card-x p-4 text-center mb-4 anim-up">
                    <div class="mb-3"><?php echo avatar_html($user, 76); ?></div>
                    <h5 class="mb-1"><?php echo escapeHtml($user['name']); ?></h5>
                    <div class="text-secondary small mb-3"><?php echo escapeHtml($user['email']); ?></div>
                    <?php if ($isAdmin): ?><span class="pill pill-admin mb-3"><i class="bi bi-shield-check"></i> <?php echo t('admin'); ?></span><br><?php endif; ?>
                    <a href="profile.php" class="btn btn-ghost w-100"><i class="bi bi-person"></i> <?php echo t('view_profile'); ?></a>
                </div>

                <div class="row g-3 mb-4">
                    <?php
                    $tiles = [['bi-bookmark-heart', $stats['promises'], t('promises')], ['bi-check2-circle', $stats['done'], t('st_done')],
                              ['bi-fire', $stats['best_streak'], t('best_streak_short')], ['bi-people', $stats['followers'], t('followers')]];
                    foreach ($tiles as $i => $tl): ?>
                        <div class="col-6"><div class="card-x stat-h anim-up" style="--i:<?php echo $i + 1; ?>"><div class="ico"><i class="bi <?php echo $tl[0]; ?>"></i></div>
                            <div><div class="num" data-count="<?php echo (int)$tl[1]; ?>">0</div><small><?php echo $tl[2]; ?></small></div></div></div>
                    <?php endforeach; ?>
                </div>

                <?php if ($dueSoon): ?>
                <div class="card-x p-4 mb-4 anim-up">
                    <h6 class="fw-bold mb-2"><i class="bi bi-alarm text-gold"></i> <?php echo t('due_soon'); ?></h6>
                    <?php foreach ($dueSoon as $d): $di = dueInfo($d); ?>
                        <a class="trend-item" href="promise.php?id=<?php echo (int)$d['id']; ?>">
                            <span class="flex-grow-1 text-truncate"><?php echo escapeHtml($d['title']); ?></span>
                            <span class="badge-x due-<?php echo $di['class']; ?>"><?php echo escapeHtml($di['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="card-x p-4 anim-up">
                    <h6 class="fw-bold mb-2"><i class="bi bi-fire text-gold"></i> <?php echo t('trending'); ?></h6>
                    <?php if (!$trending): ?><p class="text-secondary small mb-0"><?php echo t('nothing_yet'); ?></p><?php endif; ?>
                    <?php foreach ($trending as $i => $tr): ?>
                        <a class="trend-item" href="promise.php?id=<?php echo (int)$tr['id']; ?>">
                            <span class="trend-rank"><?php echo $i + 1; ?></span>
                            <span class="flex-grow-1 text-truncate"><?php echo escapeHtml($tr['title']); ?></span>
                            <small class="text-secondary"><i class="bi bi-emoji-smile"></i> <?php echo (int)$tr['rc']; ?></small>
                        </a>
                    <?php endforeach; ?>
                    <a href="leaderboard.php" class="btn btn-ghost w-100 mt-3"><i class="bi bi-trophy"></i> <?php echo t('nav_leaderboard'); ?></a>
                </div>
            </div>
        </div>
    </div>
</main>
<?php page_foot(); ?>
