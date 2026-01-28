<?php

function wrapEmailLayout($content, $lang = 'ar')
{
    $site_name_key = ($lang == 'ar') ? 'site_name_ar' : 'site_name_en';
    $site_name = getSetting($site_name_key) ?: (getSetting('site_name_en') ?: 'Marketation');
    $site_logo = getSetting('site_logo');
    $site_url = getSetting('site_url');

    // Logo Handling with remote fallback usually better for emails, 
    // but here we use what we have.
    $logo_src = $site_logo ? "$site_url/uploads/$site_logo" : "";

    $logo_html = $logo_src
        ? "<img src='$logo_src' alt='$site_name' style='height: 48px; width: auto;'>"
        : "<h2 style='color: #fff; margin:0; font-size: 26px; font-weight: 900; letter-spacing: -1px;'>$site_name</h2>";

    $dir = ($lang == 'ar') ? 'rtl' : 'ltr';
    // Fonts matching the dashboard
    $font_family = ($lang == 'ar') ? "'Cairo', sans-serif" : "'Inter', sans-serif";
    $font_url = ($lang == 'ar')
        ? "https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&display=swap"
        : "https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap";

    // Common Translation Strings for Footer
    $copyright_text = ($lang == 'ar')
        ? "&copy; " . date('Y') . " $site_name. جميع الحقوق محفوظة."
        : "&copy; " . date('Y') . " $site_name. All rights reserved.";

    return "
    <!DOCTYPE html>
    <html dir='$dir' lang='$lang'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>$site_name</title>
        <style>
            @import url('$font_url');
            
            body, table, td, p, a, li, blockquote {
                -webkit-text-size-adjust: 100%;
                -ms-text-size-adjust: 100%;
            }
            body { 
                font-family: $font_family; 
                background-color: #0f172a; /* Slate 900 */
                margin: 0; 
                padding: 0; 
                color: #e2e8f0; /* Slate 200 */
                line-height: 1.6;
            }
            
            .wrapper {
                width: 100%;
                table-layout: fixed;
                background-color: #0f172a;
                padding: 40px 0;
            }
            
            .main-card {
                background-color: #1e293b; /* Fallback for glass */
                background: linear-gradient(145deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.95));
                margin: 0 auto;
                width: 94%;
                max-width: 600px;
                border-radius: 32px; /* 2rem rounded */
                border: 1px solid rgba(255, 255, 255, 0.1);
                overflow: hidden;
                box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.05), 
                            0 20px 50px -12px rgba(0, 0, 0, 0.7);
            }
            
            .header-bar {
                padding: 40px 40px 20px 40px;
                text-align: center;
            }
            
            .content-body {
                padding: 20px 40px 40px 40px;
                text-align: " . ($dir == 'rtl' ? 'right' : 'left') . ";
            }
            
            h1, h2, h3 {
                color: #ffffff;
                font-weight: 900; /* Broad matches Dashboard */
                margin-top: 0;
                margin-bottom: 20px;
                letter-spacing: -0.025em;
            }
            
            p {
                margin-bottom: 24px;
                color: #94a3b8; /* Text Gray 400 */
                font-size: 16px;
                line-height: 1.7;
            }
            
            strong {
                color: #fff;
                font-weight: 700;
            }
            
            /* Buttons matching Dashboard Gradient */
            .btn {
                display: inline-block;
                background: #6366f1; /* Indigo 500 */
                background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); /* Indigo to Purple */
                color: #ffffff !important;
                text-decoration: none;
                padding: 16px 32px;
                border-radius: 16px;
                font-weight: 800;
                font-size: 16px;
                margin: 10px 0 30px 0;
                box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
                transition: all 0.3s ease;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            /* Data Display Boxes: Matching Stats Grid Cards */
            .info-box {
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 20px;
                padding: 25px;
                margin: 25px 0;
            }

            .info-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            }
            .info-row:last-child { border-bottom: none; }
            
            .label { 
                color: #64748b; /* Slate 500 */
                font-weight: 700; 
                font-size: 13px; 
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .value { 
                color: #f1f5f9; /* Slate 100 */
                font-weight: 700; 
                font-size: 15px;
            }
            
            .footer {
                padding: 30px;
                text-align: center;
                border-top: 1px solid rgba(255, 255, 255, 0.05);
                background: rgba(15, 23, 42, 0.5);
            }
            .footer p {
                font-size: 13px;
                color: #475569; /* Slate 600 */
                margin: 0;
            }
            
            .highlight-text {
                background: linear-gradient(to right, #818cf8, #c084fc);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                color: #818cf8; /* Fallback */
                font-weight: 800;
            }
            
            /* Mobile Responsiveness */
            @media only screen and (max-width: 600px) {
                .main-card { width: 100% !important; border-radius: 0 !important; border: none !important; }
                .content-body { padding: 20px !important; }
                .header-bar { padding: 30px 20px !important; }
            }
        </style>
    </head>
    <body dir='$dir'>
        <center class='wrapper'>
            <div class='main-card'>
                <div class='header-bar'>
                    $logo_html
                </div>
                
                <div class='content-body'>
                    $content
                </div>
                
                <div class='footer'>
                    <p>$copyright_text</p>
                    <p style='margin-top: 10px; font-size: 11px; opacity: 0.5;'>Sent automatically by $site_name System</p>
                </div>
            </div>
        </center>
    </body>
    </html>
    ";
}

function getEmailTemplate($type, $data, $lang = 'ar')
{
    $site_name_key = ($lang == 'ar') ? 'site_name_ar' : 'site_name_en';
    $site_name = getSetting($site_name_key) ?: (getSetting('site_name_en') ?: 'Marketation');
    $isAr = ($lang == 'ar');

    $template = ['subject' => '', 'body' => ''];

    switch ($type) {
        // 1. Welcome User
        case 'welcome_user':
            $template['subject'] = $isAr
                ? "🚀 تم انطلاق حسابك في $site_name!"
                : "🚀 Your Account Lift-off at $site_name!";

            $welcome_title = $isAr ? "أهلاً بك على متن المركبة! 👋" : "Welcome Aboard! 👋";
            $welcome_msg = $isAr
                ? "سعداء جداً بانضمامك إلينا. حسابك الآن جاهز تماماً، لقد قمنا بتجهيز منصة القيادة لتبدأ رحلتك في التسويق الرقمي."
                : "We are thrilled to have you with us. Your account is fully active. We've prepped the cockpit for you to start your digital marketing journey.";

            $box_msg = $isAr ? "ابدأ باستكشاف أدواتنا الاحترافية الآن." : "Start exploring our professional tools now.";
            $btn_text = $isAr ? "الدخول للوحة التحكم" : "Access Dashboard";

            $template['body'] = "
                <h1 style='text-align: center; font-size: 28px;'>$welcome_title</h1>
                <p style='text-align: center;'>$welcome_msg</p>
                
                <div class='info-box' style='text-align: center; background: rgba(99, 102, 241, 0.1); border-color: rgba(99, 102, 241, 0.2);'>
                    <p style='margin: 0; color: #fff; font-weight: 600;'>$box_msg</p>
                </div>
                
                <center><a href='{$data['login_url']}' class='btn'>$btn_text</a></center>
            ";
            break;

        // 2. New User Admin Notification
        case 'new_user_admin':
            $template['subject'] = $isAr
                ? "🔔 مستخدم جديد انضم للمنصة: {$data['name']}"
                : "🔔 New User Joined: {$data['name']}";

            $title = $isAr ? "تقرير تسجيل جديد" : "New User Report";
            $desc = $isAr ? "هناك عضو جديد انضم لعائلة $site_name." : "A new member has joined the $site_name family.";

            $lbl_name = $isAr ? "الاسم الكامل" : "FULL NAME";
            $lbl_user = $isAr ? "اسم المستخدم" : "USERNAME";
            $lbl_email = $isAr ? "البريد الإلكتروني" : "EMAIL ADDRESS";
            $lbl_date = $isAr ? "تاريخ الانضمام" : "JOIN DATE";
            $btn_text = $isAr ? "إدارة المستخدم" : "Manage User";

            $template['body'] = "
                <h2>$title</h2>
                <p>$desc</p>
                
                <div class='info-box'>
                    <div class='info-row'>
                        <span class='label'>$lbl_name</span>
                        <span class='value'>{$data['name']}</span>
                    </div>
                    <div class='info-row'>
                        <span class='label'>$lbl_user</span>
                        <span class='value'>{$data['username']}</span>
                    </div>
                    <div class='info-row'>
                        <span class='label'>$lbl_email</span>
                        <span class='value'>{$data['email']}</span>
                    </div>
                    <div class='info-row'>
                        <span class='label'>$lbl_date</span>
                        <span class='value'>" . date('Y-m-d H:i') . "</span>
                    </div>
                </div>
                
                <center><a href='{$data['admin_url']}' class='btn'>$btn_text</a></center>
            ";
            break;

        // 3. Forgot Password
        case 'forgot_password':
            $template['subject'] = $isAr ? "🔒 طلب استعادة كلمة المرور" : "🔒 Password Reset Request";

            $title = $isAr ? "هل نسيت كلمة المرور؟" : "Forgot Password?";
            $msg1 = $isAr ? "لقد تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك." : "We received a request to reset your account password.";
            $msg2 = $isAr ? "لا تقلق، يحدث ذلك لأفضلنا! اضغط الزر أدناه لاختيار كلمة مرور جديدة." : "No worries, it happens to the best of us! Click the button below to pick a new one.";
            $btn_text = $isAr ? "إعادة تعيين كلمة المرور" : "Reset My Password";
            $footer_msg = $isAr
                ? "رابط الأمان هذا صالح لمدة ساعة واحدة فقط."
                : "This security link is valid for one hour only.";

            $template['body'] = "
                <h1 style='text-align: center;'>$title</h1>
                <p style='text-align: center;'>$msg1</p>
                <p style='text-align: center;'>$msg2</p>
                
                <center><a href='{$data['reset_url']}' class='btn'>$btn_text</a></center>
                
                <p style='text-align: center; font-size: 13px; color: #64748b;'>$footer_msg</p>
            ";
            break;

        // 4. New Support Ticket (Admin)
        case 'new_ticket_admin':
            $template['subject'] = $isAr
                ? "🎫 تذكرة دعم جديدة #[{$data['ticket_id']}]"
                : "🎫 New Support Ticket #[{$data['ticket_id']}]";

            $title = $isAr ? "طلب دعم فني جديد" : "New Support Request";
            $desc = $isAr
                ? "قام <strong>{$data['user_name']}</strong> بفتح تذكرة دعم جديدة تتطلب اهتمامك."
                : "User <strong>{$data['user_name']}</strong> has opened a new ticket requiring your attention.";

            $lbl_id = $isAr ? "رقم التذكرة" : "TICKET ID";
            $lbl_subject = $isAr ? "الموضوع" : "SUBJECT";
            $lbl_priority = $isAr ? "الأهمية" : "PRIORITY";
            $lbl_msg = $isAr ? "نص الرسالة" : "MESSAGE BODY";
            $btn_text = $isAr ? "عرض والرد" : "View & Reply";

            $template['body'] = "
                <h2>$title</h2>
                <p>$desc</p>
                
                <div class='info-box'>
                    <div class='info-row'>
                        <span class='label'>$lbl_id</span>
                        <span class='value'>#{$data['ticket_id']}</span>
                    </div>
                    <div class='info-row'>
                        <span class='label'>$lbl_subject</span>
                        <span class='value'>{$data['subject']}</span>
                    </div>
                    <div class='info-row'>
                        <span class='label'>$lbl_priority</span>
                        <span class='value'>{$data['priority']}</span>
                    </div>
                </div>
                
                <p style='font-size: 12px; font-weight: bold; color: #64748b; margin-bottom: 8px;'>$lbl_msg</p>
                <div style='background: rgba(0,0,0,0.2); border-radius: 12px; padding: 15px; border: 1px solid rgba(255,255,255,0.05); color: #e2e8f0; font-size: 14px; margin-bottom: 25px;'>
                    {$data['message']}
                </div>
                
                <center><a href='{$data['admin_url']}' class='btn'>$btn_text</a></center>
            ";
            break;

        // 5. Ticket Status Update (User)
        case 'ticket_status_update':
            $status_text_ar = match ($data['status']) {
                'open' => 'مفتوحة', 'answered' => 'تم الرد', 'closed' => 'مغلقة',
                'solved' => 'تم الحل', 'pending' => 'قيد الانتظار', default => $data['status']
            };

            $status_text_en = match ($data['status']) {
                'open' => 'Open', 'answered' => 'Answered', 'closed' => 'Closed',
                'solved' => 'Solved', 'pending' => 'Pending', default => $data['status']
            };

            $status_text = $isAr ? $status_text_ar : $status_text_en;

            $color = match ($data['status']) {
                'open' => '#3b82f6', // blue
                'answered' => '#22c55e', // green (Success)
                'closed' => '#ef4444', // red
                'solved' => '#8b5cf6', // purple
                default => '#fff'
            };

            // Generate a status badge style
            $badge_style = "display: inline-block; background-color: {$color}20; color: $color; padding: 8px 16px; border-radius: 50px; border: 1px solid {$color}40; font-weight: 800; font-size: 14px;";

            $template['subject'] = $isAr
                ? "تحديث حالة التذكرة #{$data['ticket_id']}: $status_text"
                : "Ticket Status Update #{$data['ticket_id']}: $status_text";

            $title = $isAr ? "تحديث بخصوص تذكرتك" : "Update Regarding Your Ticket";
            $salutation = $isAr ? "مرحباً <strong>{$data['name']}</strong>،" : "Hi <strong>{$data['name']}</strong>,";
            $msg = $isAr
                ? "تم تغيير حالة تذكرة الدعم الفني الخاصة بك."
                : "The status of your support ticket has been updated.";

            $lbl_new_status = $isAr ? "الحالة الحالية" : "CURRENT STATUS";
            $lbl_id = $isAr ? "رقم التذكرة" : "TICKET ID";
            $lbl_subject = $isAr ? "العنوان" : "SUBJECT";
            $btn_text = $isAr ? "عرض التذكرة" : "View Ticket";

            $template['body'] = "
                <h2>$title</h2>
                <p>$salutation</p>
                <p>$msg</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                     <span style='$badge_style'>$status_text</span>
                </div>
                
                <div class='info-box'>
                     <div class='info-row'>
                        <span class='label'>$lbl_id</span>
                        <span class='value'>#{$data['ticket_id']}</span>
                    </div>
                     <div class='info-row'>
                        <span class='label'>$lbl_subject</span>
                        <span class='value'>{$data['ticket_subject']}</span>
                    </div>
                </div>
                
                <center><a href='{$data['view_url']}' class='btn'>$btn_text</a></center>
            ";
            break;

        // 6. Test Email
        case 'test_email':
            $template['subject'] = $isAr
                ? "تجربة إعدادات البريد الإلكتروني - $site_name"
                : "Email Settings Test - $site_name";

            $title = $isAr ? "نجاح العملية! ✅" : "System Success! ✅";
            $msg1 = $isAr ? "إعدادات البريد الإلكتروني (SMTP) تعمل بكفاءة تامة." : "Email settings (SMTP) are configured and working correctly.";
            $msg2 = $isAr ? "هذه رسالة اختبار تلقائية من النظام." : "This is an automated test email from the system.";
            $time_msg = $isAr ? "وقت الإرسال" : "TIMESTAMP";

            $template['body'] = "
                <div style='text-align: center;'>
                    <h1>$title</h1>
                    <p>$msg1</p>
                    <p>$msg2</p>
                    <div class='info-box'>
                        <div class='info-row'>
                            <span class='label'>$time_msg</span>
                            <span class='value'>" . date('Y-m-d H:i:s') . "</span>
                        </div>
                    </div>
                </div>
             ";
            break;
    }

    $template['body'] = wrapEmailLayout($template['body'], $lang);
    return $template;
}
