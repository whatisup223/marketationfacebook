<?php
// migrations/007_add_sample_pricing.php
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDB();

try {
    // Check if there are already pricing plans
    $count = $pdo->query("SELECT COUNT(*) FROM pricing_plans")->fetchColumn();

    if ($count == 0) {
        // Add sample pricing plans
        $plans = [
            [
                'plan_name_ar' => 'الخطة الأساسية',
                'plan_name_en' => 'Basic Plan',
                'price' => 99.00,
                'currency_ar' => 'ريال',
                'currency_en' => 'SAR',
                'billing_period_ar' => 'شهرياً',
                'billing_period_en' => 'Monthly',
                'description_ar' => 'مثالية للأفراد والمشاريع الصغيرة',
                'description_en' => 'Perfect for individuals and small projects',
                'features' => "استخراج حتى 1000 بيانات شهرياً\nدعم فني عبر البريد الإلكتروني\nتحديثات مجانية\nلوحة تحكم بسيطة",
                'is_featured' => 0,
                'button_text_ar' => 'ابدأ الآن',
                'button_text_en' => 'Get Started',
                'button_url' => '#contact',
                'display_order' => 1
            ],
            [
                'plan_name_ar' => 'الخطة الاحترافية',
                'plan_name_en' => 'Professional Plan',
                'price' => 299.00,
                'currency_ar' => 'ريال',
                'currency_en' => 'SAR',
                'billing_period_ar' => 'شهرياً',
                'billing_period_en' => 'Monthly',
                'description_ar' => 'الأنسب للشركات المتوسطة',
                'description_en' => 'Best for medium-sized businesses',
                'features' => "استخراج حتى 10,000 بيانات شهرياً\nدعم فني على مدار الساعة\nتحليلات متقدمة\nتصدير بصيغ متعددة\nأولوية في المعالجة",
                'is_featured' => 1,
                'button_text_ar' => 'اشترك الآن',
                'button_text_en' => 'Subscribe Now',
                'button_url' => '#contact',
                'display_order' => 2
            ],
            [
                'plan_name_ar' => 'خطة المؤسسات',
                'plan_name_en' => 'Enterprise Plan',
                'price' => 999.00,
                'currency_ar' => 'ريال',
                'currency_en' => 'SAR',
                'billing_period_ar' => 'شهرياً',
                'billing_period_en' => 'Monthly',
                'description_ar' => 'حلول مخصصة للمؤسسات الكبرى',
                'description_en' => 'Custom solutions for large enterprises',
                'features' => "استخراج بيانات غير محدود\nمدير حساب مخصص\nتكامل API كامل\nتدريب مخصص للفريق\nSLA مضمون 99.9%\nتخصيص كامل",
                'is_featured' => 0,
                'button_text_ar' => 'تواصل معنا',
                'button_text_en' => 'Contact Us',
                'button_url' => '#contact',
                'display_order' => 3
            ]
        ];

        $stmt = $pdo->prepare("INSERT INTO pricing_plans (plan_name_ar, plan_name_en, price, currency_ar, currency_en, billing_period_ar, billing_period_en, description_ar, description_en, features, is_featured, button_text_ar, button_text_en, button_url, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($plans as $plan) {
            $stmt->execute([
                $plan['plan_name_ar'],
                $plan['plan_name_en'],
                $plan['price'],
                $plan['currency_ar'],
                $plan['currency_en'],
                $plan['billing_period_ar'],
                $plan['billing_period_en'],
                $plan['description_ar'],
                $plan['description_en'],
                $plan['features'],
                $plan['is_featured'],
                $plan['button_text_ar'],
                $plan['button_text_en'],
                $plan['button_url'],
                $plan['display_order']
            ]);
        }

        echo "✅ Sample pricing plans added successfully!\n";
    } else {
        echo "ℹ️  Pricing plans already exist. Skipping...\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
