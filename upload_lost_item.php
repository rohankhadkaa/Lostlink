<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_user();

$error = $_GET["error"] ?? "";

require_once __DIR__ . "/partials/header.php";
?>

<div class="container mt-4" style="max-width:720px;">
  <h2 class="section-title">Upload Lost / Found Item</h2>

  <?php if (!empty($error)): ?>
    <div class="alert alert-warning">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <form method="POST" action="submit_lost_item.php" enctype="multipart/form-data" id="uploadForm">

      <div class="mb-3">
        <label class="form-label">Item Name</label>
        <input class="form-control" name="item_name" required maxlength="120">
      </div>

      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" rows="3"
          placeholder="Add identifying details (color, brand, where lost/found, etc.)"></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">Type</label>
        <select class="form-select" name="item_type" id="itemType" required>
          <option value="lost">Lost (I lost this item)</option>
          <option value="found">Found (I found someone else’s item)</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label" id="pictureLabel">Picture (optional)</label>
        <input class="form-control" type="file" name="picture" id="pictureInput" accept="image/*">
        <div class="text-muted mt-1 small">
          For privacy, public browse may hide images. Admin can still view if enabled.
        </div>
      </div>

      <button class="btn btn-primary">Submit</button>
      <a href="user_dashboard.php" class="btn btn-outline-primary">Back</a>
    </form>
  </div>
</div>

<script>
const typeSelect = document.getElementById("itemType");
const pictureInput = document.getElementById("pictureInput");
const pictureLabel = document.getElementById("pictureLabel");

function updateImageRule() {
  if (typeSelect.value === "found") {
    pictureInput.required = true;
    pictureLabel.innerHTML = `Picture <span class="text-danger">(required)</span>`;
  } else {
    pictureInput.required = false;
    pictureLabel.textContent = "Picture (optional)";
  }
}

typeSelect.addEventListener("change", updateImageRule);
updateImageRule();
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>