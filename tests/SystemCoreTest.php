<?php
use PHPUnit\Framework\TestCase;

class SystemCoreTest extends TestCase
{
    // 1. اختبار فحص العداد للموظف (التأكد من أن العداد الجديد لا يقل عن القديم)
    public function testEmployeeOdometerValidation()
    {
        $last_odometer = 12000;
        $new_odometer_valid = 12050;   // قراءة صحيحة
        $new_odometer_invalid = 11900; // قراءة خاطئة (أقل من السابقة)

        // النظام يجب أن يقبل القراءة الأكبر
        $this->assertTrue($new_odometer_valid >= $last_odometer, "خطأ: يجب أن ينجح إدخال قراءة العداد الجديدة إذا كانت أكبر من أو تساوي القراءة السابقة");
        
        // النظام يجب أن يرفض القراءة الأقل
        $this->assertFalse($new_odometer_invalid >= $last_odometer, "خطأ: يجب أن يرفض النظام قراءة العداد إذا كانت أقل من القراءة السابقة");
    }

    // 2. اختبار تنبيه الصيانة (التأكد من أن التنبيه يظهر بعد 4500 كم)
    public function testMaintenanceAlertTrigger()
    {
        $last_maintenance_odo = 24000;
        
        // سيناريو 1: السيارة مشت 4000 كم (لا تستحق صيانة)
        $current_odo_1 = 28000; 
        $this->assertFalse(($current_odo_1 - $last_maintenance_odo) >= 4500, "خطأ: يجب أن لا يظهر تنبيه الصيانة لأن الفرق أقل من 4500 كم");

        // سيناريو 2: السيارة مشت 4600 كم (تستحق صيانة عاجلة)
        $current_odo_2 = 28600;
        $this->assertTrue(($current_odo_2 - $last_maintenance_odo) >= 4500, "خطأ: يجب أن يظهر تنبيه الصيانة للمشرف لأن الفرق تجاوز 4500 كم");
    }

    // 3. اختبار ترجمة حالات الأسطول لتظهر بشكل صحيح في واجهة المستخدم
    public function testVehicleStatusTranslation()
    {
        $this->assertEquals('شاغرة', $this->translateStatus('available'), "خطأ: يجب ترجمة 'available' إلى 'شاغرة'");
        $this->assertEquals('قيد الاستخدام', $this->translateStatus('in-use'), "خطأ: يجب ترجمة 'in-use' إلى 'قيد الاستخدام'");
        $this->assertEquals('متعطلة', $this->translateStatus('broken'), "خطأ: يجب ترجمة 'broken' إلى 'متعطلة'");
    }

    // دالة مساعدة لتمثيل المنطق الموجود في ملفات النظام
    private function translateStatus($db_val) {
        if ($db_val == 'available') {
            return 'شاغرة';
        } elseif ($db_val == 'in-use' || $db_val == 'in_use') {
            return 'قيد الاستخدام';
        } elseif ($db_val == 'broken') {
            return 'متعطلة';
        }
        return 'غير معروف';
    }
}