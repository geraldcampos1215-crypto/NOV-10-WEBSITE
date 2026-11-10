

<?php
require_once __DIR__ . '/../includes/auth.php';

$u = require_role('seeker');
$layout = 'dash';
$pageTitle = 'Saved Jobs';

$jobs = rows(
    JOB_SELECT . ' JOIN saved_jobs s ON s.job_id=j.id
    WHERE s.user_id=?
    ORDER BY s.created_at DESC',
    [$u['id']]
);

$saved = array_column($jobs, 'id');

include ROOT_PATH . '/includes/header.php';
?>

<!-- Saved Jobs Search -->
<div class="mb-4">
    <h5 class="mb-3">Find Your Saved Jobs</h5>

    <div class="position-relative">
        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>

        <input
            type="text"
            id="savedJobSearch"
            class="form-control ps-5 py-3"
            placeholder="Search by job title or company name..."
            autocomplete="off"
        >
    </div>

    <small class="text-muted d-block mt-2">
        Search through your saved jobs easily.
    </small>
</div>

<!-- Saved Jobs List -->
<div id="savedJobsList">

<?php
foreach ($jobs as $j) {
    echo str_replace(
        'btn-save"',
        'btn-save" data-remove="1"',
        job_card($j, $saved)
    );
}
?>

<?php if (!$jobs): ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-heart fs-1"></i>
        <p>You have no saved jobs yet.
            <a href="browse-jobs.php">Browse jobs</a>
        </p>
    </div>
<?php endif; ?>

</div>

<!-- No Search Results -->
<div id="noSearchResults" class="text-center text-muted py-5" style="display:none;">
    <i class="bi bi-search fs-1"></i>
    <p class="mt-2 mb-0">No matching jobs found.</p>
    <small>Try searching another job title or company.</small>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.getElementById('savedJobSearch');
    const jobsList = document.getElementById('savedJobsList');
    const noResults = document.getElementById('noSearchResults');

    if (!searchInput || !jobsList) return;

    const jobCards = Array.from(jobsList.children);

    searchInput.addEventListener('input', function () {

        const searchValue = this.value.toLowerCase().trim();
        let visibleCount = 0;

        jobCards.forEach(function (card) {

            const jobText = card.textContent.toLowerCase();

            const matches = jobText.includes(searchValue);

            card.style.display = matches ? '' : 'none';

            if (matches) {
                visibleCount++;
            }

        });

        if (noResults) {
            noResults.style.display =
                visibleCount === 0 ? 'block' : 'none';
        }

    });

});
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
