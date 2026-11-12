<?php
// partials/promise_card.php - promise card ke saare hisse. Har "region" AJAX se alag replace hota hai.

function card_badges(array $p): string {
    $cat = $p['category'] ?? 'other';
    $icon = PE_CATEGORIES[$cat] ?? 'bi-stars';
    $status = $p['status'] ?? 'in_progress';
    $sIcon = ['in_progress' => 'bi-hourglass-split', 'done' => 'bi-check-circle-fill', 'missed' => 'bi-x-circle-fill'][$status] ?? 'bi-hourglass-split';

    $h = '<div class="js-badges p-badges">';
    $h .= '<a class="badge-x cat" href="dashboard.php?cat=' . escapeHtml($cat) . '"><i class="bi ' . $icon . '"></i> ' . escapeHtml(t('cat_' . $cat)) . '</a>';
    $h .= '<span class="badge-x st-' . escapeHtml($status) . '"><i class="bi ' . $sIcon . '"></i> ' . escapeHtml(t('st_' . $status)) . '</span>';
    if ($due = dueInfo($p)) {
        $h .= '<span class="badge-x due-' . $due['class'] . '" title="' . escapeHtml(fmtDate($p['due_date'])) . '"><i class="bi ' . $due['icon'] . '"></i> ' . escapeHtml($due['label']) . '</span>';
    }
    return $h . '</div>';
}

function card_progress(array $p, bool $canEdit): string {
    $id = (int)$p['id'];
    $pct = (int)($p['progress'] ?? 0);
    $status = $p['status'] ?? 'in_progress';

    $h = '<div class="js-progress">';
    $h .= '<div class="prog st-' . escapeHtml($status) . '">';
    $h .= '<div class="prog-row"><span>' . escapeHtml(t('progress')) . '</span>';
    if ($status === 'done' && !empty($p['completed_at'])) {
        $h .= '<span class="prog-done"><i class="bi bi-check2-all"></i> ' . escapeHtml(t('completed_on', fmtDate($p['completed_at']))) . '</span>';
    }
    $h .= '<b>' . $pct . '%</b></div>';
    $h .= '<div class="prog-bar" role="progressbar" aria-valuenow="' . $pct . '" aria-valuemin="0" aria-valuemax="100"><span class="prog-fill" style="--w:' . $pct . '%"></span></div>';
    $h .= '</div>';

    if ($canEdit) {
        $h .= '<button type="button" class="soft-link" data-bs-toggle="collapse" data-bs-target="#upd-' . $id . '"><i class="bi bi-sliders"></i> ' . escapeHtml(t('update_progress')) . '</button>';
        $h .= '<div class="collapse" id="upd-' . $id . '"><form class="upd-form" data-id="' . $id . '">';
        $h .= '<div class="seg" role="group">';
        foreach (PE_STATUSES as $s) {
            $h .= '<input type="radio" class="btn-check" name="status" id="st-' . $id . '-' . $s . '" value="' . $s . '"' . ($status === $s ? ' checked' : '') . '>'
                . '<label class="seg-btn s-' . $s . '" for="st-' . $id . '-' . $s . '">' . escapeHtml(t('st_' . $s)) . '</label>';
        }
        $h .= '</div>';
        $h .= '<div class="range-row"><input type="range" class="range-x" name="progress" min="0" max="100" step="5" value="' . $pct . '"><output>' . $pct . '%</output></div>';
        $h .= '<button type="submit" class="btn btn-brand btn-sm"><i class="bi bi-check2"></i> ' . escapeHtml(t('save')) . '</button>';
        $h .= '</form></div>';
    }
    return $h . '</div>';
}

function card_streak(array $p, array $weekDates, bool $isOwner): string {
    $streak = effectiveStreak($p);
    $active = ($p['status'] ?? '') === 'in_progress';
    if (!($isOwner && $active) && $streak === 0) return '<div class="js-streak"></div>';

    $id = (int)$p['id'];
    $checked = ($p['last_checkin'] ?? '') === today();
    $tier = streakTier($streak);
    $set = array_flip($weekDates);
    $days = t('weekdays_short');

    $h = '<div class="js-streak streak">';
    $h .= '<div class="streak-top">';
    $h .= '<span class="streak-badge tier-' . $tier . ($streak ? '' : ' cold') . '"><i class="bi bi-fire"></i> '
        . escapeHtml($streak === 1 ? t('streak_1') : t('streak_n', $streak)) . '</span>';
    if ((int)($p['best_streak'] ?? 0) > $streak) {
        $h .= '<span class="streak-best">' . escapeHtml(t('best_streak', (int)$p['best_streak'])) . '</span>';
    }
    $h .= '</div>';
    $h .= '<div class="streak-row"><div class="week-dots" aria-hidden="true">';
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $on = isset($set[$d]);
        $h .= '<span class="wd' . ($on ? ' on' : '') . ($i === 0 ? ' now' : '') . '"><i>' . escapeHtml($days[(int)date('w', strtotime($d))] ?? '') . '</i></span>';
    }
    $h .= '</div>';
    if ($isOwner && $active) {
        if ($checked) {
            $h .= '<button type="button" class="btn-checkin done" disabled><i class="bi bi-check2-circle"></i> ' . escapeHtml(t('checked_in_today')) . '</button>';
        } else {
            $h .= '<button type="button" class="btn-checkin" data-id="' . $id . '"><i class="bi bi-calendar-check"></i> ' . escapeHtml(t('check_in_today')) . '</button>';
        }
    }
    return $h . '</div></div>';
}

function card_react(array $p, array $counts, ?string $mine, bool $interactive): string {
    $id = (int)$p['id'];
    arsort($counts);
    $total = array_sum($counts);

    $h = '<div class="js-react react-wrap" data-id="' . $id . '">';
    if ($interactive) {
        if ($mine && isset(PE_REACTIONS[$mine])) {
            $h .= '<button type="button" class="act react-btn on" aria-haspopup="true"><span class="r-emoji">' . PE_REACTIONS[$mine] . '</span> ' . escapeHtml(t('react_' . $mine)) . '</button>';
        } else {
            $h .= '<button type="button" class="act react-btn" aria-haspopup="true"><i class="bi bi-emoji-smile"></i> ' . escapeHtml(t('react')) . '</button>';
        }
        $h .= '<div class="react-picker" role="menu">';
        foreach (PE_REACTIONS as $k => $e) {
            $h .= '<button type="button" class="react-opt' . ($mine === $k ? ' sel' : '') . '" data-type="' . $k . '" title="' . escapeHtml(t('react_' . $k)) . '" role="menuitem"><span>' . $e . '</span></button>';
        }
        $h .= '</div>';
    }
    if ($total > 0) {
        $tip = [];
        foreach ($counts as $k => $c) if (isset(PE_REACTIONS[$k])) $tip[] = PE_REACTIONS[$k] . ' ' . $c;
        $h .= '<span class="react-summary" title="' . escapeHtml(implode('  ', $tip)) . '"><span class="react-stack">';
        $i = 0;
        foreach ($counts as $k => $c) {
            if (!isset(PE_REACTIONS[$k]) || $i++ >= 3) continue;
            $h .= '<span>' . PE_REACTIONS[$k] . '</span>';
        }
        $h .= '</span><b>' . $total . '</b></span>';
    }
    return $h . '</div>';
}

function render_comment(array $c, array $ctx): string {
    $canDelete = !$ctx['readonly'] && ((int)$c['user_id'] === $ctx['uid'] || $ctx['isAdmin']);
    $h = '<div class="cmt" id="comment-' . (int)$c['id'] . '">' . avatar_html(['name' => $c['author_name'], 'profile_picture' => $c['author_pic'] ?? ''], 32);
    $h .= '<div class="cmt-body"><div><a class="fw-semibold small cmt-name" href="profile.php?id=' . (int)$c['user_id'] . '">' . escapeHtml($c['author_name']) . '</a>';
    $h .= '<span class="cmt-time"> &middot; ' . escapeHtml(formatDate($c['created_at'])) . '</span></div>';
    $h .= '<div class="cmt-text">' . nl2br(escapeHtml($c['comment'])) . '</div></div>';
    if ($canDelete) {
        $h .= '<form method="POST" action="delete_comment.php" data-confirm="' . escapeHtml(t('confirm_delete_comment')) . '">'
            . '<input type="hidden" name="csrf" value="' . escapeHtml($ctx['csrf']) . '"><input type="hidden" name="id" value="' . (int)$c['id'] . '">'
            . '<button class="icon-mini" aria-label="' . escapeHtml(t('delete')) . '"><i class="bi bi-trash"></i></button></form>';
    }
    return $h . '</div>';
}

/**
 * $x = loadCardExtras(...) ; $ctx = ['uid'=>, 'isAdmin'=>, 'csrf'=>, 'readonly'=>bool, 'open_comments'=>bool, 'index'=>int]
 */
function render_promise_card(array $p, array $x, array $ctx): void {
    $ctx += ['readonly' => false, 'open_comments' => false, 'index' => 0];
    $id = (int)$p['id'];
    $isOwner = !$ctx['readonly'] && ((int)$p['user_id'] === $ctx['uid']);
    $canManage = $isOwner || (!$ctx['readonly'] && $ctx['isAdmin']);
    $private = $p['visibility'] === 'private';
    $comments = $x['comments'][$id] ?? [];
    $counts = $x['reactions'][$id] ?? [];
    $week = $x['week'][$id] ?? [];
    $following = !empty($x['following'][(int)$p['user_id']]);
    $reported = !empty($x['reported'][$id]);
    ?>
<article class="p-card status-<?php echo escapeHtml($p['status']); ?> <?php echo $private ? 'is-private' : ''; ?> anim-up"
         style="--i:<?php echo min((int)$ctx['index'], 8); ?>" id="promise-<?php echo $id; ?>" data-id="<?php echo $id; ?>">
    <div class="p-head">
        <?php echo avatar_html(['name' => $p['author_name'], 'profile_picture' => $p['author_pic'] ?? ''], 42); ?>
        <div class="p-author">
            <a class="p-name" href="<?php echo $ctx['readonly'] ? '#' : 'profile.php?id=' . (int)$p['user_id']; ?>"><?php echo escapeHtml($p['author_name']); ?></a>
            <div class="p-time"><?php echo escapeHtml(formatDate($p['created_at'])); ?></div>
        </div>

        <?php if (!$ctx['readonly'] && !$isOwner): ?>
            <button type="button" class="follow-btn <?php echo $following ? 'on' : ''; ?>" data-user="<?php echo (int)$p['user_id']; ?>" data-following="<?php echo $following ? 1 : 0; ?>">
                <i class="bi <?php echo $following ? 'bi-check2' : 'bi-plus-lg'; ?>"></i>
                <span><?php echo escapeHtml($following ? t('following') : t('follow')); ?></span>
            </button>
        <?php endif; ?>

        <?php if ($private): ?>
            <span class="pill pill-private"><i class="bi bi-lock"></i> <?php echo t('private'); ?></span>
        <?php else: ?>
            <span class="pill pill-public"><i class="bi bi-globe2"></i> <?php echo t('public'); ?></span>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <div class="dropdown">
                <button class="icon-mini" data-bs-toggle="dropdown" aria-label="Options"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="edit_promise.php?id=<?php echo $id; ?>"><i class="bi bi-pencil"></i> <?php echo t('edit'); ?></a></li>
                    <li>
                        <form method="POST" action="delete_promise.php" data-confirm="<?php echo escapeHtml(t('confirm_delete_promise')); ?>">
                            <input type="hidden" name="csrf" value="<?php echo escapeHtml($ctx['csrf']); ?>">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> <?php echo t('delete'); ?></button>
                        </form>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <?php echo card_badges($p); ?>

    <h3 class="p-title"><a href="<?php echo $ctx['readonly'] ? '#' : 'promise.php?id=' . $id; ?>"><?php echo escapeHtml($p['title']); ?></a></h3>
    <?php if (!empty($p['description'])): ?>
        <p class="p-desc"><?php echo nl2br(escapeHtml($p['description'])); ?></p>
    <?php endif; ?>

    <?php echo card_progress($p, $isOwner); ?>
    <?php if (!$ctx['readonly']) echo card_streak($p, $week, $isOwner); ?>

    <div class="p-actions">
        <?php echo card_react($p, $counts, $p['my_reaction'] ?? null, !$ctx['readonly']); ?>
        <?php if (!$ctx['readonly']): ?>
            <button type="button" class="act" data-bs-toggle="collapse" data-bs-target="#comments-<?php echo $id; ?>">
                <i class="bi bi-chat"></i> <?php echo t('comments'); ?> <span class="cnt js-ccount"><?php echo count($comments); ?></span>
            </button>
            <button type="button" class="act share-btn" data-url="promise.php?id=<?php echo $id; ?>"><i class="bi bi-share"></i> <?php echo t('share'); ?></button>
            <?php if (!$isOwner): ?>
                <button type="button" class="act report-btn ms-auto <?php echo $reported ? 'reported' : ''; ?>" data-id="<?php echo $id; ?>" <?php echo $reported ? 'disabled' : ''; ?> title="<?php echo escapeHtml(t('report')); ?>">
                    <i class="bi <?php echo $reported ? 'bi-flag-fill' : 'bi-flag'; ?>"></i> <span class="d-none d-sm-inline"><?php echo $reported ? t('reported') : t('report'); ?></span>
                </button>
            <?php endif; ?>
        <?php else: ?>
            <span class="act no-hover"><i class="bi bi-chat"></i> <?php echo count($comments); ?></span>
        <?php endif; ?>
    </div>

    <?php if (!$ctx['readonly']): ?>
    <div class="collapse mt-3 <?php echo $ctx['open_comments'] ? 'show' : ''; ?>" id="comments-<?php echo $id; ?>">
        <div class="comment-box">
            <div class="cmt-list">
                <?php if (empty($comments)): ?><p class="text-secondary small mb-3 js-nocomments"><?php echo t('no_comments_yet'); ?></p><?php endif; ?>
                <?php foreach ($comments as $c) echo render_comment($c, $ctx); ?>
            </div>
            <form method="POST" action="comment.php" class="cmt-form" data-id="<?php echo $id; ?>">
                <input type="hidden" name="csrf" value="<?php echo escapeHtml($ctx['csrf']); ?>">
                <input type="hidden" name="promise_id" value="<?php echo $id; ?>">
                <div class="input-group">
                    <input type="text" name="comment" class="form-control" placeholder="<?php echo escapeHtml(t('add_comment')); ?>" maxlength="1000" required>
                    <button class="btn btn-brand" type="submit" aria-label="<?php echo escapeHtml(t('send')); ?>"><i class="bi bi-send"></i></button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</article>
    <?php
}

/** Feed ke liye: ek saath kai cards */
function render_promise_cards(array $promises, array $x, array $ctx): void {
    foreach ($promises as $i => $p) {
        render_promise_card($p, $x, $ctx + ['index' => $i]);
    }
}
