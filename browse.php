<?php
require_once 'includes/config.php';

// Retrieve Filters from GET
$typeFilter = $_GET['type'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$locationFilter = $_GET['location'] ?? '';
$categoriesFilter = $_GET['categories'] ?? [];
$sortOrder = $_GET['sort'] ?? 'newest';

// Build SQL Query dynamically
$whereClauses = ["i.status = 'active'"];
$params = [];

// Filter by Type
if ($typeFilter !== 'all' && in_array($typeFilter, ['lost', 'found'])) {
    $whereClauses[] = "i.type = ?";
    $params[] = $typeFilter;
}

// Filter by Search Query (Title or Description)
if (!empty($searchQuery)) {
    $whereClauses[] = "(i.title LIKE ? OR i.description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

// Filter by Location
if (!empty($locationFilter)) {
    $whereClauses[] = "i.location_text LIKE ?";
    $params[] = "%$locationFilter%";
}

// Filter by Categories
if (!empty($categoriesFilter) && is_array($categoriesFilter)) {
    $placeholders = str_repeat('?,', count($categoriesFilter) - 1) . '?';
    $whereClauses[] = "i.category IN ($placeholders)";
    foreach ($categoriesFilter as $cat) {
        $params[] = $cat;
    }
}

// Assemble WHERE statement
$whereSql = "WHERE " . implode(" AND ", $whereClauses);

// Determine Order
$orderSql = "ORDER BY i.created_at DESC";
if ($sortOrder === 'oldest') {
    $orderSql = "ORDER BY i.created_at ASC";
}

// Final Query
$sql = "SELECT i.*, 
        (SELECT image_path FROM item_images WHERE item_id = i.item_id ORDER BY is_primary DESC LIMIT 1) as cover_image 
        FROM items i 
        $whereSql 
        $orderSql";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-defined categories for the sidebar
$allCategories = ['Electronics', 'Keys', 'Wallets & IDs', 'Bags', 'Clothing', 'Jewelry', 'Pets', 'Other'];
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>FindIt - Browse Listings</title>
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
<body class="bg-surface text-on-surface font-body min-h-screen flex flex-col">

<?php include 'includes/navbar.php'; ?>

<!-- Main Content -->
<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-8 py-12 flex flex-col md:flex-row gap-12">
    
    <!-- Left Filter Sidebar -->
    <aside class="w-full md:w-64 flex-shrink-0">
        <form id="filterForm" method="GET" action="browse.php" class="space-y-12">
            
            <!-- Type Toggle -->
            <div>
                <h3 class="font-headline font-semibold text-lg mb-4">Item Type</h3>
                <div class="flex bg-surface-container-highest rounded-lg p-1">
                    <label class="flex-1 text-center cursor-pointer">
                        <input type="radio" name="type" value="all" onchange="this.form.submit()" class="hidden peer" <?= $typeFilter === 'all' ? 'checked' : '' ?>>
                        <div class="py-2 px-4 rounded-md text-sm font-semibold transition-colors peer-checked:bg-white peer-checked:shadow-sm peer-checked:text-primary text-on-surface-variant">All</div>
                    </label>
                    <label class="flex-1 text-center cursor-pointer">
                        <input type="radio" name="type" value="lost" onchange="this.form.submit()" class="hidden peer" <?= $typeFilter === 'lost' ? 'checked' : '' ?>>
                        <div class="py-2 px-4 rounded-md text-sm font-semibold transition-colors peer-checked:bg-white peer-checked:shadow-sm peer-checked:text-primary text-on-surface-variant">Lost</div>
                    </label>
                    <label class="flex-1 text-center cursor-pointer">
                        <input type="radio" name="type" value="found" onchange="this.form.submit()" class="hidden peer" <?= $typeFilter === 'found' ? 'checked' : '' ?>>
                        <div class="py-2 px-4 rounded-md text-sm font-semibold transition-colors peer-checked:bg-white peer-checked:shadow-sm peer-checked:text-primary text-on-surface-variant">Found</div>
                    </label>
                </div>
            </div>

            <!-- Category Filters -->
            <div>
                <h3 class="font-headline font-semibold text-lg mb-4">Categories</h3>
                <div class="space-y-3">
                    <?php foreach($allCategories as $cat): ?>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input onchange="this.form.submit()" type="checkbox" name="categories[]" value="<?= htmlspecialchars($cat) ?>" <?= in_array($cat, $categoriesFilter) ? 'checked' : '' ?> class="w-5 h-5 rounded border-outline-variant text-secondary focus:ring-secondary/50">
                        <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($cat) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Location -->
            <div>
                <h3 class="font-headline font-semibold text-lg mb-4">Location</h3>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant" style="font-size: 20px;">location_on</span>
                    <input name="location" value="<?= htmlspecialchars($locationFilter) ?>" class="w-full pl-10 pr-4 py-2 rounded-DEFAULT border-none bg-surface-container-low text-sm focus:ring-2 focus:ring-secondary/50" placeholder="City or landmark..." type="text"/>
                </div>
                <!-- Hidden search input to preserve search term when altering other filters from sidebar -->
                <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery) ?>" id="hiddenSearch">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sortOrder) ?>" id="hiddenSort">
            </div>

            <a href="browse.php" class="block text-center w-full py-3 bg-surface-container-low text-primary font-semibold rounded-DEFAULT hover:bg-surface-container-highest transition-colors text-sm">Reset Filters</a>
        </form>
    </aside>

    <!-- Main Results Area -->
    <section class="flex-grow flex flex-col min-w-0">
        <!-- Search & Header -->
        <div class="mb-8 space-y-6">
            <div class="relative">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                <input form="filterForm" name="search" value="<?= htmlspecialchars($searchQuery) ?>" onchange="document.getElementById('hiddenSearch').value=this.value; document.getElementById('filterForm').submit();" class="w-full pl-12 pr-4 py-4 rounded-xl border-none bg-white shadow-sm text-lg placeholder:text-on-surface-variant/50 focus:ring-2 focus:ring-secondary/50 transition-shadow" placeholder="Search for 'MacBook Pro' or 'Wallet'..." type="text"/>
            </div>
            
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-primary">Browse Listings <span class="text-on-surface-variant text-lg font-normal ml-2"><?= count($items) ?> results</span></h1>
                
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-on-surface-variant">Sort by:</span>
                    <select form="filterForm" name="sort" onchange="document.getElementById('hiddenSort').value=this.value; document.getElementById('filterForm').submit();" class="bg-transparent border-none text-sm font-semibold text-primary focus:ring-0 cursor-pointer pr-8 py-1">
                        <option value="newest" <?= $sortOrder === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= $sortOrder === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Item Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php if (empty($items)): ?>
                <div class="col-span-full py-20 text-center">
                    <span class="material-symbols-outlined text-border text-6xl text-on-surface-variant/30 mb-4">search_off</span>
                    <h3 class="text-xl font-bold text-primary">No items found</h3>
                    <p class="text-on-surface-variant">Try adjusting your filters or search query.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): 
                    $isLost = $item['type'] === 'lost';
                    $borderColor = $isLost ? 'border-l-[#F4A261]' : 'border-l-[#0F7173]';
                    $badgeColor = $isLost ? 'text-[#F4A261]' : 'text-[#0F7173]';
                    $imgSrc = empty($item['cover_image']) ? 'assets/img/placeholder.jpg' : $item['cover_image'];
                    // Strip leading slash/dots just to be safe if it has any formatting issues
                    $imgSrc = preg_replace('/^\.\.\//', '', $imgSrc);
                    if (strpos($imgSrc, '/') === 0) $imgSrc = substr($imgSrc, 1);
                ?>
                <article class="bg-white rounded-xl shadow-[0_4px_24px_rgba(13,27,42,0.04)] overflow-hidden border-l-4 <?= $borderColor ?> flex flex-col hover:-translate-y-1 transition-transform duration-300 relative group">
                    <a href="item-detail.php?id=<?= $item['item_id'] ?>" class="h-48 w-full bg-surface-container-low relative overflow-hidden block">
                        <img alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="<?= htmlspecialchars($imgSrc) ?>"/>
                        <div class="absolute top-4 left-4 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full text-xs font-bold <?= $badgeColor ?> shadow-sm uppercase tracking-wider">
                            <?= ucfirst(htmlspecialchars($item['type'])) ?>
                        </div>
                    </a>
                    
                    <div class="p-6 flex-grow flex flex-col">
                        <h2 class="text-lg font-semibold text-primary mb-2 line-clamp-1">
                            <a href="item-detail.php?id=<?= $item['item_id'] ?>" class="hover:text-secondary transition-colors"><?= htmlspecialchars($item['title']) ?></a>
                        </h2>
                        
                        <div class="flex items-center gap-2 text-on-surface-variant text-sm mb-4">
                            <span class="material-symbols-outlined" style="font-size: 16px;">location_on</span>
                            <span class="truncate"><?= htmlspecialchars($item['location_text']) ?></span>
                        </div>
                        
                        <p class="text-sm text-on-surface-variant mb-6 line-clamp-2 leading-relaxed">
                            <?= htmlspecialchars(strip_tags($item['description'])) ?>
                        </p>
                        
                        <div class="mt-auto pt-4 flex items-center justify-between border-t border-surface-container-highest">
                            <span class="text-xs font-semibold text-on-surface-variant">
                                <?= date('M j, Y', strtotime($item['date_occurred'])) ?>
                            </span>
                            <a href="item-detail.php?id=<?= $item['item_id'] ?>" class="text-sm font-semibold <?= $badgeColor ?> hover:underline underline-offset-4">
                                View Details
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </section>
</main>

<!-- Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>