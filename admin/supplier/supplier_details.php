<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل المورد</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            text-align: right;
            color: #333;
        }

        .container {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 650px; /* عرض مناسب لصفحة التفاصيل */
        }

        h2 {
            color: #007bff;
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        h3 {
            color: #555;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 1px dashed #eee; /* خط منقط تحت العنوان الفرعي */
            padding-bottom: 5px;
        }

        p {
            margin-bottom: 10px;
            line-height: 1.6;
        }

        p strong {
            color: #007bff; /* لون مميز للعناوين الفرعية داخل الفقرات */
        }

        ul {
            list-style: none; /* إزالة النقاط الافتراضية للقائمة */
            padding: 0;
            margin-top: 10px;
        }

        ul li {
            background-color: #f0f8ff; /* خلفية خفيفة لعناصر القائمة */
            border: 1px solid #e0f0ff;
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between; /* توزيع المحتوى والكمية */
            align-items: center;
        }

        .back-link {
            display: inline-block;
            background-color: #6c757d; /* لون رمادي لزر الرجوع */
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 25px;
            transition: background-color 0.3s ease;
        }

        .back-link:hover {
            background-color: #5a6268;
        }

        /* رسائل الخطأ */
        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        require_once '../connect_DB.php'; // المسار الصحيح: ../ للعودة للخلف ثم connection/db_connect.php

        if (isset($_GET['id'])) {
            $id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT); // تنقية المدخلات

            // جلب معلومات المورد
            $stmt_supplier = $con->prepare("SELECT * FROM suppliers WHERE id = ?");
            if ($stmt_supplier === false) {
                die("<p class='error-message'>خطأ في إعداد استعلام جلب المورد: " . $con->error . "</p>");
            }
            $stmt_supplier->bind_param("i", $id);
            $stmt_supplier->execute();
            $result_supplier = $stmt_supplier->get_result();
            $supplier = $result_supplier->fetch_assoc();
            $stmt_supplier->close();

            if (!$supplier) {
                echo "<p class='error-message'>المورد غير موجود.</p>";
                $conn->close();
                exit();
            }

            echo "<h2>تفاصيل المورد: " . htmlspecialchars($supplier['contact_name']) . "</h2>";
            echo "<p><strong>البريد الإلكتروني:</strong> " . htmlspecialchars($supplier['contact_email']) . "</p>";
            echo "<p><strong>رقم الهاتف:</strong> " . htmlspecialchars($supplier['contact_phone']) . "</p>";
            echo "<p><strong>العنوان:</strong> " . htmlspecialchars($supplier['address']) . "</p>";
            echo "<p><strong>نوع المنتجات:</strong> " . htmlspecialchars($supplier['notes']) . "</p>";

            // // عدد الفواتير والإجمالي
            // $stmt_invoices = $conn->prepare("SELECT COUNT(id_invoice) AS total_invoices, SUM(total_amount) AS total_debt FROM invoices WHERE id_morad = ?");
            // if ($stmt_invoices === false) {
            //     die("<p class='error-message'>خطأ في إعداد استعلام ملخص الفواتير: " . $conn->error . "</p>");
            // }
            // $stmt_invoices->bind_param("i", $id);
            // $stmt_invoices->execute();
            // $result_invoices = $stmt_invoices->get_result();
            // $invoice_summary = $result_invoices->fetch_assoc();
            // $stmt_invoices->close();

            // echo "<h3>ملخص الفواتير:</h3>";
            // echo "<p><strong>عدد الفواتير الصادرة:</strong> " . htmlspecialchars($invoice_summary['total_invoices']) . "</p>";
            // echo "<p><strong>إجمالي المبالغ المستحقة (للفواتير المستلمة):</strong> " . htmlspecialchars(number_format($invoice_summary['total_debt'] ?? 0, 2)) . "</p>"; // استخدم ?? 0 للتعامل مع القيم الفارغة

            // // المنتجات المتبقية في المخزون الخاصة بهذا المورد
            // $stmt_inventory = $conn->prepare("SELECT product_name, quantity FROM inventory WHERE id_morad = ?");
            // if ($stmt_inventory === false) {
            //     die("<p class='error-message'>خطأ في إعداد استعلام جرد المخزون: " . $conn->error . "</p>");
            // }
            // $stmt_inventory->bind_param("i", $id);
            // $stmt_inventory->execute();
            // $result_inventory = $stmt_inventory->get_result();
            // $stmt_inventory->close();

            // echo "<h3>المنتجات المتبقية في المخزون من هذا المورد:</h3>";
            // if ($result_inventory->num_rows > 0) {
            //     echo "<ul>";
            //     while($item = $result_inventory->fetch_assoc()) {
            //         echo "<li>" . htmlspecialchars($item['product_name']) . " (الكمية: " . htmlspecialchars($item['quantity']) . ")</li>";
            //     }
            //     echo "</ul>";
            // } else {
            //     echo "<p>لا توجد منتجات متبقية في المخزون من هذا المورد حالياً.</p>";
            // }
            
            echo '<a href="./list_supplier.php" class="back-link">العودة إلى إدارة الموردين</a>';

        } else {
            echo "<p class='error-message'>معرف المورد غير محدد لعرض التفاصيل.</p>";
        }

        $con->close();
        ?>
    </div>
</body>
</html>