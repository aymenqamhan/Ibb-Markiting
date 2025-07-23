<?php
// add_purchase_invoice.php
session_start();
require_once '../connect_DB.php'; // تأكد من المسار الصحيح

// جلب الموردين
$suppliers = [];
$sql_suppliers = "SELECT id, name FROM suppliers ORDER BY name";
$result_suppliers = $con->query($sql_suppliers);
if ($result_suppliers && $result_suppliers->num_rows > 0) {
    while($row = $result_suppliers->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// جلب أوامر الشراء (يمكن تصفيتها لاحقاً حسب المورد أو الحالة)
$purchase_orders = [];
$sql_orders = "SELECT id, order_date, supplier_id FROM purchase_orders ORDER BY order_date DESC LIMIT 20";
$result_orders = $con->query($sql_orders);
if ($result_orders && $result_orders->num_rows > 0) {
    while($row = $result_orders->fetch_assoc()) {
        $purchase_orders[] = $row;
    }
}

// جلب جميع الأحجام المتاحة من جدول product_sizes (للعرض في الـ dropdown إذا لم يكن المنتج المحدد له حجم)
// هذا الجزء يمكن أن يصبح أقل أهمية إذا كان البحث يحدد الحجم مباشرة
$sizes = [];
$sql_sizes = "SELECT id, size FROM product_sizes ORDER BY size";
$result_sizes = $con->query($sql_sizes);
if ($result_sizes && $result_sizes->num_rows > 0) {
    while($row = $result_sizes->fetch_assoc()) {
        $sizes[] = $row;
    }
}

$current_user_id = null;
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
} else {
    echo "<script>alert('لم يتم تسجيل دخول المستخدم. يرجى تسجيل الدخول أولاً.'); window.location.href='../login/login.php';</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استلام فاتورة وإدخال للمخزون</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <style>
        /* CSS Styles - (No changes from previous version) */
        body { font-family: 'Arial', sans-serif; background-color: #f4f4f4; margin: 20px; direction: rtl; text-align: right; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 1000px; margin: 20px auto; }
        h2, h3 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
        .form-group label { flex: 0 0 120px; font-weight: bold; color: #555; }
        .form-group input[type="text"], .form-group input[type="date"], .form-group input[type="number"], .form-group select, .form-group textarea { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; min-width: 200px; }
        .form-group.full-width { flex-basis: 100%; }
        .form-group.full-width label { flex: none; }
        .form-group.full-width input, .form-group.full-width select, .form-group.full-width textarea { width: 100%; }
        .product-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .product-table th, .product-table td { border: 1px solid #ddd; padding: 10px; text-align: right; }
        .product-table th { background-color: #f2f2f2; color: #333; }
        .product-table input[type="text"], .product-table input[type="number"], .product-table select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .product-table input.full-width-input { width: calc(100% - 10px); min-width: 150px; }
        .product-table input[type="number"] { width: 80px; }
        .add-row-btn, .action-btn { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 20px; }
        .add-row-btn:hover, .action-btn:hover { background-color: #0056b3; }
        .remove-row-btn { background-color: #dc3545; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; }
        .remove-row-btn:hover { background-color: #c82333; }
        .submit-btn { background-color: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 18px; margin-top: 30px; width: auto; float: left; }
        .submit-btn:hover { background-color: #218838; }
        .search-container { display: flex; align-items: center; gap: 5px; }
        .search-container button { padding: 8px 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .search-container button:hover { background-color: #0056b3; }
        .search-results-display {
            background-color: #e9e9e9;
            padding: 5px 8px;
            border-radius: 4px;
            min-height: 20px;
            border: 1px solid #ccc;
            font-size: 0.9em;
            color: #444;
            margin-top: 5px;
            word-break: break-all;
        }
        /* Style for messages */
        #formMessage {
            margin-top: 20px;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
            display: none; /* Hidden by default */
        }
        #formMessage.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        #formMessage.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

    </style>
</head>
<body>
    <div class="container">
        <h2>استلام فاتورة وإدخال للمخزون</h2>

        <div id="formMessage"></div>

        <form id="purchaseInvoiceForm" method="POST" action="process_purchase_invoice.php">
            <h3>تفاصيل الفاتورة الرئيسية</h3>
            <div class="form-group">
                <label for="invoice_number">رقم الفاتورة:</label>
                <input type="text" id="invoice_number" name="invoice_number" required>
            </div>
            <div class="form-group">
                <label for="invoice_date">تاريخ الفاتورة:</label>
                <input type="date" id="invoice_date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label for="supplier_id">المورد:</label>
                <select id="supplier_id" name="supplier_id" >
                    <option value="">-- اختر مورد --</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="purchase_order_id">ربط بأمر شراء (اختياري):</label>
                <select id="purchase_order_id" name="purchase_order_id">
                    <option value="">-- لا يوجد أمر شراء --</option>
                    <?php foreach ($purchase_orders as $order): ?>
                        <option value="<?php echo $order['id']; ?>">
                            <?php echo htmlspecialchars($order['id'] . ' - ' . $order['order_date'] . ' (مورد: ' . $order['supplier_id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="total_amount">إجمالي مبلغ الفاتورة:</label>
                <input type="number" id="total_amount" name="total_amount" step="0.01" min="0" value="0.00" required readonly>
                </div>
            <div class="form-group full-width">
                <label for="notes">ملاحظات:</label>
                <textarea id="notes" name="notes" rows="3" style="width: 100%;"></textarea>
            </div>

            <input type="hidden" name="created_by_user_id" value="<?php echo $current_user_id; ?>">


            <h3>تفاصيل المنتجات في الفاتورة (لإدخال المخزون)</h3>
            <table class="product-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">البحث عن المنتج/SKU/الباركود</th>
                        <th style="width: 15%;">الحجم</th>
                        <th style="width: 20%;">اسم المنتج (للتأكيد)</th>
                        <th style="width: 10%;">الكمية</th>
                        <th style="width: 10%;">سعر الشراء</th>
                        <th style="width: 10%;">الإجمالي الجزئي</th>
                        <th style="width: 5%;">إجراء</th>
                    </tr>
                </thead>
                <tbody id="product_items_body">
                    <tr class="product-row">
                        <td>
                            <div class="search-container">
                                <input type="text" class="product-search-input full-width-input" placeholder="اسم/SKU/باركود" data-item-index="0">
                                <button type="button" class="search-product-btn" data-item-index="0">بحث</button>
                            </div>
                            <div class="search-results-display" id="searchResults_0"></div>
                            <input type="hidden" name="items[0][inventory_id]" class="item-inventory-id">
                            <input type="hidden" name="items[0][product_id]" class="item-product-id">
                            <input type="hidden" name="items[0][size_id]" class="item-size-id">
                            <input type="hidden" name="items[0][is_serial_tracked]" class="item-is-serial-tracked" value="0">
                        </td>
                        <td>
                            <select name="items[0][selected_size_id]" class="item-size-select" data-item-index="0" disabled>
                                <option value="">-- يتم التحديد تلقائياً --</option>
                                <?php foreach ($sizes as $size): ?>
                                    <option value="<?php echo $size['id']; ?>"><?php echo htmlspecialchars($size['size']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" class="item-size-display-text" readonly style="margin-top: 5px; background-color: #f0f0f0; border: 1px solid #ccc; padding: 5px;">
                        </td>
                        <td><input type="text" class="product-name-display full-width-input" readonly></td>
                        <td><input type="number" name="items[0][quantity_received]" class="item-quantity" min="1" value="1" required></td>
                        <td><input type="number" name="items[0][unit_cost]" class="item-unit-cost" step="0.01" min="0" value="0.00" required></td>
                        <td><input type="number" class="item-subtotal" step="0.01" min="0" value="0.00" readonly></td>
                        <td><button type="button" class="remove-row-btn">إزالة</button></td>
                    </tr>
                </tbody>
            </table>

            <button type="button" class="add-row-btn">إضافة منتج فاتورة آخر</button>

            <button type="submit" class="submit-btn">تسجيل الفاتورة وترحيل للمخزون</button>

        </form>
    </div>
    <button class="btn btn-secondary mt-3" onclick="window.location.href='../dashbord.php';">العودة للصفحة الرئيسية</button>

    <script>
        let itemIndex = 0; // لضمان معرفات فريدة لكل صف

        function addProductRow() {
            itemIndex++;
            const row = `
                <tr class="product-row">
                    <td>
                        <div class="search-container">
                            <input type="text" class="product-search-input full-width-input" placeholder="اسم/SKU/باركود" data-item-index="${itemIndex}">
                            <button type="button" class="search-product-btn" data-item-index="${itemIndex}">بحث</button>
                        </div>
                        <div class="search-results-display" id="searchResults_${itemIndex}"></div>
                        <input type="hidden" name="items[${itemIndex}][inventory_id]" class="item-inventory-id">
                        <input type="hidden" name="items[${itemIndex}][product_id]" class="item-product-id">
                        <input type="hidden" name="items[${itemIndex}][size_id]" class="item-size-id">
                        <input type="hidden" name="items[${itemIndex}][is_serial_tracked]" class="item-is-serial-tracked" value="0">
                    </td>
                    <td>
                        <select name="items[${itemIndex}][selected_size_id]" class="item-size-select" data-item-index="${itemIndex}" disabled>
                            <option value="">-- يتم التحديد تلقائياً --</option>
                            <?php foreach ($sizes as $size): ?>
                                <option value="<?php echo $size['id']; ?>"><?php echo htmlspecialchars($size['size']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" class="item-size-display-text" readonly style="margin-top: 5px; background-color: #f0f0f0; border: 1px solid #ccc; padding: 5px;">
                    </td>
                    <td><input type="text" class="product-name-display full-width-input" readonly></td>
                    <td><input type="number" name="items[${itemIndex}][quantity_received]" class="item-quantity" min="0" value="1" required></td>                    <td><input type="number" name="items[${itemIndex}][unit_cost]" class="item-unit-cost" step="0.01" min="0" value="0.00" required></td>
                    <td><input type="number" class="item-subtotal" step="0.01" min="0" value="0.00" readonly></td>
                    <td><button type="button" class="remove-row-btn">إزالة</button></td>
                </tr>
            `;
            $('#product_items_body').append(row);
            $(`input[data-item-index="${itemIndex}"]`).focus();
        }

        $(document).on('click', '.remove-row-btn', function() {
            $(this).closest('.product-row').remove();
            calculateSubtotal();
        });

        $('.add-row-btn').on('click', addProductRow);

        function calculateSubtotal() {
            let totalInvoiceAmount = 0;
            $('.product-row').each(function() {
                const quantity = parseFloat($(this).find('.item-quantity').val()) || 0;
                const unitCost = parseFloat($(this).find('.item-unit-cost').val()) || 0;
                const subtotal = quantity * unitCost;
                $(this).find('.item-subtotal').val(subtotal.toFixed(2));
                totalInvoiceAmount += subtotal;
            });
            $('#total_amount').val(totalInvoiceAmount.toFixed(2));
        }

        $(document).on('input', '.item-quantity, .item-unit-cost', calculateSubtotal);

        // -----------------------------------------------------
        // وظيفة البحث عند الضغط على الزر (محدثة قليلاً)
        // -----------------------------------------------------
        $(document).on('click', '.search-product-btn', function() {
            const itemIndex = $(this).data('item-index');
            const currentProductRow = $(this).closest('.product-row');
            const searchTerm = currentProductRow.find('.product-search-input').val().trim();
            const searchResultsDiv = $(`#searchResults_${itemIndex}`);

            // تنظيف الحقول المرتبطة بالمنتج الحالي قبل البحث الجديد
            resetProductRowFields(currentProductRow);
            searchResultsDiv.empty(); // مسح أي نتائج سابقة

            if (searchTerm.length < 2) {
                searchResultsDiv.html('<span style="color:red;">الرجاء إدخال حرفين على الأقل للبحث.</span>');
                return;
            }

            $.ajax({
                url: './search_products.php', // تأكد أن هذا هو المسار الصحيح لملف search_products.php
                method: 'GET',
                data: { term: searchTerm },
                dataType: 'json',
                success: function(data) {
                    if (data.length > 0) {
                        searchResultsDiv.empty();
                        if (data.length === 1) {
                            // إذا كانت نتيجة واحدة، اخترها تلقائياً
                            selectProduct(data[0], currentProductRow);
                            searchResultsDiv.html('تم اختيار: ' + data[0].display_text);
                        } else {
                            // إذا كانت هناك نتائج متعددة، اعرضها كأزرار لاختيار المستخدم
                            searchResultsDiv.html('<span>اختر من النتائج:</span>');
                            data.forEach(function(item) {
                                const resultItem = $('<button type="button" class="action-btn" style="margin-right: 5px; margin-bottom: 5px;">').text(item.display_text);
                                resultItem.data('item-data', item); // تخزين كائن البيانات بالكامل في زر
                                resultItem.on('click', function() {
                                    selectProduct($(this).data('item-data'), currentProductRow);
                                    searchResultsDiv.html('تم اختيار: ' + $(this).text()); // عرض اسم المنتج المختار
                                });
                                searchResultsDiv.append(resultItem);
                            });
                        }
                    } else {
                        searchResultsDiv.html('<span style="color:orange;">لا توجد نتائج مطابقة.</span>');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    searchResultsDiv.html('<span style="color:red;">حدث خطأ في البحث.</span>');
                    console.error("AJAX Error: " + textStatus, errorThrown, jqXHR.responseText); // عرض استجابة الخادم لمزيد من التصحيح
                }
            });
        });

        // دالة لإعادة تعيين حقول المنتج في الصف المحدد
        function resetProductRowFields(row) {
            row.find('.item-inventory-id').val('');
            row.find('.item-product-id').val('');
            row.find('.item-size-id').val(''); // مسح size_id
            row.find('.item-is-serial-tracked').val('0');
            row.find('.product-name-display').val('');
            row.find('.item-size-select').val('').prop('disabled', true); // إعادة المقاس إلى الخيار الافتراضي وتعطيله
            row.find('.item-size-display-text').val(''); // مسح حقل عرض اسم الحجم
            row.find('.item-quantity').val('1').prop('readonly', false);
            row.find('.item-unit-cost').val('0.00');
            calculateSubtotal();
        }

        // دالة لملء بيانات المنتج بعد اختياره (محدثة)
        function selectProduct(itemData, currentRow) {
            const inventoryIdInput = currentRow.find('.item-inventory-id');
            const productIdInput = currentRow.find('.item-product-id');
            const sizeIdInput = currentRow.find('.item-size-id');
            const isSerialTrackedInput = currentRow.find('.item-is-serial-tracked');
            const productNameDisplay = currentRow.find('.product-name-display');
            const itemSizeSelect = currentRow.find('.item-size-select');
            const itemSizeDisplayText = currentRow.find('.item-size-display-text'); // حقل نصي لعرض اسم الحجم
            const itemQuantityInput = currentRow.find('.item-quantity');
            const itemUnitCostInput = currentRow.find('.item-unit-cost');

            inventoryIdInput.val(itemData.inventory_id); // تعيين inventory_id
            productIdInput.val(itemData.product_id); // تعيين product_id
            sizeIdInput.val(itemData.size_id || ''); // تعيين size_id (سيكون null إذا لم يكن هناك حجم)
            isSerialTrackedInput.val(itemData.is_serial_tracked ? '1' : '0');

            productNameDisplay.val(itemData.product_name);
            itemUnitCostInput.val(itemData.cost_price || '0.00');

            // تحديث عرض الحجم
            if (itemData.size_id) {
                // إذا كان هناك حجم، نعرض اسمه ونجعل الـ select معطل
                itemSizeSelect.val(itemData.size_id).prop('disabled', true);
                itemSizeDisplayText.val(itemData.size_name || 'لا يوجد'); // عرض اسم الحجم
            } else {
                // إذا لم يكن هناك حجم، نجعل الـ select معطل ولا نعرض شيئاً في حقل الحجم
                itemSizeSelect.val('').prop('disabled', true);
                itemSizeDisplayText.val('لا يوجد');
            }


            if (itemData.is_serial_tracked) {
                itemQuantityInput.val(1).prop('readonly', true); // اجعل الكمية 1 وغير قابلة للتعديل
            } else {
                itemQuantityInput.val(1).prop('readonly', false);
            }
            calculateSubtotal();
        }

        // -------------------------------------------------------------------
        // تعديل على معالج إرسال النموذج لاستخدام AJAX وعرض الرسائل
        // -------------------------------------------------------------------
        $('#purchaseInvoiceForm').on('submit', function(e) {
            e.preventDefault(); // منع الإرسال الافتراضي للنموذج

            const form = $(this);
            const url = form.attr('action');
            const formData = form.serialize(); // جمع كل بيانات النموذج

            // إخفاء أي رسائل سابقة
            $('#formMessage').hide().removeClass('success error').text('');

            // التحقق من صحة النموذج (Client-Side Validation) قبل الإرسال
            let isValid = true;
            $('.product-row').each(function(index) {
                const productId = $(this).find('.item-product-id').val();
                const inventoryId = $(this).find('.item-inventory-id').val();
                const quantity = parseFloat($(this).find('.item-quantity').val());
                const unitCost = parseFloat($(this).find('.item-unit-cost').val());
                const isSerialTracked = $(this).find('.item-is-serial-tracked').val() === '1';

                $(this).css('border', 'none'); // Reset border for valid rows

                if (productId === '' || productId <= 0 || inventoryId === '' || inventoryId <= 0 || isNaN(quantity) || quantity <= 0 || isNaN(unitCost) || unitCost < 0) {
                    isValid = false;
                    $(this).css('border', '1px solid red');
                    // يمكن عرض رسالة خطأ محددة هنا إذا أردت، أو الاعتماد على الرسالة العامة
                    // alert('خطأ في الصف رقم ' + (index + 1) + ': يرجى التأكد من اختيار منتج صحيح، وتحديد الكمية، وتحديد سعر الشراء.');
                    return false; // يوقف حلقة each
                }

                if (isSerialTracked && quantity !== 1) {
                    isValid = false;
                    $(this).css('border', '1px solid red');
                    // alert('المنتج التسلسلي في الصف رقم ' + (index + 1) + ' يجب أن تكون كميته 1.');
                    return false; // يوقف حلقة each
                }
            });

            if (!isValid) {
                $('#formMessage').addClass('error').text('حدث خطأ في بيانات بعض بنود الفاتورة. يرجى مراجعة البنود المحاطة باللون الأحمر.');
                $('#formMessage').show();
                return; // منع إرسال AJAX
            }

            // إرسال البيانات باستخدام AJAX
            $.ajax({
                type: 'POST',
                url: url,
                data: formData,
                dataType: 'json', // نتوقع استجابة JSON من الخادم
                success: function(response) {
                    if (response.success) {
                        $('#formMessage').addClass('success').text(response.message);
                        // اختيارياً: إعادة تعيين النموذج بعد النجاح
                        $('#purchaseInvoiceForm')[0].reset();
                        $('#product_items_body').empty(); // إفراغ المنتجات
                        addProductRow(); // إضافة صف فارغ جديد
                        calculateSubtotal();
                    } else {
                        $('#formMessage').addClass('error').text(response.message);
                    }
                    $('#formMessage').show();
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $('#formMessage').addClass('error').text('حدث خطأ غير متوقع أثناء الاتصال بالخادم. يرجى المحاولة مرة أخرى.');
                    $('#formMessage').show();
                    console.error("AJAX Error: " + textStatus, errorThrown, jqXHR.responseText);
                }
            });
        });

        calculateSubtotal(); // Initial calculation
    </script>
</body>
</html>
<?php
$con->close();
?>