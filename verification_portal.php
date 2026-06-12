<?php
// verification_portal.php — dedicated Admin Verification Portal (sidebar module)
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/notifications.php";

require_admin(); // role-based access control — admins only

$adminId = (int)($_SESSION["user_id"] ?? 0);

$view = $_GET["view"] ?? "dashboard";
$allowedViews = ["dashboard","queue","submissions","submission","audit"];
if (!in_array($view, $allowedViews, true)) { $view = "dashboard"; }

$STATUS = [
  'submitted'            => ['Claimed', 'secondary'],
  'under_review'         => ['Under Review', 'warning text-dark'],
  'verification'         => ['Verification in Progress', 'info'],
  'awaiting_response'    => ['Awaiting Claimant Response', 'primary'],
  'verified'             => ['Verified', 'success'],
  'ready_for_collection' => ['Ready for Collection', 'success'],
  'collected'            => ['Collected', 'dark'],
  'rejected'             => ['Rejected', 'danger'],
];
$ACTION_NEEDED = ['submitted','under_review','verification','verified'];

// ---- Submission review actions: approve / reject / request clarification ----
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submission_action"])) {
    $iid    = (int)($_POST["item_id"] ?? 0);
    $action = $_POST["submission_action"];
    $note   = trim($_POST["note"] ?? "");
    $q = $conn->prepare("SELECT user_id, item_name FROM lost_items WHERE id=? LIMIT 1");
    $q->bind_param("i", $iid); $q->execute();
    $it = $q->get_result()->fetch_assoc();
    $map = ['approve'=>'approved','reject'=>'rejected','clarify'=>'clarification'];
    if ($it && isset($map[$action])) {
        $newStatus = $map[$action];
        $up = $conn->prepare("UPDATE lost_items SET review_status=?, review_note=?, reviewed_at=NOW() WHERE id=?");
        $up->bind_param("ssi", $newStatus, $note, $iid); $up->execute();
        $titles = ['approved'=>"Report Approved", 'rejected'=>"Report Rejected", 'clarification'=>"More Information Needed"];
        $msgs = [
          'approved'      => "Your report '{$it['item_name']}' has been approved.",
          'rejected'      => "Your report '{$it['item_name']}' was rejected." . ($note!==""?" Reason: $note":""),
          'clarification' => "An admin needs more information about '{$it['item_name']}'." . ($note!==""?" $note":""),
        ];
        add_notification((int)$it['user_id'], $titles[$newStatus], $msgs[$newStatus]);
    }
    header("Location: verification_portal.php?view=submissions&msg=done");
    exit;
}

require_once __DIR__ . "/partials/header.php";
?>

<style>
  .vp-side .list-group-item { border:0; }
  .vp-side .list-group-item.active { background:#0d6efd; }
  .vp-stat { text-align:center; color:#eef2f7; }
  .vp-stat .num { font-size:1.8rem; font-weight:700; line-height:1.2; }
  .vp-stat .text-muted { color:#aab4c5 !important; }
  .vp-recent { color:#eef2f7; }
  .vp-recent .text-muted { color:#aab4c5 !important; }
  .vp-recent a { color:#7fb2ff; text-decoration:none; }
</style>

<div class="container-fluid mt-4">
  <div class="row g-3">

    <!-- Sidebar -->
    <div class="col-12 col-md-3 col-lg-2 vp-side">
      <div class="card p-0">
        <div class="list-group list-group-flush">
          <a class="list-group-item list-group-item-action <?= $view==='dashboard'?'active':'' ?>"
             href="?view=dashboard"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
          <a class="list-group-item list-group-item-action <?= $view==='queue'?'active':'' ?>"
             href="?view=queue"><i class="bi bi-inbox me-2"></i>Request Queue</a>
          <a class="list-group-item list-group-item-action <?= in_array($view,['submissions','submission'],true)?'active':'' ?>"
             href="?view=submissions"><i class="bi bi-clipboard-check me-2"></i>Submissions</a>
          <a class="list-group-item list-group-item-action <?= $view==='audit'?'active':'' ?>"
             href="?view=audit"><i class="bi bi-clock-history me-2"></i>Audit Log</a>
          <a class="list-group-item list-group-item-action" href="admin_dashboard.php">
            <i class="bi bi-arrow-left me-2"></i>Back to Admin</a>
        </div>
      </div>
    </div>

    <!-- Main -->
    <div class="col-12 col-md-9 col-lg-10">

    <?php if ($view === "dashboard"): ?>
      <?php
        $counts = array_fill_keys(array_keys($STATUS), 0);
        $total = 0; $needAction = 0;
        // count the SAME claims the queue shows (valid item + claimant) so the numbers match
        if ($r = $conn->query("
                SELECT c.status AS status, COUNT(*) AS cnt
                FROM item_claims c
                JOIN lost_items li ON li.id = c.item_id
                JOIN users u       ON u.id  = c.claimant_id
                GROUP BY c.status")) {
            while ($row = $r->fetch_assoc()) {
                if (isset($counts[$row["status"]])) $counts[$row["status"]] = (int)$row["cnt"];
                $total += (int)$row["cnt"];
                if (in_array($row["status"], $ACTION_NEEDED, true)) $needAction += (int)$row["cnt"];
            }
        }
      ?>
      <h3 class="mb-3">Verification Dashboard</h3>

      <!-- Key figures -->
      <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
          <div class="card vp-stat"><div class="num"><?= $total ?></div><div class="text-muted">Total Claims</div></div>
        </div>
        <div class="col-12 col-md-6">
          <a href="?view=queue&status=needs_action" class="text-decoration-none">
            <div class="card vp-stat"><div class="num text-warning"><?= $needAction ?></div><div class="text-muted">Needs Admin Action</div></div>
          </a>
        </div>
      </div>

      <!-- Status breakdown -->
      <div class="text-muted small mb-2">Status breakdown (click to open the queue)</div>
      <div class="row g-3 mb-4">
        <?php foreach ($STATUS as $key => [$lbl,$cls]): ?>
          <div class="col-6 col-md-3">
            <a href="?view=queue&status=<?= $key ?>" class="text-decoration-none">
              <div class="card vp-stat">
                <div class="num"><span class="badge bg-<?= $cls ?>"><?= $counts[$key] ?></span></div>
                <div class="text-muted small"><?= htmlspecialchars($lbl) ?></div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Recent activity -->
      <div class="card vp-recent">
        <h5 class="mb-3">Recent activity</h5>
        <?php
          $ra = $conn->query("
            SELECT a.action, a.created_at, a.claim_id,
                   COALESCE(u.full_name,'System') AS actor, li.item_name,
                   c.claimant_name, c.contact_info, c.created_at AS claimed_at,
                   cu.full_name AS claimant_account
            FROM claim_audit a
            LEFT JOIN users u  ON u.id=a.user_id
            LEFT JOIN item_claims c ON c.id=a.claim_id
            LEFT JOIN lost_items li ON li.id=c.item_id
            LEFT JOIN users cu ON cu.id=c.claimant_id
            ORDER BY a.id DESC LIMIT 8
          ");
          if ($ra && $ra->num_rows):
            while ($a = $ra->fetch_assoc()):
        ?>
          <?php $claimant = $a['claimant_name'] ?: ($a['claimant_account'] ?? ''); ?>
          <div class="border-bottom py-2 small">
            <div class="d-flex justify-content-between">
              <span><b><?= htmlspecialchars(ucwords(str_replace('_',' ',$a['action']))) ?></b>
                — <a href="claim_view.php?id=<?= (int)$a['claim_id'] ?>">#<?= (int)$a['claim_id'] ?> <?= htmlspecialchars($a['item_name'] ?? '') ?></a>
                <span class="text-muted">by <?= htmlspecialchars($a['actor']) ?></span></span>
              <span class="text-muted"><?= htmlspecialchars($a['created_at']) ?></span>
            </div>
            <div class="text-muted">
              Claimed by <?= htmlspecialchars($claimant !== '' ? $claimant : '—') ?>
              <?php if (!empty($a['contact_info'])): ?> &middot; <?= htmlspecialchars($a['contact_info']) ?><?php endif; ?>
              <?php if (!empty($a['claimed_at'])): ?> &middot; on <?= htmlspecialchars($a['claimed_at']) ?><?php endif; ?>
            </div>
          </div>
        <?php endwhile; else: ?>
          <div class="text-muted small">No activity yet.</div>
        <?php endif; ?>
      </div>

    <?php elseif ($view === "queue"): ?>
      <?php
        $statusFilter = $_GET["status"] ?? "all";
        $q = trim($_GET["q"] ?? "");
        $sql = "
          SELECT c.id, c.claimant_name, c.status, c.created_at,
                 li.item_name, u.full_name AS account_name
          FROM item_claims c
          JOIN lost_items li ON li.id=c.item_id
          JOIN users u ON u.id=c.claimant_id
        ";
        $conds=[]; $params=[]; $types="";
        if ($statusFilter === "needs_action") {
            $ph = implode(",", array_fill(0,count($ACTION_NEEDED),"?"));
            $conds[] = "c.status IN ($ph)";
            foreach ($ACTION_NEEDED as $s){$params[]=$s;$types.="s";}
        } elseif (isset($STATUS[$statusFilter])) {
            $conds[]="c.status=?"; $params[]=$statusFilter; $types.="s";
        }
        if ($q!==""){
            $like="%$q%"; $idm=ctype_digit($q)?(int)$q:0;
            $conds[]="(li.item_name LIKE ? OR c.claimant_name LIKE ? OR u.full_name LIKE ? OR c.id=?)";
            array_push($params,$like,$like,$like,$idm); $types.="sssi";
        }
        if($conds){$sql.=" WHERE ".implode(" AND ",$conds);}
        $sql.=" ORDER BY c.created_at DESC";
        $stq=$conn->prepare($sql);
        if($types!==""){$stq->bind_param($types,...$params);}
        $stq->execute(); $rows=$stq->get_result();
      ?>
      <h3 class="mb-3">Request Queue</h3>
      <form method="GET" class="row g-2 mb-3">
        <input type="hidden" name="view" value="queue">
        <div class="col-md-5"><input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search item, claimant, or claim ID..."></div>
        <div class="col-md-4">
          <select name="status" class="form-select">
            <option value="all" <?= $statusFilter==='all'?'selected':'' ?>>All statuses</option>
            <option value="needs_action" <?= $statusFilter==='needs_action'?'selected':'' ?>>Needs admin action</option>
            <?php foreach ($STATUS as $key=>[$lbl,$x]): ?>
              <option value="<?= $key ?>" <?= $statusFilter===$key?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button class="btn btn-primary flex-fill">Filter</button>
          <a href="?view=queue" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>

      <table class="table table-bordered bg-white">
        <thead class="table-dark"><tr>
          <th>#</th><th>Date</th><th>Item</th><th>Claimant</th><th>Status</th><th style="width:110px;">Action</th>
        </tr></thead>
        <tbody>
        <?php if ($rows && $rows->num_rows): while($c=$rows->fetch_assoc()):
            [$lbl,$cls]=$STATUS[$c['status']]??[ucfirst($c['status']),'secondary']; ?>
          <tr>
            <td>#<?= (int)$c['id'] ?></td>
            <td><?= htmlspecialchars($c['created_at']) ?></td>
            <td><?= htmlspecialchars($c['item_name']) ?></td>
            <td><?= htmlspecialchars($c['claimant_name'] ?: $c['account_name']) ?></td>
            <td><span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($lbl) ?></span></td>
            <td><a class="btn btn-sm btn-primary" href="claim_view.php?id=<?= (int)$c['id'] ?>">Review</a></td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No claims match.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>

    <?php elseif ($view === "submissions"): ?>
      <?php
        $SUB = ['pending'=>['Pending Review','warning text-dark'],'approved'=>['Approved','success'],
                'rejected'=>['Rejected','danger'],'clarification'=>['Needs More Info','info']];
        $sf = $_GET["sf"] ?? "pending";
        $sql = "SELECT li.id, li.item_name, li.category, li.item_type, li.loc_building, li.loc_level,
                       li.occurred_date, li.review_status, u.full_name AS owner
                FROM lost_items li JOIN users u ON u.id=li.user_id";
        $p=[]; $t="";
        if (isset($SUB[$sf])) { $sql.=" WHERE li.review_status=?"; $p[]=$sf; $t.="s"; }
        $sql.=" ORDER BY li.date_lost DESC";
        $stq=$conn->prepare($sql); if($t!=="") $stq->bind_param($t,...$p); $stq->execute(); $rows=$stq->get_result();
      ?>
      <h3 class="mb-3">Report Submissions</h3>
      <?php if(($_GET['msg']??'')==='done'): ?><div class="alert alert-success">Decision recorded and the user was notified.</div><?php endif; ?>
      <div class="mb-3 d-flex gap-2 flex-wrap">
        <?php foreach (['pending','clarification','approved','rejected','all'] as $f):
           $lbl = $f==='all' ? 'All' : ($SUB[$f][0] ?? ucfirst($f)); ?>
          <a class="btn btn-sm <?= $sf===$f?'btn-primary':'btn-outline-primary' ?>" href="?view=submissions&sf=<?= $f ?>"><?= htmlspecialchars($lbl) ?></a>
        <?php endforeach; ?>
      </div>
      <table class="table table-bordered bg-white">
        <thead class="table-dark"><tr><th>#</th><th>Item</th><th>Category</th><th>Type</th><th>Location</th><th>Date</th><th>Owner</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php if($rows && $rows->num_rows): while($r=$rows->fetch_assoc()):
           [$lbl,$cls]=$SUB[$r['review_status']]??[ucfirst($r['review_status']),'secondary']; ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['item_name']) ?></td>
            <td><?= htmlspecialchars($r['category']??'-') ?></td>
            <td><?= strtoupper($r['item_type']) ?></td>
            <td class="small"><?= htmlspecialchars(trim(($r['loc_building']??'').' '.($r['loc_level']??''))) ?: '-' ?></td>
            <td class="small"><?= htmlspecialchars($r['occurred_date']??'-') ?></td>
            <td><?= htmlspecialchars($r['owner']) ?></td>
            <td><span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($lbl) ?></span></td>
            <td><a class="btn btn-sm btn-primary" href="?view=submission&id=<?= (int)$r['id'] ?>">Review</a></td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No submissions.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>

    <?php elseif ($view === "submission"): ?>
      <?php
        $iid=(int)($_GET['id']??0);
        $q=$conn->prepare("SELECT li.*, u.full_name AS owner, u.email AS owner_email FROM lost_items li JOIN users u ON u.id=li.user_id WHERE li.id=? LIMIT 1");
        $q->bind_param("i",$iid); $q->execute(); $it=$q->get_result()->fetch_assoc();
        if(!$it): echo '<div class="alert alert-danger">Submission not found.</div>';
        else:
          $SUB=['pending'=>['Pending Review','warning text-dark'],'approved'=>['Approved','success'],'rejected'=>['Rejected','danger'],'clarification'=>['Needs More Info','info']];
          [$lbl,$cls]=$SUB[$it['review_status']]??[ucfirst($it['review_status']),'secondary'];
          $opp = $it['item_type']==='lost' ? 'found' : 'lost';
          $mq=$conn->prepare("SELECT id,item_name,loc_building,loc_level,occurred_date FROM lost_items
                              WHERE item_type=? AND category=? AND id<>? AND review_status='approved'
                              ORDER BY occurred_date DESC LIMIT 8");
          $mq->bind_param("ssi",$opp,(string)$it['category'],$iid); $mq->execute(); $matches=$mq->get_result();
          function f($v){ return htmlspecialchars(($v===null||$v==='')?'-':$v); }
      ?>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">Review Submission #<?= (int)$it['id'] ?></h3>
        <a href="?view=submissions" class="btn btn-outline-primary">Back to Submissions</a>
      </div>

      <div class="row g-3">
        <div class="col-md-8">
          <div class="card vp-recent">
            <div class="d-flex justify-content-between align-items-start">
              <h5 class="mb-2"><?= f($it['item_name']) ?> <span class="badge bg-<?= $cls ?>"><?= $lbl ?></span></h5>
              <span class="text-muted"><?= strtoupper($it['item_type']) ?></span>
            </div>
            <div class="row small">
              <div class="col-md-6 mb-2"><b>Category:</b> <?= f($it['category']) ?></div>
              <div class="col-md-6 mb-2"><b>Owner:</b> <?= f($it['owner']) ?> (<?= f($it['owner_email']) ?>)</div>
              <div class="col-md-6 mb-2"><b>Contact:</b> <?= f($it['contact_info']) ?></div>
              <div class="col-md-6 mb-2"><b>Location:</b> <?= f(trim(($it['loc_building']??'').' '.($it['loc_level']??'').' '.($it['loc_area']??''))) ?></div>
              <div class="col-md-6 mb-2"><b>Date / Time:</b> <?= f($it['occurred_date']) ?> <?= f($it['occurred_time']) ?></div>
              <div class="col-md-6 mb-2"><b>Colour:</b> <?= f($it['color']) ?></div>
              <div class="col-md-6 mb-2"><b>Brand / Model:</b> <?= f($it['brand_model']) ?></div>
              <div class="col-12 mb-2"><b>Description:</b> <?= f($it['description']) ?></div>
              <div class="col-12 mb-2"><b>Unique features:</b> <?= f($it['unique_features']) ?></div>
              <div class="col-12 mb-2"><b><?= $it['item_type']==='lost'?'Last had it':'Found at / now' ?>:</b> <?= f($it['cond_details']) ?></div>
              <?php if(!empty($it['extra_notes'])): ?><div class="col-12 mb-2"><b>Additional notes:</b> <?= f($it['extra_notes']) ?></div><?php endif; ?>
            </div>
          </div>

          <div class="card vp-recent mt-3">
            <h6 class="mb-2">Potential matches <span class="text-muted small">(approved <?= htmlspecialchars($opp) ?> items in the same category)</span></h6>
            <?php if($matches && $matches->num_rows): while($m=$matches->fetch_assoc()): ?>
              <div class="border-bottom py-1 small d-flex justify-content-between">
                <a href="?view=submission&id=<?= (int)$m['id'] ?>">#<?= (int)$m['id'] ?> <?= htmlspecialchars($m['item_name']) ?></a>
                <span class="text-muted"><?= htmlspecialchars(trim(($m['loc_building']??'').' '.($m['loc_level']??''))) ?> &middot; <?= htmlspecialchars($m['occurred_date']??'') ?></span>
              </div>
            <?php endwhile; else: ?>
              <div class="text-muted small">No matching items found yet.</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-md-4">
          <?php if(!empty($it['picture'])): ?>
            <div class="card mb-3"><img src="uploads/<?= htmlspecialchars($it['picture']) ?>" class="img-fluid rounded" alt="Item photo"></div>
          <?php endif; ?>
          <div class="card">
            <h6 class="mb-2">Decision</h6>
            <form method="POST">
              <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
              <label class="form-label small">Note (sent with reject / clarification)</label>
              <textarea class="form-control mb-2" name="note" rows="3"><?= htmlspecialchars($it['review_note']??'') ?></textarea>
              <div class="d-grid gap-2">
                <button class="btn btn-success" name="submission_action" value="approve" onclick="return confirm('Approve this report?');">Approve</button>
                <button class="btn btn-warning text-dark" name="submission_action" value="clarify">Request Clarification</button>
                <button class="btn btn-danger" name="submission_action" value="reject" onclick="return confirm('Reject this report?');">Reject</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <?php endif; ?>

    <?php elseif ($view === "audit"): ?>
      <h3 class="mb-3">Audit Log</h3>
      <table class="table table-bordered bg-white">
        <thead class="table-dark"><tr>
          <th>When</th><th>Action</th><th>Detail</th><th>Claim</th><th>By</th>
        </tr></thead>
        <tbody>
        <?php
          $al = $conn->query("
            SELECT a.action, a.detail, a.created_at, a.claim_id,
                   COALESCE(u.full_name,'System') AS actor, li.item_name
            FROM claim_audit a
            LEFT JOIN users u ON u.id=a.user_id
            LEFT JOIN item_claims c ON c.id=a.claim_id
            LEFT JOIN lost_items li ON li.id=c.item_id
            ORDER BY a.id DESC LIMIT 200
          ");
          if ($al && $al->num_rows): while($a=$al->fetch_assoc()): ?>
            <tr>
              <td class="small"><?= htmlspecialchars($a['created_at']) ?></td>
              <td><?= htmlspecialchars(ucwords(str_replace('_',' ',$a['action']))) ?></td>
              <td class="small"><?= htmlspecialchars($a['detail'] ?? '') ?></td>
              <td><a href="claim_view.php?id=<?= (int)$a['claim_id'] ?>">#<?= (int)$a['claim_id'] ?> <?= htmlspecialchars($a['item_name'] ?? '') ?></a></td>
              <td class="small"><?= htmlspecialchars($a['actor']) ?></td>
            </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="5" class="text-center text-muted py-4">No audit entries yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>

    <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>