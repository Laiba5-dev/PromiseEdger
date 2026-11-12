<?php
// File: leaderboard.php - sabse zyada promises poore karne wale
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$uid = (int)getCurrentUserId();
$type = ($_GET['type'] ?? 'done') === 'streak' ? 'streak' : 'done';
$period = $_GET['period'] ?? 'all';
if (!in_array($period, ['all', 'month', 'week'], true)) $period = 'all';

if ($type === 'done') {
    $since = ['all' => '', 'month' => "AND p.completed_at >= CURDATE() - INTERVAL 30 DAY", 'week' => "AND p.completed_at >= CURDATE() - INTERVAL 7 DAY"][$period];
    $rows = db_all($conn, "SELECT u.id, u.name, u.profile_picture, COUNT(*) AS score, MAX(p.completed_at) AS last_at,
            (SELECT COUNT(*) FROM promises x WHERE x.user_id = u.id AND x.visibility = 'public') AS total
        FROM promises p JOIN users u ON u.id = p.user_id
        WHERE p.status = 'done' AND p.visibility = 'public' $since
        GROUP BY u.id, u.name, u.profile_picture ORDER BY score DESC, last_at ASC LIMIT 50");
} else {
    $rows = db_all($conn, "SELECT u.id, u.name, u.profile_picture, MAX(p.streak_count) AS score, MAX(p.best_streak) AS best,
            (SELECT COUNT(*) FROM promises x WHERE x.user_id = u.id AND x.visibility = 'public') AS total
        FROM promises p JOIN users u ON u.id = p.user_id
        WHERE p.visibility = 'public' AND p.streak_count > 0 AND p.last_checkin >= CURDATE() - INTERVAL 1 DAY
        GROUP BY u.id, u.name, u.profile_picture ORDER BY score DESC, best DESC LIMIT 50");
}

$following = [];
foreach (db_all($conn, "SELECT following_id FROM follows WHERE follower_id = ?", "i", [$uid]) as $r) $following[(int)$r['following_id']] = true;
$myRank = null;
foreach ($rows as $i => $r) if ((int)$r['id'] === $uid) $myRank = $i + 1;

function lb_url(array $o): string {
    global $type, $period;
    return 'leaderboard.php?' . http_build_query(array_merge(['type' => $type, 'period' => $period], $o));
}
function lb_follow(array $r, int $uid, array $following): string {
    if ((int)$r['id'] === $uid) return '<span class="pill pill-admin">' . escapeHtml(t('you')) . '</span>';
    $on = !empty($following[(int)$r['id']]);
    return '<button type="button" class="follow-btn ' . ($on ? 'on' : '') . '" data-user="' . (int)$r['id'] . '" data-following="' . ($on ? 1 : 0) . '"><i class="bi ' . ($on ? 'bi-check2' : 'bi-plus-lg') . '"></i> <span>'
        . escapeHtml($on ? t('following') : t('follow')) . '</span></button>';
}
$unit = $type === 'done' ? t('lb_kept') : t('lb_days');

page_head(t('nav_leaderboard') . ' - PromiseEdger');
page_nav('leaderboard');
?>
<main class="container py-4 py-lg-5" style="max-width: 900px;">
    <?php page_flash(); ?>
    <div class="text-center mb-4 anim-up">
        <h1 class="h2 page-title mb-2"><i class="bi bi-trophy text-gold"></i> <?php echo t('nav_leaderboard'); ?></h1>
        <p class="text-secondary mb-0"><?php echo $type === 'done' ? t('lb_sub_done') : t('lb_sub_streak'); ?></p>
    </div>

    <div class="d-flex flex-wrap justify-content-center gap-3 mb-4 anim-up" style="--i:1">
        <div class="tabs-x">
            <a class="<?php echo $type === 'done' ? 'active' : ''; ?>" href="<?php echo lb_url(['type' => 'done']); ?>"><i class="bi bi-check2-circle"></i> <?php echo t('lb_tab_done'); ?></a>
            <a class="<?php echo $type === 'streak' ? 'active' : ''; ?>" href="<?php echo lb_url(['type' => 'streak']); ?>"><i class="bi bi-fire"></i> <?php echo t('lb_tab_streak'); ?></a>
        </div>
        <?php if ($type === 'done'): ?>
        <div class="tabs-x">
            <?php foreach (['all', 'month', 'week'] as $pr): ?>
                <a class="<?php echo $period === $pr ? 'active' : ''; ?>" href="<?php echo lb_url(['period' => $pr]); ?>"><?php echo t('period_' . $pr); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$rows): ?>
        <div class="card-x empty anim-up"><i class="bi bi-trophy"></i><h5 class="mt-3"><?php echo t('lb_empty'); ?></h5><a href="add_promise.php" class="btn btn-brand mt-2"><?php echo t('nav_new_promise'); ?></a></div>
    <?php else: ?>
        <?php $top = array_slice($rows, 0, 3); $order = count($top) >= 3 ? [1, 0, 2] : array_keys($top); ?>
        <div class="podium anim-up" style="--i:2">
            <?php foreach ($order as $idx): $r = $top[$idx]; $rank = $idx + 1; ?>
                <div class="pod pod-<?php echo $rank; ?>" style="--d:<?php echo $rank === 1 ? 0.35 : ($rank === 2 ? 0.15 : 0.05); ?>s">
                    <div class="pod-medal"><?php echo ['🥇', '🥈', '🥉'][$idx]; ?></div>
                    <?php echo avatar_html($r, $rank === 1 ? 78 : 62); ?>
                    <a class="pod-name" href="profile.php?id=<?php echo (int)$r['id']; ?>"><?php echo escapeHtml($r['name']); ?></a>
                    <div class="pod-score"><b data-count="<?php echo (int)$r['score']; ?>">0</b> <small><?php echo $unit; ?></small></div>
                    <?php echo lb_follow($r, $uid, $following); ?>
                    <div class="pod-base"><span><?php echo $rank; ?></span></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($rows) > 3): ?>
        <div class="card-x lb-list mt-4 anim-up" style="--i:3">
            <?php foreach (array_slice($rows, 3, null, true) as $i => $r): $isMe = (int)$r['id'] === $uid; ?>
                <div class="lb-row <?php echo $isMe ? 'me' : ''; ?>" style="--i:<?php echo min($i - 3, 10); ?>">
                    <span class="lb-rank"><?php echo $i + 1; ?></span>
                    <?php echo avatar_html($r, 40); ?>
                    <div class="flex-grow-1"><a class="fw-semibold" href="profile.php?id=<?php echo (int)$r['id']; ?>"><?php echo escapeHtml($r['name']); ?></a>
                        <div class="small text-secondary"><?php echo t('n_public_promises', (int)$r['total']); ?></div></div>
                    <div class="lb-score"><b><?php echo (int)$r['score']; ?></b> <small><?php echo $unit; ?></small></div>
                    <?php echo lb_follow($r, $uid, $following); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <p class="text-center small text-secondary mt-3"><?php echo $myRank ? t('lb_your_rank', $myRank) : t('lb_not_ranked'); ?> &middot; <?php echo t('lb_note'); ?></p>
    <?php endif; ?>
</main>
<?php page_foot(); ?>
