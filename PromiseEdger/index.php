<?php
// File: index.php - Public landing page
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';

$logged = isLoggedIn();

$stats = $conn->query("SELECT
    (SELECT COUNT(*) FROM users) AS users_n,
    (SELECT COUNT(*) FROM promises WHERE visibility = 'public') AS promises_n,
    (SELECT COUNT(*) FROM promises WHERE status = 'done' AND visibility = 'public') AS done_n,
    (SELECT COUNT(*) FROM reactions) AS reactions_n")->fetch_assoc();

$recent = queryPromises($conn, 0, ['limit' => 6]);
$x = loadCardExtras($conn, $recent, 0);

$top = db_all($conn, "SELECT u.id, u.name, u.profile_picture, COUNT(*) AS score FROM promises p JOIN users u ON u.id = p.user_id
    WHERE p.status = 'done' AND p.visibility = 'public' GROUP BY u.id, u.name, u.profile_picture ORDER BY score DESC, MAX(p.completed_at) DESC LIMIT 3");

$primaryHref = $logged ? 'dashboard.php' : 'register.php';
$primaryText = $logged ? t('open_dashboard') : t('get_started');

page_head('PromiseEdger - ' . t('hero_1') . ' ' . t('hero_2'));
page_nav();
?>

<header class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="eyebrow mb-4 anim-up"><span class="dot"></span> <?php echo t('eyebrow'); ?></span>
                <h1 class="mb-4 anim-up" style="--i:1"><?php echo t('hero_1'); ?><br><span class="grad-text"><?php echo t('hero_2'); ?></span></h1>
                <p class="lead mb-4 anim-up" style="--i:2"><?php echo t('hero_lead'); ?></p>
                <div class="d-flex flex-wrap gap-3 anim-up" style="--i:3">
                    <a href="<?php echo $primaryHref; ?>" class="btn btn-brand btn-lg-x"><?php echo $primaryText; ?> <i class="bi <?php echo i_next(); ?>"></i></a>
                    <?php if (!$logged): ?><a href="login.php" class="btn btn-ghost btn-lg-x"><?php echo t('login'); ?></a>
                    <?php else: ?><a href="add_promise.php" class="btn btn-ghost btn-lg-x"><i class="bi bi-plus-lg"></i> <?php echo t('nav_new_promise'); ?></a><?php endif; ?>
                </div>
            </div>

            <div class="col-lg-6 d-none d-lg-block">
                <div class="hero-visual">
                    <?php
                    $demo = [
                        ['Aisha', t('demo_1'), 'health', 'in_progress', 72, 3, 12, '👍❤️🔥', 24],
                        ['Hamza', t('demo_2'), 'study', 'in_progress', 45, 9, 0, '👏💪', 17],
                        ['Sara', t('demo_3'), 'money', 'done', 100, null, 21, '🎉❤️', 41],
                    ];
                    foreach ($demo as $d): $due = $d[5] !== null ? ['class' => $d[5] <= 3 ? 'warn' : 'calm', 'label' => t('days_left', $d[5])] : null; ?>
                    <div class="float-card p-card status-<?php echo $d[3]; ?>">
                        <div class="p-head"><?php echo avatar_html(['name' => $d[0]], 34); ?><div class="p-author"><span class="p-name"><?php echo $d[0]; ?></span></div>
                            <span class="badge-x cat"><i class="bi <?php echo PE_CATEGORIES[$d[2]]; ?>"></i> <?php echo t('cat_' . $d[2]); ?></span></div>
                        <h3 class="p-title mb-2" style="font-size:1.02rem;"><?php echo escapeHtml($d[1]); ?></h3>
                        <div class="prog st-<?php echo $d[3]; ?> mb-2"><div class="prog-row"><span><?php echo t('progress'); ?></span><b><?php echo $d[4]; ?>%</b></div>
                            <div class="prog-bar"><span class="prog-fill" style="--w:<?php echo $d[4]; ?>%"></span></div></div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if ($due): ?><span class="badge-x due-<?php echo $due['class']; ?>"><i class="bi bi-alarm"></i> <?php echo escapeHtml($due['label']); ?></span><?php endif; ?>
                            <?php if ($d[6]): ?><span class="streak-badge tier-<?php echo streakTier($d[6]); ?>"><i class="bi bi-fire"></i> <?php echo t('streak_n', $d[6]); ?></span><?php endif; ?>
                            <span class="react-summary ms-auto"><span class="react-stack"><?php foreach (mb_str_split($d[7]) as $em) echo '<span>' . $em . '</span>'; ?></span><b><?php echo $d[8]; ?></b></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</header>

<section class="container reveal">
    <div class="card-x stat-strip">
        <div class="row text-center g-4">
            <div class="col-6 col-md-3"><div class="big grad-text" data-count="<?php echo (int)$stats['users_n']; ?>">0</div><div class="text-secondary"><?php echo t('members'); ?></div></div>
            <div class="col-6 col-md-3"><div class="big grad-text" data-count="<?php echo (int)$stats['promises_n']; ?>">0</div><div class="text-secondary"><?php echo t('promises'); ?></div></div>
            <div class="col-6 col-md-3"><div class="big grad-text" data-count="<?php echo (int)$stats['done_n']; ?>">0</div><div class="text-secondary"><?php echo t('promises_kept'); ?></div></div>
            <div class="col-6 col-md-3"><div class="big grad-text" data-count="<?php echo (int)$stats['reactions_n']; ?>">0</div><div class="text-secondary"><?php echo t('reactions'); ?></div></div>
        </div>
    </div>
</section>

<section class="section" id="features">
    <div class="container">
        <h2 class="section-title text-center mb-5 reveal"><?php echo t('features_title'); ?></h2>
        <div class="row g-4">
            <?php
            $features = [['bi-bookmark-heart', 'f1'], ['bi-bar-chart-steps', 'f2'], ['bi-alarm', 'f3'], ['bi-tags', 'f4'], ['bi-fire', 'f5'], ['bi-people', 'f6'],
                         ['bi-emoji-smile', 'f7'], ['bi-trophy', 'f8'], ['bi-bell', 'f9']];
            foreach ($features as $i => $f): ?>
                <div class="col-md-6 col-lg-4 reveal" style="--i:<?php echo $i % 3; ?>">
                    <div class="card-x feature-card">
                        <div class="feature-ico"><i class="bi <?php echo $f[0]; ?>"></i></div>
                        <h5><?php echo t($f[1] . '_t'); ?></h5>
                        <p><?php echo t($f[1] . '_d'); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section pt-0" id="how">
    <div class="container">
        <h2 class="section-title text-center mb-5 reveal"><?php echo t('how_title'); ?></h2>
        <div class="row g-4">
            <?php foreach ([1, 2, 3] as $i): ?>
                <div class="col-md-4 reveal" style="--i:<?php echo $i - 1; ?>">
                    <div class="card-x feature-card">
                        <div class="step-num"><?php echo $i; ?></div>
                        <h5><?php echo t('step' . $i . '_t'); ?></h5>
                        <p><?php echo t('step' . $i . '_d'); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($top): ?>
<section class="section pt-0">
    <div class="container" style="max-width:760px;">
        <h2 class="section-title text-center mb-4 reveal"><?php echo t('top_keepers'); ?></h2>
        <div class="card-x lb-list reveal">
            <?php foreach ($top as $i => $r): ?>
                <div class="lb-row"><span class="lb-rank"><?php echo ['🥇', '🥈', '🥉'][$i]; ?></span><?php echo avatar_html($r, 40); ?>
                    <div class="flex-grow-1 fw-semibold"><?php echo escapeHtml($r['name']); ?></div>
                    <div class="lb-score"><b><?php echo (int)$r['score']; ?></b> <small><?php echo t('lb_kept'); ?></small></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section pt-0" id="explore">
    <div class="container">
        <h2 class="section-title text-center mb-5 reveal"><?php echo t('latest_promises'); ?></h2>
        <?php if (empty($recent)): ?>
            <div class="card-x empty reveal"><i class="bi bi-bookmark-heart"></i><h5 class="mt-3"><?php echo t('no_promises_yet'); ?></h5>
                <a href="<?php echo $primaryHref; ?>" class="btn btn-brand mt-2"><?php echo t('make_first'); ?></a></div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($recent as $i => $r): ?>
                    <div class="col-md-6 col-lg-4 reveal" style="--i:<?php echo $i % 3; ?>">
                        <div class="h-100"><?php render_promise_card($r, $x, ['uid' => 0, 'isAdmin' => false, 'csrf' => '', 'readonly' => true, 'index' => 0]); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-5 reveal">
                <a href="<?php echo $primaryHref; ?>" class="btn btn-ghost btn-lg-x"><?php echo $logged ? t('see_all') : t('join_to_react'); ?> <i class="bi <?php echo i_next(); ?>"></i></a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="container pb-5 reveal">
    <div class="cta">
        <h2 class="section-title mb-4"><?php echo t('cta_title'); ?></h2>
        <a href="<?php echo $primaryHref; ?>" class="btn btn-brand btn-lg-x"><?php echo $primaryText; ?></a>
    </div>
</section>

<footer class="footer-x">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        <a class="brand" href="index.php"><span class="brand-mark"><i class="bi bi-patch-check-fill"></i></span> PromiseEdger</a>
        <div class="d-flex gap-4 small">
            <a href="#features"><?php echo t('nav_features'); ?></a><a href="#how"><?php echo t('nav_how'); ?></a><a href="#explore"><?php echo t('nav_explore'); ?></a>
        </div>
        <div class="small">&copy; <?php echo date('Y'); ?> PromiseEdger</div>
    </div>
</footer>

<?php page_foot(); ?>
