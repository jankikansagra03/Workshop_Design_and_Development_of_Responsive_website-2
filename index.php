<style>
    #addProductModal .modal-content {
        height: 80vh;
    }

    #addProductModal .modal-body {
        overflow-y: scroll;
        max-height: calc(80vh - 130px);
    }

    [id^="editProductModal"] .modal-content {
        height: 80vh;
    }

    [id^="editProductModal"] .modal-body {
        overflow-y: scroll;
        max-height: calc(80vh - 130px);
    }

    [id^="viewProductModal"] .modal-content {
        max-height: 80vh;
    }

    [id^="viewProductModal"] .modal-body {
        overflow-y: scroll;
        max-height: calc(80vh - 100px);
    }
</style>
<link rel="stylesheet" href="css/bootstrap.min.css">
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/jquery-3.7.1.min.js"></script>
<script src="js/validate.js"></script>
<script>
    $(document).ready(function() {
        // Step 1: Debounce live search input before refreshing results.
        var debounceTimer;
        var searchInput = $('#searchInput');

        // Focus and move cursor to end
        searchInput.focus();
        var val = searchInput.val();
        searchInput[0].setSelectionRange(val.length, val.length);

        searchInput.on('input', function() {
            clearTimeout(debounceTimer);
            var searchValue = $(this).val().trim();
            debounceTimer = setTimeout(function() {
                var url = '?page=1';
                if (searchValue) {
                    url += '&search=' + encodeURIComponent(searchValue);
                }
                window.location.href = url;
            }, 500);
        });
    });
</script>
<?php
include_once 'db_config.php';

// Step 3: Handle create action and insert new product records.
if (isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = $_POST['name'];
    $categoryId = $_POST['category_id'];
    $brand = $_POST['brand'];
    $description = $_POST['description'];
    $longDescription = $_POST['long_description'];
    $price = $_POST['price'];
    $discount = $_POST['discount'];
    $stock = $_POST['stock'];
    $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

    $main_image = "uploads/products/main/" . uniqid() . $_FILES['main_image']['name'];
    $gallery_images = $_FILES['gallery_images']['name'];

    $gallery_images = [];
    $temp_gallery_images = [];
    foreach ($_FILES['gallery_images']['name'] as $index => $filename) {
        $uniqueName = uniqid() . $filename;
        $temp_gallery_images[$index] = $_FILES['gallery_images']['tmp_name'][$index];
        $gallery_images[$index] = "uploads/products/gallery/" . $uniqueName;
    }
    $main_dir = 'uploads/products/main/';
    $gallery_dir = 'uploads/products/gallery/';
    if (!is_dir($main_dir)) {
        mkdir($main_dir, 0755, true);
    }
    if (!is_dir($gallery_dir)) {
        mkdir($gallery_dir, 0755, true);
    }

    $insertStmt = mysqli_prepare($connection, 'INSERT INTO products (name, category_id, brand, price, discount, stock, description, long_description, image, gallery_images, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if ($insertStmt) {
        $galleryImagesValue = !empty($gallery_images) ? implode(',', $gallery_images) : null;

        mysqli_stmt_bind_param(
            $insertStmt,
            'sisdiisssss',
            $name,
            $categoryId,
            $brand,
            $price,
            $discount,
            $stock,
            $description,
            $longDescription,
            $main_image,
            $galleryImagesValue,
            $status
        );

        if (mysqli_stmt_execute($insertStmt)) {
            move_uploaded_file($_FILES['main_image']['tmp_name'], $main_image);
            foreach ($temp_gallery_images as $index => $tmp_name) {
                move_uploaded_file($tmp_name, $gallery_images[$index]);
            }
            setcookie('success', 'Product added successfully!', time() + 5);
            // echo "<script> window.location.href = 'teach.php';</?php>";
        } else {
            setcookie('error', 'Failed to add product. Please try again.', time() + 5);
        }

        mysqli_stmt_close($insertStmt);
        flush_stored_results($connection);
    }
}

//delete Prodcut
// Step 4: Handle delete action and remove related image files.
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $productId = (int) $_POST['product_id'];

    // 1) Fetch product to get image paths for deletion
    $query = "SELECT image, gallery_images FROM products WHERE id = ?";
    $selectStmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($selectStmt, 'i', $productId);
    mysqli_stmt_execute($selectStmt);
    $selectResult = mysqli_stmt_get_result($selectStmt);
    $product = mysqli_fetch_assoc($selectResult);
    mysqli_stmt_close($selectStmt);

    if ($product) {
        // 2) Delete product from DB
        $deleteQuery = "DELETE FROM products WHERE id = ?";
        $deleteStmt = mysqli_prepare($connection, $deleteQuery);
        mysqli_stmt_bind_param($deleteStmt, 'i', $productId);
        if (mysqli_stmt_execute($deleteStmt)) {
            // 3) If DB deletion successful, delete images from disk
            if (!empty($product['image']) && file_exists($product['image'])) {
                @unlink($product['image']);
            }
            if (!empty($product['gallery_images'])) {
                foreach (explode(',', $product['gallery_images']) as $galleryImage) {
                    $galleryImage = trim($galleryImage);
                    if (!empty($galleryImage) && file_exists($galleryImage)) {
                        @unlink($galleryImage);
                    }
                }
            }
            setcookie('success', 'Product deleted successfully!', time() + 5);
        } else {
            setcookie('error', 'Failed to delete product. Please try again.', time() + 5);
        }
        mysqli_stmt_close($deleteStmt);
    } else {
        setcookie('error', 'Product not found. It may have already been deleted.', time() + 5);
    }
}

// Step 5: Handle status toggle requests from status modal.
if (isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $productId = $_POST['product_id'];
    $newStatus = (($_POST['new_status'] ?? 'Active') === 'Inactive') ? 'Inactive' : 'Active';

    $updateQuery = "UPDATE products SET status=? WHERE id=?";
    $updateStmt = mysqli_prepare($connection, $updateQuery);
    if ($updateStmt) {
        mysqli_stmt_bind_param($updateStmt, 'si', $newStatus, $productId);
        if (mysqli_stmt_execute($updateStmt)) {
            setcookie('success', 'Product status updated successfully!', time() + 5);
        } else {
            setcookie('error', 'Failed to update product status. Please try again.', time() + 5);
        }
        mysqli_stmt_close($updateStmt);
    }
}

// Step 2: Handle update action with optional image replacement/removal.
if (isset($_POST['action']) && $_POST['action'] === 'update') {
    $productId = (int)$_POST['product_id'];
    $remove_main_image = isset($_POST['remove_main_image']) && $_POST['remove_main_image'] == 1;
    $remove_gallery_images = $_POST['remove_gallery_images'] ?? [];

    // 1) Fetch existing product
    $query = "SELECT * FROM products WHERE id = ?";
    $selectStmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($selectStmt, 'i', $productId);
    mysqli_stmt_execute($selectStmt);
    $selectResult = mysqli_stmt_get_result($selectStmt);
    $existing_product = mysqli_fetch_assoc($selectResult);
    mysqli_stmt_close($selectStmt);

    if ($existing_product) {

        // Keep originals for later deletion decisions
        $old_main_image = $existing_product['image'];
        $old_gallery_string = $existing_product['gallery_images'];


        // ----- MAIN IMAGE -----
        $main_image_path = $old_main_image; // default: keep old
        $main_tmp_file = null; // tmp path of new uploaded main image (if any)

        if ($remove_main_image) {
            $main_image_path = null; // remove in DB
        } elseif (!empty($_FILES['main_image']['name'])) {
            $main_dir = 'uploads/products/main/';
            if (!is_dir($main_dir)) {
                mkdir($main_dir, 0755, true);
            }
            $main_image_path = $main_dir . uniqid() . '_' . basename($_FILES['main_image']['name']);
            $main_tmp_file = $_FILES['main_image']['tmp_name']; // will move later
        }

        // ----- GALLERY: start from existing -----
        $existing_gallery = !empty($old_gallery_string) ? explode(',', $old_gallery_string) : [];
        $existing_gallery = array_map('trim', $existing_gallery);

        // Remove selected old gallery images (DB side)
        if (!empty($remove_gallery_images)) {
            $existing_gallery = array_filter($existing_gallery, function ($img) use ($remove_gallery_images) {
                return !in_array($img, $remove_gallery_images, true);
            });
        }

        // Prepare new gallery file paths (no move yet)
        $new_gallery_files = []; // list of ['tmp' => tmp_path, 'dest' => final_path]

        if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
            $gallery_dir = 'uploads/products/gallery/';
            if (!is_dir($gallery_dir)) {
                mkdir($gallery_dir, 0755, true);
            }

            $galleryCount = count($_FILES['gallery_images']['name']);
            for ($i = 0; $i < $galleryCount; $i++) {
                if (empty($_FILES['gallery_images']['name'][$i])) {
                    continue;
                }

                $dest = $gallery_dir . uniqid() . $_FILES['gallery_images']['name'][$i];
                $tmp = $_FILES['gallery_images']['tmp_name'][$i];
                $new_gallery_files[] = ['tmp' => $tmp, 'dest' => $dest];
                $existing_gallery[] = $dest; // include in final gallery list
            }
        }

        // Final gallery string for DB
        $gallery_images_path = !empty($existing_gallery) ? implode(',', $existing_gallery) : null;

        // =========================
        // 3) Read other fields
        // =========================
        $name = $_POST['name'];
        $categoryId = $_POST['category_id'];
        $brand = $_POST['brand'];
        $description = $_POST['description'];
        $longDescription = $_POST['long_description'];
        $price = $_POST['price'];
        $discount = $_POST['discount'];
        $stock = $_POST['stock'];
        $status = $_POST['status'];

        // =========================
        // 4) UPDATE DB only (no file ops)
        // =========================
        $updateQuery = "UPDATE products
    SET name=?, category_id=?, brand=?, price=?, discount=?, stock=?,
    description=?, long_description=?, image=?, gallery_images=?, status=?
    WHERE id=?";

        $updateStmt = mysqli_prepare($connection, $updateQuery);
        if ($updateStmt) {
            mysqli_stmt_bind_param(
                $updateStmt,
                'sisddisssssi', // adjust according to your column types
                $name,
                $categoryId,
                $brand,
                $price,
                $discount,
                $stock,
                $description,
                $longDescription,
                $main_image_path,
                $gallery_images_path,
                $status,
                $productId
            );

            if (mysqli_stmt_execute($updateStmt)) {
                // =========================
                // 5) DB update success → now handle filesystem
                // =========================

                // (a) Move new main image (if any)
                if (!empty($main_tmp_file) && !empty($main_image_path)) {
                    @move_uploaded_file($main_tmp_file, $main_image_path);
                }

                // (b) Move new gallery images
                foreach ($new_gallery_files as $file) {
                    @move_uploaded_file($file['tmp'], $file['dest']);
                }

                // (c) Delete removed main image from disk
                if ($remove_main_image && !empty($old_main_image) && file_exists($old_main_image)) {
                    @unlink($old_main_image);
                }

                // (d) Delete removed gallery images from disk
                if (!empty($remove_gallery_images)) {
                    foreach ($remove_gallery_images as $old_gallery_img) {
                        if (!empty($old_gallery_img) && file_exists($old_gallery_img)) {
                            @unlink($old_gallery_img);
                        }
                    }
                }
                setcookie('success', 'Product updated successfully!', time() + 5);
            } else {
                setcookie('error', 'Failed to update product. Please try again.', time() + 5);
            }

            mysqli_stmt_close($updateStmt);
        }
    }
}
?>

<div class="container">
    <br>
    <h1 class="text-center mb-4">Product Management</h1>


    <!-- Display success and error Messages -->
    <?php if (isset($_COOKIE['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_COOKIE['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_COOKIE['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_COOKIE['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Add product Button -->

    <div class="row">
        <div class="col-md-2">
            <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#addProductModal">
                Add New Product
            </button>
        </div>

        <!-- Search Box -->
        <div class="col-md-10">
            <input type="text" id="searchInput" class="form-control" placeholder="Search products..."
                value="<?= isset($_GET['search']) ? htmlspecialchars(trim($_GET['search']), ENT_QUOTES) : '' ?>">
        </div>
    </div>

    <!-- Add product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data" class="h-100 d-flex flex-column" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title">Add Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required
                                data-validation="required,min,max" data-min="2" data-max="255">
                            <small id="name_error"></small>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Category ID</label>
                                <input type="number" step="1" min="0" class="form-control" name="category_id"
                                    value="0" data-validation="required,number">
                                <small id="category_id_error"></small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Brand</label>
                                <input type="text" class="form-control" name="brand"
                                    data-validation="required,min,max" data-min="2" data-max="100">
                                <small id="brand_error"></small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4" data-validation="required max"
                                data-max="2000"></textarea>
                            <small id="description_error"></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Long Description</label>
                            <textarea class="form-control" name="long_description" rows="5"
                                data-validation="required max" data-max="10000"></textarea>
                            <small id="long_description_error"></small>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Price</label>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="price"
                                    required data-validation="required">
                                <small id="price_error"></small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Discount (%)</label>
                                <input type="number" step="1" min="0" max="30" class="form-control" name="discount"
                                    value="0" data-validation="required,number">
                                <small id="discount_error"></small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Stock</label>
                            <input type="number" step="1" min="0" class="form-control" name="stock"
                                data-validation="required,number">
                            <small id="stock_error"></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Main Image</label>
                            <input type="file" class="form-control" name="main_image" accept=".jpg,.jpeg,.png,.webp"
                                data-validation="required,fileSize,fileType" data-filesize-mb="2"
                                data-filetype="image/jpeg,image/png,image/jpg,image/jpeg,image/webp">
                            <small id="main_image_error"></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Gallery Images</label>
                            <input type="file" class="form-control" name="gallery_images[]"
                                accept=".jpg,.jpeg,.png,.webp" multiple data-validation="required,fileSize,fileType"
                                data-filesize-mb="2"
                                data-filetype="image/jpeg,image/png,image/jpg,image/jpeg,image/webp"
                                data-error="#gallery_images_error">
                            <small id="gallery_images_error"></small>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" data-validation="required,select">
                                <option value="Active" selected>Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <small id="status_error"></small>
                        </div>
                    </div>
                    <div class="modal-footer mt-auto">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <br>

    <?php
    include_once 'db_config.php';

    // Pagination + search logic
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $hasSearch = $search !== '';

    if ($hasSearch) {
        $searchLike = '%' . $search . '%';
        $count_query = "SELECT COUNT(*) AS total FROM products WHERE name LIKE ? OR brand LIKE ? OR description LIKE ?";
        $countStmt = $connection->prepare($count_query);
        $countStmt->bind_param('sss', $searchLike, $searchLike, $searchLike);
    } else {
        $count_query = "SELECT COUNT(*) AS total FROM products";
        $countStmt = $connection->prepare($count_query);
    }

    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalProducts = (int) (mysqli_fetch_assoc($countResult)['total'] ?? 0);
    $countStmt->close();
    flush_stored_results($connection);

    $per_page = 4;
    $total_pages = max(1, (int) ceil($totalProducts / $per_page));
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1) $page = 1;
    if ($page > $total_pages) $page = $total_pages;

    $offset = ($page - 1) * $per_page;

    if ($hasSearch) {
        $query = "SELECT * FROM products WHERE name LIKE ? OR brand LIKE ? OR description LIKE ? ORDER BY id LIMIT ?, ?";
        $selectStmt = $connection->prepare($query);
        $selectStmt->bind_param("sssii", $searchLike, $searchLike, $searchLike, $offset, $per_page);
    } else {
        $query = "SELECT * FROM products ORDER BY id LIMIT ?, ?";
        $selectStmt = $connection->prepare($query);
        $selectStmt->bind_param("ii", $offset, $per_page);
    }

    $selectStmt->execute();
    $result = $selectStmt->get_result();



    ?>
    <table class="table table-bordered table-striped align-middle" id="productsTable">
        <thead>
            <tr>
                <th>Product ID</th>
                <th>Product Name</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Category</th>
                <th>Description</th>
                <th>Image</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="productsTableBody">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= (int) $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['price']) ?></td>
                        <td><?= htmlspecialchars($row['stock']) ?></td>
                        <td><?= htmlspecialchars($row['category_id']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td>
                            <?php if (!empty($row['image'])) { ?>
                                <img src="<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>"
                                    width="100">
                            <?php } ?>
                        </td>
                        <td><?= (strtolower((string) $row['status']) === 'active' || $row['status'] == 1) ? 'Active' : 'Inactive' ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary mb-1" data-bs-toggle="modal"
                                data-bs-target="#viewProductModal<?= (int) $row['id'] ?>">View</button>

                            <!-- View Product Modal -->
                            <?php
                            $productStatusIsActive = strtolower((string) $row['status']) === 'active' || $row['status'] == 1;
                            $galleryImagesRaw = trim((string) ($row['gallery_images'] ?? ''));
                            $galleryImages = [];

                            if ($galleryImagesRaw !== '') {
                                $galleryImagesArray = explode(',', $galleryImagesRaw);

                                foreach ($galleryImagesArray as $galleryImage) {
                                    $galleryImage = trim($galleryImage);
                                    if ($galleryImage !== '') {
                                        $galleryImages[] = $galleryImage;
                                    }
                                }
                            }
                            ?>
                            <div class="modal fade" id="viewProductModal<?= (int) $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                    <div class="modal-content border-0 shadow">
                                        <div class="modal-header bg-light">
                                            <h5 class="modal-title">Product Details - <?= htmlspecialchars($row['name']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <div class="border rounded p-3 h-100 bg-light">
                                                        <h6 class="mb-3">Basic Info</h6>
                                                        <p class="mb-2"><strong>ID:</strong> <?= (int) $row['id'] ?></p>
                                                        <p class="mb-2"><strong>Name:</strong> <?= htmlspecialchars((string) $row['name']) ?></p>
                                                        <p class="mb-2"><strong>Brand:</strong> <?= htmlspecialchars((string) $row['brand']) ?></p>
                                                        <p class="mb-2"><strong>Category ID:</strong> <?= htmlspecialchars((string) $row['category_id']) ?></p>
                                                        <p class="mb-0"><strong>Status:</strong>
                                                            <span class="badge <?= $productStatusIsActive ? 'bg-success' : 'bg-secondary' ?>">
                                                                <?= $productStatusIsActive ? 'Active' : 'Inactive' ?>
                                                            </span>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <div class="border rounded p-3 h-100">
                                                        <h6 class="mb-3">Pricing and Stock</h6>
                                                        <p class="mb-2"><strong>Price:</strong> <?= htmlspecialchars((string) $row['price']) ?></p>
                                                        <p class="mb-2"><strong>Discount:</strong> <?= htmlspecialchars((string) $row['discount']) ?></p>
                                                        <p class="mb-2"><strong>Discounted Price:</strong> <?= htmlspecialchars((string) ($row['final_price'] ?? '')) ?></p>
                                                        <p class="mb-2"><strong>Stock:</strong> <?= htmlspecialchars((string) $row['stock']) ?></p>

                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <div class="border rounded p-3 h-100">
                                                        <h6 class="mb-3">Descriptions</h6>
                                                        <p class="mb-2"><strong>Description</strong></p>
                                                        <p class="text-muted small"><?= nl2br(htmlspecialchars((string) $row['description'])) ?></p>
                                                        <hr>
                                                        <p class="mb-2"><strong>Long Description</strong></p>
                                                        <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars((string) $row['long_description'])) ?></p>
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <div class="border rounded p-3">
                                                        <h6 class="mb-3">Images</h6>
                                                        <div class="row g-3">
                                                            <div class="col-md-4">
                                                                <p class="mb-2"><strong>Main Image</strong></p>
                                                                <?php if (!empty($row['image'])): ?>
                                                                    <img src="<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>"
                                                                        class="img-fluid rounded border mb-2">
                                                                <?php else: ?>
                                                                    <p class="text-muted mb-2">No main image.</p>
                                                                <?php endif; ?>
                                                            </div>

                                                            <div class="col-md-8">
                                                                <p class="mb-2"><strong>Gallery Images Field</strong></p>

                                                                <?php if (!empty($galleryImages)): ?>
                                                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                                                        <?php foreach ($galleryImages as $galleryImage): ?>
                                                                            <img src="<?= htmlspecialchars($galleryImage) ?>" alt="<?= htmlspecialchars($row['name']) ?>"
                                                                                class="rounded border" style="width:150px;height:150px;object-fit:cover;">
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <p class="text-muted mb-0">No gallery images.</p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Product Button -->
                            <button class="btn btn-sm btn-warning mb-1" data-bs-toggle="modal"
                                data-bs-target="#editProductModal<?= (int) $row['id'] ?>">Edit</button>
                            <!-- Edit product modal -->
                            <div class="modal fade" id="editProductModal<?= (int) $row['id'] ?>" tabindex="-1"
                                aria-labelledby="editProductModalLabel<?= (int) $row['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <form method="post" enctype="multipart/form-data" novalidate>
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editProductModalLabel<?= (int) $row['id'] ?>">
                                                    Edit Product - <?= htmlspecialchars($row['name']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">

                                                <div class="mb-3">
                                                    <label class="form-label">Name</label>
                                                    <input type="text" class="form-control" name="name"
                                                        value="<?= htmlspecialchars($row['name']) ?>" required
                                                        data-validation="required,min,max" data-min="2" data-max="255">
                                                    <small id="edit_name_error_<?= (int) $row['id'] ?>"></small>
                                                </div>
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Category ID</label>
                                                        <input type="number" step="1" min="0" class="form-control"
                                                            name="category_id"
                                                            value="<?= htmlspecialchars((string) $row['category_id']) ?>"
                                                            data-validation="required,number">
                                                        <small id="edit_category_id_error_<?= (int) $row['id'] ?>"></small>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Brand</label>
                                                        <input type="text" class="form-control" name="brand"
                                                            value="<?= htmlspecialchars((string) $row['brand']) ?>"
                                                            data-validation="required,min,max" data-min="2" data-max="100">
                                                        <small id="edit_brand_error_<?= (int) $row['id'] ?>"></small>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea class="form-control" name="description" rows="4"
                                                        data-validation="required max"
                                                        data-max="2000"><?= htmlspecialchars((string) $row['description']) ?></textarea>
                                                    <small id="edit_description_error_<?= (int) $row['id'] ?>"></small>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Long Description</label>
                                                    <textarea class="form-control" name="long_description" rows="5"
                                                        data-validation="required max"
                                                        data-max="10000"><?= htmlspecialchars((string) $row['long_description']) ?></textarea>
                                                    <small id="edit_long_description_error_<?= (int) $row['id'] ?>"></small>
                                                </div>
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Price</label>
                                                        <input type="number" step="0.01" min="0.01" class="form-control"
                                                            name="price"
                                                            value="<?= htmlspecialchars((string) $row['price']) ?>" required
                                                            data-validation="required">
                                                        <small id="edit_price_error_<?= (int) $row['id'] ?>"></small>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Discount</label>
                                                        <input type="number" step="0.01" min="0.01" class="form-control"
                                                            name="discount"
                                                            value="<?= htmlspecialchars((string) $row['discount']) ?>" required
                                                            data-validation="required">
                                                        <small id="edit_price_error_<?= (int) $row['id'] ?>"></small>
                                                    </div>
                                                </div>
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Stock</label>
                                                        <input type="number" step="1" min="0" class="form-control"
                                                            name="stock"
                                                            value="<?= htmlspecialchars((string) $row['stock']) ?>" required
                                                            data-validation="required,number,min" data-min="0">
                                                        <small id="edit_stock_error_<?= (int) $row['id'] ?>"></small>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Status</label>
                                                        <select class="form-select" name="status" required>
                                                            <option value="Active"
                                                                <?= (strtolower((string) $row['status']) === 'active' || $row['status'] == 1) ? 'selected' : '' ?>>
                                                                Active</option>
                                                            <option value="Inactive"
                                                                <?= (strtolower((string) $row['status']) !== 'active' && $row['status'] != 1) ? 'selected' : '' ?>>
                                                                Inactive</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Main Image</label>
                                                    <?php if (!empty($row['image'])): ?>
                                                        <div class="mb-2 p-3 bg-light rounded border">
                                                            <p class="mb-2"><strong>Current Main Image</strong></p>
                                                            <img src="<?= htmlspecialchars($row['image']) ?>"
                                                                alt="<?= htmlspecialchars($row['name']) ?>"
                                                                class="img-fluid rounded mb-2" style="max-height: 150px;">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox"
                                                                    id="remove_main_<?= (int) $row['id'] ?>"
                                                                    name="remove_main_image" value="1">
                                                                <label class="form-check-label"
                                                                    for="remove_main_<?= (int) $row['id'] ?>">
                                                                    Remove this image
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <label class="form-label mt-3">Upload New Main Image</label>
                                                    <input type="file" class="form-control" name="main_image"
                                                        accept=".jpg,.jpeg,.png,.webp" data-validation="fileSize,fileType"
                                                        data-filesize-mb="2"
                                                        data-filetype="image/jpeg,image/png,image/jpg,image/jpeg,image/webp"
                                                        data-error="#edit_main_image_error_<?= (int) $row['id'] ?>">
                                                    <small id="edit_main_image_error_<?= (int) $row['id'] ?>"></small>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Gallery Images</label>
                                                    <?php if (!empty($row['gallery_images'])): ?>
                                                        <div class="mb-3 p-3 bg-light rounded border">
                                                            <p class="mb-2"><strong>Current Gallery Images</strong></p>
                                                            <div class="row g-2">
                                                                <?php foreach (explode(',', (string) $row['gallery_images']) as $index => $galleryImage): ?>
                                                                    <?php $galleryImage = trim($galleryImage); ?>
                                                                    <?php if ($galleryImage !== ''): ?>
                                                                        <div class="col-md-3">
                                                                            <div class="d-flex flex-column">
                                                                                <img src="<?= htmlspecialchars($galleryImage) ?>"
                                                                                    alt="Gallery" class="img-fluid rounded border"
                                                                                    style="height: 100px; object-fit: cover;">
                                                                                <div class="form-check mt-2">
                                                                                    <input class="form-check-input" type="checkbox"
                                                                                        id="remove_gallery_<?= (int) $row['id'] ?>_<?= $index ?>"
                                                                                        name="remove_gallery_images[]"
                                                                                        value="<?= htmlspecialchars($galleryImage) ?>">
                                                                                    <label class="form-check-label"
                                                                                        for="remove_gallery_<?= (int) $row['id'] ?>_<?= $index ?>">Remove</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <small class="d-block mt-2 text-muted">Check images to remove
                                                                them</small>
                                                        </div>
                                                    <?php endif; ?>
                                                    <label class="form-label mt-3">Add New Gallery Images</label>
                                                    <input type="file" class="form-control" name="gallery_images[]"
                                                        accept=".jpg,.jpeg,.png,.webp" multiple
                                                        data-validation="fileSize,fileType" data-filesize-mb="2"
                                                        data-filetype="image/jpeg,image/png,image/jpg,image/jpeg,image/webp"
                                                        data-error="#edit_gallery_images_error_<?= (int) $row['id'] ?>">
                                                    <small id="edit_gallery_images_error_<?= (int) $row['id'] ?>"></small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary"
                                                    data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Update Product</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>



                            <!-- Delete Product Button -->
                            <button class="btn btn-sm btn-danger mb-1" data-bs-toggle="modal"
                                data-bs-target="#deleteProductModal<?= (int) $row['id'] ?>">Delete</button>

                            <!-- Delete Product Modal -->
                            <!-- Delete Product Modal -->
                            <div class="modal fade" id="deleteProductModal<?= (int) $row['id'] ?>" tabindex="-1"
                                aria-labelledby="deleteProductModalLabel<?= (int) $row['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteProductModalLabel<?= (int) $row['id'] ?>">
                                                    Confirm Deletion</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                Are you sure you want to delete the product
                                                "<strong><?= htmlspecialchars($row['name']) ?></strong>"?
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary"
                                                    data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Delete Product</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Change Status Button -->
                            <button class="btn btn-sm btn-secondary mb-1" type="button" data-bs-toggle="modal"
                                data-bs-target="#changeStatusModal<?= (int) $row['id'] ?>">
                                <?= (strtolower((string) $row['status']) === 'active' || $row['status'] == 1) ? 'Deactivate' : 'Activate' ?>
                            </button>

                            <!-- Change Status Modal -->
                            <div class="modal fade" id="changeStatusModal<?= (int) $row['id'] ?>" tabindex="-1"
                                aria-labelledby="changeStatusModalLabel<?= (int) $row['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="changeStatusModalLabel<?= (int) $row['id'] ?>">
                                                    Change Status</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                Are you sure you want to
                                                <?= (strtolower((string) $row['status']) === 'active' || $row['status'] == 1) ? 'deactivate' : 'activate' ?>
                                                the product "<strong><?= htmlspecialchars($row['name']) ?></strong>"?
                                                <input type="hidden" name="action" value="change_status">
                                                <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">
                                                <input type="hidden" name="new_status"
                                                    value="<?= (strtolower((string) $row['status']) === 'active' || $row['status'] == 1) ? 'Inactive' : 'Active' ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary"
                                                    data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Change Status</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>


                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center text-muted">No products found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <nav aria-label="Products pagination" id="paginationWrapper">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link"
                    href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">Previous</a>
            </li>

            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link"
                        href="?page=<?= $p ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link"
                    href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">Next</a>
            </li>
        </ul>
    </nav>
    <br>
    <br>
</div>