<?php

function wrapEmailLayout($content, $lang = 'ar')
{
    $site_name_key = ($lang == 'ar') ? 'site_name_ar' : 'site_name_en';
    $site_name = getSetting($site_name_key) ?: (getSetting('site_name_en') ?: 'Marketation');
    $site_logo = getSetting('site_logo');
    $site_url = getSetting('site_url');

    $logo_src = $site_logo ? "$site_url/uploads/$site_logo" : "";

    $logo_html = $logo_src
        ? "<img src='$logo_src' alt='$site_name' style='height: 40px; border-radius: 8px;'>"
        : "<h2 style='color: #fff; margin:0; font-size: 24px; letter-spacing: -1px;'>$site_name</h2>";

    $dir = ($lang == 'ar') ? 'rtl' : 'ltr';
    $font_family = ($lang == 'ar') ? "'Cairo', sans-serif" : "'Inter', sans-serif";
    $font_url = ($lang == 'ar')
        ? "https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap"
        : "https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap";

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
        <style>
            @import url('$font_url');
            
            body { 
                font-family: $font_family; 
                background-color: #0f172a; 
                background-image: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                margin: 0; 
                padding: 0; 
                color: #e2e8f0;
            }
            
            .wrapper {
                width: 100%;
                table-layout: fixed;
                padding-bottom: 40px;
            }
            
            .main-content {
                background-color: rgba(30, 41, 59, 0.7);
                margin: 0 auto;
                width: 100%;
                max-width: 600px;
                border-radius: 24px;
                border: 1px solid rgba(255, 255, 255, 0.1);
                overflow: hidden;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            }
            
            .header {
                padding: 30px;
                text-align: center;
                background: linear-gradient(to right, rgba(79, 70, 229, 0.1), rgba(147, 51, 234, 0.1));
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            }
            
            .body {
                padding: 40px 30px;
                text-align: " . ($dir == 'rtl' ? 'right' : 'left') . ";
            }
            
            h1, h2, h3 {
                color: #fff;
                margin-top: 0;
            }
            
            p {
                margin-bottom: 20px;
                line-height: 1.6;
                color: #cbd5e1;
            }
            
            .btn {
                display: inline-block;
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                color: #ffffff !important;
                text-decoration: none;
                padding: 14px 28px;
                border-radius: 12px;
                font-weight: 700;
                font-size: 16px;
                margin: 20px 0;
                box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
            }
            
            .glass-box {
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 255, 255, 0.05);
                border-radius: 16px;
                padding: 20px;
                margin: 20px 0;
            }

            .info-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            }
            .info-row:last-child { border-bottom: none; }
            
            .label { color: #94a3b8; font-weight: 600; font-size: 14px; }
            .value { color: #fff; font-weight: 700; }
            
            .footer {
                padding: 20px;
                text-align: center;
                color: #64748b;
                font-size: 13px;
            }
            
            .highlight { color: #818cf8; font-weight: bold; }
        </style>
    </head>
    <body dir='$dir'>
        <center class='wrapper'>
            <div style='height: 40px;'></div>
            <div class='main-content'>
                <div class='header'>
                    $logo_html
                </div>
                <div class='body'>
                    $content
                </div>
                <div class='footer'>
                    <p style='margin: 0;'>$copyright_text</p>
                </div>
            </div>
            <div style='height: 40px;'></div>
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
                ? "مرحباً بك في $site_name - تم التسجيل بنجاح! 🚀"
                : "Welcome to $site_name - Registration Successful! 🚀";

            $welcome_title = $isAr ? "مرحباً {$data['name']}! 👋" : "Hi {$data['name']}! 👋";
            $welcome_msg = $isAr
                ? "شكراً لانضمامك إلينا. حسابك جاهز الآن ويمكنك البدء في استخدام أدواتنا المتقدمة."
                : "Thanks for joining us. Your account is now ready and you can start using our advanced tools.";
            $box_msg = $isAr ? "استمتع بتجربة تسويقية فريدة." : "Enjoy a unique marketing experience.";
            $btn_text = $isAr ? "تسجيل الدخول لحسابك" : "Login to Your Account";
            $footer_msg = $isAr
                ? "إذا كانت لديك أي أسئلة، فريق الدعم لدينا جاهز لمساعدتك دائماً."
                : "If you have any questions, our support team is always ready to help.";

            $template['body'] = "
                <h2 style='text-align: center;'>$welcome_title</h2>
                <p>$welcome_msg</p>
                
                <div class='glass-box' style='text-align: center;'>
                    <p style='margin:0; font-size: 18px; color: #fff;'>$box_msg</p>
                </div>
                
                <center><a href='{$data['login_url']}' class='btn'>$btn_text</a></center>
                
                <p>$footer_msg</p>
            ";
            break;

        // 2. New User Admin Notification
        case 'new_user_admin':
            $template['subject'] = $isAr
                ? "🔔 مستخدم جديد: {$data['name']}"
                : "🔔 New User: {$data['name']}";

            $title = $isAr ? "تسجيل مستخدم جديد" : "New User Registration";
            $desc = $isAr ? "قام مستخدم جديد بالتسجيل في المنصة." : "A new user has registered on the platform.";
            $lbl_name = $isAr ? "الاسم" : "Name";
            $lbl_user = $isAr ? "اسم المستخدم" : "Username";
            $lbl_email = $isAr ? "البريد الإلكتروني" : "Email";
            $lbl_date = $isAr ? "التاريخ" : "Date";
            $btn_text = $isAr ? "عرض المستخدم" : "View User";

            $template['body'] = "
                <h2>$title</h2>
                <p>$desc</p>
                
                <div class='glass-box'>
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
            $template['subject'] = $isAr ? "🔒 استعادة كلمة المرور" : "🔒 Password Reset Request";

            $title = $isAr ? "طلب استعادة كلمة المرور" : "Password Reset Request";
            $msg1 = $isAr ? "لقد تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بك." : "We received a request to reset your password.";
            $msg2 = $isAr ? "اضغط على الزر أدناه لإعادة التعيين. هذا الرابط صالح لمدة ساعة واحدة." : "Click the button below to reset it. This link is valid for one hour.";
            $btn_text = $isAr ? "إعادة تعيين كلمة المرور" : "Reset Password";
            $footer_msg = $isAr
                ? "إذا لم تطلب هذا التغيير، يمكنك تجاهل هذه الرسالة بأمان."
                : "If you didn't request this change, you can safely ignore this email.";

            $template['body'] = "
                <h2>$title</h2>
                <p>$msg1</p>
                <p>$msg2</p>
                
                <center><a href='{$data['reset_url']}' class='btn'>$btn_text</a></center>
                
                <p style='font-size: 12px; color: #64748b;'>$footer_msg</p>
            ";
            break;

        // 4. New Support Ticket (Admin Notification)
        case 'new_ticket_admin':
            $template['subject'] = $isAr
                ? "🎫 تذكرة دعم جديدة #[{$data['ticket_id']}]"
                : "🎫 New Support Ticket #[{$data['ticket_id']}]";

            $title = $isAr ? "تذكرة دعم فني جديدة" : "New Support Ticket";
            $desc = $isAr
                ? "قام <strong>{$data['user_name']}</strong> بفتح تذكرة دعم جديدة."
                : "User <strong>{$data['user_name']}</strong> has opened a new support ticket.";

            $lbl_id = $isAr ? "رقم التذكرة" : "Ticket ID";
            $lbl_subject = $isAr ? "العنوان" : "Subject";
            $lbl_priority = $isAr ? "الأهمية" : "Priority";
            $lbl_msg = $isAr ? "نص الرسالة:" : "Message Content:";
            $btn_text = $isAr ? "الرد على التذكرة" : "Reply to Ticket";

            $template['body'] = "
                <h2>$title</h2>
                <p>$desc</p>
                
                <div class='glass-box'>
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
                
                <div class='glass-box'>
                    <p style='margin-bottom: 5px; font-weight: bold; color: #94a3b8;'>$lbl_msg</p>
                    <p style='color: #fff;'>{$data['message']}</p>
                </div>
                
                <center><a href='{$data['admin_url']}' class='btn'>$btn_text</a></center>
            ";
            break;

        // 5. Ticket Status Update (User Notification)
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
                'open' => '#3b82f6', 'answered' => '#22c55e', 'closed' => '#ef4444',
                'solved' => '#8b5cf6', default => '#fff'
            };

            $template['subject'] = $isAr
                ? "تحديث حالة التذكرة #{$data['ticket_id']}: $status_text"
                : "Ticket Status Update #{$data['ticket_id']}: $status_text";

            $title = $isAr ? "تحديث بخصوص تذكرتك" : "Update Regarding Your Ticket";
            $salutation = $isAr ? "مرحباً <strong>{$data['name']}</strong>،" : "Hi <strong>{$data['name']}</strong>,";
            $msg = $isAr
                ? "تم تغيير حالة تذكرة الدعم الفني الخاصة بك."
                : "The status of your support ticket has been updated.";

            $lbl_new_status = $isAr ? "الحالة الجديدة" : "New Status";
            $lbl_id = $isAr ? "رقم التذكرة" : "Ticket ID";
            $lbl_subject = $isAr ? "العنوان" : "Subject";
            $btn_text = $isAr ? "عرض التذكرة" : "View Ticket";

            $template['body'] = "
                <h2>$title</h2>
                <p>$salutation</p>
                <p>$msg</p>
                
                <div class='glass-box' style='text-align: center;'>
                    <p style='margin-bottom: 5px; color: #94a3b8;'>$lbl_new_status</p>
                    <h2 style='color: $color; margin: 0;'>$status_text</h2>
                </div>
                
                <div class='glass-box'>
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

            $title = $isAr ? "نجاح! ✅" : "Success! ✅";
            $msg1 = $isAr ? "إعدادات البريد الإلكتروني (SMTP) تعمل بشكل صحيح." : "Email settings (SMTP) are working correctly.";
            $msg2 = $isAr ? "هذه رسالة اختبار تلقائية من النظام." : "This is an automated test email from the system.";
            $time_msg = $isAr ? "وقت الإرسال:" : "Sent Time:";

            $template['body'] = "
                <div style='text-align: center;'>
                    <h1>$title</h1>
                    <p>$msg1</p>
                    <p>$msg2</p>
                    <div class='glass-box'>
                        <p style='margin: 0; color: #fff;'>$time_msg " . date('Y-m-d H:i:s') . "</p>
                    </div>
                </div>
             ";
            break;
    }

    $template['body'] = wrapEmailLayout($template['body'], $lang);
    return $template;
}
