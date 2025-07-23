// تحميل الأقسام الفرعية بناءً على القسم الرئيسي
function loadSubcategories() {
    var categoryId = document.getElementById('category_id').value;
    var subcategorySelect = document.getElementById('subcategory_id');

    // إفراغ الاختيارات السابقة
    subcategorySelect.innerHTML = "<option value=''>اختر قسم فرعي</option>";

    // التحقق من وجود قسم رئيسي
    if (categoryId) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', './get_subcategories.php?category_id=' + categoryId, true);
        xhr.onload = function () {
            if (xhr.status === 200) {
                var subcategories = JSON.parse(xhr.responseText);
                subcategories.forEach(function (subcategory) {
                    var option = document.createElement('option');
                    option.value = subcategory.id;
                    option.textContent = subcategory.category_name;
                    subcategorySelect.appendChild(option);
                });
            }
        };
        xhr.send();
    }
}

// تحميل الأقسام التابعة بناءً على القسم الفرعي
function loadSubsubcategories() {
    var subcategoryId = document.getElementById('subcategory_id').value;
    var subsubcategorySelect = document.getElementById('subsubcategory_id');
localhost
    // إفراغ الاختيارات السابقة
    subsubcategorySelect.innerHTML = "<option value=''>اختر قسم تابع</option>";

    // التحقق من وجود قسم فرعي
    if (subcategoryId) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', './get_subsubcategories.php?subcategory_id=' + subcategoryId, true);
        xhr.onload = function () {
            if (xhr.status === 200) {
                var subsubcategories = JSON.parse(xhr.responseText);
                subsubcategories.forEach(function (subsubcategory) {
                    var option = document.createElement('option');
                    option.value = subsubcategory.id;
                    option.textContent = subsubcategory.category_name;
                    subsubcategorySelect.appendChild(option);
                });
            }
        };
        xhr.send();
    }
}