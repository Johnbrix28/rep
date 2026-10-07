<?php
/**
 * Shared add/edit form fields.
 * Expects: $form (array), $cats, $programs, $error, $isEdit (bool), $record (array|null)
 */
?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" data-validate novalidate>
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <label class="form-label" for="title">Research title *</label>
            <input class="form-control" id="title" name="title" required maxlength="255" value="<?= e($form['title']) ?>">

            <label class="form-label mt-3" for="researchers">Authors / researchers *</label>
            <input class="form-control" id="researchers" name="researchers" required placeholder="Juan D. Santos, Maria L. Reyes" value="<?= e($form['researchers']) ?>">
            <div class="form-text">Separate names with commas.</div>

            <label class="form-label mt-3" for="abstract">Abstract</label>
            <textarea class="form-control" id="abstract" name="abstract" rows="9"><?= e($form['abstract']) ?></textarea>

            <label class="form-label mt-3" for="keywords">Keywords</label>
            <input class="form-control" id="keywords" name="keywords" maxlength="500" placeholder="web system, repository, information technology" value="<?= e($form['keywords']) ?>">
        </div>

        <div class="col-lg-4">
            <label class="form-label" for="category_id">Category *</label>
            <select class="form-select" id="category_id" name="category_id" required>
                <option value="">Select category</option>
                <?php foreach ($cats as $cat): ?>
                    <option value="<?= (int) $cat['category_id'] ?>" <?= (int) $form['category_id'] === (int) $cat['category_id'] ? 'selected' : '' ?>><?= e($cat['category_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="form-label mt-3" for="published_date">Date published *</label>
            <input class="form-control" type="date" id="published_date" name="published_date" min="2000-01-01" max="<?= date('Y-m-d') ?>" required value="<?= e($form['published_date']) ?>">
            <div class="form-text">The research year is taken from this date.</div>

            <label class="form-label mt-3" for="program_id">Program</label>
            <select class="form-select" id="program_id" name="program_id">
                <?php foreach ($programs as $p): ?>
                    <option value="<?= (int) $p['program_id'] ?>" <?= (int) $form['program_id'] === (int) $p['program_id'] ? 'selected' : '' ?>><?= e($p['program_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="mt-3">
                <label class="form-label" for="document">Manuscript PDF</label>
                <?php if ($isEdit && $record && research_file_exists($record['file_path'])): ?>
                    <a class="btn btn-sm btn-outline-primary d-block mb-2" href="<?= e(manuscript_viewer_url((int) $record['research_id'])) ?>" target="_blank" rel="noopener">Open current PDF (protected viewer)</a>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="remove_file" value="1" id="remove">
                        <label class="form-check-label" for="remove">Remove current PDF</label>
                    </div>
                <?php endif; ?>
                <input class="form-control" type="file" id="document" name="document" accept="application/pdf,.pdf">
                <div class="form-text">PDF only, up to <?= (int) (MAX_UPLOAD_BYTES / 1048576) ?> MB. Stored privately.</div>
            </div>

            <?php if (!$isEdit): ?>
                <div class="note-box mt-3">New uploads are saved as <strong>Pending</strong>. They stay hidden from students until you approve them.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>admin/research-database.php">Cancel</a>
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Upload (as Pending)' ?></button>
    </div>
</form>
