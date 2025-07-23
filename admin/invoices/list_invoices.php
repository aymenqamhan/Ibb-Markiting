    <?php
    // list_purchase_invoices.php
    session_start();
    include('../connect_DB.php'); // تأكد من المسار الصحيح لملف اتصال قاعدة البيانات

    // متغيرات البحث والتصفية الافتراضية
    $search_invoice_number = $_GET['search_invoice_number'] ?? '';
    $search_supplier_name = $_GET['search_supplier_name'] ?? '';
    $invoice_status = $_GET['invoice_status'] ?? 'all'; // 'all', 'pending', 'received', 'paid', 'cancelled'
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';

    // بناء الاستعلام الأساسي لجلب بيانات فواتير المشتريات
    $sql = "SELECT
                inv.id AS invoice_id,
                inv.invoice_number,
                inv.invoice_date,
                inv.total_amount,
                inv.status AS invoice_status,
                inv.notes,
                inv.created_at,
                inv.purchase_order_id,
                s.name AS supplier_name,
                u_created.name AS created_by_user_name
            FROM
                purchase_invoices inv
            LEFT JOIN
                suppliers s ON inv.supplier_id = s.id
            LEFT JOIN
                user_tb u_created ON inv.created_by_user_id = u_created.id
            WHERE 1=1";

    // تطبيق فلاتر البحث والتصفية
    if (!empty($search_invoice_number)) {
        $search_invoice_number = $con->real_escape_string($search_invoice_number);
        $sql .= " AND inv.invoice_number LIKE '%{$search_invoice_number}%'";
    }

    if (!empty($search_supplier_name)) {
        $search_supplier_name = $con->real_escape_string($search_supplier_name);
        $sql .= " AND s.name LIKE '%{$search_supplier_name}%'";
    }

    if ($invoice_status != 'all') {
        $invoice_status = $con->real_escape_string($invoice_status);
        $sql .= " AND inv.status = '{$invoice_status}'";
    }

    if (!empty($start_date)) {
        $start_date = $con->real_escape_string($start_date);
        $sql .= " AND DATE(inv.invoice_date) >= '{$start_date}'";
    }

    if (!empty($end_date)) {
        $end_date = $con->real_escape_string($end_date);
        $sql .= " AND DATE(inv.invoice_date) <= '{$end_date}'";
    }

    $sql .= " ORDER BY inv.invoice_date DESC"; // ترتيب الفواتير حسب التاريخ الأحدث

    $result = $con->query($sql);

    ?>

    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>إدارة فواتير المشتريات</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <style>
            body { background-color: #f8f9fa; }
            .container-fluid { padding: 20px; }
            .card { margin-bottom: 20px; box-shadow: 0 0 15px rgba(0,0,0,0.05); border: none; border-radius: 8px; }
            .card-header { background-color: #007bff; color: white; font-weight: bold; border-radius: 8px 8px 0 0; padding: 15px; }
            .table-responsive { margin-top: 20px; }
            th, td { vertical-align: middle; }
            .filter-header { cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
            .filter-header i { transition: transform 0.3s ease; }
            .filter-header[aria-expanded="true"] i { transform: rotate(180deg); }
            /* ألوان حالات الفاتورة (يمكن تعديلها حسب الحاجة) */
            .status-paid { color: green; font-weight: bold; }
            .status-pending { color: orange; font-weight: bold; }
            .status-received { color: blue; font-weight: bold; } /* حالة جديدة للمشتريات */
            .status-cancelled { color: red; font-weight: bold; }
        </style>
    </head>
    <body>

        <div class="container-fluid">

            <div class="card">
                <div class="card-header filter-header" data-bs-toggle="collapse" data-bs-target="#searchFilters" aria-expanded="true" aria-controls="searchFilters">
                    فلاتر البحث عن فواتير المشتريات
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div id="searchFilters" class="collapse show">
                    <div class="card-body">
                        <form action="" method="GET">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="search_invoice_number" class="form-label">رقم فاتورة المورد</label>
                                    <input type="text" class="form-control" id="search_invoice_number" name="search_invoice_number" placeholder="رقم الفاتورة" value="<?php echo htmlspecialchars($search_invoice_number); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="search_supplier_name" class="form-label">اسم المورد</label>
                                    <input type="text" class="form-control" id="search_supplier_name" name="search_supplier_name" placeholder="اسم المورد" value="<?php echo htmlspecialchars($search_supplier_name); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="invoice_status" class="form-label">حالة الفاتورة</label>
                                    <select class="form-select" id="invoice_status" name="invoice_status">
                                        <option value="all" <?php echo ($invoice_status == 'all') ? 'selected' : ''; ?>>الكل</option>
                                        <option value="pending" <?php echo ($invoice_status == 'pending') ? 'selected' : ''; ?>>معلقة</option>
                                        <option value="received" <?php echo ($invoice_status == 'received') ? 'selected' : ''; ?>>تم الاستلام</option>
                                        <option value="paid" <?php echo ($invoice_status == 'paid') ? 'selected' : ''; ?>>مدفوعة</option>
                                        <option value="cancelled" <?php echo ($invoice_status == 'cancelled') ? 'selected' : ''; ?>>ملغاة</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label">من تاريخ</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="end_date" class="form-label">إلى تاريخ</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                                </div>
                                <div class="col-md-3 d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> تطبيق الفلتر
                                    </button>
                                </div>
                                <div class="col-md-3 d-grid">
                                    <a href="list_purchase_invoices.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-sync-alt"></i> مسح الفلاتر
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    قائمة فواتير المشتريات
                    <button class="btn btn-success btn-sm float-end" onclick="window.location.href='create_purchase_invoice.php'">
                        <i class="fas fa-plus"></i> إنشاء فاتورة مشتريات جديدة
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>رقم الفاتورة</th>
                                    <th>المورد</th>
                                    <th>التاريخ</th>
                                    <th>إجمالي المبلغ</th>
                                    <th>رقم أمر الشراء</th>
                                    <th>الحالة</th>
                                    <th>ملاحظات</th>
                                    <th>تاريخ الإنشاء</th>
                                    <th>من أنشأ</th>
                                    <th>من حدث</th> <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result->num_rows > 0) {
                                    $counter = 1;
                                    while ($row = $result->fetch_assoc()) {
                                        $status_class = '';
                                        switch ($row['invoice_status']) {
                                            case 'paid':
                                                $status_class = 'status-paid';
                                                break;
                                            case 'pending':
                                                $status_class = 'status-pending';
                                                break;
                                            case 'received':
                                                $status_class = 'status-received';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'status-cancelled';
                                                break;
                                        }
                                        echo "<tr>";
                                        echo "<td>" . $counter++ . "</td>";
                                        echo "<td>" . htmlspecialchars($row['invoice_number']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['supplier_name'] ?? 'N/A') . "</td>";
                                        echo "<td>" . date('Y-m-d H:i', strtotime($row['invoice_date'])) . "</td>";
                                        echo "<td>" . number_format($row['total_amount'], 2) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['purchase_order_id'] ?? 'N/A') . "</td>";
                                        echo "<td class='{$status_class}'>" . htmlspecialchars($row['invoice_status']) . "</td>";
                                        echo "<td>" . htmlspecialchars(substr($row['notes'], 0, 50)) . (strlen($row['notes']) > 50 ? '...' : '') . "</td>"; // عرض جزء من الملاحظات
                                        echo "<td>" . date('Y-m-d H:i', strtotime($row['created_at'])) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['created_by_user_name'] ?? 'N/A') . "</td>";
                                        // تأكد من وجود عمود updated_at في جدول الفواتير
                                        echo "<td>" . htmlspecialchars($row['updated_by_user_name'] ?? 'N/A') . "</td>";
                                        echo "<td>";
                                        echo "<a href='view_purchase_invoice.php?id=" . $row['invoice_id'] . "' class='btn btn-sm btn-info me-1' title='عرض التفاصيل'><i class='fas fa-eye'></i></a>";
                                        echo "<a href='edit_purchase_invoice.php?id=" . $row['invoice_id'] . "' class='btn btn-sm btn-primary me-1' title='تعديل الفاتورة'><i class='fas fa-edit'></i></a>";
                                        echo "<a href='./delete_purchase_invoice.php?id=" . $row['invoice_id'] . "' class='btn btn-sm btn-danger' title='حذف الفاتورة' onclick=\"return confirm('هل أنت متأكد أنك تريد حذف هذه الفاتورة؟');\"><i class='fas fa-trash'></i></a>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='12' class='text-center py-4'><i class='fas fa-receipt me-2'></i> لا توجد فواتير مشتريات لعرضها.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="../dashbord.php" class="btn btn-secondary">العودة للادارة </a>
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
    // إغلاق الاتصال بقاعدة البيانات في نهاية الصفحة
    $con->close();
    ?>