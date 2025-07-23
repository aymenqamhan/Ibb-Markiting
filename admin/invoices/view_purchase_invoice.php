<?php
// view_purchase_invoice.php
session_start();
include('../connect_DB.php'); // تأكد من المسار الصحيح لملف اتصال قاعدة البيانات

$invoice_id = $_GET['id'] ?? 0;

if ($invoice_id <= 0) {
    // إذا لم يتم تحديد معرف الفاتورة بشكل صحيح
    $_SESSION['error_message'] = "لم يتم تحديد فاتورة لعرض تفاصيلها.";
    header("Location: list_purchase_invoices.php");
    exit();
}

// 1. جلب بيانات الفاتورة الأساسية
// *********************************************************************************
// ملاحظة هامة: هذا الاستعلام لا يزال يتوقع وجود الأعمدة التالية في جداولها الخاصة:
// - purchase_invoices.updated_at
// - purchase_invoices.updated_by_user_id
// - suppliers.phone
// - suppliers.address
// إذا كانت أي من هذه الأعمدة غير موجودة لديك في قاعدة البيانات، ستظهر لك أخطاء جديدة.
// يرجى إضافة هذه الأعمدة أو إزالتها من الكود (كما شرحت لك في الردود السابقة).
// *********************************************************************************
$sql_invoice = "SELECT
                    inv.id AS invoice_id,
                    inv.invoice_number,
                    inv.invoice_date,
                    inv.total_amount,
                    inv.purchase_order_id,
                    inv.status AS invoice_status,
                    inv.notes,
                    inv.created_at,
                    inv.created_by_user_id,
                    inv.updated_at,
                    inv.updated_by_user_id,
                    s.contact_name AS supplier_name,
                    s.contact_phone AS supplier_phone,
                    s.address AS supplier_address,
                    u_created.name AS created_by_user_name,
                    u_updated.name AS updated_by_user_name
                FROM
                    purchase_invoices inv
                LEFT JOIN
                    suppliers s ON inv.supplier_id = s.id
                LEFT JOIN
                    user_tb u_created ON inv.created_by_user_id = u_created.id
                LEFT JOIN
                    user_tb u_updated ON inv.updated_by_user_id = u_updated.id
                WHERE inv.id = ?";

$stmt_invoice = $con->prepare($sql_invoice);
if ($stmt_invoice) {
    $stmt_invoice->bind_param("i", $invoice_id);
    $stmt_invoice->execute();
    $result_invoice = $stmt_invoice->get_result();
    $invoice_details = $result_invoice->fetch_assoc();
    $stmt_invoice->close();
} else {
    $_SESSION['error_message'] = "خطأ في تحضير استعلام الفاتورة: " . $con->error;
    header("Location: list_purchase_invoices.php");
    exit();
}

if (!$invoice_details) {
    $_SESSION['error_message'] = "الفاتورة المطلوبة غير موجودة.";
    header("Location: list_purchase_invoices.php");
    exit();
}

// 2. جلب تفاصيل عناصر الفاتورة - تم تعديل أسماء الأعمدة هنا
$sql_items = "SELECT
                  item.id AS item_id,
                  item.quantity_received, -- تم التعديل من quantity
                  item.unit_cost,         -- تم التعديل من price_per_unit
                  item.item_total,        -- تم التعديل من subtotal
                  p.name AS product_name  -- افترضنا وجود جدول products
              FROM
                  purchase_invoice_items item
              LEFT JOIN
                  products p ON item.product_id = p.id -- الربط مع جدول المنتجات
              WHERE item.invoice_id = ?";

$stmt_items = $con->prepare($sql_items);
if ($stmt_items) {
    $stmt_items->bind_param("i", $invoice_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $invoice_items = [];
    while ($row = $result_items->fetch_assoc()) {
        $invoice_items[] = $row;
    }
    $stmt_items->close();
} else {
    $_SESSION['error_message'] = "خطأ في تحضير استعلام عناصر الفاتورة: " . $con->error;
    header("Location: list_purchase_invoices.php");
    exit();
}

// دالة لمساعدتك في عرض الحالة بلون مختلف
function getStatusClass($status) {
    switch ($status) {
        case 'paid':
            return 'text-success';
        case 'pending':
            return 'text-warning';
        case 'received':
            return 'text-primary'; // لون أزرق لحالة تم الاستلام
        case 'cancelled':
            return 'text-danger';
        default:
            return '';
    }
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل فاتورة المشتريات: <?php echo htmlspecialchars($invoice_details['invoice_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container-fluid { padding: 20px; }
        .card { margin-bottom: 20px; box-shadow: 0 0 15px rgba(0,0,0,0.05); border: none; border-radius: 8px; }
        .card-header { background-color: #007bff; color: white; font-weight: bold; border-radius: 8px 8px 0 0; padding: 15px; }
        .detail-row {
            margin-bottom: 10px;
        }
        .detail-row strong {
            display: inline-block;
            width: 120px; /* لتنسيق المحاذاة */
        }
        .table-items th, .table-items td {
            vertical-align: middle;
        }
    </style>
</head>
<body>

    <div class="container-fluid">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                تفاصيل فاتورة المشتريات: #<?php echo htmlspecialchars($invoice_details['invoice_number']); ?>
                <div class="float-end">
                    <a href="edit_purchase_invoice.php?id=<?php echo htmlspecialchars($invoice_details['invoice_id']); ?>" class="btn btn-warning btn-sm me-2"><i class="fas fa-edit"></i> تعديل الفاتورة</a>
                    <a href="./list_invoices.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> العودة لقائمة الفواتير</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3">معلومات الفاتورة</h5>
                        <div class="detail-row">
                            <strong>رقم الفاتورة:</strong>
                            <span><?php echo htmlspecialchars($invoice_details['invoice_number']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>تاريخ الفاتورة:</strong>
                            <span><?php echo date('Y-m-d H:i', strtotime($invoice_details['invoice_date'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>إجمالي المبلغ:</strong>
                            <span><?php echo number_format($invoice_details['total_amount'], 2); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>رقم أمر الشراء:</strong>
                            <span><?php echo htmlspecialchars($invoice_details['purchase_order_id'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>الحالة:</strong>
                            <span class="<?php echo getStatusClass($invoice_details['invoice_status']); ?>">
                                <?php echo htmlspecialchars($invoice_details['invoice_status']); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <strong>ملاحظات:</strong>
                            <span><?php echo nl2br(htmlspecialchars($invoice_details['notes'] ?? 'لا توجد ملاحظات')); ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="mb-3">معلومات المورد</h5>
                        <div class="detail-row">
                            <strong>اسم المورد:</strong>
                            <span><?php echo htmlspecialchars($invoice_details['supplier_name'] ?? 'غير محدد'); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>هاتف المورد:</strong>
                            <span><?php echo htmlspecialchars($invoice_details['supplier_phone'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>عنوان المورد:</strong>
                            <span><?php echo nl2br(htmlspecialchars($invoice_details['supplier_address'] ?? 'N/A')); ?></span>
                        </div>

                        <h5 class="mt-4 mb-3">تفاصيل الإنشاء والتحديث</h5>
                        <div class="detail-row">
                            <strong>تاريخ الإنشاء:</strong>
                            <span><?php echo date('Y-m-d H:i', strtotime($invoice_details['created_at'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>بواسطة:</strong>
                            <span><?php echo htmlspecialchars($invoice_details['created_by_user_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>آخر تحديث:</strong>
                            <span><?php echo ($invoice_details['updated_at'] ? date('Y-m-d H:i', strtotime($invoice_details['updated_at'])) : 'لم يتم التحديث'); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>بواسطة:</strong>
                            <span><?php echo htmlspecialchars($invoice_details['updated_by_user_name'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3">المنتجات في الفاتورة</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-items">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المنتج</th>
                                <th>الكمية المستلمة</th> <th>تكلفة الوحدة</th> <th>إجمالي البند</th> </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($invoice_items)): ?>
                                <?php $item_counter = 1; ?>
                                <?php foreach ($invoice_items as $item): ?>
                                    <tr>
                                        <td><?php echo $item_counter++; ?></td>
                                        <td><?php echo htmlspecialchars($item['product_name'] ?? 'منتج غير معروف'); ?></td>
                                        <td><?php echo htmlspecialchars($item['quantity_received']); ?></td> <td><?php echo number_format($item['unit_cost'], 2); ?></td>         <td><?php echo number_format($item['item_total'], 2); ?></td>        </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-3">لا توجد منتجات مرتبطة بهذه الفاتورة.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZdfFk2U5fdg/NgfABwwbYqFDhbuIf7gof+6mojHujR4WpRcPRRSLn5W5eH" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js" integrity="sha384-..." crossorigin="anonymous"></script>
</body>
</html>

<?php
$con->close();
?>