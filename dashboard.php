<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Get counts
$stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_posts = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE user_id = ? AND status = 'returned'");
$stmt->execute([$user_id]);
$items_recovered = $stmt->fetchColumn();

// Pending claims ON user's items
$stmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE item_id IN (SELECT item_id FROM items WHERE user_id = ?) AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_claims = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_messages = $stmt->fetchColumn();

// Get active reports (items user posted)
$stmt = $pdo->prepare("
    SELECT i.*, 
           (SELECT image_path FROM item_images WHERE item_id = i.item_id ORDER BY is_primary DESC LIMIT 1) as primary_image
    FROM items i
    WHERE i.user_id = ? AND i.status IN ('active', 'matched', 'verified')
    ORDER BY i.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$my_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch claims made by OTHERS on items owned by current user (Action Required)
$stmt = $pdo->prepare("
    SELECT c.claim_id, c.created_at, c.status as claim_status, i.title as item_title, i.item_id, u.full_name as claimer_name, u.email as claimer_email
    FROM claims c
    JOIN items i ON c.item_id = i.item_id
    JOIN users u ON c.user_id = u.user_id
    WHERE i.user_id = ? AND c.status = 'pending'
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$claims_on_my_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch claims made BY the current user (Status Updates)
$stmt = $pdo->prepare("
    SELECT c.claim_id, c.created_at, c.status as claim_status, i.title as item_title, i.item_id, u.full_name as owner_name
    FROM claims c
    JOIN items i ON c.item_id = i.item_id
    JOIN users u ON i.user_id = u.user_id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$my_active_claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>User Dashboard - FindIt</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "primary": "#000000",
                        "secondary": "#00696b",
                        "surface": "#f7fafc",
                        "surface-container-low": "#f1f4f6",
                        "surface-container-highest": "#e0e3e5",
                        "on-surface": "#181c1e",
                        "on-surface-variant": "#44474c",
                        "outline-variant": "#c4c6cc"
                    },
                    "fontFamily": {
                        "headline": ["Inter"],
                        "body": ["Inter"]
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="assets/css/smooth.css">
    <script src="assets/js/smooth.js" defer></script>
</head>
<body class="bg-surface text-on-surface flex min-h-screen">
    <!-- SideNavBar -->
    <nav class="hidden md:flex flex-col gap-4 p-6 bg-[#f1f4f6] w-[220px] h-screen fixed left-0 border-r border-outline-variant/20 z-40">
        <div class="mb-8">
            <h1 class="text-xl font-bold text-[#0D1B2A] font-['Inter'] tracking-tight">FindIt</h1>
            <p class="text-sm text-on-surface-variant font-['Inter']">Digital Concierge</p>
        </div>
        <div class="flex flex-col gap-2 font-['Inter'] text-sm font-medium">
            <a class="flex items-center gap-3 p-3 text-[#0F7173] bg-white rounded-lg shadow-sm font-semibold hover:translate-x-1 transition-all" href="dashboard.php">
                <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span> Dashboard
            </a>
            <a class="flex items-center gap-3 p-3 text-slate-500 hover:bg-slate-200 hover:translate-x-1 transition-all rounded-lg" href="browse.php">
                <span class="material-symbols-outlined" data-icon="search">search</span> Browse Items
            </a>
            <a class="flex items-center gap-3 p-3 text-slate-500 hover:bg-slate-200 hover:translate-x-1 transition-all rounded-lg" href="#">
                <span class="material-symbols-outlined" data-icon="settings">settings</span> Settings
            </a>
        </div>
        <div class="mt-auto pt-6">
            <a href="post-lost.php" class="w-full bg-[#0D1B2A] hover:bg-primary text-white py-3 px-4 rounded-DEFAULT font-bold text-sm transition-all shadow-sm flex items-center justify-center gap-2">
                <span class="material-symbols-outlined" data-icon="add">add</span> New Report
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 ml-0 md:ml-[220px] p-6 md:p-12 max-w-7xl">
        <?php include 'includes/navbar.php'; ?>

        <!-- Welcome Section -->
        <div class="mb-8 mt-4">
            <h2 class="text-2xl font-bold text-[#0D1B2A] mb-1">Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>! 👋</h2>
            <p class="text-sm text-on-surface-variant">Here's an overview of your activity and reports.</p>
        </div>

        <!-- Stats Row -->
        <section class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-12">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-outline-variant/20">
                <div class="flex items-center gap-3 mb-4 text-on-surface-variant">
                    <span class="material-symbols-outlined text-[#0D1B2A]">post_add</span>
                    <span class="text-xs font-semibold uppercase tracking-wider">Total Posts</span>
                </div>
                <p class="text-3xl font-bold text-[#0D1B2A]"><?= $total_posts ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm border border-outline-variant/20">
                <div class="flex items-center gap-3 mb-4 text-on-surface-variant">
                    <span class="material-symbols-outlined text-[#0F7173]">check_circle</span>
                    <span class="text-xs font-semibold uppercase tracking-wider">Items Recovered</span>
                </div>
                <p class="text-3xl font-bold text-[#0D1B2A]"><?= $items_recovered ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm border border-outline-variant/20">
                <div class="flex items-center gap-3 mb-4 text-on-surface-variant">
                    <span class="material-symbols-outlined text-[#F4A261]">pending_actions</span>
                    <span class="text-xs font-semibold uppercase tracking-wider">Pending Approvals</span>
                </div>
                <p class="text-3xl font-bold text-[#0D1B2A]"><?= $pending_claims ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm border border-outline-variant/20">
                <div class="flex items-center gap-3 mb-4 text-on-surface-variant">
                    <span class="material-symbols-outlined text-[#0D1B2A]">approval</span>
                    <span class="text-xs font-semibold uppercase tracking-wider">My Claims</span>
                </div>
                <p class="text-3xl font-bold text-[#0D1B2A]"><?= count($my_active_claims) ?></p>
            </div>
        </section>

        <!-- Dynamic Grid: Approvals & My Claims -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-16">
            
            <!-- Approvals (Claims on My Items) -->
            <section>
                <h3 class="text-lg font-bold text-[#0D1B2A] mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#F4A261]">notification_important</span> Action Required
                </h3>
                
                <div class="space-y-4">
                    <?php if (empty($claims_on_my_items)): ?>
                        <div class="p-6 text-center text-on-surface-variant bg-white rounded-xl shadow-sm border border-outline-variant/20">
                            <span class="material-symbols-outlined text-4xl opacity-50 mb-2">done_all</span>
                            <p>No pending claims on your items.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($claims_on_my_items as $claim): ?>
                        <div class="bg-white p-5 rounded-xl shadow-sm border border-outline-variant/20 flex flex-col gap-4" id="claim-box-<?= $claim['claim_id'] ?>">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-bold text-[#0D1B2A]"><?= htmlspecialchars($claim['claimer_name']) ?> <span class="font-normal text-on-surface-variant text-sm">is claiming your</span></h4>
                                    <a href="item-detail.php?id=<?= $claim['item_id'] ?>" class="text-[#0F7173] font-semibold hover:underline block mt-1"><?= htmlspecialchars($claim['item_title']) ?></a>
                                </div>
                                <span class="text-xs text-on-surface-variant"><?= date('M j, Y', strtotime($claim['created_at'])) ?></span>
                            </div>
                            
                            <div class="text-sm bg-surface-container-low p-3 rounded-lg text-on-surface">
                                <strong>Contact Email:</strong> <a href="mailto:<?= htmlspecialchars($claim['claimer_email']) ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($claim['claimer_email']) ?></a>
                            </div>

                            <div class="flex gap-3 mt-2">
                                <button onclick="manageClaim(<?= $claim['claim_id'] ?>, 'approve')" class="flex-1 bg-[#0F7173] hover:bg-[#0a4e50] text-white py-2 rounded-md text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-sm">check_circle</span> Approve
                                </button>
                                <button onclick="manageClaim(<?= $claim['claim_id'] ?>, 'reject')" class="flex-1 bg-surface-container-highest hover:bg-outline-variant text-[#0D1B2A] py-2 rounded-md text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-sm">cancel</span> Reject
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- My Claims -->
            <section>
                <h3 class="text-lg font-bold text-[#0D1B2A] mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#0F7173]">track_changes</span> My Active Claims
                </h3>

                <div class="space-y-4">
                    <?php if (empty($my_active_claims)): ?>
                        <div class="p-6 text-center text-on-surface-variant bg-white rounded-xl shadow-sm border border-outline-variant/20">
                            <span class="material-symbols-outlined text-4xl opacity-50 mb-2">hourglass_empty</span>
                            <p>You haven't claimed any items yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($my_active_claims as $my_claim): 
                            $statusColor = 'bg-yellow-100 text-yellow-800';
                            if ($my_claim['claim_status'] === 'approved') $statusColor = 'bg-green-100 text-green-800';
                            if ($my_claim['claim_status'] === 'rejected') $statusColor = 'bg-red-100 text-red-800';
                        ?>
                        <div class="bg-white p-5 rounded-xl shadow-sm border border-outline-variant/20">
                            <div class="flex justify-between items-start mb-2">
                                <a href="item-detail.php?id=<?= $my_claim['item_id'] ?>" class="font-bold text-[#0D1B2A] hover:text-[#0F7173] hover:underline"><?= htmlspecialchars($my_claim['item_title']) ?></a>
                                <span class="text-xs px-2 py-1 rounded-full font-bold uppercase tracking-wider <?= $statusColor ?>">
                                    <?= htmlspecialchars($my_claim['claim_status']) ?>
                                </span>
                            </div>
                            <p class="text-sm text-on-surface-variant">Posted by: <strong><?= htmlspecialchars($my_claim['owner_name']) ?></strong></p>
                            <p class="text-xs text-outline mt-3">Date Claimed: <?= date('M j, Y', strtotime($my_claim['created_at'])) ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- Horizontal Strip for 'My Posts' (Items I posted) -->
        <section>
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-[#0D1B2A]">Active Reports I Posted</h3>
                <a class="text-sm text-[#0F7173] font-medium hover:underline" href="#">View All</a>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (empty($my_items)): ?>
                    <div class="col-span-full p-6 text-center text-on-surface-variant bg-white shadow-sm rounded-xl">
                        <span class="material-symbols-outlined text-4xl opacity-50 mb-2">inbox</span>
                        <p>You haven't posted any items yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($my_items as $item): ?>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-outline-variant/20 flex gap-4">
                        <?php if (!empty($item['primary_image'])): ?>
                            <img alt="Item" class="w-20 h-20 rounded-lg object-cover" src="<?= htmlspecialchars(preg_replace('/^\.\.\//', '', $item['primary_image'])) ?>"/>
                        <?php else: ?>
                            <div class="w-20 h-20 rounded-lg bg-surface-container flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-outline">image</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex flex-col justify-center overflow-hidden">
                            <span class="text-[10px] font-bold uppercase <?= $item['type'] === 'lost' ? 'text-[#F4A261]' : 'text-[#0F7173]' ?> tracking-wider mb-1"><?= htmlspecialchars($item['type']) ?></span>
                            <h4 class="text-sm font-bold text-[#0D1B2A] truncate"><a href="item-detail.php?id=<?= $item['item_id'] ?>" class="hover:underline"><?= htmlspecialchars($item['title']) ?></a></h4>
                            <p class="text-xs text-on-surface-variant mt-1 truncate"><?= htmlspecialchars($item['location_text']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <script>
    function manageClaim(claimId, action) {
        if (!confirm(`Are you sure you want to ${action} this claim?`)) return;

        const formData = new FormData();
        formData.append('claim_id', claimId);
        formData.append('action', action);

        fetch('api/manage_claim.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Claim ${action}ed successfully!`);
                location.reload(); // Quickest way to refresh all lists and counters
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('A network error occurred.');
        });
    }
    </script>
</body>
</html>