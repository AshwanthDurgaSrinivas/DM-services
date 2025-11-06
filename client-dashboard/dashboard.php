 <?php
require 'config.php';
session_start();
if (!isset($_SESSION['client_id'])) {
    header("Location: index.php");
    exit();
}
$client_id = $_SESSION['client_id'] ?? ($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GiggleZen — Dashboard</title>
    <!-- Bootstrap 5 + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Quicksand:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
            .sidebar-toggle {
    position: fixed;             
    top: 50%;                    
    z-index: 3;       
    transform: translateY(-50%); 
              
    width: 40px;
    height: 40px;
    background: #ebebeb;
    border-radius: 10%;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    border: none;
    cursor: pointer;
    transition: background 0.3s, left 0.3s;
}@media (max-width: 768px) {
    .sidebar.active+.sidebar-toggle {
        left: 240px;
    }
}
    </style>
    <script>
 const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    }</script>
</head>
<body>
    <div class="app">
       <aside class="sidebar">
            <ul class="nav-tabs">
                <li><a href="dashboard.php" class="active"><i class="bi bi-house-door-fill"></i><span class="txt">Overview</span></a></li>
                <li><a href="deliverables.php"><i class="bi bi-box-seam"></i><span
                            class="txt">Deliverables</span></a></li>
                <li><a href="manager.php"><i class="bi bi-person-badge"></i><span class="txt">Manager
                            Details</span></a></li>
            </ul>
        </aside>
        <button class="sidebar-toggle" aria-label="Toggle sidebar">
                   <i class="bi bi-box-arrow-in-right"></i>
                </button>
        <main class="main">
              <div class="topbar">
                
               <?php
// Assuming you already have $client_id from session
$client_q = $conn->query("SELECT name, logo FROM clients WHERE id = $client_id");
$client_data = $client_q->fetch_assoc();

$client_name = $client_data['name'] ?? 'Client';
$client_logo = !empty($client_data['logo']) ? '../dm_admin/uploads/clients/' . htmlspecialchars($client_data['logo']) : 'assets/default-logo.png';
?>
<div class="brand">
    <div class="logo">
        <img src="<?= $client_logo ?>" alt="Client Logo" style="width:45px; height:45px; object-fit:cover; border-radius:50%;">
    </div>
    <div>
        <h3><?= htmlspecialchars($client_name) ?></h3>
        <small>Client Dashboard</small>
    </div>
</div>

               <button id="logoutBtn" class="logoutButton">
                    <svg class="doorway" viewBox="0 0 100 100">
                        <path
                            d="M93.4 86.3H58.6c-1.9 0-3.4-1.5-3.4-3.4V17.1c0-1.9 1.5-3.4 3.4-3.4h34.8c1.9 0 3.4 1.5 3.4 3.4v65.8c0 1.9-1.5 3.4-3.4 3.4z" />
                        <path class="bang"
                            d="M40.5 43.7L26.6 31.4l-2.5 6.7zM41.9 50.4l-19.5-4-1.4 6.3zM40 57.4l-17.7 3.9 3.9 5.7z" />
                    </svg>
                    <svg class="figure" viewBox="0 0 100 100">
                        <circle cx="52.1" cy="32.4" r="6.4" />
                        <path
                            d="M50.7 62.8c-1.2 2.5-3.6 5-7.2 4-3.2-.9-4.9-3.5-4-7.8.7-3.4 3.1-13.8 4.1-15.8 1.7-3.4 1.6-4.6 7-3.7 4.3.7 4.6 2.5 4.3 5.4-.4 3.7-2.8 15.1-4.2 17.9z" />
                        <g class="arm1">
                            <path
                                d="M55.5 56.5l-6-9.5c-1-1.5-.6-3.5.9-4.4 1.5-1 3.7-1.1 4.6.4l6.1 10c1 1.5.3 3.5-1.1 4.4-1.5.9-3.5.5-4.5-.9z" />
                            <path class="wrist1"
                                d="M69.4 59.9L58.1 58c-1.7-.3-2.9-1.9-2.6-3.7.3-1.7 1.9-2.9 3.7-2.6l11.4 1.9c1.7.3 2.9 1.9 2.6 3.7-.4 1.7-2 2.9-3.8 2.6z" />
                        </g>
                        <g class="arm2">
                            <path
                                d="M34.2 43.6L45 40.3c1.7-.6 3.5.3 4 2 .6 1.7-.3 4-2 4.5l-10.8 2.8c-1.7.6-3.5-.3-4-2-.6-1.6.3-3.4 2-4z" />
                            <path class="wrist2"
                                d="M27.1 56.2L32 45.7c.7-1.6 2.6-2.3 4.2-1.6 1.6.7 2.3 2.6 1.6 4.2L33 58.8c-.7 1.6-2.6 2.3-4.2 1.6-1.7-.7-2.4-2.6-1.7-4.2z" />
                        </g>
                        <g class="leg1">
                            <path
                                d="M52.1 73.2s-7-5.7-7.9-6.5c-.9-.9-1.2-3.5-.1-4.9 1.1-1.4 3.8-1.9 5.2-.9l7.9 7c1.4 1.1 1.7 3.5.7 4.9-1.1 1.4-4.4 1.5-5.8.4z" />
                            <path class="calf1"
                                d="M52.6 84.4l-1-12.8c-.1-1.9 1.5-3.6 3.5-3.7 2-.1 3.7 1.4 3.8 3.4l1 12.8c.1 1.9-1.5 3.6-3.5 3.7-2 0-3.7-1.5-3.8-3.4z" />
                        </g>
                        <g class="leg2">
                            <path
                                d="M37.8 72.7s1.3-10.2 1.6-11.4 2.4-2.8 4.1-2.6c1.7.2 3.6 2.3 3.4 4l-1.8 11.1c-.2 1.7-1.7 3.3-3.4 3.1-1.8-.2-4.1-2.4-3.9-4.2z" />
                            <path class="calf2"
                                d="M29.5 82.3l9.6-10.9c1.3-1.4 3.6-1.5 5.1-.1 1.5 1.4.4 4.9-.9 6.3l-8.5 9.6c-1.3 1.4-3.6 1.5-5.1.1-1.4-1.3-1.5-3.5-.2-5z" />
                        </g>
                    </svg>
                    <svg class="door" viewBox="0 0 100 100">
                        <path
                            d="M93.4 86.3H58.6c-1.9 0-3.4-1.5-3.4-3.4V17.1c0-1.9 1.5-3.4 3.4-3.4h34.8c1.9 0 3.4 1.5 3.4 3.4v65.8c0 1.9-1.5 3.4-3.4 3.4z" />
                        <circle cx="66" cy="50" r="3.7" />
                    </svg>
                    <span class="button-text">Log Out</span>
                </button>
            </div>

         <?php



// If client ID is already known
$client_id = $_SESSION['client_id'] ?? ($_GET['client_id'] ?? 0);

if ($client_id > 0) {
    $sql = "SELECT name, insta_link, facebook_link, x_link, youtube_link, website_link 
            FROM clients 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result->fetch_assoc();
}

$client_name = $client['name'] ?? 'Your Client';
$insta = $client['insta_link'] ?? '';
$fb = $client['facebook_link'] ?? '';
$x = $client['x_link'] ?? '';
$yt = $client['youtube_link'] ?? '';
$website = $client['website_link'] ?? '';
?>


<h1 class="mb-3" style="font-size:1.6rem">Welcome <?= htmlspecialchars($client_name) ?>...</h1>

<!-- SOCIAL IFRAMES -->
<section class="social-iframes">
    <div class="social-card">
        <h5><i class="bi bi-instagram text-danger"></i> Instagram</h5>
        <?php if (!empty($insta)): ?>
            <iframe src="https://www.instagram.com/p/<?= htmlspecialchars(basename($insta)) ?>/embed" allowfullscreen></iframe>
        <?php else: ?>
            <p>No Instagram link found</p>
        <?php endif; ?>
    </div>

    <div class="social-card">
        <h5><i class="bi bi-facebook text-primary"></i> Facebook</h5>
        <?php if (!empty($fb)): ?>
            <iframe src="https://www.facebook.com/plugins/page.php?href=<?= urlencode($fb) ?>&tabs=timeline&width=340&height=340&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=true&appId"
                    scrolling="no" frameborder="0" allowfullscreen="true"></iframe>
        <?php else: ?>
            <p>No Facebook link found</p>
        <?php endif; ?>
    </div>

    <div class="social-card">
        <h5><i class="bi bi-twitter text-info"></i> Twitter</h5>
        <?php if (!empty($x)): ?>
            <iframe src="https://twitframe.com/twitter_widget?screen_name=<?= htmlspecialchars(basename($x)) ?>" height="340"></iframe>
        <?php else: ?>
            <p>No Twitter link found</p>
        <?php endif; ?>
    </div>

    <div class="social-card">
        <h5><i class="bi bi-youtube text-danger"></i> YouTube</h5>
        <?php if (!empty($yt)): ?>
            <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars(basename($yt)) ?>" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                allowfullscreen></iframe>
        <?php else: ?>
            <p>No YouTube link found</p>
        <?php endif; ?>
    </div>
</section>
            <!-- GRID -->
          <div class="right-col">
  <div class="row g-4">
    <!-- Website Preview -->
    <div class="col-md-7">
      <div class="card p-3 iframe-preview h-100">
        <h3 class="h5 mb-2">Website Preview</h3>
        <iframe src="<?= htmlspecialchars($website) ?>" allowfullscreen style="width:100%; height:400px; border:0; border-radius:8px;"></iframe>
      </div>
    </div>
 <?php
// Fetch upcoming tasks for this client
$client_id = $_SESSION['client_id'] ?? ($_GET['client_id'] ?? 0);
$tasks = [];

if ($client_id > 0) {
    $sql = "SELECT title, deadline, status 
            FROM tasks 
            WHERE client_id = ? 
            ORDER BY deadline DESC
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tasks = $result->fetch_all(MYSQLI_ASSOC);
}
?>
    <!-- Upcoming Tasks -->
    <div class="col-md-5">
      <div class="card p-3 events-card h-100">
        <h6 class="card-title"><i class="bi bi-calendar-event"></i> Upcoming</h6>

        <?php if (!empty($tasks)): ?>
          <?php foreach ($tasks as $task): ?>
            <?php
              $badgeClass = '';
              if ($task['status'] === 'Pending') {
                  $badgeClass = 'badge-deadline';
              } elseif ($task['status'] === 'Completed') {
                  $badgeClass = 'badge-meeting';
              }
              $formattedDate = date("M j, Y", strtotime($task['deadline']));
            ?>
            <div class="event-item d-flex justify-content-between align-items-center mb-2">
              <div class="event-info">
                <h6 class="mb-0"><?= htmlspecialchars($task['title']) ?></h6>
                <small class="text-muted"><?= htmlspecialchars($formattedDate) ?></small>
              </div>
              <span class="event-badge <?= $badgeClass ?>"><?= htmlspecialchars($task['status']) ?></span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-muted small mb-0">No upcoming tasks found.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>





                 
            </div>
        </main>
    </div>
    <!-- Project Modal -->
    <div id="projectModal" class="modal" tabindex="-1" aria-hidden="true">
        <div class="modal-content">
            <h2 id="modalTitle"></h2>
            <div id="modalBody"></div>
            <button class="btn-secondary">Close</button>
            <button class="close-btn" aria-label="Close modal">×</button>
        </div>
    </div>
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="script.js"></script>

    <script>
document.getElementById('logoutBtn').addEventListener('click', function () {
    this.classList.add('clicked'); // optional animation trigger
    setTimeout(() => {
        window.location.href = "logout.php";
    }, 800); // wait 0.8s before redirecting
});
</script>


</body>
</html>