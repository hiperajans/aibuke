<?php
require_once 'config.php';
session_start();
require_once 'includes/auth.php';

if (isset($_SESSION['student_id'])) {
    header('Location: chat.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $result = loginStudent($pdo, $username, $password);

        if ($result['success']) {
            header('Location: chat.php');
            exit;
        } else {
            $error = $result['message'];
        }
    } else {
        $error = 'Lütfen kullanıcı adı ve şifre girin.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aibüke Öğretmen - Giriş</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --white: #ffffff;
            --off-white: #f8f9fc;
            --light-gray: #f1f3f8;
            --mid-gray: #e2e6ef;
            --text-dark: #1a1d2e;
            --text-medium: #4a4e6b;
            --text-light: #7c7f9a;
            --primary: #5b5fed;
            --primary-light: #7b7ff0;
            --primary-soft: rgba(91, 95, 237, 0.08);
            --accent: #f06292;
            --accent-soft: rgba(240, 98, 146, 0.1);
            --shadow-soft: 0 4px 24px rgba(91, 95, 237, 0.08);
            --shadow-medium: 0 8px 40px rgba(91, 95, 237, 0.12);
            --shadow-strong: 0 20px 60px rgba(91, 95, 237, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--off-white);
            height: 100vh;
            height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Soft gradient background */
        .bg-gradient {
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 30% 20%, rgba(91, 95, 237, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 70% 80%, rgba(240, 98, 146, 0.05) 0%, transparent 50%),
                linear-gradient(180deg, var(--off-white) 0%, var(--white) 100%);
            z-index: 0;
        }

        /* Floating shapes */
        .shapes {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 1;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.5;
        }

        .shape-1 {
            width: 300px;
            height: 300px;
            top: -100px;
            right: -80px;
            background: linear-gradient(135deg, var(--primary-soft), transparent);
            animation: floatShape 12s ease-in-out infinite;
        }

        .shape-2 {
            width: 200px;
            height: 200px;
            bottom: -50px;
            left: -60px;
            background: linear-gradient(135deg, var(--accent-soft), transparent);
            animation: floatShape 10s ease-in-out infinite reverse;
        }

        .shape-3 {
            width: 150px;
            height: 150px;
            top: 50%;
            left: 10%;
            background: var(--primary-soft);
            animation: floatShape 8s ease-in-out infinite;
            animation-delay: -3s;
        }

        @keyframes floatShape {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(20px, -20px) rotate(5deg); }
            66% { transform: translate(-10px, 15px) rotate(-3deg); }
        }

        /* Sparkles */
        .sparkle {
            position: absolute;
            width: 6px;
            height: 6px;
            background: var(--primary);
            border-radius: 50%;
            animation: sparkle 3s ease-in-out infinite;
        }

        @keyframes sparkle {
            0%, 100% { opacity: 0.2; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        /* Login Container */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 400px;
            padding: 20px;
            margin: auto;
            transform: translateY(25px);
        }

        .login-card {
            background: var(--white);
            border-radius: 32px;
            padding: 52px 44px;
            box-shadow: var(--shadow-strong);
            position: relative;
            overflow: hidden;
            animation: cardRise 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(50px);
        }

        @keyframes cardRise {
            to { opacity: 1; transform: translateY(0); }
        }

        /* Subtle top border accent */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 0 0 4px 4px;
        }


        /* Logo Section */
        .logo-section {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }

        /* Avatar watermark behind text */
        .logo-avatar-bg {
            position: absolute;
            top: 50%;
            right: -40px;
            transform: translateY(-50%);
            width: 180px;
            height: 180px;
            opacity: 1;
            pointer-events: none;
            z-index: -1;
        }

        .logo-avatar-bg img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Brand text */
        .brand-name {
            font-size: 42px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -1px;
            margin-bottom: 4px;
            position: relative;
            z-index: 1;
        }

        .brand-name span {
            display: block;
        }

        .brand-name .brand-ogretmen {
            font-size: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-top: -5px;
        }

        .brand-tagline {
            font-size: 14px;
            color: var(--text-light);
            font-weight: 500;
            margin-top: 8px;
            position: relative;
            z-index: 1;
        }

        /* Form */
        .form-group {
            margin-bottom: 20px;
            opacity: 0;
            animation: fadeUp 0.6s ease-out forwards;
            position: relative;
            z-index: 1;
        }

        .form-group:nth-child(1) { animation-delay: 0.3s; }
        .form-group:nth-child(2) { animation-delay: 0.4s; }

        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
            from { opacity: 0; transform: translateY(15px); }
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-medium);
            margin-bottom: 10px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper svg {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            color: var(--text-light);
            transition: all 0.3s ease;
        }

        .input-wrapper:focus-within svg {
            color: var(--primary);
        }

        .form-group input {
            width: 100%;
            padding: 16px 16px 16px 50px;
            background: var(--light-gray);
            border: 2px solid transparent;
            border-radius: 16px;
            font-size: 15px;
            font-family: inherit;
            font-weight: 500;
            color: var(--text-dark);
            transition: all 0.3s ease;
            outline: none;
        }

        .form-group input::placeholder {
            color: var(--text-light);
            font-weight: 400;
        }

        .form-group input:focus {
            background: var(--white);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-soft);
        }

        /* Button */
        .btn {
            width: 100%;
            padding: 18px 24px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 700;
            font-family: inherit;
            color: white;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin-top: 12px;
            position: relative;
            overflow: hidden;
            opacity: 0;
            animation: fadeUp 0.6s ease-out 0.5s forwards;
        }

        .btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(91, 95, 237, 0.35);
        }

        .btn:hover::before {
            opacity: 1;
        }

        .btn:active {
            transform: translateY(-1px);
        }

        .btn span {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn svg {
            width: 20px;
            height: 20px;
            transition: transform 0.3s ease;
        }

        .btn:hover svg {
            transform: translateX(5px);
        }

        /* Error */
        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(239, 68, 68, 0.04));
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #dc2626;
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shakeIn 0.5s ease;
        }

        @keyframes shakeIn {
            0% { opacity: 0; transform: translateX(-10px); }
            20% { transform: translateX(8px); }
            40% { transform: translateX(-6px); }
            60% { transform: translateX(4px); }
            80% { transform: translateX(-2px); }
            100% { opacity: 1; transform: translateX(0); }
        }

        .alert-error svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        /* Footer */
        .footer {
            margin-top: 32px;
            padding-top: 28px;
            border-top: 1px solid var(--mid-gray);
            opacity: 0;
            animation: fadeUp 0.6s ease-out 0.6s forwards;
            position: relative;
            z-index: 1;
        }

        .footer-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 24px;
            background: var(--light-gray);
            border: 1px solid var(--mid-gray);
            border-radius: 14px;
            color: var(--text-medium);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .footer-btn:hover {
            background: var(--white);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .footer-btn svg {
            width: 18px;
            height: 18px;
            transition: transform 0.3s ease;
        }

        .footer-btn:hover svg {
            transform: scale(1.1);
        }

        .footer-credit {
            text-align: center;
            margin-top: 16px;
            font-size: 12px;
            color: var(--text-light);
            font-weight: 500;
        }

        .footer-credit .brand {
            color: var(--primary);
            font-weight: 600;
        }

        /* Top Auth Buttons */
        .top-buttons {
            position: fixed;
            top: 1.5rem;
            left: 1.5rem;
            right: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            animation: fadeDown 0.8s ease-out 0.3s both;
        }

        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .auth-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 16px;
            text-decoration: none;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            background: var(--white);
            border: 1.5px solid var(--mid-gray);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .auth-btn svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }

        .auth-btn.admin-btn {
            color: var(--text-medium);
        }

        .auth-btn.admin-btn:hover {
            background: var(--primary-soft);
            color: var(--primary);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-soft);
        }

        .auth-btn.teacher-btn {
            color: var(--text-dark);
            border-color: var(--mid-gray);
            background: var(--white);
        }

        .auth-btn.teacher-btn:hover {
            background: var(--text-dark);
            color: var(--white);
            border-color: var(--text-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Responsive */
        @media (max-width: 440px) {
            .top-buttons {
                top: 1rem;
                left: 1rem;
                right: 1rem;
            }
            .auth-btn .btn-label {
                display: none;
            }
            .auth-btn {
                padding: 10px 12px;
            }
            .login-card {
                padding: 40px 28px;
                border-radius: 24px;
            }
            .brand-name {
                font-size: 32px;
            }
            .brand-name .brand-ogretmen {
                font-size: 36px;
            }
        }

        /* Dark Mode - System Preference */
        @media (prefers-color-scheme: dark) {
            :root {
                --white: #1a1a24;
                --off-white: #0f0f14;
                --light-gray: #252532;
                --mid-gray: #3a3a4a;
                --text-dark: #f0f0f5;
                --text-medium: #b0b0c0;
                --text-light: #6c6c80;
                --primary: #7b7ff0;
                --primary-light: #9b9ef5;
                --primary-soft: rgba(123, 127, 240, 0.15);
                --accent: #f472b6;
                --accent-soft: rgba(244, 114, 182, 0.15);
                --shadow-soft: 0 4px 24px rgba(0, 0, 0, 0.3);
                --shadow-medium: 0 8px 40px rgba(0, 0, 0, 0.4);
                --shadow-strong: 0 20px 60px rgba(0, 0, 0, 0.5);
            }

            .bg-gradient {
                background:
                    radial-gradient(ellipse at 30% 20%, rgba(123, 127, 240, 0.1) 0%, transparent 50%),
                    radial-gradient(ellipse at 70% 80%, rgba(244, 114, 182, 0.08) 0%, transparent 50%),
                    linear-gradient(180deg, #0f0f14 0%, #1a1a24 100%);
            }

            .shape {
                opacity: 0.3;
            }

            .shape-1 {
                background: linear-gradient(135deg, var(--primary-soft), transparent);
            }

            .shape-2 {
                background: linear-gradient(135deg, var(--accent-soft), transparent);
            }

            .shape-3 {
                background: var(--primary-soft);
            }

            .login-card {
                box-shadow: var(--shadow-strong);
            }

            .footer-btn {
                background: var(--light-gray);
                border-color: var(--mid-gray);
            }

            .form-group input {
                background: var(--light-gray);
                color: var(--text-dark);
            }

            .form-group input:focus {
                background: var(--white);
            }

            .alert-error {
                background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.08));
                border-color: rgba(239, 68, 68, 0.3);
                color: #f87171;
            }

            .auth-btn.admin-btn {
                background: var(--light-gray);
                color: var(--text-medium);
                border-color: var(--mid-gray);
            }

            .auth-btn.admin-btn:hover {
                background: var(--primary-soft);
                color: var(--primary);
                border-color: var(--primary);
            }

            .auth-btn.teacher-btn {
                background: var(--light-gray);
                color: var(--text-dark);
                border-color: var(--mid-gray);
            }

            .auth-btn.teacher-btn:hover {
                background: var(--text-dark);
                color: var(--light-gray);
                border-color: var(--text-dark);
            }
        }
    </style>
</head>
<body>
    <!-- Top Auth Buttons -->
    <div class="top-buttons">
        <a href="/admin/" class="auth-btn admin-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            <span class="btn-label">Admin</span>
        </a>
        <a href="/ogretmen/" class="auth-btn teacher-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span class="btn-label">Öğretmen</span>
        </a>
    </div>

    <div class="bg-gradient"></div>

    <div class="shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-avatar-bg">
                    <img src="assets/aibuke-avatar.png" alt="">
                </div>

                <h1 class="brand-name">
                    <span>Aibüke</span>
                    <span class="brand-ogretmen">Öğretmen</span>
                </h1>
                <p class="brand-tagline">Yapay Zeka Eğitim Asistanın</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Kullanıcı Adı</label>
                    <div class="input-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <input type="text" name="username" placeholder="Kullanıcı adınız" required autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label>Şifre</label>
                    <div class="input-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" name="password" placeholder="Şifreniz" required autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" name="login" class="btn">
                    <span>
                        Giriş Yap
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </span>
                </button>
            </form>

            <div class="footer">
                <p class="footer-credit">&copy; <?= date('Y') ?> <span class="brand">Hiper Ajans</span> tarafından geliştirilmiştir.</p>
            </div>
        </div>
    </div>
</body>
</html>
