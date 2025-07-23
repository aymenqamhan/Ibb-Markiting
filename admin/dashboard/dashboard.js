function changeMenu(section) {
    const menu = document.getElementById("menu");
    const userRoleElement = document.getElementById("user-role");

    if (!userRoleElement) {
        alert('لم يتم العثور على بيانات المستخدم.');
        return;
    }

    const roleId = parseInt(userRoleElement.getAttribute("data-role-id"), 10); //يتم تحويل الصلاحية إلى رقم لاستخدامها في التحقق.


    menu.innerHTML = ""; // تفريغ القائمة قبل التحديث

    // 🔐 إدارة المستخدمين
    if (section === "manage_users") {
        if ([1, 5].includes(roleId)) {// 1 = Admin, 5 = Super Admin
            menu.innerHTML = `
                <a href='/NEW_IBB/admin/file/user.php?show_user=true'><li class="d-flex align-items-center"><i class="fas fa-user me-2"></i> عرض المستخدمين</li></a>
                <a href='/NEW_IBB/admin/file/user.php?role_users=true'><li class="d-flex align-items-center"><i class="fas fa-lock me-2"></i> صلاحيات المستخدمين</li></a>
                <a href='/NEW_IBB/admin/file/Search_User.php'><li class="d-flex align-items-center"><i class="fas fa-search me-2"></i> بحث عن مستخدم</li></a>
                <a href='/NEW_IBB/admin/file/Show_All.php'><li class="d-flex align-items-center"><i class="fas fa-list-alt me-2"></i> عرض كل بيانات المستخدمين</li></a>
                <li onclick="goBackToMainMenu()" class="d-flex align-items-center"><i class="fas fa-arrow-left me-2"></i> العودة إلى القائمة الرئيسية</li>
            `;
        } else {
            alert('ليس لديك الصلاحية للوصول إلى إدارة المستخدمين.');
            goBackToMainMenu();
        }

    // 🧩 إدارة الأقسام
    } else if (section === "categories") {
        if ([1, 3, 5].includes(roleId)) {
            menu.innerHTML = `
                <a href='/NEW_IBB/admin/categories/list_categories.php'><li class="d-flex align-items-center"><i class="fas fa-folder-open me-2"></i> عرض الأقسام</li></a>
                <a href='/NEW_IBB/admin/categories/add_category.php'><li class="d-flex align-items-center"><i class="fas fa-plus-circle me-2"></i> إضافة قسم</li></a>
                <a href='/NEW_IBB/admin/categories/delete_category.php'><li class="d-flex align-items-center"><i class="fas fa-trash-alt me-2"></i> حذف قسم</li></a>
                <a href='/NEW_IBB/admin/categories/edit_category.php'><li class="d-flex align-items-center"><i class="fas fa-edit me-2"></i> تعديل قسم</li></a>
                <a href='/NEW_IBB/admin/categories/search_category.php'><li class="d-flex align-items-center"><i class="fas fa-search me-2"></i> بحث عن قسم</li></a>
                <li onclick="goBackToMainMenu()" class="d-flex align-items-center"><i class="fas fa-arrow-left me-2"></i> العودة إلى القائمة الرئيسية</li>
            `;
        } else {
            alert('ليس لديك الصلاحية للوصول إلى إدارة الأقسام.');
            goBackToMainMenu();
        }

    // 🛍️ إدارة المنتجات
    } else if (section === "products") {
        if ([1, 5].includes(roleId)) {
            menu.innerHTML = `
                <a href='/NEW_IBB/admin/products/list_products.php'><li class="d-flex align-items-center"><i class="fas fa-boxes me-2"></i> عرض المنتجات</li></a>
                <a href='/NEW_IBB/admin/products/add_product.php'><li class="d-flex align-items-center"><i class="fas fa-plus-square me-2"></i> إضافة منتج</li></a>
                <a href='/NEW_IBB/admin/products/search_product.php'><li class="d-flex align-items-center"><i class="fas fa-search me-2"></i> بحث عن منتج</li></a>
                <a href='/NEW_IBB/admin/products/edit_delete_product.php'><li class="d-flex align-items-center"><i class="fas fa-times-circle me-2"></i> اجراء علئ منتج</li></a>
                <a href='/NEW_IBB/admin/products/edit_product_prices.php'><li class="d-flex align-items-center"><i class="fas fa-dollar-sign me-2"></i> التحكم في أسعار المنتجات</li></a>
                <li onclick="goBackToMainMenu()" class="d-flex align-items-center"><i class="fas fa-arrow-left me-2"></i> العودة إلى القائمة الرئيسية</li>
            `;
        } else {
            alert('ليس لديك الصلاحية للوصول إلى إدارة المنتجات.');
            goBackToMainMenu();
        }

    // 📥 إدارة المشتريات
    } else if (section === "purchase_order") {
        if ([1, 5].includes(roleId)) {
            menu.innerHTML = `
                <a href='/NEW_IBB/admin/purchase_order/list_purchase_orders.php'><li class="d-flex align-items-center"><i class="fas fa-file-alt me-2"></i> عرض أوامر الشراء</li></a>
                <a href='/NEW_IBB/admin/purchase_order/add_purchase_order.php'><li class="d-flex align-items-center"><i class="fas fa-cart-plus me-2"></i> إضافة أمر شراء جديد</li></a>
                <a href='/NEW_IBB/admin/purchase_order/search_purchase_order.php'><li class="d-flex align-items-center"><i class="fas fa-search me-2"></i> بحث عن أمر شراء</li></a>
                <a href='/NEW_IBB/admin/invoices/add_purchase_invoice.php'><li class="d-flex align-items-center"><i class="fas fa-receipt me-2"></i> إضافة فاتورة شراء</li></a>
                <a href='/NEW_IBB/admin/invoices/list_invoices.php'><li class="d-flex align-items-center"><i class="fas fa-clipboard-list me-2"></i> عرض فواتير الشراء</li></a>
                <li onclick="goBackToMainMenu()" class="d-flex align-items-center"><i class="fas fa-arrow-left me-2"></i> العودة إلى القائمة الرئيسية</li>
            `;
        } else {
            alert('ليس لديك الصلاحية للوصول إلى إدارة المشتريات.');
            goBackToMainMenu();
        }

    // 📈 إدارة التقارير
    } else if (section === "Reports") {
        if ([1, 5].includes(roleId)) {
            menu.innerHTML = `
                <a href='/NEW_IBB/admin/Reports/sales_report.php'><li class="d-flex align-items-center"><i class="fas fa-chart-bar me-2"></i> تقرير المبيعات</li></a>
                <a href='/NEW_IBB/admin/Reports/purchase_reports.php'><li class="d-flex align-items-center"><i class="fas fa-file-invoice-dollar me-2"></i> تقرير المشتريات</li></a>
                <a href='/NEW_IBB/admin/Reports/inventory_report.php'><li class="d-flex align-items-center"><i class="fas fa-boxes me-2"></i> تقرير المخزون</li></a>
                <a href='/NEW_IBB/admin/Reports/user_activity_report.php'><li class="d-flex align-items-center"><i class="fas fa-user-clock me-2"></i> تقرير نشاط المستخدمين</li></a>
                <li onclick="goBackToMainMenu()" class="d-flex align-items-center"><i class="fas fa-arrow-left me-2"></i> العودة إلى القائمة الرئيسية</li>
            `;
        } else {
            alert('ليس لديك الصلاحية للوصول إلى التقارير والإحصائيات.');
            goBackToMainMenu();
        }

    // 🧑‍💼 إدارة الموردين
    } else if (section === "manage_supplier") {
        if ([1, 5].includes(roleId)) {
            menu.innerHTML = `
                <a href='/NEW_IBB/admin/supplier/list_supplier.php'><li class="d-flex align-items-center"><i class="fas fa-truck-loading me-2"></i> عرض الموردين</li></a>
                <a href='/NEW_IBB/admin/supplier/add_supplier.php'><li class="d-flex align-items-center"><i class="fas fa-user-plus me-2"></i> إضافة مورد جديد</li></a>
                <li onclick="goBackToMainMenu()" class="d-flex align-items-center"><i class="fas fa-arrow-left me-2"></i> العودة إلى القائمة الرئيسية</li>
            `;
        } else {
            alert('ليس لديك الصلاحية للوصول إلى إدارة الموردين.');
            goBackToMainMenu();
        }

    // 📦 إدارة المخزون
    } else if (section === "inventory") {
        if ([1, 5].includes(roleId)) {
            menu.innerHTML = `
                <a href='/NEW_IBB/admin/inventory/list_inventory.php'><li class="d-flex align-items-center"><i class="fas fa-warehouse me-2"></i> عرض المخزون</li></a>
                <li onclick="goBackToMainMenu()" class="d-flex align-items-center"><i class="fas fa-arrow-left me-2"></i> العودة إلى القائمة الرئيسية</li>
            `;
        } else {
            alert('ليس لديك الصلاحية للوصول إلى إدارة المخزون.');
            goBackToMainMenu();
        }

    // 💰 إدارة المحفظة
    } else if (section === "wallet_management") {
        if ([1, 5].includes(roleId)) {
            menu.innerHTML = `
                <a href='/NEW_IBB/admin/wallet_management/create_customer_wallet.php'><li class="d-flex align-items-center"><i class="fas fa-wallet me-2"></i> إنشاء محفظة للعميل</li></a>
                <a href='/NEW_IBB/admin/wallet_management/deposit_to_customer.php'><li class="d-flex align-items-center"><i class="fas fa-money-bill-wave me-2"></i> إيداع رصيد</li></a>
                <a href='/NEW_IBB/admin/wallet_management/view_customer_transactions.php'><li class="d-flex align-items-center"><i class="fas fa-exchange-alt me-2"></i> معاملات العميل</li></a>
                <li onclick="goBackToMainMenu()" class="d-flex align-items-center"><i class="fas fa-arrow-left me-2"></i> العودة إلى القائمة الرئيسية</li>
            `;
        } else {
            alert('ليس لديك الصلاحية للوصول إلى إدارة المحافظ.');
            goBackToMainMenu();
        }

    // 📝 إدارة الطلبات
    } else if (section === "order") {
        if ([1, 5].includes(roleId)) {
            menu.innerHTML = `
                <a href='/NEW_IBB/admin/Orders/list_orders.php'><li class="d-flex align-items-center"><i class="fas fa-clipboard-list me-2"></i> عرض الطلبات</li></a>
                <a href='/NEW_IBB/admin/orders/pending_orders.php'><li class="d-flex align-items-center"><i class="fas fa-hourglass-half me-2"></i> طلبات قيد الانتظار</li></a>
                <a href='/NEW_IBB/admin/orders/approved_orders.php'><li class="d-flex align-items-center"><i class="fas fa-check-circle me-2"></i> الطلبات المعتمدة</li></a>
                <a href='/NEW_IBB/admin/orders/canceled_orders.php'><li class="d-flex align-items-center"><i class="fas fa-times-circle me-2"></i> الطلبات الملغاة</li></a>
                <a href='/NEW_IBB/admin/orders/order_details.php'><li class="d-flex align-items-center"><i class="fas fa-info-circle me-2"></i> تفاصيل الطلب</li></a>
                <a href='/NEW_IBB/admin/orders/track_order.php'><li class="d-flex align-items-center"><i class="fas fa-shipping-fast me-2"></i> تتبع الشحن</li></a>
                <li onclick="goBackToMainMenu()" class="d-flex align-items-center"><i class="fas fa-arrow-left me-2"></i> العودة إلى القائمة الرئيسية</li>
            `;
        } else {
            alert('ليس لديك الصلاحية للوصول إلى إدارة الطلبات.');
            goBackToMainMenu();
        }

    // ⚙️ الإعدادات (تقييمات المنتجعات والإعدادات العامة)
    } else if (section === "settings") {
        if ([1, 5].includes(roleId)) {
            menu.innerHTML = `
                <a href='/NEW_IBB/admin/settings/resort_ratings.php'><li class="d-flex align-items-center"><i class="fas fa-star-half-alt me-2"></i> تقييمات المنتجعات</li></a>
                <a href='/NEW_IBB/admin/settings/general_settings.php'><li class="d-flex align-items-center"><i class="fas fa-cogs me-2"></i> الإعدادات العامة</li></a>
                <a href='/NEW_IBB/admin/settings/user_settings.php'><li class="d-flex align-items-center"><i class="fas fa-users-cog me-2"></i> إعدادات المستخدمين</li></a>
                <a href='/NEW_IBB/admin/settings/notification_settings.php'><li class="d-flex align-items-center"><i class="fas fa-bell me-2"></i> إعدادات الإشعارات</li></a>
                <li onclick="goBackToMainMenu()" class="d-flex align-items-center"><i class="fas fa-arrow-left me-2"></i> العودة إلى القائمة الرئيسية</li>
            `;
        } else {
            alert('ليس لديك الصلاحية للوصول إلى الإعدادات.');
            goBackToMainMenu();
        }

    // 📋 القائمة الرئيسية (الافتراضية)
    } else if (section === "main") {
        menu.innerHTML = `
                <li onclick="changeMenu('manage_users')" class="d-flex align-items-center"><i class="fas fa-users me-2"></i> إدارة المستخدمين</li>
                <li onclick="changeMenu('products')" class="d-flex align-items-center"><i class="fas fa-box me-2"></i> إدارة المنتجات</li>
                <li onclick="changeMenu('inventory')" class="d-flex align-items-center"><i class="fas fa-warehouse me-2"></i> إدارة المخزون</li>
                <li onclick="changeMenu('categories')" class="d-flex align-items-center"><i class="fas fa-folder me-2"></i> إدارة الأقسام</li>
                <li onclick="changeMenu('purchase_order')" class="d-flex align-items-center"><i class="fas fa-shopping-cart me-2"></i> إدارة المشتريات</li>
                <li onclick="changeMenu('manage_supplier')" class="d-flex align-items-center"><i class="fas fa-truck me-2"></i> إدارة الموردين</li>
                <li onclick="changeMenu('order')" class="d-flex align-items-center"><i class="fas fa-file-invoice me-2"></i> إدارة الطلبات</li>
                <li onclick="changeMenu('wallet_management')" class="d-flex align-items-center"><i class="fas fa-wallet me-2"></i> ادارة المحافظ الالكترونية</li>
                <li onclick="changeMenu('Reports')" class="d-flex align-items-center"><i class="fas fa-chart-line me-2"></i> التقارير والإحصائيات</li>
                <li onclick="loadSection('settings')" class="d-flex align-items-center"><i class="fas fa-star-half-alt me-2"></i> تقييمات المنتجعات</li>
                <li onclick="loadSection('settings')" class="d-flex align-items-center"><i class="fas fa-cogs me-2"></i> الإعدادات</li>
                <li onclick="logout()" class="d-flex align-items-center"><i class="fas fa-sign-out-alt me-2"></i> تسجيل الخروج</li>
                <li onclick="GoToMarket()" class="d-flex align-items-center"><i class="fas fa-store me-2"></i> المتجر</li>
        `;
    } else {
        goBackToMainMenu(); // fallback to main menu if unknown section
    }
}

// 🔁 العودة للقائمة الرئيسية
function goBackToMainMenu() {
    changeMenu('main');
}

// 🚪 تسجيل الخروج
function logout() {
    if (confirm("هل أنت متأكد من تسجيل الخروج؟")) {
        window.location.href = "/NEW_IBB/admin/login/login_user.php?logout=out";
    }
}

// 🛍️ الذهاب إلى المتجر
function GoToMarket() {
    if (confirm('هل تريد الذهاب إلى المتجر؟')) {
        window.location.href = "/NEW_IBB/index.php";
    }
}

// Ensure the main menu loads on page load
document.addEventListener('DOMContentLoaded', () => {
    changeMenu('main');
});