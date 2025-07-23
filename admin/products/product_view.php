<?php
session_start();
// تمكين عرض الأخطاء لغرض التصحيح (يمكن إزالتها بعد الانتهاء)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// تأكد من المسار الصحيح لملف اتصال قاعدة البيانات
include('../connect_DB.php');

// التأكد من وجود معرف المنتج في الرابط
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header('Location: /NEW_IBB/admin/products/product.php'); // إعادة التوجيه لصفحة المنتجات
    exit();
}

$product_id = $_GET['id'];
$product = null;
$product_images = [];
$available_sizes_with_quantities = []; // ستتضمن الآن الكميات والأسعار
$reviews = [];
$user_review_interaction_types = []; // لتتبع تفاعلات المستخدم الحالي مع التقييمات

// جلب تفاصيل المنتج الأساسية ومتوسط التقييم وعدد التقييمات
// **تعديل: جلب السعر الافتراضي (أقل سعر) من المخزون وتصحيح Average Rating و Total Reviews Count**
$stmt = $con->prepare("
    SELECT 
        p.*, 
        c.category_name as category_name, 
        COALESCE((SELECT MIN(i.selling_price) FROM inventory i WHERE i.product_id = p.id), 0.00) AS price, -- جلب أقل سعر من المخزون
        COALESCE((SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id AND status = 'approved'), 0) AS average_rating,
        COALESCE((SELECT COUNT(id) FROM product_reviews WHERE product_id = p.id AND status = 'approved'), 0) AS total_reviews_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");

if ($stmt === false) {
    die("Error preparing product query: " . $con->error);
}
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header('Location: /NEW_IBB/products.php'); // المنتج غير موجود
    exit();
}
$product = $result->fetch_assoc();
$stmt->close();

// جلب صور المنتج
$stmt_images = $con->prepare("SELECT image_path FROM products_images WHERE product_id = ? ORDER BY id ASC");
if ($stmt_images === false) {
    error_log("Error preparing images query: " . $con->error);
} else {
    $stmt_images->bind_param("i", $product_id);
    $stmt_images->execute();
    $result_images = $stmt_images->get_result();
    while ($row_image = $result_images->fetch_assoc()) {
        $product_images[] = $row_image['image_path'];
    }
    $stmt_images->close();
}

// **تعديل: جلب المقاسات والكميات والأسعار المتاحة من جدول inventory**
$stmt_sizes_quantities = $con->prepare("
    SELECT 
        ps.id AS size_id, 
        ps.size, 
        COALESCE(i.quantity, 0) AS quantity, -- جلب الكمية، 0 إذا لم يكن موجودًا
        COALESCE(i.selling_price, 0.00) AS selling_price -- جلب سعر البيع، 0.00 إذا لم يكن موجودًا
    FROM 
        product_sizes ps
    LEFT JOIN 
        inventory i ON ps.id = i.size_id AND i.product_id = ps.product_id
    WHERE 
        ps.product_id = ? 
    ORDER BY 
        ps.size ASC
");

if ($stmt_sizes_quantities === false) {
    error_log("Failed to prepare sizes and quantities query: " . $con->error);
} else {
    $stmt_sizes_quantities->bind_param("i", $product_id);
    $stmt_sizes_quantities->execute();
    $result_sizes_quantities = $stmt_sizes_quantities->get_result();
    while ($row = $result_sizes_quantities->fetch_assoc()) {
        $available_sizes_with_quantities[] = $row;
    }
    $stmt_sizes_quantities->close();
}

// جلب التقييمات المعتمدة والردود عليها
$stmt_reviews = $con->prepare("
    SELECT
        pr.id, pr.rating, pr.review_title, pr.review_text, pr.created_at,
        pr.likes_count, pr.dislikes_count, pr.comments_count,
        u.name as username, 
        u.user_data as profile_image, 
        u.id as user_id
    FROM product_reviews pr
    JOIN user_tb u ON pr.user_id = u.id
    WHERE pr.product_id = ? AND pr.status = 'approved'
    ORDER BY pr.created_at DESC
");
if ($stmt_reviews === false) {
    error_log("Failed to prepare reviews query: " . $con->error);
} else {
    $stmt_reviews->bind_param("i", $product_id);
    $stmt_reviews->execute();
    $result_reviews = $stmt_reviews->get_result();

    while ($row_review = $result_reviews->fetch_assoc()) {
        $reviews[] = $row_review;
    }
    $stmt_reviews->close();
}

// جلب الردود لكل تقييم
foreach ($reviews as &$review) {
    $review_comments = [];
    $stmt_comments = $con->prepare("
        SELECT
            rc.comment_text, rc.created_at, rc.is_admin_reply,
            u.name as username, 
            u.user_data as profile_image, 
            u.id as user_id
        FROM review_comments rc
        LEFT JOIN user_tb u ON rc.user_id = u.id 
        WHERE rc.review_id = ?
        ORDER BY rc.created_at ASC
    ");
    if ($stmt_comments === false) {
        error_log("Failed to prepare comments query for review ID " . $review['id'] . ": " . $con->error);
    } else {
        $stmt_comments->bind_param("i", $review['id']);
        $stmt_comments->execute();
        $result_comments = $stmt_comments->get_result();
        while ($row_comment = $result_comments->fetch_assoc()) {
            $review_comments[] = $row_comment;
        }
        $stmt_comments->close();
    }
    $review['comments'] = $review_comments;
}
unset($review); 

// جلب تفاعلات المستخدم الحالي مع التقييمات (like/dislike)
if (isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
    $stmt_interactions = $con->prepare("SELECT review_id, interaction_type FROM review_interactions WHERE user_id = ?");
    if ($stmt_interactions === false) {
        error_log("Failed to prepare interactions query: " . $con->error);
    } else {
        $stmt_interactions->bind_param("i", $current_user_id);
        $stmt_interactions->execute();
        $result_interactions = $stmt_interactions->get_result();
        while ($row = $result_interactions->fetch_assoc()) {
            $user_review_interaction_types[$row['review_id']] = $row['interaction_type'];
        }
        $stmt_interactions->close();
    }
}

$con->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name'] ?? 'منتج غير معروف'); ?> - متجر IBB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* General product view styling */
        .product-hero {
            background-color: #f8f9fa;
            padding: 30px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .product-images .main-image img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .thumbnail-gallery img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: 5px;
            transition: all 0.2s ease-in-out;
            opacity: 0.7;
        }
        .thumbnail-gallery img:hover,
        .thumbnail-gallery img.active {
            border-color: #007bff;
            opacity: 1;
        }
        .product-details h1 {
            font-size: 2.5em;
            font-weight: 700;
            color: #343a40;
            margin-bottom: 15px;
        }
        .product-details .price {
            font-size: 2.2em;
            font-weight: bold;
            color: #dc3545; /* Red for price */
            margin-bottom: 20px;
        }
        .product-details .price span {
            font-size: 0.7em;
            color: #6c757d;
        }
        .option-group {
            margin-bottom: 20px;
        }
        .option-group label {
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
            color: #495057;
        }
        .option-buttons .btn {
            margin-right: 10px;
            margin-bottom: 10px;
            min-width: 60px;
            border-radius: 5px;
        }
        .option-buttons .btn.selected {
            background-color: #007bff;
            color: #fff;
            border-color: #007bff;
        }
        .option-buttons .btn.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background-color: #e9ecef;
            color: #6c757d;
            border-color: #e9ecef;
        }
        .product-description h2 {
            font-size: 1.8em;
            font-weight: 600;
            margin-bottom: 20px;
            color: #343a40;
            position: relative;
        }
        .product-description h2::after {
            content: '';
            position: absolute;
            right: 0;
            bottom: -5px;
            width: 50px;
            height: 3px;
            background-color: #007bff;
            border-radius: 2px;
        }

        /* Reviews Section Styling */
        .product-reviews h2 {
            font-size: 2em;
            font-weight: 700;
            color: #212529;
            margin-bottom: 25px;
            position: relative;
        }
        .product-reviews h2::after {
            content: '';
            position: absolute;
            right: 0;
            bottom: -10px;
            width: 80px; /* Wider line for reviews section */
            height: 4px;
            background-color: #28a745; /* Green for reviews section title */
            border-radius: 2px;
        }

        .average-rating-summary {
            background-color: #e6f7ff; /* Light blue background */
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid #cceeff;
            display: flex;
            align-items: center;
        }
        .average-rating-summary .stars-display {
            font-size: 1.8em;
            color: gold;
        }
        .average-rating-summary .average-score {
            font-size: 1.8em;
            font-weight: bold;
            color: #333;
            margin-right: 10px; /* Space between stars and score */
        }
        .average-rating-summary .total-reviews-text {
            color: #555;
            font-size: 0.9em;
        }


        /* Star Rating for form */
        .star-rating {
            font-size: 1.8em;
            color: #ccc;
            cursor: pointer;
            direction: ltr; /* Ensure stars are LTR even in RTL document */
            display: inline-block;
        }
        .star-rating .fas.fa-star {
            color: gold;
        }
        .star-rating i {
            transition: color 0.2s ease;
        }
        .star-rating i:hover {
            color: orange;
        }

        /* Individual Review Item */
        .review-item {
            background-color: #fdfdfd;
            border: 1px solid #eee;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .review-item .review-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .review-item .reviewer-profile-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-left: 10px;
            border: 1px solid #eee;
        }
        .review-item h5.reviewer-name {
            font-weight: 600;
            color: #444;
            margin-bottom: 0;
            line-height: 1.2;
        }
        .review-item .stars-display {
            font-size: 1.2em;
            color: gold;
            margin-right: 10px;
        }
        .review-item p.review-date {
            font-size: 0.85em;
            color: #888;
            margin-bottom: 0;
        }
        .review-item h6.review-title {
            font-weight: 600;
            color: #333;
            margin-top: 5px;
            margin-bottom: 8px;
        }
        .review-item p.review-text {
            font-size: 0.95em;
            color: #555;
            line-height: 1.6;
        }

        /* Review Actions (Like/Dislike/Reply) */
        .review-actions .btn {
            border-radius: 20px;
            font-size: 0.9em;
            padding: 5px 12px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }
        .review-actions .btn i {
            margin-left: 5px;
        }
        .review-actions .btn:hover {
            transform: translateY(-1px);
        }
        .review-actions .btn.active-like {
            background-color: #28a745;
            color: white;
            border-color: #28a745;
        }
        .review-actions .btn.active-dislike {
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
        }


        /* Review Comments */
        .review-comments {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e0e0e0;
        }
        .review-comments h6 {
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 1px dashed #eee;
            padding-bottom: 10px;
        }
        .comment-item {
            padding-right: 10px; /* Adjust for RTL */
            margin-bottom: 10px;
            border-right: 3px solid; /* For border-start (RTL) */
            padding-left: 0; /* Remove default padding-left */
        }
        .comment-item.border-primary { border-color: #007bff !important; } /* Admin reply */
        .comment-item.border-secondary { border-color: #6c757d !important; } /* User reply */
        .comment-item .comment-header {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .comment-item .commenter-profile-image {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            margin-left: 8px;
            border: 1px solid #eee;
        }
        .comment-item p.comment-text {
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .comment-item strong {
            color: #222;
        }
        .comment-item small {
            font-size: 0.75em;
            color: #888;
        }

        .reply-form {
            background-color: #f0f0f0;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9e9e9;
        }
        .reply-form textarea {
            resize: vertical;
        }

        /* Generic message styling */
        .message-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            width: 350px;
        }
        .message-container .alert {
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>


    <div id="message-container" class="message-container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>
    </div>

    <div class="container mt-5">
        <div class="row product-hero">
            <div class="col-md-6 mb-4">
                <div class="main-image mb-3 text-center">
                    <?php $main_image = !empty($product_images) ? $product_images[0] : '/NEW_IBB/assets/images/placeholder.png'; ?>
                    <img id="displayMainImage" src="<?php echo htmlspecialchars($main_image); ?>" class="img-fluid" alt="<?php echo htmlspecialchars($product['name'] ?? 'صورة المنتج'); ?>">
                </div>
                <div class="thumbnail-gallery d-flex justify-content-center flex-wrap gap-2">
                    <?php foreach ($product_images as $index => $image_path): ?>
                        <img src="<?php echo htmlspecialchars($image_path); ?>" 
                             class="img-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                             alt="Thumbnail <?php echo $index + 1; ?>" 
                             onclick="changeMainImage(this)">
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-6 mb-4 product-details">
                <h1 class="mb-2"><?php echo htmlspecialchars($product['name'] ?? 'اسم المنتج غير معروف'); ?></h1>
                <div class="d-flex align-items-center mb-3">
                    <div class="stars-display me-2" style="color: gold;">
                        <?php
                        $avg_rating = $product['average_rating'] ?? 0;
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= round($avg_rating)) {
                                echo '<i class="fas fa-star"></i>';
                            } else if ($i - 0.5 <= $avg_rating) {
                                echo '<i class="fas fa-star-half-alt"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }
                        ?>
                    </div>
                    <span class="text-muted">(<?php echo $product['total_reviews_count'] ?? 0; ?> تقييم)</span>
                </div>

                <p class="price">
                    <span id="product-display-price"><?php echo number_format($product['price'] ?? 0.00, 2); ?></span> <span>ريال يمني</span>
                </p>

                <?php if (!empty($available_sizes_with_quantities)): ?>
                    <div class="option-group">
                        <label>المقاس:</label>
                        <div class="btn-group option-buttons" role="group" data-option-type="Size">
                            <?php foreach ($available_sizes_with_quantities as $size_info): ?>
                                <button type="button"
                                        class="btn btn-outline-secondary <?php echo ($size_info['quantity'] <= 0) ? 'disabled' : ''; ?>"
                                        data-option-value="<?php echo htmlspecialchars($size_info['size']); ?>"
                                        data-size-id="<?php echo htmlspecialchars($size_info['size_id']); ?>" data-stock-quantity="<?php echo htmlspecialchars($size_info['quantity']); ?>"
                                        data-selling-price="<?php echo htmlspecialchars(number_format($size_info['selling_price'], 2, '.', '')); ?>"
                                        <?php echo ($size_info['quantity'] <= 0) ? 'disabled' : ''; ?>
                                        >
                                    <?php echo htmlspecialchars($size_info['size']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg" id="addToCartBtn">
                        <i class="fas fa-shopping-cart me-2"></i> أضف إلى السلة
                    </button>
                    <a href="#" class="btn btn-outline-secondary btn-lg">
                        <i class="far fa-heart me-2"></i> أضف إلى قائمة الرغبات
                    </a>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-12 product-description">
                <h2 class="mb-4">وصف المنتج</h2>
                <p><?php echo nl2br(htmlspecialchars($product['description'] ?? 'لا يوجد وصف لهذا المنتج.')); ?></p>
            </div>
        </div>

        <div class="product-reviews mt-5">
            <h2 class="mb-4">تقييمات العملاء</h2>

            <?php if (($product['total_reviews_count'] ?? 0) > 0): ?>
                <div class="average-rating-summary mb-4 d-flex align-items-center">
                    <div class="stars-display me-3">
                        <?php
                        $avg_rating = $product['average_rating'] ?? 0;
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= round($avg_rating)) {
                                echo '<i class="fas fa-star"></i>';
                            } else if ($i - 0.5 <= $avg_rating) {
                                echo '<i class="fas fa-star-half-alt"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }
                        ?>
                    </div>
                    <span class="average-score h4 mb-0"><?php echo number_format($avg_rating, 1); ?> من 5</span>
                    <span class="total-reviews-text ms-3">(بناءً على <?php echo $product['total_reviews_count'] ?? 0; ?> تقييم)</span>
                </div>
            <?php else: ?>
                <p class="text-muted">لا توجد تقييمات لهذا المنتج حتى الآن.</p>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="add-review-form card p-4 mb-4">
                    <h4 class="mb-3">أضف تقييمك</h4>
                    <form action="./submit_review.php" method="POST">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>"> 

                        <div class="mb-3">
                            <label for="rating" class="form-label">تقييمك (النجوم):</label>
                            <div class="star-rating" data-rating="0">
                                <i class="far fa-star" data-value="1"></i>
                                <i class="far fa-star" data-value="2"></i>
                                <i class="far fa-star" data-value="3"></i>
                                <i class="far fa-star" data-value="4"></i>
                                <i class="far fa-star" data-value="5"></i>
                                <input type="hidden" name="rating" id="rating_input" value="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="review_title" class="form-label">عنوان التقييم (اختياري):</label>
                            <input type="text" class="form-control" id="review_title" name="review_title" maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label for="review_text" class="form-label">مراجعتك:</label>
                            <textarea class="form-control" id="review_text" name="review_text" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-success">إرسال التقييم</button>
                    </form>
                </div>
            <?php else: ?>
                <p class="text-info">الرجاء <a href="/NEW_IBB/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">تسجيل الدخول</a> لإضافة تقييم.</p>
            <?php endif; ?>

            <div class="reviews-list">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="card review-item mb-4 p-4 shadow-sm" id="review-<?php echo htmlspecialchars($review['id']); ?>">
                            <div class="review-header d-flex align-items-center mb-2">
                                <img src="<?php echo !empty($review['profile_image']) ? htmlspecialchars($review['profile_image']) : '/NEW_IBB/assets/images/default_avatar.png'; ?>" class="reviewer-profile-image" alt="Profile Image">
                                <div>
                                    <h5 class="reviewer-name mb-0"><?php echo htmlspecialchars($review['username']); ?></h5>
                                    <div class="stars-display">
                                        <?php
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $review['rating']) {
                                                echo '<i class="fas fa-star"></i>';
                                            } else {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <p class="review-date text-muted small mb-2"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($review['created_at']))); ?></p>
                            <?php if (!empty($review['review_title'])): ?>
                                <h6 class="review-title"><?php echo htmlspecialchars($review['review_title']); ?></h6>
                            <?php endif; ?>
                            <p class="review-text"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>

                            <div class="review-actions d-flex align-items-center mt-3 border-top pt-3">
                                <?php
                                $user_interaction = $user_review_interaction_types[$review['id']] ?? '';
                                ?>
                                <button type="button" class="btn btn-sm btn-outline-primary me-2 like-btn <?php echo ($user_interaction === 'like' ? 'active-like' : ''); ?>" data-review-id="<?php echo htmlspecialchars($review['id']); ?>" data-action="like" <?php echo !isset($_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                    <i class="far fa-thumbs-up"></i> مفيد (<span class="like-count"><?php echo htmlspecialchars($review['likes_count']); ?></span>)
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger me-2 dislike-btn <?php echo ($user_interaction === 'dislike' ? 'active-dislike' : ''); ?>" data-review-id="<?php echo htmlspecialchars($review['id']); ?>" data-action="dislike" <?php echo !isset($_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                    <i class="far fa-thumbs-down"></i> (<span class="dislike-count"><?php echo htmlspecialchars($review['dislikes_count']); ?></span>)
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info reply-btn" data-review-id="<?php echo htmlspecialchars($review['id']); ?>" <?php echo !isset($_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                    <i class="far fa-comment"></i> رد (<span class="comment-count"><?php echo htmlspecialchars($review['comments_count']); ?></span>)
                                </button>
                            </div>

                            <?php if (!empty($review['comments'])): ?>
                                <div class="review-comments mt-3 border-top pt-3">
                                    <h6 class="mb-3">الردود:</h6>
                                    <?php foreach ($review['comments'] as $comment): ?>
                                        <div class="comment-item ps-3 mb-2 <?php echo $comment['is_admin_reply'] ? 'border-primary' : 'border-secondary'; ?>">
                                            <div class="comment-header d-flex align-items-center">
                                                <img src="<?php echo !empty($comment['profile_image']) ? htmlspecialchars($comment['profile_image']) : '/NEW_IBB/assets/images/default_avatar.png'; ?>" class="commenter-profile-image" alt="Profile Image">
                                                <strong><?php echo $comment['is_admin_reply'] ? 'إدارة المتجر' : htmlspecialchars($comment['username']); ?>:</strong>
                                            </div>
                                            <p class="comment-text mb-1 ms-4"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                            <small class="text-muted ms-4"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($comment['created_at']))); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="reply-form mt-3" style="display: none;" id="reply-form-<?php echo htmlspecialchars($review['id']); ?>">
                                <form class="submit-comment-form">
                                    <input type="hidden" name="review_id" value="<?php echo htmlspecialchars($review['id']); ?>">
                                    <textarea class="form-control mb-2" name="comment_text" rows="2" placeholder="اكتب ردك هنا..." required></textarea>
                                    <button type="submit" class="btn btn-sm btn-primary">إرسال الرد</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <?php 
    // include('../../includes/footer.php'); // مثال: إذا كان footer.php في NEW_IBB/includes/
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // دالة لعرض الرسائل التي تأتي من PHP (عبر $_SESSION)
        function showMessage(message, type = 'success') {
            const messageContainer = document.getElementById('message-container');
            const alertDiv = document.createElement('div');
            alertDiv.classList.add('alert', `alert-${type}`, 'alert-dismissible', 'fade', 'show');
            alertDiv.setAttribute('role', 'alert');
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            messageContainer.appendChild(alertDiv);
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }, 5000); // Hide after 5 seconds
        }

        // Image Gallery Logic
        function changeMainImage(thumbnail) {
            document.querySelectorAll('.thumbnail-gallery img').forEach(img => img.classList.remove('active'));
            thumbnail.classList.add('active');
            document.getElementById('displayMainImage').src = thumbnail.src;
        }

        // Product Sizes Logic
        let selectedSize = null;
        let selectedSizeId = null; // إضافة لمعرف المقاس
        let selectedSizeStock = 0;
        // السعر الافتراضي للمنتج قبل اختيار المقاس (يأتي من PHP)
        let selectedSizePrice = parseFloat(<?php echo json_encode($product['price'] ?? 0.00); ?>); 

        const priceDisplayElement = document.getElementById('product-display-price');
        
        // تحديث السعر المعروض
        function updatePriceDisplay() {
            priceDisplayElement.textContent = selectedSizePrice.toFixed(2);
        }

        document.querySelectorAll('.option-buttons .btn').forEach(button => {
            button.addEventListener('click', function() {
                if (this.disabled) {
                    return;
                }
                document.querySelectorAll('.option-buttons .btn').forEach(btn => {
                    btn.classList.remove('selected');
                });
                this.classList.add('selected');
                selectedSize = this.dataset.optionValue;
                selectedSizeId = this.dataset.sizeId; // جلب معرف المقاس
                selectedSizeStock = parseInt(this.dataset.stockQuantity);
                selectedSizePrice = parseFloat(this.dataset.sellingPrice);
                
                updatePriceDisplay(); // تحديث السعر المعروض
                updateAddToCartButton();
            });
        });

        function updateAddToCartButton() {
            const addToCartBtn = document.getElementById('addToCartBtn');
            const hasSizes = <?php echo json_encode(!empty($available_sizes_with_quantities)); ?>;

            if (hasSizes) {
                if (selectedSize === null || selectedSizeStock <= 0) {
                    addToCartBtn.disabled = true;
                    //addToCartBtn.textContent = 'أضف إلى السلة (الرجاء اختيار مقاس)'; // يمكن إضافة هذه الرسالة
                } else {
                    addToCartBtn.disabled = false;
                    //addToCartBtn.textContent = 'أضف إلى السلة';
                }
            } else {

                addToCartBtn.disabled = false; 
            }
        }
        
        // استدعاء الدالة عند تحميل الصفحة لضبط حالة الزر والسعر الأولي
        document.addEventListener('DOMContentLoaded', function() {
            updateAddToCartButton(); // لضبط حالة زر الإضافة للسلة عند التحميل
            updatePriceDisplay(); // لعرض السعر الافتراضي للمنتج عند التحميل
        });


        // Add to Cart button click handler (AJAX)
        document.getElementById('addToCartBtn').addEventListener('click', function() {
            if (this.disabled) {
                showMessage('الرجاء اختيار مقاس وكمية متاحة للمنتج.', 'warning');
                return;
            }

            const productId = <?php echo json_encode($product_id); ?>;
            const quantity = 1; // يمكنك إضافة حقل لاختيار الكمية إذا أردت

            // التحقق من وجود مقاس إذا كان المنتج يتطلب ذلك
            const hasSizes = <?php echo json_encode(!empty($available_sizes_with_quantities)); ?>;
            let dataToSend = {
                product_id: productId,
                quantity: quantity
            };

            if (hasSizes) {
                if (selectedSize === null || selectedSizeId === null) {
                    showMessage('الرجاء اختيار مقاس للمنتج قبل الإضافة إلى السلة.', 'warning');
                    return;
                }
                dataToSend.size = selectedSize; // المقاس كـ نص (S, M, L)
                dataToSend.size_id = selectedSizeId; // معرف المقاس من قاعدة البيانات
            }

            fetch('/NEW_IBB/admin/Carts/add_to_cart.php', { 
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dataToSend)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showMessage(data.message, 'success');
                    // يمكنك تحديث عدد العناصر في سلة التسوق هنا
                    if (data.cart_item_count !== undefined) {
                        // تحديث عنصر HTML لعرض عدد العناصر في السلة
                        // مثلاً: document.getElementById('cart-item-count').textContent = data.cart_item_count;
                    }
                } else {
                    showMessage(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                showMessage('حدث خطأ أثناء إضافة المنتج إلى السلة.', 'danger');
            });
        });


        // Star Rating logic for review form
        const starRatingContainer = document.querySelector('.star-rating');
        if (starRatingContainer) {
            const stars = starRatingContainer.querySelectorAll('.fa-star');
            const ratingInput = document.getElementById('rating_input');

            stars.forEach(star => {
                star.addEventListener('mouseover', function() {
                    const value = parseInt(this.dataset.value);
                    stars.forEach((s, i) => {
                        if (i < value) {
                            s.classList.remove('far');
                            s.classList.add('fas');
                        } else {
                            s.classList.remove('fas');
                            s.classList.add('far');
                        }
                    });
                });

                star.addEventListener('click', function() {
                    const value = parseInt(this.dataset.value);
                    ratingInput.value = value;
                    starRatingContainer.dataset.rating = value; // Update the data-rating attribute
                    stars.forEach((s, i) => {
                        if (i < value) {
                            s.classList.remove('far');
                            s.classList.add('fas');
                        } else {
                            s.classList.remove('fas');
                            s.classList.add('far');
                        }
                    });
                });

                star.addEventListener('mouseout', function() {
                    const currentRating = parseInt(starRatingContainer.dataset.rating);
                    stars.forEach((s, i) => {
                        if (i < currentRating) {
                            s.classList.remove('far');
                            s.classList.add('fas');
                        } else {
                            s.classList.remove('fas');
                            s.classList.add('far');
                        }
                    });
                });
            });
        }


        // Reply button functionality
        document.querySelectorAll('.reply-btn').forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.dataset.reviewId;
                const replyForm = document.getElementById(`reply-form-${reviewId}`);
                if (replyForm) {
                    replyForm.style.display = replyForm.style.display === 'none' ? 'block' : 'none';
                }
            });
        });

        // Submit comment form (AJAX)
        document.querySelectorAll('.submit-comment-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const reviewId = this.querySelector('input[name="review_id"]').value;
                const commentText = this.querySelector('textarea[name="comment_text"]').value;
                const userId = <?php echo isset($_SESSION['user_id']) ? json_encode($_SESSION['user_id']) : 'null'; ?>;

                if (!userId) {
                    showMessage('الرجاء تسجيل الدخول لإضافة رد.', 'warning');
                    return;
                }

                if (!commentText.trim()) {
                    showMessage('الرجاء كتابة محتوى الرد.', 'warning');
                    return;
                }

                fetch('/NEW_IBB/actions/submit_comment.php', { // تأكد من المسار الصحيح لـ submit_comment.php
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ review_id: reviewId, user_id: userId, comment_text: commentText })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showMessage(data.message, 'success');
                        // إعادة تحميل الصفحة أو تحديث التعليقات ديناميكيًا
                        location.reload(); // أسهل حل حالياً
                    } else {
                        showMessage(data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error submitting comment:', error);
                    showMessage('حدث خطأ أثناء إرسال الرد.', 'danger');
                });
            });
        });


        // Like/Dislike button functionality (AJAX)
        document.querySelectorAll('.like-btn, .dislike-btn').forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.dataset.reviewId;
                const action = this.dataset.action; // 'like' or 'dislike'
                const userId = <?php echo isset($_SESSION['user_id']) ? json_encode($_SESSION['user_id']) : 'null'; ?>;

                if (!userId) {
                    showMessage('الرجاء تسجيل الدخول للتفاعل مع التقييمات.', 'warning');
                    return;
                }

                fetch('/NEW_IBB/actions/review_interaction.php', { // تأكد من المسار الصحيح لـ review_interaction.php
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ review_id: reviewId, user_id: userId, interaction_type: action })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showMessage(data.message, 'success');
                        // تحديث أعداد الإعجابات/عدم الإعجاب والأزرار ديناميكيًا
                        const reviewItem = document.getElementById(`review-${reviewId}`);
                        if (reviewItem) {
                            const likeCountSpan = reviewItem.querySelector('.like-count');
                            const dislikeCountSpan = reviewItem.querySelector('.dislike-count');
                            const likeBtn = reviewItem.querySelector('.like-btn');
                            const dislikeBtn = reviewItem.querySelector('.dislike-btn');

                            likeCountSpan.textContent = data.new_likes_count;
                            dislikeCountSpan.textContent = data.new_dislikes_count;

                            // تحديث حالة الأزرار
                            likeBtn.classList.remove('active-like');
                            dislikeBtn.classList.remove('active-dislike');
                            if (data.user_action === 'like') {
                                likeBtn.classList.add('active-like');
                            } else if (data.user_action === 'dislike') {
                                dislikeBtn.classList.add('active-dislike');
                            }
                        }
                    } else {
                        showMessage(data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error with review interaction:', error);
                    showMessage('حدث خطأ أثناء التفاعل مع التقييم.', 'danger');
                });
            });
        });
    </script>
</body>
</html>