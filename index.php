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
<?php
include_once 'db_config.php';

// code to insert data

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


    // Step 2: Insert product and image paths into database.
    $galleryImagesValue = !empty($gallery_images) ? json_encode($gallery_images) : null;
    $insertStmt = $connection->prepare("CALL sp_products_insert(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($insertStmt) {
        $insertStmt->bind_param(
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

        if ($insertStmt->execute()) {
            move_uploaded_file($_FILES['main_image']['tmp_name'], $main_image);
            foreach ($temp_gallery_images as $index => $tmp_name) {
                move_uploaded_file($tmp_name, $gallery_images[$index]);
            }
            setcookie('success', 'Product added successfully!', time() + 5);
            // echo "<script> window.location.href = 'teach.php';</script>";
        } else {
            setcookie('error', 'Failed to add product. Please try again.', time() + 5);
        }

        $insertStmt->close();
        flush_stored_results($connection);
    }
}
// Step 1: Load DB connection for pagination queries.
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

    // Select all products from the database

    // $query = "SELECT * FROM products";
    // $selectStmt = $connection->prepare($query);
    // $selectStmt->execute();
    // $result = $selectStmt->get_result();

    // Step 2: Count total rows and compute pagination values.

    $count_query = "SELECT COUNT(*) AS total FROM products";
    $countStmt = $connection->prepare($count_query);


    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalProducts = (int) (mysqli_fetch_assoc($countResult)['total'] ?? 0);
    $countStmt->close();
    flush_stored_results($connection);

    $per_page = 10;
    $total_pages = max(1, (int) ceil($totalProducts / $per_page));
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1) {
        $page = 1;
    }
    if ($page > $total_pages) {
        $page = $total_pages;
    }

    $offset = ($page - 1) * $per_page;


    // Step 3: Fetch only the records for the current page.
    $query = "SELECT * FROM products ORDER BY id LIMIT ?, ?";
    $selectStmt = $connection->prepare($query);
    $selectStmt->bind_param("ii", $offset, $per_page);


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


                            <!-- Edit Product Button -->
                            <button class="btn btn-sm btn-warning mb-1" data-bs-toggle="modal"
                                data-bs-target="#editProductModal<?= (int) $row['id'] ?>">Edit</button>
                            <!-- Edit product modal -->


                            <!-- Delete Product Button -->
                            <button class="btn btn-sm btn-danger mb-1" data-bs-toggle="modal"
                                data-bs-target="#deleteProductModal<?= (int) $row['id'] ?>">Delete</button>

                            <!-- Delete Product Modal -->


                            <!-- Change Status Button -->
                            <button class="btn btn-sm btn-secondary mb-1" type="button" data-bs-toggle="modal"
                                data-bs-target="#changeStatusModal<?= (int) $row['id'] ?>">
                                <?= (strtolower((string) $row['status']) === 'active' || $row['status'] == 1) ? 'Deactivate' : 'Activate' ?>
                            </button>

                            <!-- Change Status Modal -->


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
                    href="?<?= http_build_query(['page' => $page - 1]) ?>">Previous</a>
            </li>

            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link"
                        href="?<?= http_build_query(['page' => $p]) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link"
                    href="?<?= http_build_query(['page' => $page + 1]) ?>">Next</a>
            </li>
        </ul>
    </nav>
    <br>
    <br>
</div>
<br>
<br>
</div>