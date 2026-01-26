<?php

function wrapEmailLayout($content)
{
    $site_name = getSetting('site_name_en') ?: 'أوفا كاش';
    $site_logo = getSetting('site_logo');
    $site_url = getSetting('site_url');
    $logo_html = $site_logo ? "<img src='$site_url/uploads/$site_logo' alt='$site_name' style='height: 50px;'>" : "<h2 style='color: #fff; margin:0; font-size: 28px;'>$site_name</h2>";

    return "
    <!DOCTYPE html>
    <html dir='auto'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');
            
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body { 
                font-family: 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                margin: 0; 
                padding: 20px; 
                line-height: 1.6;
            }
            
            .email-wrapper {
                max-width: 600px;
                margin: 0 auto;
                background: rgba(15, 23, 42, 0.95);
                border-radius: 24px;
                overflow: hidden;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
                border: 1px solid rgba(99, 102, 241, 0.2);
            }
            
            .header {
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                padding: 40px 30px;
                text-align: center;
                position: relative;
                overflow: hidden;
            }
            
            .header::before {
                content: '';
                position: absolute;
                top: -50%;
                right: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                animation: pulse 3s ease-in-out infinite;
            }
            
            @keyframes pulse {
                0%, 100% { transform: scale(1); opacity: 0.5; }
                50% { transform: scale(1.1); opacity: 0.8; }
            }
            
            .content {
                padding: 40px 30px;
                color: #e2e8f0;
                background: rgba(30, 41, 59, 0.5);
            }
            
            h1, h2 {
                color: #ffffff;
                margin-bottom: 20px;
                font-weight: 700;
            }
            
            h1 { font-size: 28px; }
            h2 { font-size: 24px; }
            
            p {
                color: #cbd5e1;
                margin-bottom: 15px;
                font-size: 15px;
            }
            
            .glass-card {
                background: rgba(51, 65, 85, 0.4);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(148, 163, 184, 0.1);
                border-radius: 16px;
                padding: 24px;
                margin: 24px 0;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            }
            
            .info-grid {
                display: grid;
                gap: 16px;
                margin: 20px 0;
            }
            
            .info-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 16px;
                background: rgba(30, 41, 59, 0.5);
                border-radius: 12px;
                border-left: 3px solid #6366f1;
            }
            
            .info-label {
                color: #94a3b8;
                font-size: 14px;
                font-weight: 600;
            }
            
            .info-value {
                color: #ffffff;
                font-size: 15px;
                font-weight: 700;
                text-align: right;
            }
            
            .amount-display {
                background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(124, 58, 237, 0.2) 100%);
                border: 1px solid rgba(99, 102, 241, 0.3);
                border-radius: 16px;
                padding: 20px;
                text-align: center;
                margin: 20px 0;
            }
            
            .amount-label {
                color: #94a3b8;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 8px;
            }
            
            .amount-value {
                color: #ffffff;
                font-size: 32px;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
            
            .currency-badge {
                background: rgba(99, 102, 241, 0.3);
                color: #a5b4fc;
                padding: 6px 12px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
            }
            
            .status-badge {
                display: inline-block;
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .status-pending {
                background: rgba(251, 191, 36, 0.2);
                color: #fbbf24;
                border: 1px solid rgba(251, 191, 36, 0.3);
            }
            
            .status-completed {
                background: rgba(34, 197, 94, 0.2);
                color: #22c55e;
                border: 1px solid rgba(34, 197, 94, 0.3);
            }
            
            .status-cancelled {
                background: rgba(239, 68, 68, 0.2);
                color: #ef4444;
                border: 1px solid rgba(239, 68, 68, 0.3);
            }
            
            .btn {
                display: inline-block;
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                color: #ffffff !important;
                text-decoration: none;
                padding: 14px 32px;
                border-radius: 12px;
                font-weight: 700;
                font-size: 15px;
                margin: 20px 0;
                box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
                transition: all 0.3s ease;
            }
            
            .btn:hover {
                box-shadow: 0 6px 24px rgba(99, 102, 241, 0.6);
                transform: translateY(-2px);
            }
            
            .payment-method-card {
                background: rgba(30, 41, 59, 0.6);
                border: 1px solid rgba(99, 102, 241, 0.2);
                border-radius: 12px;
                padding: 16px;
                margin: 12px 0;
            }
            
            .payment-method-title {
                color: #6366f1;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 8px;
                font-weight: 600;
            }
            
            .payment-method-name {
                color: #ffffff;
                font-size: 16px;
                font-weight: 700;
            }
            
            .divider {
                height: 1px;
                background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.3), transparent);
                margin: 24px 0;
            }
            
            .footer {
                background: rgba(15, 23, 42, 0.8);
                padding: 30px;
                text-align: center;
                color: #64748b;
                font-size: 13px;
                border-top: 1px solid rgba(99, 102, 241, 0.1);
            }
            
            .footer p {
                color: #64748b;
                margin: 8px 0;
            }
            
            .highlight {
                color: #6366f1;
                font-weight: 700;
            }
            
            @media only screen and (max-width: 600px) {
                .email-wrapper { border-radius: 0; }
                .content, .header, .footer { padding: 20px; }
                .amount-value { font-size: 24px; }
            }
        </style>
    </head>
    <body>
        <div class='email-wrapper'>
            <div class='header'>
                $logo_html
            </div>
            <div class='content'>
                $content
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " <strong>$site_name</strong>. جميع الحقوق محفوظة.</p>
                <p style='margin-top: 12px;'>منصة تبادل العملات الآمنة والموثوقة</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function getEmailTemplate($type, $data)
{
    $site_name = getSetting('site_name_en') ?: 'أوفا كاش';
    $site_url = getSetting('site_url');

    $template = ['subject' => '', 'body' => ''];

    switch ($type) {
        case 'welcome_user':
            $template['subject'] = "مرحباً بك في $site_name - تم التسجيل بنجاح";
            $template['body'] = "
                <h1>مرحباً، {$data['name']}! 🎉</h1>
                <p>شكراً لانضمامك إلى <strong class='highlight'>$site_name</strong>. نحن سعداء بوجودك معنا.</p>
                
                <div class='glass-card'>
                    <p>يمكنك الآن تسجيل الدخول إلى لوحة التحكم الخاصة بك لبدء تبادل العملات بشكل فوري وآمن.</p>
                </div>
                
                <center><a href='{$data['login_url']}' class='btn'>تسجيل الدخول إلى لوحة التحكم</a></center>
                
                <p style='margin-top: 24px; color: #94a3b8; font-size: 14px;'>إذا كان لديك أي استفسار، لا تتردد في التواصل معنا.</p>
            ";
            break;

        case 'new_user_admin':
            $template['subject'] = "[إدارة] تسجيل مستخدم جديد: {$data['name']}";
            $template['body'] = "
                <h2>📋 تسجيل مستخدم جديد</h2>
                <p>تم تسجيل مستخدم جديد على المنصة.</p>
                
                <div class='glass-card'>
                    <div class='info-grid'>
                        <div class='info-row'>
                            <span class='info-label'>الاسم</span>
                            <span class='info-value'>{$data['name']}</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>البريد الإلكتروني</span>
                            <span class='info-value'>{$data['email']}</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>التاريخ</span>
                            <span class='info-value'>" . date('Y-m-d H:i') . "</span>
                        </div>
                    </div>
                </div>
                
                <center><a href='{$data['admin_url']}' class='btn'>عرض المستخدم</a></center>
            ";
            break;

        case 'new_exchange_user':
            $template['subject'] = "طلب تحويل #{$data['id']} - تم الاستلام";
            $template['body'] = "
                <h2>✅ تم استلام طلبك</h2>
                <p>مرحباً <strong>{$data['name']}</strong>،</p>
                <p>تم استلام طلب التحويل الخاص بك بنجاح. يرجى إكمال الدفع إذا لم تقم بذلك بعد.</p>
                
                <div class='glass-card'>
                    <div class='info-row' style='background: rgba(99, 102, 241, 0.1); border-left-color: #6366f1;'>
                        <span class='info-label'>رقم الطلب</span>
                        <span class='info-value'>#{$data['id']}</span>
                    </div>
                    
                    <div class='divider'></div>
                    
                    <div class='amount-display' style='background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.15) 100%); border-color: rgba(239, 68, 68, 0.3);'>
                        <div class='amount-label'>المبلغ المرسل</div>
                        <div class='amount-value' style='color: #fca5a5;'>
                            {$data['amount_send']}
                            <span class='currency-badge' style='background: rgba(239, 68, 68, 0.2); color: #fca5a5;'>{$data['curr_send']}</span>
                        </div>
                    </div>
                    
                    " . (isset($data['payment_method_send']) ? "
                    <div class='payment-method-card'>
                        <div class='payment-method-title'>وسيلة الدفع للإرسال</div>
                        <div class='payment-method-name'>{$data['payment_method_send']}</div>
                    </div>
                    " : "") . "
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <svg width='40' height='40' viewBox='0 0 24 24' fill='none' stroke='#6366f1' stroke-width='2'>
                            <path d='M7 10l5 5 5-5'/>
                        </svg>
                    </div>
                    
                    <div class='amount-display' style='background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(22, 163, 74, 0.15) 100%); border-color: rgba(34, 197, 94, 0.3);'>
                        <div class='amount-label'>المبلغ المستلم</div>
                        <div class='amount-value' style='color: #86efac;'>
                            {$data['amount_receive']}
                            <span class='currency-badge' style='background: rgba(34, 197, 94, 0.2); color: #86efac;'>{$data['curr_receive']}</span>
                        </div>
                    </div>
                    
                    " . (isset($data['payment_method_receive']) ? "
                    <div class='payment-method-card'>
                        <div class='payment-method-title'>وسيلة الدفع للاستقبال</div>
                        <div class='payment-method-name'>{$data['payment_method_receive']}</div>
                    </div>
                    " : "") . "
                    
                    <div class='divider'></div>
                    
                    <div class='info-row'>
                        <span class='info-label'>الحالة</span>
                        <span class='info-value'><span class='status-badge status-pending'>قيد الانتظار</span></span>
                    </div>
                </div>
                
                <center><a href='{$data['view_url']}' class='btn'>عرض تفاصيل الطلب</a></center>
                
                <p style='margin-top: 24px; color: #94a3b8; font-size: 14px;'>سيتم مراجعة طلبك والرد عليك في أقرب وقت ممكن.</p>
            ";
            break;

        case 'new_exchange_admin':
            $template['subject'] = "[إدارة] طلب تحويل جديد #{$data['id']}";
            $template['body'] = "
                <h2>🔔 طلب تحويل جديد</h2>
                <p>تم تقديم طلب تحويل جديد على المنصة.</p>
                
                <div class='glass-card'>
                    <div class='info-row' style='background: rgba(99, 102, 241, 0.1); border-left-color: #6366f1;'>
                        <span class='info-label'>رقم الطلب</span>
                        <span class='info-value'>#{$data['id']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='info-label'>المستخدم</span>
                        <span class='info-value'>{$data['name']}</span>
                    </div>
                    
                    <div class='divider'></div>
                    
                    <div class='amount-display' style='background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.15) 100%); border-color: rgba(239, 68, 68, 0.3);'>
                        <div class='amount-label'>المبلغ المرسل</div>
                        <div class='amount-value' style='color: #fca5a5;'>
                            {$data['amount_send']}
                            <span class='currency-badge' style='background: rgba(239, 68, 68, 0.2); color: #fca5a5;'>{$data['curr_send']}</span>
                        </div>
                    </div>
                    
                    " . (isset($data['payment_method_send']) ? "
                    <div class='payment-method-card'>
                        <div class='payment-method-title'>وسيلة الدفع للإرسال</div>
                        <div class='payment-method-name'>{$data['payment_method_send']}</div>
                    </div>
                    " : "") . "
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <svg width='40' height='40' viewBox='0 0 24 24' fill='none' stroke='#6366f1' stroke-width='2'>
                            <path d='M7 10l5 5 5-5'/>
                        </svg>
                    </div>
                    
                    <div class='amount-display' style='background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(22, 163, 74, 0.15) 100%); border-color: rgba(34, 197, 94, 0.3);'>
                        <div class='amount-label'>المبلغ المستلم</div>
                        <div class='amount-value' style='color: #86efac;'>
                            {$data['amount_receive']}
                            <span class='currency-badge' style='background: rgba(34, 197, 94, 0.2); color: #86efac;'>{$data['curr_receive']}</span>
                        </div>
                    </div>
                    
                    " . (isset($data['payment_method_receive']) ? "
                    <div class='payment-method-card'>
                        <div class='payment-method-title'>وسيلة الدفع للاستقبال</div>
                        <div class='payment-method-name'>{$data['payment_method_receive']}</div>
                    </div>
                    " : "") . "
                </div>
                
                <center><a href='{$data['admin_url']}' class='btn'>إدارة الطلب</a></center>
            ";
            break;

        case 'exchange_status_update':
            $statusMap = [
                'completed' => ['text' => 'مكتمل', 'class' => 'status-completed', 'icon' => '✅'],
                'cancelled' => ['text' => 'ملغي', 'class' => 'status-cancelled', 'icon' => '❌'],
                'pending' => ['text' => 'قيد الانتظار', 'class' => 'status-pending', 'icon' => '⏳'],
                'processing' => ['text' => 'قيد المعالجة', 'class' => 'status-pending', 'icon' => '🔄']
            ];

            $status = $statusMap[$data['status']] ?? $statusMap['pending'];

            $template['subject'] = "تحديث حالة الطلب #{$data['id']}: {$status['text']}";
            $template['body'] = "
                <h2>{$status['icon']} تحديث حالة الطلب</h2>
                <p>مرحباً <strong>{$data['name']}</strong>،</p>
                <p>تم تحديث حالة طلب التحويل <strong class='highlight'>#{$data['id']}</strong>.</p>
                
                <div class='glass-card'>
                    <div style='text-align: center; padding: 30px; background: rgba(99, 102, 241, 0.05); border-radius: 12px; margin: 20px 0;'>
                        <div style='font-size: 48px; margin-bottom: 12px;'>{$status['icon']}</div>
                        <span class='status-badge {$status['class']}' style='font-size: 16px; padding: 10px 24px;'>{$status['text']}</span>
                    </div>
                    
                    " . (isset($data['amount_send']) && isset($data['amount_receive']) ? "
                    <div class='divider'></div>
                    
                    <div class='info-row'>
                        <span class='info-label'>المبلغ المرسل</span>
                        <span class='info-value'>{$data['amount_send']} {$data['curr_send']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='info-label'>المبلغ المستلم</span>
                        <span class='info-value'>{$data['amount_receive']} {$data['curr_receive']}</span>
                    </div>
                    " : "") . "
                </div>
                
                <center><a href='{$data['view_url']}' class='btn'>عرض تفاصيل الطلب</a></center>
                
                " . ($data['status'] == 'completed' ? "
                <p style='margin-top: 24px; color: #86efac; font-size: 14px; text-align: center;'>✨ شكراً لاستخدامك $site_name. نتطلع لخدمتك مرة أخرى!</p>
                " : "") . "
            ";
            break;

        case 'forgot_password':
            $template['subject'] = "إعادة تعيين كلمة المرور - $site_name";
            $template['body'] = "
                <h2>🔐 طلب إعادة تعيين كلمة المرور</h2>
                <p>تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بك. إذا لم تقم بهذا الطلب، يمكنك تجاهل هذا البريد الإلكتروني بأمان.</p>
                
                <div class='glass-card'>
                    <p style='text-align: center; color: #cbd5e1;'>انقر على الزر أدناه لإعادة تعيين كلمة المرور:</p>
                    <center><a href='{$data['reset_url']}' class='btn'>إعادة تعيين كلمة المرور</a></center>
                </div>
                
                <p style='margin-top: 20px; font-size: 13px; color: #94a3b8; text-align: center;'>⏰ هذا الرابط صالح لمدة ساعة واحدة فقط.</p>
            ";
            break;
    }

    $template['body'] = wrapEmailLayout($template['body']);
    return $template;
}
