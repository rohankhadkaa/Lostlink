<?php require_once __DIR__ . "/partials/header.php"; ?>

<div class="page">
  <div class="shell page-narrow">

    <div class="page-head">
      <div>
        <h1>About VU LostLink</h1>
        <p>
          A secure and structured Lost &amp; Found management system designed for university campus environments.
        </p>
      </div>
    </div>

    <!-- Introduction -->
    <section class="panel mb-4">
      <div class="panel-header">
        <h2 class="panel-title">Our Mission</h2>
        <span class="chip">Secure • Transparent • Efficient</span>
      </div>
      <div class="panel-body">
        <p>
          <strong>VU LostLink</strong> is a secure and structured Lost &amp; Found management system
          designed specifically for university campus environments.
          It provides a streamlined digital solution for reporting items, claiming belongings,
          verifying ownership, and safely returning items on campus.
        </p>
        <p class="mb-0">
          The system prioritises <strong>security, transparency, and efficiency</strong>
          while reducing manual paperwork and preventing fraudulent claims.
        </p>
      </div>
    </section>

    <!-- How It Works -->
    <section class="panel mb-4">
      <div class="panel-header">
        <h2 class="panel-title">How the System Works</h2>
        <span class="chip">Claim &amp; verify workflow</span>
      </div>
      <div class="panel-body">
        <ol class="mb-0">
          <li><strong>Report or Browse</strong> – Students and staff report lost or found items. Found items appear in <strong>Browse</strong> with limited public detail.</li>
          <li><strong>Submit a Claim</strong> – If you recognise your item in Browse, submit a claim with your details and a description as proof of ownership.</li>
          <li><strong>Ownership Verification</strong> – An administrator reviews the claim and sends structured verification questions. You answer them securely in-app, and a per-claim conversation thread is available for any back-and-forth.</li>
          <li><strong>Decision</strong> – The admin verifies ownership or rejects the claim. You are notified in-app at every step.</li>
          <li><strong>Collection / Handover</strong> – Verified claims are marked <strong>Ready for Collection</strong>. Collect your item at <strong>Level G (University Building)</strong>, after which the admin marks it <strong>Collected</strong>.</li>
        </ol>
      </div>
    </section>

    <!-- Claim status journey -->
    <section class="panel mb-4">
      <div class="panel-header">
        <h2 class="panel-title">The Claim Status Journey</h2>
        <span class="chip">Tracked end-to-end</span>
      </div>
      <div class="panel-body">
        <p>Every claim moves through a clear, trackable set of stages:</p>
        <ul class="mb-0">
          <li><strong>Submitted</strong> → <strong>Under Review</strong> → <strong>Verification in Progress</strong> (with <strong>Awaiting Claimant Response</strong> while you answer questions)</li>
          <li>→ <strong>Verified</strong> → <strong>Ready for Collection</strong> → <strong>Collected</strong></li>
          <li>A claim can also be <strong>Rejected</strong> if ownership cannot be confirmed.</li>
        </ul>
      </div>
    </section>

    <!-- Security & Features -->
    <section class="panel mb-4">
      <div class="panel-header">
        <h2 class="panel-title">Security &amp; Key Features</h2>
        <span class="status-pill info">Built for campus safety</span>
      </div>
      <div class="panel-body">
        <ul class="mb-0">
          <li>Email-based OTP authentication for secure login</li>
          <li>Role-based access control (User / Admin)</li>
          <li>User-initiated claims with structured, multi-round ownership verification</li>
          <li>In-app conversation thread on every claim — all communication stays inside the platform</li>
          <li>Real-time status tracking from Submitted through to Collected</li>
          <li>Dedicated <strong>Admin Verification Portal</strong> with statistics, a request queue, and an audit log</li>
          <li>Full audit trail recording every decision, who made it, and when</li>
          <li>Item photos visible to administrators only, to protect privacy and reduce false claims</li>
          <li>In-system notification centre for all status updates</li>
          <li>Admin management of users and claims (set roles, remove accounts, remove claims/items)</li>
          <li>Integrated chatbot for instant help and FAQs</li>
        </ul>
      </div>
    </section>

    <!-- Benefits -->
    <section class="panel mb-4">
      <div class="panel-header">
        <h2 class="panel-title">Benefits to Campus Community</h2>
        <span class="chip">Faster • Safer • Centralised</span>
      </div>
      <div class="panel-body">
        <ul class="mb-0">
          <li>A simple, self-service way to claim your own belongings</li>
          <li>Reduced administrative workload through a single verification portal</li>
          <li>Improved security and fraud prevention via structured verification</li>
          <li>Centralised tracking, transparency, and a complete audit trail</li>
          <li>Professional digital solution aligned with modern campus needs</li>
        </ul>
      </div>
    </section>

    <!-- Closing -->
    <section class="panel">
      <div class="panel-header">
        <h2 class="panel-title">Our Vision</h2>
        <span class="chip">Scalable future roadmap</span>
      </div>
      <div class="panel-body">
        <p>
          VU LostLink aims to become a fully scalable campus solution
          that can be extended with AI-based matching, analytics dashboards,
          and mobile integration in future versions.
        </p>
        <p class="mb-0">
          We are committed to creating a safer, smarter, and more connected university environment.
        </p>
      </div>
    </section>

  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>