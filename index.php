<?php require_once __DIR__ . "/partials/header.php"; ?>

<div class="page">
  <div class="shell">

    <!-- HERO -->
    <section class="hero mb-5">
      <span class="kicker">Victoria University</span>

      <h1>VU LostLink</h1>

      <p>
        Victoria University's secure Lost &amp; Found platform — report items, verify ownership,
        and safely return belongings on campus.
      </p>

      <div class="d-flex gap-3 flex-wrap align-items-center">
        <a href="browse_items.php" class="btn btn-light btn-lg fw-semibold">
          <i class="bi bi-search me-2"></i>Browse Items
        </a>

        <?php if (empty($_SESSION["user_id"])): ?>
          <a href="login.php" class="btn btn-outline-light btn-lg">
            <i class="bi bi-box-arrow-in-right me-2"></i>Login
          </a>
          <a href="register.php" class="btn btn-outline-light btn-lg">
            <i class="bi bi-person-plus me-2"></i>Register
          </a>
        <?php else: ?>
          <a href="dashboard.php" class="btn btn-outline-light btn-lg">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
          </a>
        <?php endif; ?>
      </div>

      <div class="mt-4 small" style="opacity:0.9;">
        Collection / Handover Point: <strong>Level G – University Building</strong>
      </div>
    </section>

    <!-- FEATURES -->
    <section class="mb-5">
      <div class="row g-4 text-center">

        <div class="col-md-4">
          <div class="feature-card h-100">
            <div class="icon-circle mb-3">
              <i class="bi bi-shield-lock fs-2"></i>
            </div>
            <h5>Secure Verification</h5>
            <p class="mb-0">
              Admin verifies ownership using structured questions to prevent false claims.
            </p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="feature-card h-100">
            <div class="icon-circle mb-3">
              <i class="bi bi-lightning-charge fs-2"></i>
            </div>
            <h5>Easy Reporting</h5>
            <p class="mb-0">
              Users can report lost items or found items in seconds through a simple form.
            </p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="feature-card h-100">
            <div class="icon-circle mb-3">
              <i class="bi bi-eye-slash fs-2"></i>
            </div>
            <h5>Privacy First</h5>
            <p class="mb-0">
              Public listings show limited details to protect security and reduce theft risk.
            </p>
          </div>
        </div>

      </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="panel mb-5">
      <div class="panel-header">
        <h2 class="panel-title">How It Works</h2>
        <span class="chip">Clear verification flow</span>
      </div>

      <div class="panel-body">
        <div class="row text-center g-4">

          <div class="col-md-3">
            <i class="bi bi-upload fs-2 text-primary"></i>
            <h6 class="mt-3">1) Report</h6>
            <p class="small mb-0 text-muted">
              Report a lost item or submit a found item via your dashboard.
            </p>
          </div>

          <div class="col-md-3">
            <i class="bi bi-question-circle fs-2 text-warning"></i>
            <h6 class="mt-3">2) Verify</h6>
            <p class="small mb-0 text-muted">
              Admin sends verification questions to confirm ownership.
            </p>
          </div>

          <div class="col-md-3">
            <i class="bi bi-check-circle fs-2 text-success"></i>
            <h6 class="mt-3">3) Decide</h6>
            <p class="small mb-0 text-muted">
              Admin reviews answers and approves or rejects the request.
            </p>
          </div>

          <div class="col-md-3">
            <i class="bi bi-building fs-2 text-danger"></i>
            <h6 class="mt-3">4) Collect / Handover</h6>
            <p class="small mb-0 text-muted">
              Approved users collect items or hand them to staff at Level G.
            </p>
          </div>

        </div>
      </div>
    </section>

    <!-- SUPPORT CTA -->
    <section class="panel">
      <div class="panel-body">
        <div class="row align-items-center g-3">
          <div class="col-md-8">
            <h4 class="mb-1" style="color: var(--text-900); font-weight: 950;">Need help?</h4>
            <div class="text-muted">
              Visit the Help page for instructions, collection details, and contact support.
            </div>
          </div>
          <div class="col-md-4 text-md-end">
            <a href="help.php" class="btn btn-primary">
              <i class="bi bi-life-preserver me-2"></i>Help &amp; Contact
            </a>
          </div>
        </div>
      </div>
    </section>

  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>