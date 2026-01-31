<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            [
                'question_en' => 'How do I place an order?',
                'question_ar' => 'كيف أقوم بعمل طلب؟',
                'answer_en' => 'You can place an order by browsing our products, adding items to your cart, and proceeding to checkout. Follow the steps to enter your delivery address and payment method.',
                'answer_ar' => 'يمكنك تقديم طلب من خلال تصفح منتجاتنا وإضافة العناصر إلى سلة التسوق الخاصة بك ثم المتابعة إلى الدفع. اتبع الخطوات لإدخال عنوان التوصيل وطريقة الدفع.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'question_en' => 'What are the delivery hours?',
                'question_ar' => 'ما هي ساعات التوصيل؟',
                'answer_en' => 'Our delivery service operates from 10:00 AM to 10:00 PM, 7 days a week. You can select your preferred delivery time slot during checkout.',
                'answer_ar' => 'تعمل خدمة التوصيل لدينا من الساعة 10:00 صباحاً حتى 10:00 مساءً، 7 أيام في الأسبوع. يمكنك اختيار وقت التوصيل المفضل لديك أثناء الدفع.',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'question_en' => 'What payment methods do you accept?',
                'question_ar' => 'ما هي طرق الدفع المقبولة؟',
                'answer_en' => 'We accept cash on delivery, credit/debit cards, and online payment through our secure payment gateway.',
                'answer_ar' => 'نقبل الدفع نقداً عند الاستلام، وبطاقات الائتمان/الخصم، والدفع الإلكتروني من خلال بوابة الدفع الآمنة لدينا.',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'question_en' => 'How can I track my order?',
                'question_ar' => 'كيف يمكنني تتبع طلبي؟',
                'answer_en' => 'Once your order is confirmed, you can track its status in the "My Orders" section of the app. You will also receive notifications about your order status.',
                'answer_ar' => 'بمجرد تأكيد طلبك، يمكنك تتبع حالته في قسم "طلباتي" في التطبيق. ستتلقى أيضاً إشعارات حول حالة طلبك.',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'question_en' => 'What is your return policy?',
                'question_ar' => 'ما هي سياسة الإرجاع لديكم؟',
                'answer_en' => 'If you are not satisfied with your order, please contact our customer support within 24 hours of delivery. We will review your request and process refunds or replacements as appropriate.',
                'answer_ar' => 'إذا لم تكن راضياً عن طلبك، يرجى الاتصال بدعم العملاء لدينا خلال 24 ساعة من التوصيل. سنراجع طلبك ونعالج المبالغ المستردة أو البدائل حسب الاقتضاء.',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'question_en' => 'How do I contact customer support?',
                'question_ar' => 'كيف أتواصل مع دعم العملاء؟',
                'answer_en' => 'You can reach our customer support team through the app, by email, or by calling our hotline. Our support team is available during business hours to assist you.',
                'answer_ar' => 'يمكنك التواصل مع فريق دعم العملاء لدينا من خلال التطبيق أو البريد الإلكتروني أو الاتصال بخطنا الساخن. فريق الدعم لدينا متاح خلال ساعات العمل لمساعدتك.',
                'sort_order' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question_en' => $faq['question_en']],
                $faq
            );
        }
    }
}
