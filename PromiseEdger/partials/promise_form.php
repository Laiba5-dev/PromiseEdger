<?php
// partials/promise_form.php - add + edit ke liye common fields
function promise_form_fields(array $v, bool $edit = false) {
    $cat = sanitizeCategory($v['category'] ?? 'other');
    $vis = ($v['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
    $status = $v['status'] ?? 'in_progress';
    $progress = (int)($v['progress'] ?? 0);
    ?>
<div class="mb-4">
    <label class="form-label"><?php echo t('title'); ?></label>
    <input type="text" name="title" class="form-control" maxlength="255" placeholder="<?php echo escapeHtml(t('title_placeholder')); ?>" required
           value="<?php echo escapeHtml($v['title'] ?? ''); ?>">
</div>
<div class="mb-4">
    <label class="form-label"><?php echo t('description'); ?></label>
    <textarea name="description" rows="4" class="form-control" placeholder="<?php echo escapeHtml(t('description_placeholder')); ?>"><?php echo escapeHtml($v['description'] ?? ''); ?></textarea>
</div>
<div class="mb-4">
    <label class="form-label d-block"><?php echo t('category'); ?></label>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach (PE_CATEGORIES as $k => $icon): ?>
            <input type="radio" class="btn-check" name="category" id="cat-<?php echo $k; ?>" value="<?php echo $k; ?>" <?php echo $cat === $k ? 'checked' : ''; ?>>
            <label class="btn btn-ghost btn-sm" for="cat-<?php echo $k; ?>"><i class="bi <?php echo $icon; ?>"></i> <?php echo t('cat_' . $k); ?></label>
        <?php endforeach; ?>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <label class="form-label"><?php echo t('deadline'); ?> <small class="text-secondary">(<?php echo t('optional'); ?>)</small></label>
        <input type="date" name="due_date" class="form-control" dir="ltr" <?php echo $edit ? '' : 'min="' . today() . '"'; ?>
               value="<?php echo escapeHtml($v['due_date'] ?? ''); ?>">
        <div class="form-text"><?php echo t('deadline_hint'); ?></div>
    </div>
    <div class="col-sm-6">
        <label class="form-label d-block"><?php echo t('visibility'); ?></label>
        <input type="radio" class="btn-check" name="visibility" id="v-public" value="public" <?php echo $vis === 'public' ? 'checked' : ''; ?>>
        <label class="btn btn-ghost me-1" for="v-public"><i class="bi bi-globe2"></i> <?php echo t('public'); ?></label>
        <input type="radio" class="btn-check" name="visibility" id="v-private" value="private" <?php echo $vis === 'private' ? 'checked' : ''; ?>>
        <label class="btn btn-ghost" for="v-private"><i class="bi bi-lock"></i> <?php echo t('private'); ?></label>
    </div>
</div>
<?php if ($edit): ?>
<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <label class="form-label"><?php echo t('status'); ?></label>
        <select name="status" class="form-select">
            <?php foreach (PE_STATUSES as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo t('st_' . $s); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-6">
        <label class="form-label"><?php echo t('progress'); ?> (%)</label>
        <input type="number" name="progress" class="form-control" min="0" max="100" dir="ltr" value="<?php echo $progress; ?>">
    </div>
</div>
<?php endif; ?>
    <?php
}
