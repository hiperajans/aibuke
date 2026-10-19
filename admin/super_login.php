<?php
session_start();
require __DIR__ . '/../config.php';

// Zaten giriş yapmışsa soru havuzuna yönlendir
if (!empty($_SESSION['super_admin_logged_in'])) {
    header('Location: question_pool.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, full_name FROM super_admins WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['super_admin_logged_in'] = true;
                $_SESSION['super_admin_id'] = $admin['id'];
                $_SESSION['super_admin_username'] = $admin['username'];
                $_SESSION['super_admin_name'] = $admin['full_name'];

                $pdo->prepare("UPDATE super_admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

                header('Location: question_pool.php');
                exit();
            } else {
                $error_message = 'Geçersiz kullanıcı adı veya şifre.';
            }
        } catch (PDOException $e) {
            $error_message = 'Giriş sırasında bir hata oluştu.';
            error_log("Super admin login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin - Aibuke</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #f59e0b;
            --primary-dark: #d97706;
            --primary-light: #fbbf24;
            --secondary: #8b5cf6;
            --bg-dark: #0f0f13;
            --bg-card: #18181b;
            --bg-input: #27272a;
            --text-primary: #fafafa;
            --text-secondary: #a1a1aa;
            --text-muted: #71717a;
            --border: #3f3f46;
            --error: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow-x: hidden;
        }
        .bg-pattern {
            position: fixed; inset: 0; z-index: 0; overflow: hidden;
        }
        .bg-pattern::before {
            content: '';
            position: absolute; width: 150%; height: 150%; top: -25%; left: -25%;
            background:
                radial-gradient(ellipse at 20% 30%, rgba(245,158,11,0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, rgba(139,92,246,0.1) 0%, transparent 50%);
            animation: bgPulse 15s ease-in-out infinite;
        }
        @keyframes bgPulse {
            0%, 100% { transform: translate(0,0) scale(1); }
            33% { transform: translate(2%,2%) scale(1.05); }
            66% { transform: translate(-1%,1%) scale(0.98); }
        }
        .glow-orb {
            position: absolute; border-radius: 50%; filter: blur(80px);
            animation: orbFloat 20s ease-in-out infinite;
        }
        .glow-orb:nth-child(1) { width:400px;height:400px;background:rgba(245,158,11,0.2);top:-100px;right:-100px; }
        .glow-orb:nth-child(2) { width:300px;height:300px;background:rgba(139,92,246,0.15);bottom:-50px;left:-50px;animation-delay:-7s; }
        @keyframes orbFloat {
            0%,100%{transform:translate(0,0)} 25%{transform:translate(30px,-30px)} 50%{transform:translate(-20px,20px)} 75%{transform:translate(20px,10px)}
        }
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            width: 100%; max-width: 420px;
            position: relative; z-index: 10;
            box-shadow: 0 35px 60px -15px rgba(0,0,0,0.6);
            animation: cardEntrance 0.8s cubic-bezier(0.16,1,0.3,1) forwards;
        }
        @keyframes cardEntrance {
            from { opacity:0; transform:translateY(40px) scale(0.96); }
            to { opacity:1; transform:translateY(0) scale(1); }
        }
        .header { text-align: center; margin-bottom: 2.5rem; }
        .logo {
            width:72px;height:72px;margin:0 auto 1.5rem;
            background:linear-gradient(135deg,var(--primary),var(--secondary));
            border-radius:20px;display:flex;align-items:center;justify-content:center;
            font-size:2rem;box-shadow:0 12px 32px rgba(245,158,11,0.35);
        }
        .header h1 { font-family:'Space Grotesk',sans-serif;font-size:1.75rem;font-weight:700;color:var(--text-primary);margin-bottom:0.5rem; }
        .header p { color:var(--text-muted);font-size:0.9rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display:block;font-size:0.8rem;font-weight:500;color:var(--text-secondary);margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:0.5px; }
        .input-wrapper { position: relative; }
        .input-wrapper svg { position:absolute;left:1rem;top:50%;transform:translateY(-50%);width:20px;height:20px;color:var(--text-muted);transition:color 0.3s; }
        .form-group input {
            width:100%;padding:1rem 1rem 1rem 3rem;background:var(--bg-input);
            border:1px solid var(--border);border-radius:14px;font-size:1rem;
            font-family:inherit;color:var(--text-primary);transition:all 0.3s;outline:none;
        }
        .form-group input::placeholder { color:var(--text-muted); }
        .form-group input:focus { border-color:var(--primary);box-shadow:0 0 0 4px rgba(245,158,11,0.15); }
        .input-wrapper:focus-within svg { color:var(--primary); }
        .submit-btn {
            width:100%;padding:1rem;background:linear-gradient(135deg,var(--primary),var(--primary-dark));
            border:none;border-radius:14px;font-size:1rem;font-weight:600;font-family:inherit;
            color:#0f0f13;cursor:pointer;transition:all 0.3s;display:flex;align-items:center;
            justify-content:center;gap:0.5rem;margin-top:2rem;
        }
        .submit-btn:hover { transform:translateY(-2px);box-shadow:0 12px 32px rgba(245,158,11,0.4); }
        .submit-btn:active { transform:translateY(0); }
        .submit-btn svg { width:20px;height:20px; }
        .error-message {
            background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);
            border-radius:12px;padding:1rem;margin-bottom:1.5rem;
            display:flex;align-items:center;gap:0.75rem;color:#fca5a5;font-size:0.9rem;
            animation:shake 0.5s ease-in-out;
        }
        .error-message svg { width:20px;height:20px;flex-shrink:0; }
        @keyframes shake { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-8px)} 40%{transform:translateX(8px)} 60%{transform:translateX(-4px)} 80%{transform:translateX(4px)} }
        .footer { text-align:center;margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--border); }
        .footer p { color:var(--text-muted);font-size:0.8rem; }
        .footer a { color:var(--primary-light);text-decoration:none;font-weight:500; }
        .footer a:hover { color:var(--primary); }
        @media (max-width:480px) {
            body { padding:1rem;align-items:flex-start;padding-top:2rem; }
            .login-card { padding:2rem 1.5rem;border-radius:20px; }
            .logo { width:60px;height:60px;font-size:1.5rem; }
            .header h1 { font-size:1.5rem; }
        }
    </style>
</head>
<body>
    <div class="bg-pattern">
        <div class="glow-orb"></div>
        <div class="glow-orb"></div>
    </div>

    <div class="login-card">
        <div class="header">
            <div class="logo">👑</div>
            <h1>SuperAdmin</h1>
            <p>Aibuke Soru Havuzu Yönetimi</p>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Kullanıcı Adı</label>
                <div class="input-wrapper">
                    <input type="text" id="username" name="username" placeholder="Kullanıcı adınızı girin" required autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Şifre</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" placeholder="Şifrenizi girin" required autocomplete="current-password">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
            </div>

            <button type="submit" class="submit-btn">
                <span>Giriş Yap</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </form>

        <div class="footer">
            <p>© <?= date('Y') ?> Aibuke</p>
        </div>
    </div>
</body>
</html>
