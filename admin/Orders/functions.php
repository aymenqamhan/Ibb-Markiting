<?php

function getStatusArabic($status) {
    switch (strtolower($status)) {
        case 'pending':
            return 'قيد الانتظار';
        case 'approved':
            return 'تمت الموافقة';
        case 'shipped':
            return 'تم الشحن';
        case 'delivered':
            return 'تم التوصيل';
        case 'canceled':
            return 'ملغى';
        default:
            return $status;
    }
}
?>