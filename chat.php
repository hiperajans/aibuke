<?php
/**
 * Aibuke Ogretmen - Modern Chat Sayfasi
 */

require_once 'config.php';
session_start();
require_once 'includes/auth.php';

// Cache önle — her zaman taze sayfa (Safari/iOS dahil)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
header('Surrogate-Control: no-store');

// Giris kontrolu
if (!isset($_SESSION['student_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'includes/StudentContext.php';
require_once 'includes/PointsManager.php';

// Ogrenci bilgilerini al
$student = getStudentInfo($pdo, $_SESSION['student_id']);
$profile = getOrCreateProfile($pdo, $_SESSION['student_id'], $student);

$studentName = htmlspecialchars($student['first_name']);
$studentClass = htmlspecialchars($student['class']);
$studentPhoto = !empty($student['photo'])
    ? '/uploads/photos/' . htmlspecialchars($student['photo'])
    : null;

$slogans = [
    'Her zaman yanında',
    'Nefes al, yanındayım!',
    'Başarı için buradayım',
    'Birlikte hallederiz',
    'Sen yapabilirsin!',
    'Hedefine odaklan',
    'Bugün bir adım daha',
];
$slogan = $slogans[array_rand($slogans)];

$pm = new PointsManager($pdo);
$studentPoints = $pm->getTotalPoints($_SESSION['student_id']);
$studentStreak = $pm->calculateStreak($_SESSION['student_id']);
$studentBadges = $pm->getStudentBadges($_SESSION['student_id']);
$studentRank = $pm->getStudentRank($_SESSION['student_id'], $student['school_id']);
$badgeIcons = implode('', array_column(array_slice($studentBadges, 0, 3), 'badge_icon'));

$dailyLimit = checkDailyLimit($pdo, $_SESSION['student_id']);
$limitReached = !$dailyLimit['can_send'];
$limitResetMinutes = (int)((strtotime('tomorrow') - time()) / 60);
$limitResetHours = intdiv($limitResetMinutes, 60);
$limitResetMins = $limitResetMinutes % 60;
$limitResetStr = $limitResetHours > 0
    ? "{$limitResetHours} saat {$limitResetMins} dakika"
    : "{$limitResetMins} dakika";
?>
<!DOCTYPE html>
<html lang="tr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Aibüke Öğretmen - Sohbet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Grotesk:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ═══ THEME VARIABLES ═══ */
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --primary-glow: rgba(99, 102, 241, 0.15);
            --accent: #ec4899;
            --accent-light: #f472b6;
            --success: #22c55e;
            --error: #ef4444;
            --warning: #f59e0b;
            --user-bubble: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --user-bubble-text: #ffffff;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }

        /* ─── DARK THEME (default) ─── */
        [data-theme="dark"] {
            --bg-dark: #0a0a0f;
            --bg-chat: #0e0e14;
            --bg-card: #121218;
            --bg-surface: #1a1a24;
            --bg-input: #16161e;
            --bg-hover: #22222e;
            --bg-header: rgba(18, 18, 24, 0.85);
            --bg-input-area: rgba(18, 18, 24, 0.9);
            --text-primary: #fafafa;
            --text-secondary: #a1a1aa;
            --text-muted: #71717a;
            --border: #2a2a35;
            --border-light: #3a3a48;
            --ai-bubble: #1a1a24;
            --ai-bubble-border: #2a2a35;
            --code-bg: rgba(0, 0, 0, 0.3);
            --pre-bg: rgba(0, 0, 0, 0.4);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.2);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.4);
            --chat-gradient-1: rgba(99, 102, 241, 0.04);
            --chat-gradient-2: rgba(236, 72, 153, 0.03);
            --quick-border-bottom: rgba(42, 42, 53, 0.5);
            --welcome-card-hover: rgba(99, 102, 241, 0.05);
            --avatar-border: var(--bg-card);
            --bold-color: #818cf8;
            --user-bold-color: #e0e7ff;
            --scrollbar-thumb: #2a2a35;
            --user-bubble-shadow: 0 2px 12px rgba(99, 102, 241, 0.25);
            --header-avatar-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
        }

        /* ─── LIGHT THEME ─── */
        [data-theme="light"] {
            --bg-dark: #f5f5f7;
            --bg-chat: #ffffff;
            --bg-card: #ffffff;
            --bg-surface: #f0f0f5;
            --bg-input: #f5f5f7;
            --bg-hover: #e8e8f0;
            --bg-header: rgba(255, 255, 255, 0.85);
            --bg-input-area: rgba(255, 255, 255, 0.9);
            --text-primary: #18181b;
            --text-secondary: #52525b;
            --text-muted: #a1a1aa;
            --border: #e4e4e7;
            --border-light: #d4d4d8;
            --ai-bubble: #f4f4f5;
            --ai-bubble-border: #e4e4e7;
            --code-bg: rgba(0, 0, 0, 0.06);
            --pre-bg: rgba(0, 0, 0, 0.04);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.1);
            --chat-gradient-1: rgba(99, 102, 241, 0.03);
            --chat-gradient-2: rgba(236, 72, 153, 0.02);
            --quick-border-bottom: rgba(228, 228, 231, 0.8);
            --welcome-card-hover: rgba(99, 102, 241, 0.06);
            --avatar-border: #ffffff;
            --bold-color: #4f46e5;
            --user-bold-color: #e0e7ff;
            --scrollbar-thumb: #d4d4d8;
            --user-bubble-shadow: 0 2px 12px rgba(99, 102, 241, 0.2);
            --header-avatar-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--bg-dark);
            height: 100vh;
            height: 100svh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            transition: background-color 0.3s ease;
        }

        /* ═══ HEADER ═══ */
        .header {
            background: var(--bg-header);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            padding: 10px 16px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            z-index: 100;
            position: relative;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .header-top {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
        }

        .header-avatar {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: var(--radius-md);
            flex-shrink: 0;
            position: relative;
            box-shadow: var(--header-avatar-shadow);
            overflow: visible;
            align-self: center;
        }
        .header-avatar img {
            position: absolute;
            bottom: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 120%;
            height: auto;
            object-fit: cover;
            object-position: center top;
            z-index: 1;
        }

        .header-avatar::after {
            content: '';
            position: absolute;
            bottom: -1px;
            right: -1px;
            width: 10px;
            height: 10px;
            background: var(--success);
            border-radius: 50%;
            border: 2px solid var(--avatar-border);
            box-shadow: 0 0 6px rgba(34, 197, 94, 0.5);
            z-index: 2;
        }

        .header-title {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
            min-width: 0;
        }
        .header-title-main {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-light), var(--accent-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.1;
            letter-spacing: -0.3px;
        }
        .header-title-sub {
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 0.3px;
        }
        .header-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .header-status {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .header-actions {
            display: flex;
            gap: 4px;
            margin-left: auto;
        }

        .header-btn {
            background: transparent;
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            padding: 8px 10px;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 15px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-btn:hover {
            background: var(--bg-surface);
            color: var(--text-secondary);
            border-color: var(--border);
        }

        /* ═══ QUICK ACTIONS ═══ */
        .quick-actions {
            display: flex;
            gap: 6px;
            padding: 8px 16px;
            background: var(--bg-dark);
            border-bottom: 1px solid var(--quick-border-bottom);
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .quick-actions::-webkit-scrollbar { display: none; }

        .quick-btn {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 6px 12px;
            color: var(--text-secondary);
            font-size: 12px;
            font-family: 'DM Sans', sans-serif;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .quick-btn:hover {
            border-color: var(--primary);
            color: var(--primary-light);
            background: var(--primary-glow);
        }
        .quick-btn:active { transform: scale(0.96); }

        .quick-btn .icon {
            font-size: 13px;
            line-height: 1;
        }

        /* ═══ CHAT CONTAINER ═══ */
        .chat-container {
            flex: 1;
            overflow-y: auto;
            padding: 24px 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            scroll-behavior: smooth;
            background: var(--bg-chat);
            background-image:
                radial-gradient(ellipse at 15% 85%, var(--chat-gradient-1) 0%, transparent 50%),
                radial-gradient(ellipse at 85% 15%, var(--chat-gradient-2) 0%, transparent 50%);
        }

        .chat-container::-webkit-scrollbar {
            width: 4px;
        }

        .chat-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-container::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 4px;
        }

        /* ═══ WELCOME ═══ */
        .welcome-msg {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 48px 24px 32px;
            animation: fadeUp 0.5s ease;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .welcome-avatar {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.3);
            animation: float 4s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .welcome-msg h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .welcome-msg p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
            max-width: 300px;
        }

        .welcome-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 28px;
            width: 100%;
            max-width: 360px;
        }

        .welcome-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 16px 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
        }

        .welcome-card:hover {
            border-color: var(--primary);
            background: var(--welcome-card-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .welcome-card .card-icon {
            font-size: 20px;
            margin-bottom: 8px;
            display: block;
        }

        .welcome-card .card-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .welcome-card .card-desc {
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* ═══ MESSAGES ═══ */
        .message-group {
            display: flex;
            gap: 10px;
            animation: msgIn 0.3s ease;
            max-width: 80%;
        }

        .message-group.user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .message-group.ai {
            align-self: flex-start;
        }

        @keyframes msgIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .msg-avatar {
            width: 28px;
            height: 28px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
            align-self: flex-end;
            overflow: hidden;
        }
        .msg-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
        }

        .message-group.ai .msg-avatar {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            overflow: hidden;
            position: relative;
            align-self: flex-end;
        }
        .message-group.ai .msg-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            border-radius: 0;
        }

        .message-group.user .msg-avatar {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            font-size: 12px;
            color: var(--text-secondary);
        }

        .msg-content {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .msg-bubble {
            padding: 12px 16px;
            font-size: 14px;
            line-height: 1.65;
            white-space: pre-wrap;
            word-wrap: break-word;
            position: relative;
            user-select: none;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
        }

        .message-group.user .msg-bubble {
            background: var(--user-bubble);
            color: var(--user-bubble-text);
            border-radius: var(--radius-lg) var(--radius-lg) 4px var(--radius-lg);
            box-shadow: var(--user-bubble-shadow);
        }

        .message-group.ai .msg-bubble {
            background: var(--ai-bubble);
            color: var(--text-primary);
            border-radius: var(--radius-lg) var(--radius-lg) var(--radius-lg) 4px;
            border: 1px solid var(--ai-bubble-border);
        }

        .choice-btn {
            display: block;
            width: 100%;
            margin: 5px 0 2px 0;
            padding: 10px 14px;
            background: var(--bg-surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 14px;
            text-align: left;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: inherit;
        }
        .choice-btn:hover:not(:disabled) {
            border-color: var(--primary);
            background: var(--primary-glow);
            color: var(--primary-light);
        }
        .choice-btn.selected {
            border-color: var(--primary);
            background: var(--primary-glow);
            color: var(--primary-light);
            font-weight: 600;
        }
        .choice-btn:disabled {
            opacity: 0.55;
            cursor: default;
        }
        .btn-stop {
            background: rgba(239, 68, 68, 0.15);
            border-color: #ef4444;
            color: #ef4444;
        }
        .btn-stop:hover {
            background: rgba(239, 68, 68, 0.3) !important;
        }
        .choice-btn.correct {
            border-color: #22c55e !important;
            background: rgba(34, 197, 94, 0.15) !important;
            color: #22c55e !important;
            opacity: 1 !important;
            font-weight: 600;
        }
        .choice-btn.wrong {
            border-color: #ef4444 !important;
            background: rgba(239, 68, 68, 0.15) !important;
            color: #ef4444 !important;
            opacity: 1 !important;
        }

        .continue-btn {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 14px;
            background: transparent;
            border: 1.5px solid var(--primary);
            border-radius: var(--radius-sm);
            color: var(--primary-light);
            font-size: 13px;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.15s ease;
        }
        .continue-btn:hover {
            background: var(--primary-glow);
        }

        /* ═══ SCHEDULE CARD ═══ */
        .schedule-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-top: 8px;
            max-width: 100%;
        }
        .schedule-card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .schedule-card-header-icon {
            font-size: 24px;
        }
        .schedule-card-header-text {
            flex: 1;
        }
        .schedule-card-header-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: white;
        }
        .schedule-card-header-sub {
            font-size: 11px;
            color: rgba(255,255,255,0.8);
            margin-top: 2px;
        }
        .schedule-days {
            padding: 0;
        }
        .schedule-day {
            border-bottom: 1px solid var(--border);
        }
        .schedule-day:last-child {
            border-bottom: none;
        }
        .schedule-day-header {
            padding: 10px 14px;
            background: var(--bg-surface);
            font-size: 13px;
            font-weight: 700;
            font-family: 'Space Grotesk', sans-serif;
            color: var(--primary-light);
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }
        .schedule-day-header:hover {
            background: var(--bg-hover);
        }
        .schedule-day-header .day-toggle {
            font-size: 10px;
            color: var(--text-muted);
            transition: transform 0.2s;
        }
        .schedule-day.collapsed .day-toggle {
            transform: rotate(-90deg);
        }
        .schedule-day.collapsed .schedule-sessions {
            display: none;
        }
        .schedule-day-header .day-total {
            margin-left: auto;
            font-size: 11px;
            font-weight: 500;
            color: var(--text-muted);
        }
        .schedule-sessions {
            padding: 6px 10px;
        }
        .schedule-session {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 6px;
            border-radius: var(--radius-sm);
            transition: background 0.15s;
        }
        .schedule-session:hover {
            background: var(--bg-hover);
        }
        .schedule-session-time {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--text-muted);
            white-space: nowrap;
            min-width: 80px;
            padding-top: 2px;
        }
        .schedule-session-info {
            flex: 1;
            min-width: 0;
        }
        .schedule-session-subject {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .schedule-session-topic {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 1px;
        }
        .schedule-session-type {
            display: inline-block;
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 100px;
            font-weight: 500;
            margin-top: 3px;
        }
        .schedule-session-type.type-konu {
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary-light);
        }
        .schedule-session-type.type-soru {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
        }
        .schedule-session-type.type-tekrar {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }
        .schedule-session-type.type-deneme {
            background: rgba(236, 72, 153, 0.15);
            color: var(--accent);
        }
        .schedule-card-notes {
            padding: 10px 14px;
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.5;
            border-top: 1px solid var(--border);
            background: var(--bg-surface);
        }
        .schedule-card-motivation {
            padding: 12px 14px;
            font-size: 13px;
            color: var(--primary-light);
            text-align: center;
            font-weight: 500;
            background: linear-gradient(135deg, var(--primary-glow), rgba(236,72,153,0.05));
            border-top: 1px solid var(--border);
        }
        .schedule-card-actions {
            padding: 10px 14px;
            display: flex;
            gap: 8px;
            border-top: 1px solid var(--border);
        }
        .schedule-save-btn {
            flex: 1;
            padding: 10px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .schedule-save-btn:hover { background: var(--primary-dark); }
        .schedule-save-btn:disabled { opacity: 0.5; cursor: default; }
        .schedule-save-btn.saved {
            background: var(--success);
        }
        .schedule-regen-btn {
            padding: 10px 16px;
            background: var(--bg-surface);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
        }
        .schedule-regen-btn:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        /* Schedule card makes AI message wider */
        .message-group.ai.has-schedule {
            max-width: 95%;
        }
        @media (max-width: 480px) {
            .message-group.ai.has-schedule {
                max-width: 100%;
            }
            .schedule-session-time {
                min-width: 65px;
                font-size: 10px;
            }
        }

        /* AI message rich content */
        .msg-bubble strong {
            color: var(--bold-color);
            font-weight: 600;
        }

        .message-group.user .msg-bubble strong {
            color: var(--user-bold-color);
        }

        .msg-bubble code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            background: var(--code-bg);
            padding: 2px 6px;
            border-radius: 4px;
        }

        .msg-bubble pre {
            background: var(--pre-bg);
            border-radius: var(--radius-sm);
            padding: 12px;
            margin: 8px 0;
            overflow-x: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            line-height: 1.5;
            border: 1px solid var(--border);
        }

        .msg-bubble pre code {
            background: none;
            padding: 0;
        }

        .msg-time {
            font-size: 11px;
            color: var(--text-muted);
            padding: 0 4px;
        }

        .message-group.user .msg-time {
            text-align: right;
        }

        /* ═══ TYPING INDICATOR ═══ */
        .typing-group {
            display: none;
            gap: 10px;
            align-self: flex-start;
            max-width: 80%;
        }

        .typing-group.active {
            display: flex;
        }

        .typing-bubble {
            background: var(--ai-bubble);
            border: 1px solid var(--ai-bubble-border);
            border-radius: var(--radius-lg) var(--radius-lg) var(--radius-lg) 4px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .typing-bubble span {
            width: 7px;
            height: 7px;
            background: var(--text-muted);
            border-radius: 50%;
            animation: typingDot 1.4s infinite;
        }

        .typing-bubble span:nth-child(2) { animation-delay: 0.15s; }
        .typing-bubble span:nth-child(3) { animation-delay: 0.3s; }

        @keyframes typingDot {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-6px); opacity: 1; }
        }

        /* Streaming cursor - Glow Effect */
        .streaming-cursor {
            display: inline-block;
            width: 2px;
            height: 18px;
            background: linear-gradient(180deg, var(--primary-light) 0%, var(--accent-light) 100%);
            margin-left: 2px;
            animation: blink 0.7s infinite;
            vertical-align: middle;
            border-radius: 2px;
            box-shadow: 0 0 8px var(--primary-light), 0 0 15px var(--primary-glow);
        }

        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }

        /* AI Message Bubble - Pulse Effect */
        .message-group.ai.streaming .msg-bubble {
            animation: bubblePulse 1.5s ease-in-out infinite;
        }

        @keyframes bubblePulse {
            0%, 100% { box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
            50% { box-shadow: 0 0 20px var(--primary-glow), 0 4px 15px rgba(99, 102, 241, 0.15); }
        }

        /* Message appear animation */
        .message-group.ai .msg-bubble {
            animation: messageSettle 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes messageSettle {
            0% { opacity: 0; transform: translateY(10px) scale(0.95); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ═══ THEME TOGGLE ═══ */
        .theme-toggle {
            position: relative;
            width: 56px;
            height: 28px;
            flex-shrink: 0;
            cursor: pointer;
        }

        .theme-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
            position: absolute;
        }

        .theme-slider {
            position: absolute;
            inset: 0;
            border-radius: 100px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        /* Dark state (checked = dark) */
        .theme-toggle input:checked + .theme-slider {
            background: #1a1a2e;
            border: 1.5px solid #2a2a45;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3), inset 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        /* Light state (unchecked = light) */
        .theme-toggle input:not(:checked) + .theme-slider {
            background: #ffffff;
            border: 1.5px solid #93c5fd;
            box-shadow: 0 2px 8px rgba(147, 197, 253, 0.25);
        }

        /* Knob */
        .theme-slider::before {
            content: '';
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 22px;
            height: 22px;
            border-radius: 50%;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2;
        }

        /* Dark state knob — left, lavender white */
        .theme-toggle input:checked + .theme-slider::before {
            left: 2px;
            background: linear-gradient(145deg, #e0e0f0 0%, #c8c8e0 100%);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
        }

        /* Light state knob — right, sky blue */
        .theme-toggle input:not(:checked) + .theme-slider::before {
            left: calc(100% - 24px);
            background: linear-gradient(145deg, #93c5fd 0%, #7ab8fc 100%);
            box-shadow: 0 1px 4px rgba(147, 197, 253, 0.4);
        }

        /* Icon container */
        .theme-icons {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: absolute;
            inset: 0;
            padding: 0 6px;
            pointer-events: none;
            z-index: 1;
        }

        .theme-icon {
            font-size: 13px;
            line-height: 1;
            transition: all 0.4s;
        }

        /* Sun icon — visible in light mode (unchecked), hidden behind knob in dark */
        .theme-icon-sun {
            opacity: 0;
            transform: scale(0.5) rotate(-90deg);
        }

        .theme-toggle input:not(:checked) ~ .theme-icons .theme-icon-sun {
            opacity: 1;
            transform: scale(1) rotate(0deg);
        }

        /* Moon icon — visible in dark mode (checked), hidden behind knob in light */
        .theme-icon-moon {
            opacity: 0;
            transform: scale(0.5) rotate(90deg);
        }

        .theme-toggle input:checked ~ .theme-icons .theme-icon-moon {
            opacity: 1;
            transform: scale(1) rotate(0deg);
        }

        /* ═══ INPUT AREA ═══ */
        .input-area {
            background: var(--bg-input-area);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-top: 1px solid var(--border);
            padding: 16px 20px;
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }

        .input-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .image-preview {
            display: none;
            position: relative;
            padding: 10px;
            background: var(--bg-surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            animation: slideUp 0.2s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .image-preview.active {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .image-preview img {
            max-width: 72px;
            max-height: 72px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            border: 1px solid var(--border);
        }

        .image-preview .preview-info {
            flex: 1;
            font-size: 12px;
            color: var(--text-muted);
        }

        .image-preview .remove-image {
            width: 26px;
            height: 26px;
            background: var(--bg-hover);
            color: var(--text-muted);
            border: 1px solid var(--border);
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .image-preview .remove-image:hover {
            background: var(--error);
            color: white;
            border-color: var(--error);
        }

        .input-field-wrapper {
            display: flex;
            align-items: flex-end;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            transition: all 0.2s;
            padding: 4px;
        }

        .input-field-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .input-field-wrapper textarea {
            flex: 1;
            padding: 10px 12px;
            border: none;
            background: transparent;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text-primary);
            outline: none;
            resize: none;
            max-height: 120px;
            line-height: 1.5;
        }

        .input-field-wrapper textarea::placeholder {
            color: var(--text-muted);
        }

        /* Limit reached state */
        .input-field-wrapper.limit-reached {
            border-color: var(--warning);
            background: rgba(245, 158, 11, 0.05);
        }
        .input-field-wrapper.limit-reached textarea {
            color: var(--text-muted);
            cursor: not-allowed;
        }
        .input-field-wrapper.limit-reached + .limit-timer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 12px;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(239, 68, 68, 0.1) 100%);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: var(--radius-sm);
            margin-top: 8px;
            color: var(--warning);
            font-size: 13px;
            font-weight: 500;
            animation: limitFadeIn 0.4s ease-out;
        }
        @keyframes limitFadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .limit-timer i {
            font-size: 14px;
        }
        .limit-timer .time {
            color: var(--text-primary);
            font-weight: 600;
        }

        .input-actions {
            display: flex;
            align-items: center;
            gap: 2px;
            padding: 2px;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            background: transparent;
            color: var(--text-muted);
        }

        .action-btn:hover {
            background: var(--bg-hover);
            color: var(--text-secondary);
        }

        .action-btn.btn-send {
            background: var(--primary);
            color: white;
            border-radius: var(--radius-sm);
        }

        .action-btn.btn-send:hover {
            background: var(--primary-dark);
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.4);
        }

        .action-btn.btn-send:disabled {
            opacity: 0.35;
            cursor: not-allowed;
            box-shadow: none;
        }

        #imageInput {
            display: none;
        }

        /* ═══ MOBILE ═══ */
        @media (max-width: 600px) {
            .header {
                padding: 10px 16px;
            }

            .message-group {
                max-width: 88%;
            }

            .chat-container {
                padding: 16px 14px;
                gap: 16px;
            }

            .msg-avatar {
                display: none;
            }
            .message-group {
                max-width: 96%;
            }
            .msg-content {
                width: 100%;
            }
            .message-group.user .msg-content {
                align-items: flex-end;
            }
            .msg-bubble {
                padding: 12px 16px;
                font-size: 15px;
                line-height: 1.7;
            }

            .welcome-cards {
                max-width: 100%;
            }

            .quick-actions {
                padding: 6px 10px;
                gap: 5px;
            }
            .quick-btn {
                padding: 5px 10px;
                font-size: 11px;
            }

            .input-area {
                padding: 12px 14px;
            }
        }

        /* ═══ SAFE AREA (notched phones) ═══ */
        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .input-area {
                padding-bottom: calc(16px + env(safe-area-inset-bottom));
            }
        }

        /* ═══ FAZ 2: PUAN/STREAK BAR ═══ */
        .stats-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--text-muted);
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .stats-bar::-webkit-scrollbar { display: none; }
        .stat-chip {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            padding: 4px 10px;
            border-radius: 8px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            transition: all 0.2s;
            position: relative;
            flex-shrink: 0;
            white-space: nowrap;
        }
        .stat-chip:hover {
            border-color: var(--primary);
            background: var(--primary-glow);
        }
        .stat-chip:active { transform: scale(0.96); }
        .stat-chip-icon {
            font-size: 13px;
            line-height: 1;
        }
        .stat-chip-info {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }
        .stat-chip-value {
            font-weight: 700;
            font-size: 12px;
            color: var(--text-primary);
            font-family: 'Space Grotesk', sans-serif;
        }
        .stat-chip-label {
            font-size: 8px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .stat-chip.points { border-color: rgba(99, 102, 241, 0.3); }
        .stat-chip.points:hover { border-color: var(--primary); }
        .stat-chip.streak { border-color: rgba(245, 158, 11, 0.3); }
        .stat-chip.streak:hover { border-color: var(--warning); }
        .badges-chip { border-color: rgba(236, 72, 153, 0.3); position: relative; }
        .badges-chip:hover { border-color: var(--accent); }
        .badges-popup {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 200px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            z-index: 150;
            padding: 10px 0;
            animation: msgIn 0.2s ease;
        }
        .badges-popup.show { display: block; }
        .badges-popup-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2px 14px 8px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 4px;
        }
        .badge-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 14px;
        }
        .badge-medal {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(145deg, var(--bg-surface), var(--bg-hover));
            border: 2px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15), inset 0 1px 2px rgba(255,255,255,0.1);
        }
        .badge-row-info { display: flex; flex-direction: column; gap: 2px; }
        .badge-row-name { font-size: 13px; font-weight: 600; color: var(--text-primary); }
        .badge-row-desc { font-size: 11px; color: var(--text-muted); }

        /* ═══ FAZ 2: VOICE BANNER ═══ */
        .voice-banner {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(236, 72, 153, 0.08) 100%);
            border-bottom: 1px solid var(--border);
            animation: msgIn 0.4s ease;
        }
        .voice-banner.show { display: flex; }
        .voice-banner-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .voice-banner-icon:hover { transform: scale(1.1); }
        .voice-banner-icon.playing { animation: pulse 1s ease infinite; }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }
        .voice-banner-text {
            flex: 1;
            min-width: 0;
        }
        .voice-banner-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .voice-banner-preview {
            font-size: 11px;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 1px;
        }
        .voice-banner-close {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 14px;
            padding: 4px;
            border-radius: 4px;
            transition: color 0.2s;
        }
        .voice-banner-close:hover { color: var(--text-primary); }

        /* ═══ FAZ 2: LISTEN BUTTON ═══ */
        .message-group.ai .msg-bubble { padding-bottom: 32px; }
        .btn-listen {
            position: absolute;
            bottom: 6px;
            right: 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border: 1px solid var(--border-light);
            border-radius: 100px;
            color: var(--text-secondary);
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0;
            white-space: nowrap;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .msg-bubble:hover .btn-listen { opacity: 1; }
        .btn-listen:hover {
            border-color: var(--primary-light);
            color: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }
        .btn-listen:active { transform: scale(0.95); }
        .btn-listen.playing {
            border-color: var(--primary);
            color: #fff;
            opacity: 1;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            animation: listenPulse 1.5s ease-in-out infinite;
        }
        .btn-listen.playing.activating {
            animation: playActivating 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes playActivating {
            0% { transform: scale(1); }
            30% { transform: scale(0.85); }
            60% { transform: scale(1.1); box-shadow: 0 0 30px rgba(99, 102, 241, 0.9); }
            100% { transform: scale(1); }
        }
        @keyframes listenPulse {
            0%, 100% { box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4); }
            50% { box-shadow: 0 4px 25px rgba(99, 102, 241, 0.6); }
        }
        .btn-listen i { font-size: 12px; }
        .btn-listen .btn-text { margin-left: 2px; }
        /* Waveform Animation */
        .btn-listen .waveform {
            display: none;
            align-items: flex-end;
            gap: 2px;
            height: 14px;
        }
        .btn-listen.playing .volume-up { display: none; }
        .btn-listen.playing .waveform { display: flex; }
        .btn-listen .waveform span {
            width: 3px;
            background: currentColor;
            border-radius: 2px;
            animation: wave 0.8s ease-in-out infinite;
        }
        .btn-listen .waveform span:nth-child(1) { height: 40%; animation-delay: 0s; }
        .btn-listen .waveform span:nth-child(2) { height: 70%; animation-delay: 0.15s; }
        .btn-listen .waveform span:nth-child(3) { height: 100%; animation-delay: 0.3s; }
        .btn-listen .waveform span:nth-child(4) { height: 60%; animation-delay: 0.45s; }
        @keyframes wave {
            0%, 100% { transform: scaleY(0.5); opacity: 0.6; }
            50% { transform: scaleY(1); opacity: 1; }
        }

        /* ═══ FAZ 2: SLIDE PANEL ═══ */
        .slide-panel {
            position: fixed;
            top: 0;
            right: -420px;
            width: min(400px, 90vw);
            height: 100vh;
            height: 100dvh;
            background: var(--bg-card);
            border-left: 1px solid var(--border);
            z-index: 200;
            transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
        }
        .slide-panel.open { right: 0; }
        .panel-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 199;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        .panel-overlay.open { opacity: 1; pointer-events: auto; }
        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }
        .panel-header h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
            font-weight: 600;
        }
        .panel-close {
            width: 32px; height: 32px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 16px;
            display: flex; align-items: center; justify-content: center;
        }
        .panel-close:hover { color: var(--text-primary); }
        .panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        /* ═══ FAZ 2: LEADERBOARD ═══ */
        .lb-my-rank {
            background: linear-gradient(135deg, var(--primary-glow), rgba(236,72,153,0.08));
            border: 1px solid var(--primary);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-bottom: 20px;
            text-align: center;
        }
        .lb-my-rank .rank-pos {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-light);
        }
        .lb-my-rank .rank-label { font-size: 12px; color: var(--text-muted); }
        .lb-my-rank .rank-points {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        .lb-filter {
            padding: 5px 12px;
            border: 1px solid var(--border);
            border-radius: 20px;
            background: transparent;
            color: var(--text-muted);
            font-size: 11px;
            cursor: pointer;
            transition: all 0.15s;
            font-family: inherit;
        }
        .lb-filter.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .lb-filter:hover:not(.active) { border-color: var(--primary); color: var(--primary); }
        .lb-list { list-style: none; }
        .lb-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .lb-pos {
            width: 28px;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-muted);
            text-align: center;
        }
        .lb-item:nth-child(1) .lb-pos { color: #fbbf24; font-size: 18px; }
        .lb-item:nth-child(2) .lb-pos { color: #94a3b8; font-size: 16px; }
        .lb-item:nth-child(3) .lb-pos { color: #cd7f32; font-size: 16px; }
        .lb-name { flex: 1; font-size: 14px; color: var(--text-primary); }
        .lb-class { font-size: 11px; color: var(--text-muted); margin-left: 4px; }
        .lb-points {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
            font-size: 14px;
            color: var(--primary-light);
        }
        .lb-badges { font-size: 12px; letter-spacing: -1px; }

        /* ═══ FAZ 3: CALENDAR ═══ */
        .cal-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .cal-nav-btn {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.15s;
        }
        .cal-nav-btn:hover { border-color: var(--primary); color: var(--primary-light); }
        .cal-month-label {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        .cal-weekday {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-muted);
            text-align: center;
            text-transform: uppercase;
            padding: 4px 0 8px;
            letter-spacing: 0.5px;
        }
        .cal-day {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            position: relative;
            transition: all 0.15s;
        }
        .cal-day.empty { visibility: hidden; }
        .cal-day.today {
            border: 1.5px solid var(--primary);
            color: var(--primary-light);
            font-weight: 700;
        }
        .cal-day.active {
            background: var(--primary);
            color: #fff;
            font-weight: 600;
        }
        .cal-day.active.low { background: rgba(99,102,241,0.35); color: var(--primary-light); }
        .cal-day.active.med { background: rgba(99,102,241,0.6); color: #fff; }
        .cal-day.active.high { background: var(--primary); color: #fff; }
        .cal-day .cal-dot {
            width: 4px; height: 4px;
            border-radius: 50%;
            background: var(--success);
            position: absolute;
            bottom: 3px;
        }
        .cal-streak-card {
            background: linear-gradient(135deg, var(--primary-glow), rgba(236,72,153,0.05));
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .cal-streak-num {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-light);
            line-height: 1;
        }
        .cal-streak-info { flex: 1; }
        .cal-streak-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .cal-streak-sub {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .cal-summary {
            margin-top: 16px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .cal-stat {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px 8px;
            text-align: center;
        }
        .cal-stat-val {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .cal-stat-lbl {
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ═══ FAZ 2: DAILY PLAN ═══ */
        .plan-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 14px;
            margin-bottom: 10px;
        }
        .plan-card .plan-subject {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .plan-card .plan-duration {
            font-size: 12px;
            color: var(--primary-light);
            margin-bottom: 6px;
        }
        .plan-card .plan-desc {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.5;
        }
        .plan-motivation {
            background: linear-gradient(135deg, var(--primary-glow), rgba(236,72,153,0.05));
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-top: 16px;
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.6;
            text-align: center;
        }
        .plan-complete-btn {
            display: block;
            width: 100%;
            margin-top: 16px;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
        }
        .plan-complete-btn:hover { background: var(--primary-dark); }
        .plan-complete-btn:disabled { opacity: 0.5; cursor: default; }
        .plan-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        /* ═══ FAZ 2: EXAM SIM ═══ */
        /* ─── Exam Modal (Full Screen) ─── */
        .sim-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: #fff;
            z-index: 2000;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
        }
        [data-theme="dark"] .sim-modal-overlay { background: #111118; }
        .sim-modal-overlay.open { display: block; }
        .sim-modal {
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
            padding: 0 16px;
            padding-bottom: env(safe-area-inset-bottom, 20px);
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes themeFlash {
            0% { opacity: 0; }
            30% { opacity: 1; }
            100% { opacity: 0; }
        }
        .sim-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            position: sticky;
            top: 0;
            z-index: 20;
            background: #fff;
        }
        [data-theme="dark"] .sim-modal-header { background: #111118; }
        .sim-modal-header h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
        }
        .sim-modal-close {
            width: 28px; height: 28px;
            display: flex; align-items: center; justify-content: center;
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: 50%; color: var(--text-secondary);
            font-size: 14px; cursor: pointer;
            transition: all 0.15s;
        }
        .sim-modal-close:hover { background: var(--border); }
        .sim-modal-body {
            padding-bottom: 40px;
            animation: modalFadeIn 0.35s ease-out;
        }
        .sim-modal-body:not(.exam-active) {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: calc(100vh - 80px);
        }

        /* Setup */
        .sim-setup {
            text-align: center;
            max-width: 420px;
            margin: 0 auto;
            width: 100%;
        }
        .sim-setup-icon {
            font-size: 56px;
            margin-bottom: 12px;
        }
        .sim-setup-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        .sim-setup-desc {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .sim-fields-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
            text-align: left;
        }
        @media (max-width: 480px) {
            .sim-fields-grid { grid-template-columns: 1fr; }
        }
        /* Soru sayısı butonları */
        .sim-count-row {
            display: flex; gap: 8px;
        }
        .sim-count-btn {
            flex: 1;
            padding: 12px 4px;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--bg-surface);
            color: var(--text-secondary);
            font-size: 18px;
            font-weight: 700;
            font-family: 'Space Grotesk', sans-serif;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .sim-count-btn::after {
            content: attr(data-label);
            display: block;
            font-size: 10px;
            font-weight: 500;
            color: var(--text-muted);
            margin-top: 2px;
            font-family: 'DM Sans', sans-serif;
            letter-spacing: 0.3px;
        }
        .sim-count-btn:hover {
            border-color: var(--primary);
            color: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99,102,241,0.15);
        }
        .sim-count-btn.active {
            border-color: var(--primary);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 4px 16px rgba(99,102,241,0.3);
        }
        .sim-count-btn.active::after { color: rgba(255,255,255,0.7); }

        /* Countdown overlay */
        .sim-countdown-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-dark);
            z-index: 10;
        }
        .sim-countdown-num {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 120px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-light), var(--accent-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: countdownPop 1s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            filter: drop-shadow(0 0 40px rgba(99,102,241,0.4));
        }
        .sim-countdown-label {
            position: absolute;
            bottom: 25%;
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
            animation: fadeUp 0.4s ease;
        }
        @keyframes countdownPop {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.15); opacity: 1; }
            70% { transform: scale(0.95); }
            100% { transform: scale(1); opacity: 1; }
        }
        .sim-countdown-ring {
            position: absolute;
            width: 180px; height: 180px;
            border-radius: 50%;
            border: 3px solid transparent;
            border-top-color: var(--primary-light);
            border-right-color: var(--accent-light);
            animation: countdownSpin 1s linear;
            opacity: 0.4;
        }
        @keyframes countdownSpin {
            0% { transform: rotate(0deg) scale(0.8); opacity: 0; }
            50% { opacity: 0.6; }
            100% { transform: rotate(360deg) scale(1.2); opacity: 0; }
        }
        .sim-countdown-go {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 64px;
            font-weight: 700;
            color: var(--success);
            animation: countdownGo 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            text-shadow: 0 0 40px rgba(34,197,94,0.5);
        }
        @keyframes countdownGo {
            0% { transform: scale(0.2) rotate(-10deg); opacity: 0; }
            60% { transform: scale(1.2) rotate(3deg); opacity: 1; }
            100% { transform: scale(1) rotate(0); opacity: 1; }
        }
        .sim-field { margin-bottom: 0; }
        .sim-field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sim-field select, .sim-field input[type="number"] {
            width: 100%;
            padding: 11px 12px;
            background: var(--bg-surface);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.15s;
        }
        .sim-field select:focus, .sim-field input:focus {
            outline: none;
            border-color: var(--primary);
        }
        .sim-accordion {
            border: 1.5px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        .sim-accordion-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 11px 14px;
            background: var(--bg-surface);
            border: none;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s;
        }
        .sim-accordion-toggle:hover { color: var(--text-primary); }
        .sim-acc-hint {
            font-weight: 400;
            color: var(--text-muted);
            font-size: 11px;
        }
        .sim-acc-badge {
            margin-left: auto;
            font-size: 11px;
            font-weight: 600;
            color: var(--primary);
        }
        .sim-acc-icon {
            font-size: 11px;
            transition: transform 0.2s;
            color: var(--text-muted);
        }
        .sim-accordion-toggle.open .sim-acc-icon {
            transform: rotate(180deg);
        }
        .sim-accordion-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.25s ease;
            padding: 0 12px;
        }
        .sim-accordion-body.open {
            max-height: 200px;
            padding: 8px 12px 12px;
            overflow-y: auto;
        }
        .sim-topic-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            max-height: 120px;
            overflow-y: auto;
            padding: 2px 0;
        }
        .sim-topic-chip {
            padding: 6px 12px;
            border: 1.5px solid var(--border);
            border-radius: 20px;
            background: var(--bg-surface);
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .sim-topic-chip:hover { border-color: var(--primary); color: var(--text-primary); }
        .sim-topic-chip.active {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
        }
        .sim-topic-chips-empty {
            font-size: 12px;
            color: var(--text-muted);
            padding: 8px 0;
        }
        .sim-difficulty-row {
            display: flex;
            gap: 6px;
            text-align: center;
        }
        .sim-diff-btn {
            flex: 1;
            padding: 10px 4px 6px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            background: var(--bg-surface);
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s;
            text-align: center;
            line-height: 1.2;
        }
        .sim-diff-btn .diff-meta {
            display: block;
            font-size: 10px;
            font-weight: 400;
            color: var(--text-muted);
            margin-top: 3px;
        }
        .sim-diff-btn:hover { border-color: var(--primary); color: var(--text-primary); }
        .sim-diff-btn.active {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
            font-weight: 600;
        }
        .sim-diff-btn.active .diff-meta { color: rgba(255,255,255,0.7); }
        .sim-start-btn {
            display: block;
            width: 100%;
            margin: 24px auto 0;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 4px 16px rgba(99,102,241,0.25);
        }
        .sim-start-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.35); }
        .sim-start-btn:active { transform: translateY(0); }

        /* Loading / Preparing */
        .sim-preparing {
            text-align: center;
            padding: 50px 20px;
            max-width: 380px;
            margin: 0 auto;
        }
        .sim-preparing-icon {
            font-size: 64px;
            animation: prepBounce 1.5s ease-in-out infinite;
        }
        @keyframes prepBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }
        .sim-preparing-text {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 20px 0 8px;
        }
        .sim-preparing-sub {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        /* Progress bar */
        .sim-prep-progress {
            width: 100%;
            height: 6px;
            background: var(--bg-surface);
            border-radius: 100px;
            overflow: hidden;
            margin-bottom: 16px;
            border: 1px solid var(--border);
        }
        .sim-prep-progress-fill {
            height: 100%;
            width: 0%;
            border-radius: 100px;
            background: linear-gradient(90deg, var(--primary), var(--accent), var(--primary));
            background-size: 200% 100%;
            animation: prepProgressShimmer 1.5s ease infinite, prepProgressGrow 120s cubic-bezier(0.1, 0.4, 0.2, 0.9) forwards;
        }
        @keyframes prepProgressShimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        @keyframes prepProgressGrow {
            0% { width: 0%; }
            10% { width: 5%; }
            25% { width: 12%; }
            40% { width: 22%; }
            55% { width: 35%; }
            70% { width: 50%; }
            80% { width: 62%; }
            90% { width: 75%; }
            95% { width: 82%; }
            100% { width: 88%; }
        }

        /* Rotating status text */
        .sim-prep-status {
            font-size: 13px;
            font-weight: 500;
            color: var(--primary-light);
            margin-bottom: 20px;
            min-height: 20px;
            animation: prepStatusFade 0.4s ease;
        }
        @keyframes prepStatusFade {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Spinning dots */
        .sim-prep-dots {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 20px;
        }
        .sim-prep-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--primary);
            animation: prepDotPulse 1.2s ease-in-out infinite;
        }
        .sim-prep-dot:nth-child(2) { animation-delay: 0.2s; }
        .sim-prep-dot:nth-child(3) { animation-delay: 0.4s; }
        .sim-prep-dot:nth-child(4) { animation-delay: 0.6s; }
        @keyframes prepDotPulse {
            0%, 100% { transform: scale(0.6); opacity: 0.3; }
            50% { transform: scale(1.2); opacity: 1; }
        }

        /* Hint cards */
        .sim-prep-hints {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sim-prep-hint-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            opacity: 0;
            animation: hintSlideIn 0.5s ease-out forwards;
        }
        .sim-prep-hint-card:nth-child(1) { animation-delay: 0.2s; }
        .sim-prep-hint-card:nth-child(2) { animation-delay: 1.5s; }
        .sim-prep-hint-card:nth-child(3) { animation-delay: 3s; }
        @keyframes hintSlideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .sim-prep-hint-card.hint-pencil-card {
            background: linear-gradient(135deg, #fef9c3, #fef3c7);
            color: var(--text-primary);
        }
        [data-theme="dark"] .sim-prep-hint-card.hint-pencil-card {
            background: linear-gradient(135deg, #422006, #451a03);
            color: #fbbf24;
        }
        .sim-prep-hint-card.hint-stay-card {
            background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(236,72,153,0.08));
            color: var(--primary-light);
            border: 1px solid rgba(99,102,241,0.2);
        }
        .sim-prep-hint-card.hint-focus-card {
            background: linear-gradient(135deg, rgba(34,197,94,0.1), rgba(34,197,94,0.05));
            color: var(--success);
            border: 1px solid rgba(34,197,94,0.2);
        }
        .sim-prep-hint-icon {
            font-size: 20px;
            display: inline-block;
            flex-shrink: 0;
        }
        .sim-prep-hint-card.hint-pencil-card .sim-prep-hint-icon {
            animation: pencilWrite 1.2s ease-in-out 1s infinite;
            transform-origin: bottom right;
        }
        .sim-prep-hint-card.hint-stay-card .sim-prep-hint-icon {
            animation: stayPulse 2s ease-in-out infinite;
        }
        @keyframes stayPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        @keyframes pencilWrite {
            0%, 100% { transform: rotate(0deg); }
            20% { transform: rotate(-12deg); }
            40% { transform: rotate(8deg); }
            60% { transform: rotate(-8deg); }
            80% { transform: rotate(4deg); }
        }

        /* Timer and Progress */
        .sim-timer {
            text-align: center;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 44px;
            font-weight: 700;
            color: var(--primary-light);
            padding: 12px 0 4px;
            letter-spacing: 2px;
        }
        .sim-timer.warning { color: var(--warning); }
        .sim-timer.danger { color: var(--error); animation: blink 0.5s infinite; }
        .sim-progress {
            font-size: 13px;
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 20px;
            font-weight: 500;
        }

        /* Question */
        .sim-question {
            background: var(--bg-surface);
            border: 1.5px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 12px;
        }
        .sim-question .q-num {
            font-size: 11px;
            font-weight: 600;
            color: var(--primary-light);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sim-question .q-text {
            font-size: 15px;
            line-height: 1.75;
            color: var(--text-primary);
            margin-bottom: 20px;
        }
        .sim-option {
            display: block;
            width: 100%;
            padding: 13px 16px;
            margin-bottom: 8px;
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 14px;
            text-align: left;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.15s;
        }
        .sim-option:hover { border-color: var(--primary); background: var(--primary-glow); }
        .sim-option.selected {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
            font-weight: 600;
        }
        .sim-nav {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }
        .sim-nav button {
            flex: 1;
            padding: 13px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            background: var(--bg-surface);
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s;
        }
        .sim-nav button:hover { border-color: var(--text-muted); }
        .sim-nav button.primary {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            font-weight: 600;
        }

        /* Report */
        /* ═══ EXAM REPORT — Modern Redesign ═══ */
        .sim-report-hero {
            text-align: center;
            padding: 32px 20px 28px;
            margin-bottom: 20px;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
        }
        .sim-report-hero.grade-good {
            background: linear-gradient(135deg, rgba(34,197,94,0.12), rgba(34,197,94,0.04));
            border: 1px solid rgba(34,197,94,0.25);
        }
        .sim-report-hero.grade-mid {
            background: linear-gradient(135deg, rgba(245,158,11,0.12), rgba(245,158,11,0.04));
            border: 1px solid rgba(245,158,11,0.25);
        }
        .sim-report-hero.grade-low {
            background: linear-gradient(135deg, rgba(239,68,68,0.12), rgba(239,68,68,0.04));
            border: 1px solid rgba(239,68,68,0.25);
        }
        .sim-report-emoji {
            font-size: 56px;
            margin-bottom: 8px;
            animation: reportEmojiPop 0.6s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes reportEmojiPop {
            0% { transform: scale(0); opacity: 0; }
            60% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }
        .sim-report-net {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 56px;
            font-weight: 700;
            line-height: 1;
            animation: reportNetCount 0.8s ease-out;
        }
        .grade-good .sim-report-net { color: var(--success); }
        .grade-mid .sim-report-net { color: var(--warning); }
        .grade-low .sim-report-net { color: var(--error); }
        @keyframes reportNetCount {
            0% { transform: translateY(20px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        .sim-report-net-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 4px;
        }
        .sim-report-subject {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            background: rgba(255,255,255,0.08);
            border-radius: 100px;
            border: 1px solid var(--border);
        }

        /* Doğru / Yanlış row */
        .sim-report-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .sim-report-stat {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            animation: reportStatSlide 0.5s ease-out backwards;
        }
        .sim-report-stat:nth-child(1) { animation-delay: 0.2s; }
        .sim-report-stat:nth-child(2) { animation-delay: 0.35s; }
        @keyframes reportStatSlide {
            from { opacity: 0; transform: translateX(-12px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .sim-report-stat-icon {
            width: 42px; height: 42px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .sim-report-stat.correct .sim-report-stat-icon {
            background: rgba(34,197,94,0.12);
        }
        .sim-report-stat.wrong .sim-report-stat-icon {
            background: rgba(239,68,68,0.12);
        }
        .sim-report-stat-info { display: flex; flex-direction: column; }
        .sim-report-stat .val {
            font-weight: 700; font-size: 22px;
            font-family: 'Space Grotesk', sans-serif;
        }
        .sim-report-stat .lbl {
            font-size: 11px; color: var(--text-muted);
            font-weight: 500; letter-spacing: 0.3px;
        }
        .sim-report-stat.correct .val { color: var(--success); }
        .sim-report-stat.wrong .val { color: var(--error); }

        /* Süre/tempo chip row */
        .sim-report-meta {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .sim-report-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 100px;
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .sim-report-chip i { font-size: 11px; color: var(--text-muted); }

        /* Hata analizi kartları */
        .sim-report-section-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 20px 0 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sim-error-card {
            display: flex;
            gap: 12px;
            padding: 12px 14px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 8px;
            animation: reportStatSlide 0.4s ease-out backwards;
        }
        .sim-error-badge {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; flex-shrink: 0;
            font-weight: 700;
        }
        .sim-error-badge.err-careless { background: rgba(245,158,11,0.12); color: var(--warning); }
        .sim-error-badge.err-knowledge { background: rgba(239,68,68,0.12); color: var(--error); }
        .sim-error-badge.err-time { background: rgba(99,102,241,0.12); color: var(--primary-light); }
        .sim-error-badge.err-confusion { background: rgba(236,72,153,0.12); color: var(--accent); }
        .sim-error-badge.err-default { background: rgba(113,113,122,0.12); color: var(--text-muted); }
        .sim-error-info { flex: 1; min-width: 0; }
        .sim-error-title { font-size: 13px; font-weight: 600; color: var(--text-primary); }
        .sim-error-type { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
        .sim-error-reason { font-size: 12px; color: var(--text-secondary); margin-top: 4px; line-height: 1.5; }

        /* Detay satırları */
        .sim-detail-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 4px;
            font-size: 13px;
            transition: background 0.15s;
        }
        .sim-detail-row:hover { background: var(--bg-hover); }
        .sim-detail-icon {
            width: 28px; height: 28px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; flex-shrink: 0;
        }
        .sim-detail-row.correct .sim-detail-icon { background: rgba(34,197,94,0.12); color: var(--success); }
        .sim-detail-row.wrong .sim-detail-icon { background: rgba(239,68,68,0.12); color: var(--error); }
        .sim-detail-topic { flex: 1; color: var(--text-primary); font-weight: 500; }
        .sim-detail-answer { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text-muted); }

        /* Motivasyon kartı */
        .sim-report-motivation {
            margin-top: 20px;
            padding: 16px;
            border-radius: 14px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.6;
            animation: reportStatSlide 0.5s ease-out 0.5s backwards;
        }
        .sim-report-motivation.mot-good {
            background: linear-gradient(135deg, rgba(34,197,94,0.08), rgba(34,197,94,0.03));
            border: 1px solid rgba(34,197,94,0.2);
            color: var(--success);
        }
        .sim-report-motivation.mot-mid {
            background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(245,158,11,0.03));
            border: 1px solid rgba(245,158,11,0.2);
            color: var(--warning);
        }
        .sim-report-motivation.mot-low {
            background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(99,102,241,0.03));
            border: 1px solid rgba(99,102,241,0.2);
            color: var(--primary-light);
        }

        /* Butonlar */
        .sim-report-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .sim-report-actions .sim-start-btn { margin: 0; flex: 1; }

        /* ─── YouTube Video Kartları ─── */
        .yt-suggestion {
            margin-top: 20px;
            border-top: 1px solid var(--border);
            padding-top: 14px;
        }
        @media (max-width: 600px) {
            .yt-videos { flex-direction: column; }
            .yt-card { flex-direction: row; width: 100%; }
            .yt-card-thumb { width: 90px; min-width: 90px; height: 54px; border-radius: 0; }
        }
        .yt-suggestion-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        @keyframes ytCardIn {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .yt-videos { display: flex; flex-direction: row; flex-wrap: wrap; gap: 8px; }
        .yt-card {
            display: flex;
            flex-direction: column;
            width: 140px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            text-decoration: none;
            color: inherit;
            overflow: hidden;
            transition: all 0.15s;
            cursor: pointer;
            opacity: 0;
            animation: ytCardIn 0.3s ease forwards;
        }
        .yt-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .yt-card-thumb {
            width: 100%;
            height: 79px;
            object-fit: cover;
        }
        .yt-card-info { padding: 6px 8px 8px; }
        .yt-card-title {
            font-size: 11px;
            font-weight: 500;
            color: var(--text-primary);
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .yt-card-channel {
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .yt-card-play {
            color: #ff0000;
            font-size: 10px;
            margin-top: 3px;
        }

        /* ═══ FAZ 2: SCHEDULE SETUP PANEL ═══ */
        .sched-form { display: flex; flex-direction: column; gap: 18px; }
        .sched-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        .sched-field .sched-hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .sched-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .sched-row span.sched-sep {
            color: var(--text-muted);
            font-size: 14px;
        }
        .sched-field input[type="time"],
        .sched-field input[type="number"],
        .sched-field select {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
        }
        .sched-field input[type="time"] { flex: 1; }
        .sched-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .sched-chip {
            padding: 7px 14px;
            background: var(--bg-surface);
            border: 1.5px solid var(--border);
            border-radius: 100px;
            color: var(--text-secondary);
            font-size: 13px;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s;
        }
        .sched-chip:hover {
            border-color: var(--primary);
            color: var(--primary-light);
        }
        .sched-chip.active {
            border-color: var(--primary);
            background: var(--primary-glow);
            color: var(--primary-light);
            font-weight: 600;
        }
        .sched-weekend-row {
            display: flex;
            gap: 8px;
        }
        .sched-weekend-btn {
            flex: 1;
            padding: 10px;
            background: var(--bg-surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-size: 13px;
            font-family: inherit;
            cursor: pointer;
            text-align: center;
            transition: all 0.15s;
        }
        .sched-weekend-btn.active {
            border-color: var(--primary);
            background: var(--primary-glow);
            color: var(--primary-light);
            font-weight: 600;
        }
        .sched-generate-btn {
            display: block;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            margin-top: 8px;
            transition: all 0.2s;
        }
        .sched-generate-btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .sched-generate-btn:disabled { opacity: 0.5; cursor: default; transform: none; }

        .sched-rocket {
            display: none;
            font-size: 20px;
            margin-left: 4px;
            animation: rocketLaunch 0.6s ease-out forwards, rocketFloat 1.5s ease-in-out 0.6s infinite;
        }
        @keyframes rocketLaunch {
            0% { opacity: 0; transform: translateY(10px) scale(0.5); }
            50% { opacity: 1; transform: translateY(-6px) scale(1.2); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes rocketFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        /* ═══ FAZ 3: TOURNAMENT ═══ */
        .tm-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .tm-card:hover { border-color: var(--primary); transform: translateY(-1px); }
        .tm-card-title { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
        .tm-card-meta { font-size: 12px; color: var(--text-muted); display: flex; gap: 12px; flex-wrap: wrap; }
        .tm-card-meta span { display: flex; align-items: center; gap: 4px; }
        .tm-badge {
            display: inline-block; padding: 3px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 600;
        }
        .tm-badge-waiting { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .tm-badge-active { background: rgba(34,197,94,0.15); color: #22c55e; animation: pulse 1.5s infinite; }
        .tm-badge-finished { background: rgba(99,102,241,0.15); color: var(--primary); }
        @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.6; } }

        .tm-question { font-size: 15px; font-weight: 500; line-height: 1.6; margin-bottom: 20px; }
        .tm-option {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 16px; margin-bottom: 10px;
            border: 2px solid var(--border); border-radius: 12px;
            cursor: pointer; transition: all 0.15s; font-size: 14px;
        }
        .tm-option:hover { border-color: var(--primary); background: var(--primary-glow); }
        .tm-option.selected { border-color: var(--primary); background: var(--primary-glow); }
        .tm-option.correct { border-color: #22c55e; background: rgba(34,197,94,0.1); }
        .tm-option.wrong { border-color: #ef4444; background: rgba(239,68,68,0.1); }
        .tm-option-letter {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 14px;
            background: var(--bg-primary); color: var(--text-muted);
        }
        .tm-option.selected .tm-option-letter { background: var(--primary); color: #fff; }
        .tm-option.correct .tm-option-letter { background: #22c55e; color: #fff; }
        .tm-option.wrong .tm-option-letter { background: #ef4444; color: #fff; }
        .tm-timer-bar {
            height: 4px; background: var(--border); border-radius: 2px;
            margin-bottom: 20px; overflow: hidden;
        }
        .tm-timer-fill { height: 100%; background: var(--primary); border-radius: 2px; transition: width 1s linear; }
        .tm-timer-fill.danger { background: #ef4444; }
        .tm-explanation {
            background: rgba(99,102,241,0.08); border-radius: 10px;
            padding: 12px 14px; margin-top: 16px; font-size: 13px; line-height: 1.5;
        }
        .tm-result-row {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 14px;
        }
        .tm-result-row:last-child { border-bottom: none; }
        .tm-rank { width: 30px; font-weight: 700; text-align: center; font-size: 16px; }
        .tm-result-name { flex: 1; font-weight: 500; }
        .tm-result-score { font-weight: 700; color: var(--primary); }
        .tm-next-btn {
            width: 100%; padding: 14px; margin-top: 12px;
            background: var(--primary); color: #fff; border: none;
            border-radius: 12px; font-size: 15px; font-weight: 600;
            cursor: pointer; transition: all 0.15s;
        }
        .tm-next-btn:hover { background: var(--primary-dark); }

        /* ═══ FAZ 3: GARDEN — Modern Redesign ═══ */
        .garden-scene {
            position: relative;
            text-align: center;
            padding: 40px 20px 32px;
            border-radius: 20px;
            margin-bottom: 20px;
            overflow: hidden;
            isolation: isolate;
        }
        /* Sky gradient — changes per level */
        .garden-scene.lvl-1 { background: linear-gradient(180deg, #fde68a 0%, #d4a373 40%, #8b6f47 100%); }
        .garden-scene.lvl-2 { background: linear-gradient(180deg, #bef264 0%, #86efac 50%, #6ee7b7 100%); }
        .garden-scene.lvl-3 { background: linear-gradient(180deg, #a7f3d0 0%, #6ee7b7 50%, #34d399 100%); }
        .garden-scene.lvl-4 { background: linear-gradient(180deg, #c4b5fd 0%, #f9a8d4 40%, #86efac 100%); }
        .garden-scene.lvl-5 { background: linear-gradient(180deg, #93c5fd 0%, #a7f3d0 50%, #6ee7b7 100%); }
        .garden-scene.lvl-6 { background: linear-gradient(180deg, #818cf8 0%, #c4b5fd 30%, #fbcfe8 60%, #86efac 100%); }
        [data-theme="dark"] .garden-scene.lvl-1 { background: linear-gradient(180deg, #422006 0%, #292524 50%, #1c1917 100%); }
        [data-theme="dark"] .garden-scene.lvl-2 { background: linear-gradient(180deg, #14532d 0%, #052e16 50%, #0a0f0a 100%); }
        [data-theme="dark"] .garden-scene.lvl-3 { background: linear-gradient(180deg, #064e3b 0%, #022c22 50%, #0a0f0a 100%); }
        [data-theme="dark"] .garden-scene.lvl-4 { background: linear-gradient(180deg, #312e81 0%, #4c1d95 30%, #064e3b 100%); }
        [data-theme="dark"] .garden-scene.lvl-5 { background: linear-gradient(180deg, #1e3a5f 0%, #064e3b 50%, #0a0f0a 100%); }
        [data-theme="dark"] .garden-scene.lvl-6 { background: linear-gradient(180deg, #1e1b4b 0%, #312e81 25%, #831843 50%, #064e3b 100%); }

        /* Floating particles */
        .garden-particles {
            position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 0;
        }
        .garden-particle {
            position: absolute; font-size: 14px; opacity: 0;
            animation: gardenParticleFloat 6s ease-in-out infinite;
        }
        @keyframes gardenParticleFloat {
            0% { opacity: 0; transform: translateY(100%) scale(0.5); }
            15% { opacity: 0.8; }
            85% { opacity: 0.6; }
            100% { opacity: 0; transform: translateY(-200%) scale(1) rotate(30deg); }
        }

        /* Ground with grass effect */
        .garden-ground {
            position: absolute; bottom: 0; left: 0; right: 0; height: 40px; z-index: 0;
            background: linear-gradient(180deg, transparent 0%, rgba(34,197,94,0.2) 50%, rgba(34,197,94,0.35) 100%);
            border-radius: 0 0 20px 20px;
        }
        [data-theme="dark"] .garden-ground {
            background: linear-gradient(180deg, transparent 0%, rgba(34,197,94,0.08) 50%, rgba(34,197,94,0.15) 100%);
        }

        /* Main plant/emoji */
        .garden-main-plant {
            position: relative; z-index: 1;
            font-size: 80px; line-height: 1;
            filter: drop-shadow(0 8px 24px rgba(0,0,0,0.15));
            animation: gardenPlantBounce 3s ease-in-out infinite;
            margin-bottom: 12px;
        }
        @keyframes gardenPlantBounce {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-10px) scale(1.05); }
        }

        /* Level badge pill */
        .garden-level-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 16px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 100px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 12px; font-weight: 700;
            color: #fff; text-shadow: 0 1px 4px rgba(0,0,0,0.2);
            letter-spacing: 0.5px; text-transform: uppercase;
            margin-bottom: 8px;
            position: relative; z-index: 1;
        }
        [data-theme="dark"] .garden-level-badge {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.15);
        }

        .garden-level-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px; font-weight: 700;
            color: #fff; text-shadow: 0 2px 8px rgba(0,0,0,0.15);
            margin-bottom: 4px;
            position: relative; z-index: 1;
        }
        .garden-desc {
            font-size: 13px;
            color: rgba(255,255,255,0.8);
            text-shadow: 0 1px 4px rgba(0,0,0,0.1);
            position: relative; z-index: 1;
        }
        [data-theme="dark"] .garden-desc { color: rgba(255,255,255,0.6); }

        /* Decorations orbiting */
        .garden-decos {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 8px;
            margin-top: 14px;
            position: relative; z-index: 1;
        }
        .garden-deco-item {
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            animation: gardenDecoFloat 4s ease-in-out infinite;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        }
        .garden-deco-item:nth-child(odd) { animation-delay: -1.5s; }
        .garden-deco-item:nth-child(3n) { animation-delay: -3s; }
        @keyframes gardenDecoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        /* Stat cards row */
        .garden-stats {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px;
        }
        .garden-stat {
            position: relative;
            text-align: center;
            padding: 16px 10px 14px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            transition: all 0.2s;
        }
        .garden-stat:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        .garden-stat-icon {
            font-size: 24px; margin-bottom: 6px; display: block;
        }
        .garden-stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px; font-weight: 700;
            background: linear-gradient(135deg, var(--primary-light), var(--accent-light));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .garden-stat-label {
            font-size: 11px; color: var(--text-muted); margin-top: 2px;
            font-weight: 500; letter-spacing: 0.3px;
        }
        /* Accent stripe on stat cards */
        .garden-stat::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            border-radius: 14px 14px 0 0;
        }
        .garden-stat.stat-leaf::before { background: linear-gradient(90deg, #22c55e, #86efac); }
        .garden-stat.stat-flower::before { background: linear-gradient(90deg, #ec4899, #f9a8d4); }
        .garden-stat.stat-streak::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }

        /* Progress section */
        .garden-progress { margin-bottom: 20px; }
        .garden-progress-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 8px;
        }
        .garden-progress-level {
            font-size: 12px; font-weight: 600; color: var(--text-secondary);
            display: flex; align-items: center; gap: 4px;
        }
        .garden-progress-remain {
            font-size: 11px; color: var(--text-muted);
            background: var(--bg-surface); padding: 3px 10px; border-radius: 100px;
            border: 1px solid var(--border);
        }
        .garden-progress-track {
            height: 10px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 100px;
            overflow: hidden;
            position: relative;
        }
        .garden-progress-fill {
            height: 100%; border-radius: 100px;
            background: linear-gradient(90deg, #22c55e, #6366f1, #ec4899);
            background-size: 200% 100%;
            animation: gardenProgressShimmer 3s ease infinite;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .garden-progress-fill::after {
            content: ''; position: absolute; right: 0; top: 0; bottom: 0; width: 20px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4));
            border-radius: 0 100px 100px 0;
        }
        @keyframes gardenProgressShimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        /* Milestones on progress */
        .garden-milestones {
            display: flex; justify-content: space-between;
            margin-top: 6px; padding: 0 2px;
        }
        .garden-milestone {
            font-size: 10px; color: var(--text-muted);
            display: flex; flex-direction: column; align-items: center; gap: 2px;
        }
        .garden-milestone-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--border);
        }
        .garden-milestone.reached .garden-milestone-dot {
            background: var(--success);
            box-shadow: 0 0 6px rgba(34,197,94,0.4);
        }

        /* Info card */
        .garden-info-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }
        .garden-info-header {
            display: flex; align-items: center; gap: 10px;
            padding: 14px 16px;
            background: linear-gradient(135deg, rgba(34,197,94,0.08), rgba(99,102,241,0.08));
            border-bottom: 1px solid var(--border);
            font-size: 14px; font-weight: 600; color: var(--text-primary);
        }
        .garden-info-row {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 13px; color: var(--text-secondary);
            transition: background 0.15s;
        }
        .garden-info-row:last-child { border-bottom: none; }
        .garden-info-row:hover { background: var(--bg-hover); }
        .garden-info-icon {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; flex-shrink: 0;
        }
        .garden-info-icon.leaf-icon { background: rgba(34,197,94,0.12); }
        .garden-info-icon.flower-icon { background: rgba(236,72,153,0.12); }
        .garden-info-icon.streak-icon { background: rgba(245,158,11,0.12); }
        .garden-info-icon.badge-icon { background: rgba(99,102,241,0.12); }

        /* Notification */
        .garden-notification {
            position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%);
            background: var(--bg-card);
            border: 1px solid #22c55e;
            border-radius: 14px;
            padding: 12px 20px;
            font-size: 14px; font-weight: 500;
            z-index: 9999;
            display: flex; align-items: center; gap: 10px;
            animation: gardenNotifIn 0.4s cubic-bezier(0.34,1.56,0.64,1), gardenNotifOut 0.3s ease 2.5s forwards;
            box-shadow: 0 8px 32px rgba(34,197,94,0.2), 0 2px 8px rgba(0,0,0,0.1);
        }
        @keyframes gardenNotifIn { from { opacity:0; transform: translateX(-50%) translateY(24px) scale(0.9); } to { opacity:1; transform: translateX(-50%) translateY(0) scale(1); } }
        @keyframes gardenNotifOut { from { opacity:1; } to { opacity:0; transform: translateX(-50%) translateY(-10px); } }
    </style>
</head>
<script>
    // Apply theme instantly to prevent flash
    // Priority: localStorage > system preference > dark default
    (function() {
        var saved = localStorage.getItem('aibuke-theme');
        var theme;
        if (saved) {
            theme = saved;
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
            theme = 'light';
        } else {
            theme = 'dark';
        }
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-top">
            <div class="header-avatar"><img src="assets/aibuke-avatar.png" alt="Aibüke"></div>
            <div class="header-title">
                <span class="header-title-main">Aibüke Öğretmen</span>
                <span class="header-title-sub"><?= htmlspecialchars($slogan) ?></span>
            </div>
            <div class="header-actions">
                <label class="theme-toggle" title="Tema değiştir">
                    <input type="checkbox" id="themeSwitch">
                    <span class="theme-slider"></span>
                    <span class="theme-icons">
                        <span class="theme-icon theme-icon-sun">☀️</span>
                        <span class="theme-icon theme-icon-moon">🌙</span>
                    </span>
                </label>
                <button class="header-btn" id="logoutBtn" title="Çıkış"><i class="fas fa-right-from-bracket"></i></button>
            </div>
        </div>
        <div class="stats-bar">
            <div class="stat-chip points" id="btnLeaderboard" title="Sıralamayı gör">
                <span class="stat-chip-icon">🏆</span>
                <div class="stat-chip-info">
                    <span class="stat-chip-value" id="statPoints"><?= $studentPoints ?> puan<?php if ($studentRank): ?> · #<?= $studentRank ?><?php endif; ?></span>
                    <span class="stat-chip-label">Liderlik Tablosu</span>
                </div>
            </div>
            <?php if ($studentStreak > 0): ?>
            <div class="stat-chip streak" id="btnCalendar" title="<?= $studentStreak ?> gün üst üste çalıştın!" style="cursor:pointer">
                <span class="stat-chip-icon">📅</span>
                <div class="stat-chip-info">
                    <span class="stat-chip-value" id="statStreak"><?= $studentStreak ?></span>
                    <span class="stat-chip-label">Gün</span>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($studentBadges)): ?>
            <div class="stat-chip badges-chip" id="btnBadges" title="Rozetlerin">
                <span class="stat-chip-icon">🎖️</span>
                <div class="stat-chip-info">
                    <span class="stat-chip-value"><?= count($studentBadges) ?></span>
                    <span class="stat-chip-label">Rozet</span>
                </div>
            </div>
            <div class="badges-popup" id="badgesPopup">
                <div class="badges-popup-title">Kazandığın Rozetler</div>
                <?php foreach ($studentBadges as $badge): ?>
                <div class="badge-row">
                    <div class="badge-medal"><?= $badge['badge_icon'] ?></div>
                    <div class="badge-row-info">
                        <span class="badge-row-name"><?= htmlspecialchars($badge['badge_name']) ?></span>
                        <span class="badge-row-desc"><?= htmlspecialchars($badge['description']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <div style="padding:8px 14px 4px;font-size:10px;color:var(--text-muted);border-top:1px solid var(--border);margin-top:4px;text-align:center;">Çalışmaya devam et, yeni rozetler kazanacaksın!</div>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Quick Actions -->
    <div class="quick-actions" id="quickActions">
        <button class="quick-btn" onclick="openPanel('schedule')"><span class="icon">📋</span> Program</button>
        <button class="quick-btn" id="btnPlan" onclick="openPanel('plan')"><span class="icon">📅</span> Günlük Plan</button>
        <button class="quick-btn" id="btnExamSim" onclick="openExamModal()"><span class="icon">⏱️</span> Sınav Provası</button>
        <button class="quick-btn" id="btnExamUpload" onclick="openExamAnalysisInfo()"><span class="icon">📊</span> Deneme Analizi</button>
        <button class="quick-btn" onclick="openStudyRoomInfo()"><span class="icon">👥</span> Çalışma Odası</button>
        <button class="quick-btn" onclick="openPanel('tournament')"><span class="icon">🏆</span> Turnuva</button>
        <button class="quick-btn" onclick="openPanel('garden')"><span class="icon">🌱</span> Bahçem</button>
        <button class="quick-btn" onclick="showParentLink()"><span class="icon">👨‍👩‍👧</span> Veli Paneli</button>
        <input type="file" id="examPhotoInput" accept="image/*" style="display:none">
    </div>

    <!-- Voice Banner -->
    <div class="voice-banner" id="voiceBanner">
        <div class="voice-banner-icon" id="voicePlayBtn" title="Dinle">🔊</div>
        <div class="voice-banner-text">
            <div class="voice-banner-title">Aibüke sana sesli mesaj bıraktı</div>
            <div class="voice-banner-preview" id="voiceBannerPreview"></div>
        </div>
        <button class="voice-banner-close" id="voiceBannerClose" title="Kapat"><i class="fas fa-xmark"></i></button>
    </div>
    <audio id="voiceAudio" preload="none"></audio>

    <!-- Chat Container -->
    <div class="chat-container" id="chatContainer">
        <div class="welcome-msg" id="welcomeMsg">
            <div class="welcome-avatar"><img src="assets/aibuke-avatar.png" alt="Aibüke" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></div>
            <h2>Merhaba <?= $studentName ?>!</h2>
            <p>Ben Aibüke, senin dijital eğitim mentorunum. Sana nasıl yardımcı olabilirim?</p>
            <div class="welcome-cards">
                <div class="welcome-card" data-prompt="Bu dersi anlamadım, bana açıklar mısın?">
                    <span class="card-icon">📖</span>
                    <div class="card-title">Ders Anlat</div>
                    <div class="card-desc">Konuları adım adım öğren</div>
                </div>
                <div class="welcome-card" data-prompt="Soru çözmek istiyorum, yardım eder misin?">
                    <span class="card-icon">🧮</span>
                    <div class="card-title">Soru Çöz</div>
                    <div class="card-desc">Soruları birlikte çözelim</div>
                </div>
                <div class="welcome-card" data-prompt="Sınav stresi yaşıyorum, bana moral ver">
                    <span class="card-icon">💜</span>
                    <div class="card-title">Motivasyon</div>
                    <div class="card-desc">Moral ve motivasyon desteği</div>
                </div>
                <div class="welcome-card" data-prompt="Fotoğrafla soru göndermek istiyorum">
                    <span class="card-icon">📸</span>
                    <div class="card-title">Fotoğrafla Sor</div>
                    <div class="card-desc">Soruyu çekip gönder</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Typing Indicator -->
    <div class="typing-group" id="typingIndicator">
        <div class="msg-avatar"><img src="assets/aibuke-avatar.png" alt="Aibüke" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></div>
        <div class="typing-bubble">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>

    <!-- Input Area -->
    <form class="input-area" id="chatForm" enctype="multipart/form-data">
        <div class="input-wrapper">
            <div class="image-preview" id="imagePreview">
                <img id="previewImg" src="" alt="Preview">
                <span class="preview-info">Fotoğraf seçildi</span>
                <button type="button" class="remove-image" id="removeImage"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="input-field-wrapper">
                <input type="file" id="imageInput" accept="image/*">
                <textarea id="messageInput" placeholder="Mesajını yaz..." rows="1" autocomplete="off"></textarea>
                <div class="input-actions">
                    <button type="button" class="action-btn" id="imageBtn" title="Fotoğraf gönder">
                        <i class="fas fa-image"></i>
                    </button>
                    <button type="button" class="action-btn btn-stop" id="stopBtn" title="Durdur" style="display:none">
                        <i class="fas fa-stop"></i>
                    </button>
                    <button type="submit" class="action-btn btn-send" id="sendBtn" title="Gönder">
                        <i class="fas fa-arrow-up"></i>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Panels Overlay -->
    <div class="panel-overlay" id="panelOverlay" onclick="closePanel()"></div>

    <!-- Leaderboard Panel -->
    <div class="slide-panel" id="panelLeaderboard">
        <div class="panel-header">
            <h3>🏆 Liderlik Tablosu</h3>
            <button class="panel-close" onclick="closePanel()"><i class="fas fa-xmark"></i></button>
        </div>
        <div style="display:flex;gap:6px;padding:8px 16px 0;flex-wrap:wrap;">
            <button class="lb-filter active" data-period="all" onclick="filterLeaderboard(this)">Tüm Zamanlar</button>
            <button class="lb-filter" data-period="monthly" onclick="filterLeaderboard(this)">Bu Ay</button>
            <button class="lb-filter" data-period="weekly" onclick="filterLeaderboard(this)">Bu Hafta</button>
        </div>
        <div class="panel-body" id="leaderboardBody">
            <div style="text-align:center;color:var(--text-muted);padding:40px">Yükleniyor...</div>
        </div>
    </div>

    <!-- Grup Çalışma Odaları Panel -->
    <div class="slide-panel" id="panelGroups">
        <div class="panel-header">
            <h3>👥 Çalışma Odaları</h3>
            <button class="panel-close" onclick="closePanel()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="panel-body" id="groupsBody">
            <div style="text-align:center;color:var(--text-muted);padding:40px">Yükleniyor...</div>
        </div>
    </div>

    <!-- Turnuva Panel -->
    <div class="slide-panel" id="panelTournament">
        <div class="panel-header">
            <h3>🏆 Soru Turnuvaları</h3>
            <button class="panel-close" onclick="closePanel()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="panel-body" id="tournamentBody">
            <div style="text-align:center;color:var(--text-muted);padding:40px">Yükleniyor...</div>
        </div>
    </div>

    <!-- Bahçe Panel -->
    <div class="slide-panel" id="panelGarden">
        <div class="panel-header">
            <h3>🌱 Bahçem</h3>
            <button class="panel-close" onclick="closePanel()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="panel-body" id="gardenBody">
            <div style="text-align:center;color:var(--text-muted);padding:40px">Yükleniyor...</div>
        </div>
    </div>

    <!-- Takvim Panel -->
    <div class="slide-panel" id="panelCalendar">
        <div class="panel-header">
            <h3>📅 Çalışma Takvimi</h3>
            <button class="panel-close" onclick="closePanel()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="panel-body" id="calendarBody">
            <div style="text-align:center;color:var(--text-muted);padding:40px">Yükleniyor...</div>
        </div>
    </div>

    <!-- Turnuva Modal (Full Screen Quiz) -->
    <div class="sim-modal-overlay" id="tournamentModal" style="display:none;">
        <div style="width:100%;max-width:600px;margin:auto;display:flex;flex-direction:column;background:var(--bg-primary);border-radius:16px;overflow:hidden;max-height:95vh;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:var(--bg-surface);border-bottom:1px solid var(--border);">
                <div>
                    <div id="tmTitle" style="font-weight:600;font-size:15px;">🏆 Turnuva</div>
                    <div id="tmProgress" style="font-size:12px;color:var(--text-muted);">Soru 1/5</div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div id="tmTimer" style="font-size:20px;font-weight:700;color:var(--primary);font-family:'JetBrains Mono',monospace;min-width:50px;text-align:center;">2:00</div>
                    <button onclick="closeTournamentModal()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;"><i class="fas fa-xmark"></i></button>
                </div>
            </div>
            <div id="tmBody" style="flex:1;overflow-y:auto;padding:20px;">
                <!-- Soru içeriği buraya gelecek -->
            </div>
        </div>
    </div>

    <!-- Grup Chat Modal -->
    <div class="sim-modal-overlay" id="groupChatModal" style="display:none;">
        <div style="width:100%;max-width:600px;height:90vh;margin:auto;display:flex;flex-direction:column;background:var(--bg-primary);border-radius:16px;overflow:hidden;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:var(--bg-surface);border-bottom:1px solid var(--border);">
                <div>
                    <div id="gcTitle" style="font-weight:600;font-size:15px;"></div>
                    <div id="gcMembers" style="font-size:11px;color:var(--text-muted);"></div>
                </div>
                <button onclick="closeGroupChat()" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;"><i class="fas fa-xmark"></i></button>
            </div>
            <div id="gcMessages" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:8px;"></div>
            <form id="gcForm" style="display:flex;gap:8px;padding:12px 16px;border-top:1px solid var(--border);" onsubmit="sendGroupMsg(event)">
                <input type="text" id="gcInput" placeholder="Mesaj yaz... (@aibuke ile yardım iste)" style="flex:1;padding:10px 14px;border:1px solid var(--border);border-radius:10px;background:var(--bg-surface);color:var(--text-primary);font-size:14px;outline:none;">
                <button type="submit" style="padding:10px 16px;background:var(--primary);border:none;border-radius:10px;color:#fff;cursor:pointer;"><i class="fas fa-paper-plane"></i></button>
            </form>
        </div>
    </div>

    <!-- Daily Plan Panel -->
    <div class="slide-panel" id="panelPlan">
        <div class="panel-header">
            <h3>📅 Günlük Plan</h3>
            <button class="panel-close" onclick="closePanel()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="panel-body" id="planBody">
            <div style="text-align:center;color:var(--text-muted);padding:40px">Yükleniyor...</div>
        </div>
    </div>

    <!-- Exam Simulation Modal (Full Screen) -->
    <div class="sim-modal-overlay" id="examModal">
        <div class="sim-modal">
            <div class="sim-modal-header">
                <h3>⏱️ Sınav Provası</h3>
                <button class="sim-modal-close" onclick="closeExamModal()"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="sim-modal-body" id="examBody">
                <!-- Setup form -->
                <div id="examSetup" class="sim-setup">
                    <div class="sim-setup-icon">📝</div>
                    <div class="sim-setup-title">Kendini Test Et</div>
                    <p class="sim-setup-desc">Ders seç, soru sayısını belirle ve gerçek sınav ortamını simüle et!</p>
                    <div class="sim-fields-grid">
                        <div class="sim-field">
                            <label>Ders</label>
                            <select id="simSubject" onchange="loadSimTopics(this.value)">
                            <?php if ($examType === 'lgs'): ?>
                                <option>Matematik</option>
                                <option>Fen Bilimleri</option>
                                <option>Türkçe</option>
                                <option>İnkılap Tarihi</option>
                                <option>Din Kültürü</option>
                                <option>İngilizce</option>
                            <?php else: ?>
                                <option>Matematik</option>
                                <option>Fizik</option>
                                <option>Kimya</option>
                                <option>Biyoloji</option>
                                <option>Türkçe</option>
                                <option>Tarih</option>
                                <option>Coğrafya</option>
                                <option>Felsefe</option>
                                <option>Geometri</option>
                            <?php endif; ?>
                            </select>
                        </div>
                        <div class="sim-field">
                            <label>Soru Sayısı</label>
                            <div class="sim-count-row">
                                <button type="button" class="sim-count-btn active" data-val="5" data-label="10 dk" onclick="selectSimCount(this)">5</button>
                                <button type="button" class="sim-count-btn" data-val="10" data-label="20 dk" onclick="selectSimCount(this)">10</button>
                                <button type="button" class="sim-count-btn" data-val="20" data-label="40 dk" onclick="selectSimCount(this)">20</button>
                            </div>
                        </div>
                    </div>
                    <div class="sim-field" style="margin-top:4px">
                        <label>Zorluk</label>
                        <div class="sim-difficulty-row">
                            <button type="button" class="sim-diff-btn active" data-val="easy" data-time="120" data-points="0.5" onclick="selectDifficulty(this)">Kolay<span class="diff-meta">2 dk / 0.5 puan</span></button>
                            <button type="button" class="sim-diff-btn" data-val="medium" data-time="60" data-points="1" onclick="selectDifficulty(this)">Orta<span class="diff-meta">1 dk / 1 puan</span></button>
                            <button type="button" class="sim-diff-btn" data-val="hard" data-time="30" data-points="2" onclick="selectDifficulty(this)">💀 Zor<span class="diff-meta">30 sn / 2 puan</span></button>
                        </div>
                    </div>
                    <div class="sim-accordion" style="margin-top:4px">
                        <button type="button" class="sim-accordion-toggle" onclick="toggleTopicAccordion(this)">
                            <span>Konu Seç <span class="sim-acc-hint">(seçmezsen rastgele)</span></span>
                            <span class="sim-acc-badge" id="simTopicBadge"></span>
                            <i class="fas fa-chevron-down sim-acc-icon"></i>
                        </button>
                        <div class="sim-accordion-body" id="simAccordionBody">
                            <div class="sim-topic-chips" id="simTopicChips"></div>
                        </div>
                    </div>
                    <button class="sim-start-btn" onclick="startExamSim()">
                        <i class="fas fa-play"></i> Provayı Başlat
                    </button>
                </div>
                <!-- Active exam area (hidden initially) -->
                <div id="examActive" style="display:none"></div>
                <!-- Report area (hidden initially) -->
                <div id="examReport" style="display:none"></div>
            </div>
        </div>
    </div>

    <!-- Schedule Setup Panel -->
    <div class="slide-panel" id="panelSchedule">
        <div class="panel-header">
            <h3>📋 Program Oluştur</h3>
            <button class="panel-close" onclick="closePanel()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="panel-body">
            <div class="sched-form" id="schedForm">
                <div class="sched-field">
                    <label>🏫 Okul Saatleri</label>
                    <div class="sched-hint">Okulda olduğun saatleri gir (o saatler programa dahil edilmez)</div>
                    <div class="sched-row">
                        <input type="time" id="schedSchoolStart" value="08:30">
                        <span class="sched-sep">—</span>
                        <input type="time" id="schedSchoolEnd" value="15:30">
                    </div>
                </div>

                <div class="sched-field">
                    <label>⏰ Günde Kaç Saat Çalışmak İstiyorsun?</label>
                    <div class="sched-hint">Okul dışında, evde çalışma süren</div>
                    <div class="sched-row">
                        <input type="number" id="schedStudyHours" value="3" min="1" max="12" style="width:80px" oninput="const r=document.getElementById('schedRocket');if(this.value>8){r.style.display='inline';r.style.animation='none';r.offsetHeight;r.style.animation='';}else{r.style.display='none';}">
                        <span style="color:var(--text-muted);font-size:13px">saat</span>
                        <span id="schedRocket" class="sched-rocket">🚀</span>
                    </div>
                </div>

                <div class="sched-field">
                    <label>📚 Hangi Derslere Ağırlık Verelim?</label>
                    <div class="sched-hint">Birden fazla seçebilirsin</div>
                    <div class="sched-chips" id="schedSubjects">
                        <?php
                        $examType = $profile['exam_type'] ?? 'yks';
                        $yksField = $profile['yks_field'] ?? '';

                        if ($examType === 'lgs') {
                            $subjectGroups = [
                                'LGS' => ['Türkçe', 'Matematik', 'Fen Bilimleri', 'T.C. İnkılap Tarihi', 'Din Kültürü', 'İngilizce']
                            ];
                        } else {
                            // TYT dersleri (herkes için ortak)
                            $subjectGroups = [
                                'TYT' => ['TYT Türkçe', 'TYT Matematik', 'TYT Geometri', 'TYT Fizik', 'TYT Kimya', 'TYT Biyoloji', 'TYT Tarih', 'TYT Coğrafya', 'TYT Felsefe', 'TYT Din Kültürü']
                            ];
                            // AYT dersleri (alana göre)
                            if ($yksField === 'mf') {
                                $subjectGroups['AYT Sayısal'] = ['AYT Matematik', 'AYT Geometri', 'AYT Fizik', 'AYT Kimya', 'AYT Biyoloji'];
                            } elseif ($yksField === 'tm') {
                                $subjectGroups['AYT Eşit Ağırlık'] = ['AYT Matematik', 'AYT Geometri', 'Edebiyat', 'Tarih-1', 'Coğrafya-1'];
                            } elseif ($yksField === 'sozel') {
                                $subjectGroups['AYT Sözel'] = ['Edebiyat', 'Tarih-1', 'Tarih-2', 'Coğrafya-1', 'Coğrafya-2', 'Felsefe', 'Psikoloji', 'Sosyoloji', 'Mantık', 'Din Kültürü'];
                            } elseif ($yksField === 'dil') {
                                $subjectGroups['AYT Dil'] = ['İngilizce (YDT)'];
                            } else {
                                // Alan belirlenmemiş — tüm AYT derslerini göster, öğrenci seçsin
                                $subjectGroups['AYT Sayısal'] = ['AYT Matematik', 'AYT Geometri', 'AYT Fizik', 'AYT Kimya', 'AYT Biyoloji'];
                                $subjectGroups['AYT Sözel'] = ['Edebiyat', 'Tarih-1', 'Tarih-2', 'Coğrafya-1', 'Coğrafya-2', 'Felsefe', 'Psikoloji', 'Sosyoloji', 'Mantık', 'Din Kültürü'];
                                $subjectGroups['AYT Dil'] = ['İngilizce (YDT)'];
                            }
                        }

                        foreach ($subjectGroups as $group => $subjects):
                        ?>
                        <div style="width:100%;font-size:11px;color:var(--text-muted);margin-top:4px;margin-bottom:2px;font-weight:600;"><?= $group ?></div>
                        <?php foreach ($subjects as $s): ?>
                        <button type="button" class="sched-chip" data-subject="<?= $s ?>" onclick="this.classList.toggle('active')"><?= $s ?></button>
                        <?php endforeach; endforeach; ?>
                    </div>
                </div>

                <div class="sched-field">
                    <label>📅 Hafta Sonu</label>
                    <div class="sched-weekend-row">
                        <button type="button" class="sched-weekend-btn active" data-val="study" onclick="selectWeekend(this)">Çalışırım</button>
                        <button type="button" class="sched-weekend-btn" data-val="half" onclick="selectWeekend(this)">Yarım gün</button>
                        <button type="button" class="sched-weekend-btn" data-val="rest" onclick="selectWeekend(this)">Dinlenirim</button>
                    </div>
                </div>

                <div class="sched-field">
                    <label>📝 Ekstra Notun Var mı?</label>
                    <div class="sched-hint">Örn: "Salı günü kurs var", "Matematik zayıf"</div>
                    <textarea id="schedNotes" rows="2" placeholder="İsteğe bağlı..." style="width:100%;padding:10px 12px;background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-primary);font-size:14px;font-family:inherit;resize:vertical;"></textarea>
                </div>

                <button class="sched-generate-btn" id="schedGenerateBtn" onclick="generateScheduleFromPanel()">
                    <i class="fas fa-magic"></i> Programı Oluştur
                </button>
            </div>
        </div>
    </div>

    <script>
        // bfcache kırıcı — geri/ileri veya cache'den gelince zorla yenile
        window.addEventListener('pageshow', (e) => {
            if (e.persisted) window.location.reload();
        });

        const chatContainer = document.getElementById('chatContainer');
        const chatForm = document.getElementById('chatForm');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const stopBtn = document.getElementById('stopBtn');
        const imageBtn = document.getElementById('imageBtn');
        const imageInput = document.getElementById('imageInput');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        const removeImage = document.getElementById('removeImage');
        const typingIndicator = document.getElementById('typingIndicator');
        const welcomeMsg = document.getElementById('welcomeMsg');
        const quickActions = document.getElementById('quickActions');

        let selectedImage = null;
        let currentStreamingElement = null;
        let hasMessages = false;
        let lastChoiceBubble = null;
        let lastSelectedChoice = null;
        let fetchAbortController = null;
        let streamingStopped = false;

        // ─── Theme Toggle ───
        const themeSwitch = document.getElementById('themeSwitch');

        function getActiveTheme() {
            const saved = localStorage.getItem('aibuke-theme');
            if (saved) return saved;
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) return 'light';
            return 'dark';
        }

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            themeSwitch.checked = theme === 'dark';
        }

        // Init
        applyTheme(getActiveTheme());

        // Manual toggle — saves preference
        themeSwitch.addEventListener('change', () => {
            const theme = themeSwitch.checked ? 'dark' : 'light';
            applyTheme(theme);
            localStorage.setItem('aibuke-theme', theme);
        });

        // Listen for system theme changes (only if user hasn't set a manual preference)
        if (window.matchMedia) {
            const darkMatcher = window.matchMedia('(prefers-color-scheme: dark)');
            darkMatcher.addEventListener('change', () => {
                if (!localStorage.getItem('aibuke-theme')) {
                    applyTheme(getActiveTheme());
                }
            });
            const lightMatcher = window.matchMedia('(prefers-color-scheme: light)');
            lightMatcher.addEventListener('change', () => {
                if (!localStorage.getItem('aibuke-theme')) {
                    applyTheme(getActiveTheme());
                }
            });
        }

        // Auto-resize textarea
        messageInput.addEventListener('input', () => {
            messageInput.style.height = 'auto';
            messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
        });

        // Enter to send, Shift+Enter for new line
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit'));
            }
        });

        // Kullanici avatari (PHP'den bir kez alindi)
        const userAvatar = <?= $studentPhoto
            ? "'<img src=\"{$studentPhoto}\" alt=\"{$studentName}\">'"
            : "'" . mb_substr($studentName, 0, 1) . "'" ?>;

        // Refresh veya sayfa kapatmada fetch'i iptal et
        window.addEventListener('beforeunload', () => {
            if (fetchAbortController) fetchAbortController.abort();
        });

        // Durdur butonu
        stopBtn.addEventListener('click', () => {
            streamingStopped = true;
            if (fetchAbortController) fetchAbortController.abort();
        });

        // Günlük limit kontrolü (sayfa yüklenince)
        const dailyLimitReached = <?= $limitReached ? 'true' : 'false' ?>;
        if (dailyLimitReached) {
            messageInput.disabled = true;
            sendBtn.disabled = true;
            document.querySelector('.input-field-wrapper').classList.add('limit-reached');
            messageInput.placeholder = 'Limit doldu';
            // Limit timer ekle
            const limitTimer = document.createElement('div');
            limitTimer.className = 'limit-timer';
            limitTimer.innerHTML = '<i class="fas fa-hourglass-half"></i> <span>Limitin <span class="time"><?= $limitResetStr ?></span> sonra yenilenecek</span>';
            document.querySelector('.input-wrapper').appendChild(limitTimer);
        }

        // Initialize chat
        function initChat() {
            loadChatHistory();
            loadVoiceMessages();
            if (!dailyLimitReached) messageInput.focus();
        }

        // Load chat history
        async function loadChatHistory() {
            // History gelene kadar welcome'u gizle
            if (welcomeMsg) welcomeMsg.style.display = 'none';
            try {
                const response = await fetch('api/history.php?_=' + Date.now(), {cache: 'no-store'});
                const data = await response.json();

                if (data.messages && data.messages.length > 0) {
                    hasMessages = true;
                    data.messages.forEach(msg => {
                        addMessage(msg.content, msg.role, msg.created_at);
                        if (msg.role === 'ai') {
                            const lastBubble = chatContainer.querySelector('.message-group.ai:last-child .msg-bubble');
                            if (lastBubble) addListenButton(lastBubble, msg.content);
                        }
                    });
                    // Geçmiş mesajlardaki tüm şık butonlarını kilitle
                    document.querySelectorAll('.choice-btn').forEach(btn => btn.disabled = true);
                    scrollToBottom();
                } else {
                    // Mesaj yoksa welcome'u geri göster
                    if (welcomeMsg) welcomeMsg.style.display = '';
                }
            } catch (e) {
                // History load error - sessizcegeç, kullanıcı deneyimini bozma
                console.warn('History load error:', e);
                if (welcomeMsg) welcomeMsg.style.display = '';
            }
        }

        function hideWelcome() {
            if (welcomeMsg) {
                welcomeMsg.style.display = 'none';
            }
            hasMessages = true;
        }

        // Format text with markdown-like processing
        function formatContent(text) {
            let html = text
                .replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code>$1</code>')
                .replace(/\n/g, '<br>');

            // Çoktan seçmeli şıkları buton yap — sadece A-D/E tek satırda ardışık geliyorsa
            html = html.replace(
                /<br>A\)\s*([^<]+)<br>B\)\s*([^<]+)<br>C\)\s*([^<]+)<br>D\)\s*([^<]+)(<br>E\)\s*([^<]+))?/g,
                (match, a, b, c, d, eBlock, e) => {
                    const opts = [['A',a],['B',b],['C',c],['D',d]];
                    if (e) opts.push(['E', e.trim()]);
                    return opts.map(([h, t]) =>
                        `<br><button class="choice-btn" data-choice="${h}" onclick="sendChoice('${h}')">${h}) ${t.trim()}</button>`
                    ).join('');
                }
            );

            return html;
        }

        // Şık seçildiğinde mesaj olarak gönder
        function sendChoice(harf) {
            // Soru balonunu sakla (cevap gelince butonları renklendirmek için)
            const aiMessages = document.querySelectorAll('.message-group.ai');
            if (aiMessages.length > 0) {
                lastChoiceBubble = aiMessages[aiMessages.length - 1].querySelector('.msg-bubble');
            }
            lastSelectedChoice = harf;

            // Tüm şıkları kilitle
            const choiceButtons = document.querySelectorAll('.choice-btn');
            if (choiceButtons.length > 0) {
                choiceButtons.forEach(btn => {
                    btn.disabled = true;
                    btn.onclick = null;
                    if (btn.dataset.choice === harf) btn.classList.add('selected');
                });
            }
            messageInput.value = `Cevabım ${harf}`;
            chatForm.dispatchEvent(new Event('submit'));
        }

        // Add message to chat
        function addMessage(content, role, time = null) {
            const timeStr = time || new Date().toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'});

            hideWelcome();

            const group = document.createElement('div');
            group.className = `message-group ${role}`;

            const avatarContent = role === 'ai' ? '<img src="assets/aibuke-avatar.png" alt="Aibüke" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">' : userAvatar;

            group.innerHTML = `
                <div class="msg-avatar">${avatarContent}</div>
                <div class="msg-content">
                    <div class="msg-bubble">${formatContent(content)}</div>
                    <span class="msg-time">${timeStr}</span>
                </div>
            `;

            chatContainer.appendChild(group);
            scrollToBottom();
        }

        // Add streaming message
        function addStreamingMessage() {
            const timeStr = new Date().toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'});

            hideWelcome();

            const group = document.createElement('div');
            group.className = 'message-group ai streaming';

            group.innerHTML = `
                <div class="msg-avatar">📚</div>
                <div class="msg-content">
                    <div class="msg-bubble"><span class="content"></span><span class="streaming-cursor"></span></div>
                    <span class="msg-time">${timeStr}</span>
                </div>
            `;

            chatContainer.appendChild(group);
            scrollToBottom();

            return group.querySelector('.content');
        }

        // Text-to-Speech
        // Scroll to bottom
        function scrollToBottom() {
            requestAnimationFrame(() => {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            });
        }

        // Metni bold/plain token dizisine çevir
        function buildStreamTokens(text) {
            const tokens = [];
            const lines = text.split('\n');
            for (let li = 0; li < lines.length; li++) {
                if (li > 0) tokens.push({ html: '<br>', word: '', delay: false });
                const line = lines[li].trim();
                if (!line) continue;
                const segments = line.split(/(\*\*[^*]+\*\*)/);
                let firstOnLine = true;
                for (const seg of segments) {
                    if (!seg) continue;
                    const isBold = /^\*\*[^*]+\*\*$/.test(seg);
                    const content = isBold ? seg.slice(2, -2) : seg;
                    const words = content.split(/\s+/).filter(w => w);
                    for (const word of words) {
                        const prefix = firstOnLine ? '' : ' ';
                        tokens.push({
                            html: isBold ? `${prefix}<strong>${word}</strong>` : `${prefix}${word}`,
                            word,
                            delay: true
                        });
                        firstOnLine = false;
                    }
                }
            }
            return tokens;
        }

        // Stream response text (typewriter effect)
        async function streamResponse(text, targetElement) {
            streamingStopped = false;
            const tokens = buildStreamTokens(text);
            let accumulated = '';

            for (const token of tokens) {
                if (streamingStopped) break;
                accumulated += token.html;
                targetElement.innerHTML = accumulated;
                scrollToBottom();

                if (token.delay) {
                    let delay = 70 + Math.random() * 40;
                    if (/[.!?]$/.test(token.word)) delay += 250;
                    else if (/[,;:]$/.test(token.word)) delay += 100;
                    await new Promise(r => setTimeout(r, delay));
                }
            }

            // Tam formatlama — durdurulmadıysa
            if (!streamingStopped) {
                targetElement.innerHTML = formatContent(text);
            }
            const cursor = targetElement.parentElement.querySelector('.streaming-cursor');
            if (cursor) cursor.remove();

            // Streaming tamamlandı - pulse animasyonunu kaldır
            const messageGroup = targetElement.closest('.message-group.ai');
            if (messageGroup) messageGroup.classList.remove('streaming');

            scrollToBottom();
        }

        // Send message
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const message = messageInput.value.trim();
            if (!message && !selectedImage) return;

            // UI update
            messageInput.value = '';
            messageInput.style.height = 'auto';
            addMessage(message || '📸 Fotoğraf gönderildi', 'user');
            sendBtn.disabled = true;

            // Show typing indicator
            typingIndicator.classList.add('active');
            chatContainer.appendChild(typingIndicator);
            scrollToBottom();

            // Prepare form data
            const formData = new FormData();
            formData.append('message', message);

            if (selectedImage) {
                formData.append('image', selectedImage);
            }

            fetchAbortController = new AbortController();
            stopBtn.style.display = 'flex';

            try {
                const response = await fetch('api/chat.php', {
                    method: 'POST',
                    body: formData,
                    signal: fetchAbortController.signal,
                    cache: 'no-store'
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const data = await response.json();

                // Hide typing
                typingIndicator.classList.remove('active');

                if (response.status === 429) {
                    // Günlük limit doldu — gece yarısına kalan süreyi hesapla
                    const now = new Date();
                    const midnight = new Date(now);
                    midnight.setHours(24, 0, 0, 0);
                    const diffMs = midnight - now;
                    const diffH = Math.floor(diffMs / 3600000);
                    const diffM = Math.floor((diffMs % 3600000) / 60000);
                    const timeStr = diffH > 0 ? `${diffH} saat ${diffM} dakika` : `${diffM} dakika`;
                    addMessage(`Bugünlük bu kadar ders yeter! 🌙\n\nGünlük kullanım limitine ulaştın. Limitin **${timeStr}** sonra yenilenecek. Biraz dinlen, yarın devam ederiz! 💪`, 'ai');
                    fetchAbortController = null;
                    stopBtn.style.display = 'none';
                    messageInput.disabled = true;
                    sendBtn.disabled = true;
                    document.querySelector('.input-field-wrapper').classList.add('limit-reached');
                    messageInput.placeholder = 'Limit doldu';
                    // Limit timer ekle (dinamik)
                    const limitTimer = document.createElement('div');
                    limitTimer.className = 'limit-timer';
                    limitTimer.innerHTML = '<i class="fas fa-hourglass-half"></i> <span>Limitin <span class="time">' + timeStr + '</span> sonra yenilenecek</span>';
                    document.querySelector('.input-wrapper').appendChild(limitTimer);
                    return;
                } else if (data.error) {
                    const errorMessages = [
                        'Bir saniye, hemen döneceğim! Tekrar yazar mısın?',
                        'Küçük bir aksaklık oldu, tekrar deneyebilir misin?',
                        'Kafamı toparlıyorum, birazdan tekrar yaz bana.'
                    ];
                    addMessage(errorMessages[Math.floor(Math.random() * errorMessages.length)], 'ai');
                } else if (data.response_type === 'schedule_setup') {
                    // ─── Program kurulum panelini aç ───
                    currentStreamingElement = addStreamingMessage();
                    await streamResponse(data.response, currentStreamingElement);
                    currentStreamingElement = null;
                    stopBtn.style.display = 'none';

                    // Dinle butonu
                    const lastAiBubbleSched = chatContainer.querySelector('.message-group.ai:last-child .msg-bubble');
                    if (lastAiBubbleSched) addListenButton(lastAiBubbleSched, data.response);

                    // Paneli aç
                    setTimeout(() => openPanel('schedule'), 500);

                } else if (data.response_type === 'exam_setup') {
                    // ─── Sınav provası modalını aç ───
                    currentStreamingElement = addStreamingMessage();
                    await streamResponse(data.response, currentStreamingElement);
                    currentStreamingElement = null;
                    stopBtn.style.display = 'none';

                    const lastAiBubbleExam = chatContainer.querySelector('.message-group.ai:last-child .msg-bubble');
                    if (lastAiBubbleExam) addListenButton(lastAiBubbleExam, data.response);

                    if (data.auto_exam) {
                        // Akıllı Kamera 2.0: otomatik konu + soru sayısı
                        setTimeout(() => {
                            openExamModal();
                            // Soru sayısı butonunu otomatik seç (5, 10 veya 20'ye yuvarla)
                            const autoCount = Math.min(data.auto_exam.num_questions || 5, 20);
                            const closest = [5,10,20].reduce((a,b) => Math.abs(b-autoCount) < Math.abs(a-autoCount) ? b : a);
                            const autoBtn = document.querySelector(`.sim-count-btn[data-val="${closest}"]`);
                            if (autoBtn) selectSimCount(autoBtn);
                        }, 500);
                    } else {
                        setTimeout(() => openExamModal(), 500);
                    }

                } else if (data.response_type === 'schedule' && data.schedule_data) {
                    // ─── Çalışma programı kartı (direkt) ───
                    currentStreamingElement = addStreamingMessage();
                    await streamResponse(data.response, currentStreamingElement);
                    currentStreamingElement = null;
                    stopBtn.style.display = 'none';

                    const aiGroups2 = chatContainer.querySelectorAll('.message-group.ai');
                    const lastAiGroup2 = aiGroups2.length > 0 ? aiGroups2[aiGroups2.length - 1] : null;
                    if (lastAiGroup2) {
                        lastAiGroup2.classList.add('has-schedule');
                        const scheduleEl = renderScheduleCard(data.schedule_data);
                        lastAiGroup2.querySelector('.msg-content').appendChild(scheduleEl);
                        scrollToBottom();
                    }

                    const lastAiBubble2 = chatContainer.querySelector('.message-group.ai:last-child .msg-bubble');
                    if (lastAiBubble2) addListenButton(lastAiBubble2, data.response);
                    handleChatResponse(data);

                } else {
                    // [DOĞRU] / [YANLIŞ:X] marker'ını yanıt metninden ayır
                    let displayResponse = data.response;
                    let answerResult = null;
                    const dogruMatch = data.response.match(/^\[DOĞRU\]\s*/);
                    const yanlisMatch = data.response.match(/^\[YANLIŞ:([A-E])\]\s*/);
                    if (dogruMatch) {
                        answerResult = { correct: true };
                        displayResponse = data.response.slice(dogruMatch[0].length);
                    } else if (yanlisMatch) {
                        answerResult = { correct: false, correctLetter: yanlisMatch[1] };
                        displayResponse = data.response.slice(yanlisMatch[0].length);
                    }

                    currentStreamingElement = addStreamingMessage();
                    stopBtn.style.display = 'flex';
                    await streamResponse(displayResponse, currentStreamingElement);
                    currentStreamingElement = null;
                    stopBtn.style.display = 'none';

                    // Faz 2: Dinle butonu ekle
                    const lastAiBubble = chatContainer.querySelector('.message-group.ai:last-child .msg-bubble');
                    if (lastAiBubble) addListenButton(lastAiBubble, displayResponse);

                    // Faz 2: Puan güncelle
                    handleChatResponse(data);

                    // Faz 3: Bahçeyi sula (her AI yanıtında)
                    waterGarden();

                    // Cevap doğru/yanlış ise soru balonundaki butonları renklendir
                    if (answerResult && lastChoiceBubble && lastSelectedChoice) {
                        lastChoiceBubble.querySelectorAll('.choice-btn').forEach(btn => {
                            if (btn.dataset.choice === lastSelectedChoice) {
                                btn.classList.add(answerResult.correct ? 'correct' : 'wrong');
                            }
                            if (!answerResult.correct && btn.dataset.choice === answerResult.correctLetter) {
                                btn.classList.add('correct');
                            }
                        });
                        lastChoiceBubble = null;
                        lastSelectedChoice = null;
                    }

                    // Önceki AI mesajlarındaki şık butonlarını kilitle
                    const aiMsgs = document.querySelectorAll('.message-group.ai');
                    for (let i = 0; i < aiMsgs.length - 1; i++) {
                        aiMsgs[i].querySelectorAll('.choice-btn').forEach(btn => btn.disabled = true);
                    }

                    // YouTube video önerisi — konu anlatımı varsa
                    if (data.youtube_query) {
                        const ytBubble = chatContainer.querySelector('.message-group.ai:last-child .msg-bubble');
                        if (ytBubble) suggestYouTubeVideos(data.youtube_query, ytBubble);
                    }

                    // Cevap kesildiyse "Devam et" butonu göster
                    if (data.truncated) {
                        const bubble = chatContainer.querySelector('.message-group.ai:last-child .msg-bubble');
                        if (bubble) {
                            const btn = document.createElement('button');
                            btn.className = 'continue-btn';
                            btn.textContent = '▶ Devam et';
                            btn.onclick = () => {
                                btn.remove();
                                messageInput.value = 'devam et';
                                chatForm.dispatchEvent(new Event('submit'));
                            };
                            bubble.appendChild(btn);
                        }
                    }
                }
            } catch (e) {
                typingIndicator.classList.remove('active');
                // Abort ise (kullanıcı durdurdu/refresh) sessizce geç
                if (e.name !== 'AbortError') {
                    const connMessages = [
                        'İnternet bağlantısında bir sorun oldu, tekrar deneyebilir misin?',
                        'Bağlantıda bir aksaklık yaşandı, bir daha dener misin?',
                        'Sana ulaşamadım bir anlık, tekrar deneyebilir misin?'
                    ];
                    addMessage(connMessages[Math.floor(Math.random() * connMessages.length)], 'ai');
                }
            }

            fetchAbortController = null;
            stopBtn.style.display = 'none';
            sendBtn.disabled = false;
            messageInput.focus();

            // Clear image
            if (selectedImage) {
                selectedImage = null;
                imagePreview.classList.remove('active');
            }
        });

        // Image upload
        imageBtn.addEventListener('click', () => {
            imageInput.click();
        });

        imageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                selectedImage = file;
                previewImg.src = URL.createObjectURL(file);
                imagePreview.classList.add('active');
            }
        });

        removeImage.addEventListener('click', () => {
            selectedImage = null;
            imagePreview.classList.remove('active');
            imageInput.value = '';
        });

        // Quick actions
        document.querySelectorAll('.quick-btn[data-prompt]').forEach(btn => {
            btn.addEventListener('click', () => {
                messageInput.value = btn.dataset.prompt;
                messageInput.focus();
                messageInput.dispatchEvent(new Event('input'));
            });
        });

        // Welcome cards
        document.querySelectorAll('.welcome-card').forEach(card => {
            card.addEventListener('click', () => {
                const prompt = card.dataset.prompt;
                if (prompt === 'Fotoğrafla soru göndermek istiyorum') {
                    imageInput.click();
                } else {
                    messageInput.value = prompt;
                    messageInput.focus();
                    messageInput.dispatchEvent(new Event('input'));
                }
            });
        });

        // Logout
        function openLogoutModal() {
            document.getElementById('logoutModal').style.display = 'flex';
        }
        function closeLogoutModal() {
            document.getElementById('logoutModal').style.display = 'none';
        }
        function confirmLogout() {
            window.location.href = 'logout.php';
        }
        document.getElementById('logoutBtn').addEventListener('click', openLogoutModal);


        // ═══════════════════════════════════════
        // FAZ 2: Panel Yönetimi
        // ═══════════════════════════════════════
        let activePanel = null;
        const panelOverlay = document.getElementById('panelOverlay');

        function openPanel(type) {
            closePanel();
            const panel = document.getElementById('panel' + type.charAt(0).toUpperCase() + type.slice(1));
            if (!panel) return;
            panel.classList.add('open');
            panelOverlay.classList.add('open');
            activePanel = type;

            if (type === 'leaderboard') loadLeaderboard();
            if (type === 'plan') loadDailyPlan();
            if (type === 'groups') loadGroups();
            if (type === 'tournament') loadTournaments();
            if (type === 'garden') loadGarden();
            if (type === 'calendar') loadCalendar();
        }

        function closePanel() {
            document.querySelectorAll('.slide-panel.open').forEach(p => p.classList.remove('open'));
            panelOverlay.classList.remove('open');
            activePanel = null;
        }

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closePanel(); });
        document.getElementById('btnLeaderboard').addEventListener('click', () => openPanel('leaderboard'));
        const btnCal = document.getElementById('btnCalendar');
        if (btnCal) btnCal.addEventListener('click', () => openPanel('calendar'));

        // Rozet popup
        const btnBadges = document.getElementById('btnBadges');
        const badgesPopup = document.getElementById('badgesPopup');
        if (btnBadges && badgesPopup) {
            btnBadges.addEventListener('click', (e) => {
                e.stopPropagation();
                badgesPopup.classList.toggle('show');
            });
            document.addEventListener('click', () => badgesPopup.classList.remove('show'));
        }

        // ═══════════════════════════════════════
        // FAZ 2: Liderlik Tablosu
        // ═══════════════════════════════════════
        let currentLbPeriod = 'all';
        async function loadLeaderboard(period) {
            if (period) currentLbPeriod = period;
            const body = document.getElementById('leaderboardBody');
            try {
                const res = await fetch(`api/points.php?action=leaderboard&period=${currentLbPeriod}`);
                const data = await res.json();

                const periodLabels = { all: 'Tüm Zamanlar', monthly: 'Bu Ay', weekly: 'Bu Hafta' };
                let html = `<div class="lb-my-rank">
                    <div class="rank-label">Sıralaman — ${periodLabels[currentLbPeriod]}</div>
                    <div class="rank-pos">#${data.my_rank}</div>
                    <div class="rank-points">${data.my_points} puan</div>
                </div>`;

                if (data.leaderboard.length === 0) {
                    html += '<div class="plan-empty">Bu dönemde henüz puan yok</div>';
                } else {
                    html += '<ul class="lb-list">';
                    data.leaderboard.forEach((item, i) => {
                        const medal = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : (i+1);
                        html += `<li class="lb-item">
                            <span class="lb-pos">${medal}</span>
                            <span class="lb-name">${item.display_name} <span class="lb-class">${item.class}</span></span>
                            <span class="lb-badges">${item.badges || ''}</span>
                            <span class="lb-points">${item.total_points}</span>
                        </li>`;
                    });
                    html += '</ul>';
                }
                body.innerHTML = html;
            } catch {
                body.innerHTML = '<div class="plan-empty">Liderlik tablosu yüklenemedi</div>';
            }
        }

        function filterLeaderboard(btn) {
            document.querySelectorAll('.lb-filter').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            loadLeaderboard(btn.dataset.period);
        }

        // ═══════════════════════════════════════
        // FAZ 2: Günlük Plan
        // ═══════════════════════════════════════
        async function loadDailyPlan() {
            const body = document.getElementById('planBody');
            try {
                const res = await fetch('api/plans.php');
                const data = await res.json();

                if (!data.plan) {
                    body.innerHTML = '<div class="plan-empty">Bugün için plan henüz oluşturulmamış.<br><br>Plan her sabah 07:00\'da otomatik oluşturulur.</div>';
                    return;
                }

                const plan = data.plan;
                const content = plan.plan_content;
                let html = '';

                if (content && content.maddeler) {
                    content.maddeler.forEach(m => {
                        html += `<div class="plan-card">
                            <div class="plan-subject">${m.konu}</div>
                            <div class="plan-duration">${m.sure_dk} dakika</div>
                            <div class="plan-desc">${m.aciklama}</div>
                        </div>`;
                    });
                } else if (content && content.baslik) {
                    html += `<div class="plan-card"><div class="plan-subject">${content.baslik}</div></div>`;
                } else if (typeof content === 'string') {
                    html += `<div class="plan-card"><div class="plan-desc">${content}</div></div>`;
                }

                if (plan.motivation_tip) {
                    html += `<div class="plan-motivation">${plan.motivation_tip}</div>`;
                }

                if (!plan.completed) {
                    html += `<button class="plan-complete-btn" onclick="completePlan(this)">Planı Tamamladım!</button>`;
                } else {
                    html += `<button class="plan-complete-btn" disabled>Tamamlandı ✓</button>`;
                }

                body.innerHTML = html;
            } catch(e) {
                body.innerHTML = '<div class="plan-empty">Plan yüklenemedi</div>';
            }
        }

        async function completePlan(btn) {
            btn.disabled = true;
            btn.textContent = 'Kaydediliyor...';
            try {
                const res = await fetch('api/plans.php', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    btn.textContent = 'Tamamlandı ✓';
                    if (data.points_earned) {
                        updatePointsDisplay(data.points_earned);
                    }
                } else {
                    btn.textContent = data.message || 'Hata';
                }
            } catch(e) { btn.textContent = 'Hata oluştu'; }
        }

        function updatePointsDisplay(added) {
            const el = document.getElementById('statPoints');
            if (el) el.textContent = parseInt(el.textContent) + (added || 0);
        }

        // ═══════════════════════════════════════
        // FAZ 2: Çalışma Programı Kartı
        // ═══════════════════════════════════════

        let lastScheduleData = null;

        // ─── Schedule Panel Helpers ───
        function selectWeekend(btn) {
            btn.parentElement.querySelectorAll('.sched-weekend-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        async function generateScheduleFromPanel() {
            const btn = document.getElementById('schedGenerateBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Oluşturuluyor...';

            const schoolStart = document.getElementById('schedSchoolStart').value;
            const schoolEnd = document.getElementById('schedSchoolEnd').value;
            const studyHours = parseInt(document.getElementById('schedStudyHours').value) || 3;
            const focusSubjects = [...document.querySelectorAll('#schedSubjects .sched-chip.active')]
                .map(c => c.dataset.subject);
            const weekendBtn = document.querySelector('.sched-weekend-btn.active');
            const weekend = weekendBtn ? weekendBtn.dataset.val : 'study';
            const notes = document.getElementById('schedNotes').value.trim();

            try {
                const res = await fetch('api/schedule.php?action=generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        school_start: schoolStart,
                        school_end: schoolEnd,
                        study_hours: studyHours,
                        focus_subjects: focusSubjects,
                        weekend: weekend,
                        notes: notes
                    }),
                    cache: 'no-store'
                });

                const data = await res.json();

                if (!data.success || !data.schedule) {
                    btn.innerHTML = '<i class="fas fa-xmark"></i> ' + (data.error || 'Hata oluştu');
                    btn.disabled = false;
                    return;
                }

                // Panel'i kapat
                closePanel();

                // Chat'e mesaj olarak ekle
                addMessage('Çalışma programı oluştur', 'user');
                const aiText = 'İşte senin için hazırladığım çalışma programı! Beğenirsen "Kaydet" butonuna basarak kaydedebilirsin.';
                addMessage(aiText, 'ai');

                // Schedule kartı ekle
                const aiGroups = chatContainer.querySelectorAll('.message-group.ai');
                const lastAiGroup = aiGroups.length > 0 ? aiGroups[aiGroups.length - 1] : null;
                if (lastAiGroup) {
                    lastAiGroup.classList.add('has-schedule');
                    const scheduleEl = renderScheduleCard(data.schedule);
                    lastAiGroup.querySelector('.msg-content').appendChild(scheduleEl);
                }
                scrollToBottom();

                // Dinle butonu
                const lastBubble = lastAiGroup?.querySelector('.msg-bubble');
                if (lastBubble) addListenButton(lastBubble, aiText);

            } catch(e) {
                btn.innerHTML = '<i class="fas fa-xmark"></i> Bağlantı hatası';
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-magic"></i> Programı Oluştur';
        }

        function renderScheduleCard(schedule) {
            lastScheduleData = schedule;

            const typeLabels = { konu: 'Konu', soru: 'Soru Çözümü', tekrar: 'Tekrar', deneme: 'Deneme', mola: 'Mola' };
            const dayIcons = { Pazartesi: '1️⃣', Salı: '2️⃣', Çarşamba: '3️⃣', Perşembe: '4️⃣', Cuma: '5️⃣', Cumartesi: '6️⃣', Pazar: '7️⃣' };

            const card = document.createElement('div');
            card.className = 'schedule-card';

            // Yapısal veri var mı kontrol et
            const hasDays = schedule.days && Array.isArray(schedule.days) && schedule.days.length > 0;

            let html = `
                <div class="schedule-card-header">
                    <span class="schedule-card-header-icon">📋</span>
                    <div class="schedule-card-header-text">
                        <div class="schedule-card-header-title">${escHtml(schedule.title || 'Haftalık Çalışma Programı')}</div>
                        <div class="schedule-card-header-sub">${schedule.daily_hours ? schedule.daily_hours + ' saat/gün hedef' : ''}</div>
                    </div>
                </div>`;

            if (hasDays) {
                // ─── Yapısal program kartı ───
                html += '<div class="schedule-days">';
                schedule.days.forEach(day => {
                    const icon = dayIcons[day.day] || '📌';
                    const sessions = day.sessions || [];

                    let totalMin = 0;
                    sessions.forEach(s => {
                        if (s.time && s.time.includes('-')) {
                            const parts = s.time.split('-');
                            if (parts.length === 2 && parts[0].includes(':') && parts[1].includes(':')) {
                                const [h1, m1] = parts[0].split(':').map(Number);
                                const [h2, m2] = parts[1].split(':').map(Number);
                                if (!isNaN(h1) && !isNaN(m1) && !isNaN(h2) && !isNaN(m2)) {
                                    totalMin += (h2 * 60 + m2) - (h1 * 60 + m1);
                                }
                            }
                        }
                    });
                    const totalStr = totalMin > 0 ? `${Math.floor(totalMin/60)}s ${totalMin%60}dk` : '';

                    html += `
                    <div class="schedule-day">
                        <div class="schedule-day-header" onclick="this.parentElement.classList.toggle('collapsed')">
                            <span class="day-toggle">▼</span>
                            ${icon} ${escHtml(day.day)}
                            <span class="day-total">${totalStr}</span>
                        </div>
                        <div class="schedule-sessions">`;

                    sessions.forEach(s => {
                        const type = (s.type || 'konu').toLowerCase();
                        const typeLabel = typeLabels[type] || s.type || '';
                        html += `
                            <div class="schedule-session">
                                <span class="schedule-session-time">${escHtml(s.time || '')}</span>
                                <div class="schedule-session-info">
                                    <div class="schedule-session-subject">${escHtml(s.subject || '')}</div>
                                    <div class="schedule-session-topic">${escHtml(s.topic || '')}</div>
                                    ${typeLabel ? `<span class="schedule-session-type type-${type}">${escHtml(typeLabel)}</span>` : ''}
                                </div>
                            </div>`;
                    });

                    html += `</div></div>`;
                });
                html += '</div>';
            } else if (schedule.raw_text) {
                // ─── AI düz metin döndürdü — formatla göster ───
                html += `<div class="schedule-card-notes" style="white-space:pre-wrap;padding:16px;">${formatContent(schedule.raw_text)}</div>`;
            } else {
                // ─── Bilinmeyen format — JSON'u okunaklı göster ───
                const readable = Object.entries(schedule)
                    .filter(([k]) => !['title','daily_hours','notes','motivation'].includes(k))
                    .map(([k,v]) => `${k}: ${typeof v === 'string' ? v : JSON.stringify(v)}`)
                    .join('\n');
                if (readable) {
                    html += `<div class="schedule-card-notes" style="white-space:pre-wrap;padding:16px;">${escHtml(readable)}</div>`;
                }
            }

            if (schedule.notes) {
                html += `<div class="schedule-card-notes">${escHtml(schedule.notes)}</div>`;
            }
            if (schedule.motivation) {
                html += `<div class="schedule-card-motivation">${escHtml(schedule.motivation)}</div>`;
            }

            html += `
                <div class="schedule-card-actions">
                    <button class="schedule-save-btn" onclick="saveSchedule(this)">
                        <i class="fas fa-bookmark"></i> Kaydet
                    </button>
                    <button class="schedule-regen-btn" onclick="regenerateSchedule(this)">
                        <i class="fas fa-rotate"></i> Yenile
                    </button>
                </div>`;

            card.innerHTML = html;
            return card;
        }

        function escHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ─── YouTube Video Önerisi ───
        async function suggestYouTubeVideos(query, targetBubble) {
            if (!query || !targetBubble) return;
            // Zaten bu bubble'da video varsa ekleme
            const msgContent = targetBubble.closest('.msg-content');
            if (!msgContent || msgContent.querySelector('.yt-suggestion')) return;

            // Önceki tüm video önerilerini gizle — sadece son mesajda göster
            document.querySelectorAll('.yt-suggestion').forEach(el => el.remove());

            try {
                const examType = '<?= ($profile["exam_type"] ?? "yks") === "lgs" ? "lgs" : "tyt" ?>';
                const res = await fetch(`api/youtube.php?q=${encodeURIComponent(query)}&exam=${examType}`);
                if (!res.ok) return;
                const data = await res.json();
                if (!data.success || !data.videos || data.videos.length === 0) return;

                let html = '<div class="yt-suggestion"><div class="yt-suggestion-title">📺 Önerilen Videolar</div><div class="yt-videos">';
                data.videos.forEach((v, i) => {
                    html += `<a class="yt-card" href="${escHtml(v.url)}" target="_blank" rel="noopener" style="animation-delay:${i * 80}ms">
                        <img class="yt-card-thumb" src="${escHtml(v.thumbnail)}" alt="" loading="lazy">
                        <div class="yt-card-info">
                            <div class="yt-card-title">${escHtml(v.title)}</div>
                            <div class="yt-card-channel">${escHtml(v.channel)}</div>
                            <div class="yt-card-play">▶ İzle</div>
                        </div>
                    </a>`;
                });
                html += '</div></div>';
                msgContent.insertAdjacentHTML('beforeend', html);
            } catch(e) { /* sessizce geç */ }
        }

        async function saveSchedule(btn) {
            if (!lastScheduleData) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';

            try {
                const res = await fetch('api/schedule.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        schedule_data: lastScheduleData,
                        title: lastScheduleData.title || 'Haftalık Çalışma Programı'
                    }),
                    cache: 'no-store'
                });
                const data = await res.json();
                if (data.success) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Kaydedildi!';
                    btn.classList.add('saved');
                    if (data.points_earned) updatePointsDisplay(data.points_earned);
                } else {
                    btn.innerHTML = '<i class="fas fa-xmark"></i> Hata';
                    btn.disabled = false;
                }
            } catch(e) {
                btn.innerHTML = '<i class="fas fa-xmark"></i> Bağlantı hatası';
                btn.disabled = false;
            }
        }

        async function regenerateSchedule(btn) {
            // Paneli tekrar aç — öğrenci ayarlarını değiştirebilsin
            openPanel('schedule');
        }

        // ═══════════════════════════════════════
        // FAZ 2: Sınav Simülasyonu
        // ═══════════════════════════════════════
        let simData = null;
        let simTimer = null;
        let simCurrentQ = 0;
        let simAnswers = {};

        function openExamModal() {
            resetExamSetup();
            document.getElementById('examModal').classList.add('open');
        }
        function closeExamModal() {
            document.getElementById('examModal').classList.remove('open');
            if (simTimer) clearInterval(simTimer);
        }
        async function loadSimTopics(subject) {
            const container = document.getElementById('simTopicChips');
            if (!container) return;
            container.innerHTML = '<span class="sim-topic-chips-empty">Yükleniyor...</span>';
            updateTopicBadge();
            if (!subject) { container.innerHTML = ''; return; }
            try {
                const res = await fetch(`api/exam_simulation.php?action=topics&subject=${encodeURIComponent(subject)}`);
                const data = await res.json();
                container.innerHTML = '';
                if (!data.topics || data.topics.length === 0) {
                    container.innerHTML = '<span class="sim-topic-chips-empty">Bu derste konu bulunamadı</span>';
                    return;
                }
                data.topics.forEach(t => {
                    const chip = document.createElement('button');
                    chip.type = 'button';
                    chip.className = 'sim-topic-chip';
                    chip.dataset.topic = t.topic;
                    chip.textContent = t.topic;
                    chip.onclick = () => { chip.classList.toggle('active'); updateTopicBadge(); };
                    container.appendChild(chip);
                });
            } catch {
                const emptyMsg = document.createElement('span');
                emptyMsg.className = 'sim-topic-chips-empty';
                emptyMsg.textContent = 'Konular yüklenemedi';
                container.appendChild(emptyMsg);
            }
        }

        function toggleTopicAccordion(btn) {
            btn.classList.toggle('open');
            const body = btn.nextElementSibling;
            body.classList.toggle('open');
        }

        function updateTopicBadge() {
            const badge = document.getElementById('simTopicBadge');
            if (!badge) return;
            const count = document.querySelectorAll('#simTopicChips .sim-topic-chip.active').length;
            badge.textContent = count > 0 ? count + ' konu' : '';
        }

        function getSelectedTopics() {
            const chips = document.querySelectorAll('#simTopicChips .sim-topic-chip.active');
            return Array.from(chips).map(c => c.dataset.topic);
        }

        function updateCountLabels() {
            const diffBtn = document.querySelector('.sim-diff-btn.active');
            const secPerQ = parseInt(diffBtn?.dataset.time || 120);
            document.querySelectorAll('.sim-count-btn').forEach(btn => {
                const count = parseInt(btn.dataset.val);
                const totalSec = count * secPerQ;
                if (totalSec >= 60) {
                    const m = Math.ceil(totalSec / 60);
                    btn.dataset.label = m + ' dk';
                } else {
                    btn.dataset.label = totalSec + ' sn';
                }
            });
        }

        function selectDifficulty(btn) {
            btn.parentElement.querySelectorAll('.sim-diff-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            updateCountLabels();
        }

        function selectSimCount(btn) {
            btn.closest('.sim-count-row').querySelectorAll('.sim-count-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        async function startExamSim() {
            const subject = document.getElementById('simSubject').value;
            const countBtn = document.querySelector('.sim-count-btn.active');
            const count = countBtn ? parseInt(countBtn.dataset.val) : 5;
            const diffBtn = document.querySelector('.sim-diff-btn.active');
            const difficulty = diffBtn ? diffBtn.dataset.val : 'easy';
            const secPerQ = parseInt(diffBtn?.dataset.time || 120);
            const pointsPerQ = parseFloat(diffBtn?.dataset.points || 0.5);
            const totalSec = count * secPerQ;
            const timeMin = Math.ceil(totalSec / 60);
            const topics = getSelectedTopics();

            const setup = document.getElementById('examSetup');
            const active = document.getElementById('examActive');
            const report = document.getElementById('examReport');

            const timeLabel = secPerQ >= 60 ? `${secPerQ/60} dk/soru` : `${secPerQ} sn/soru`;
            // Bekleme ekranı — animasyonlu
            setup.innerHTML = `<div class="sim-preparing">
                <div class="sim-preparing-icon">📝</div>
                <div class="sim-preparing-text">Sorular Oluşturuluyor</div>
                <div class="sim-preparing-sub">${count} soru · ${timeLabel} · ${pointsPerQ} puan/soru</div>
                <div class="sim-prep-progress"><div class="sim-prep-progress-fill"></div></div>
                <div class="sim-prep-dots">
                    <div class="sim-prep-dot"></div>
                    <div class="sim-prep-dot"></div>
                    <div class="sim-prep-dot"></div>
                    <div class="sim-prep-dot"></div>
                </div>
                <div class="sim-prep-status" id="prepStatus">Sorular hazırlanıyor...</div>
                <div class="sim-prep-hints">
                    <div class="sim-prep-hint-card hint-pencil-card">
                        <span class="sim-prep-hint-icon">✏️</span>
                        Yanına kağıt ve kalem hazırla!
                    </div>
                    <div class="sim-prep-hint-card hint-stay-card">
                        <span class="sim-prep-hint-icon">📱</span>
                        Lütfen bu sayfada kal, sorular yolda!
                    </div>
                    <div class="sim-prep-hint-card hint-focus-card">
                        <span class="sim-prep-hint-icon">🧘</span>
                        Bir nefes al, odaklan...
                    </div>
                </div>
            </div>`;

            // Dönen durum mesajları
            const prepStatuses = [
                'Sorular hazırlanıyor...',
                'Konuya uygun sorular seçiliyor...',
                'Neredeyse bitti...',
            ];
            let prepIdx = 0;
            const prepInterval = setInterval(() => {
                prepIdx = (prepIdx + 1) % prepStatuses.length;
                const el = document.getElementById('prepStatus');
                if (el) {
                    el.style.animation = 'none';
                    void el.offsetWidth;
                    el.textContent = prepStatuses[prepIdx];
                    el.style.animation = 'prepStatusFade 0.4s ease';
                }
            }, 1500);

            try {
                const fd = new FormData();
                fd.append('action', 'start');
                fd.append('subject', subject);
                fd.append('num_questions', count);
                fd.append('time_seconds', totalSec);
                fd.append('difficulty', difficulty);
                fd.append('points_per_q', pointsPerQ);
                if (topics.length > 0) fd.append('topics', JSON.stringify(topics));

                const res = await fetch('api/exam_simulation.php?action=start', { method: 'POST', body: fd });
                const data = await res.json();
                clearInterval(prepInterval);

                // Progress bar'ı %100'e tamamla
                const fillBar = document.querySelector('.sim-prep-progress-fill');
                if (fillBar) {
                    fillBar.style.animation = 'prepProgressShimmer 1.5s ease infinite';
                    fillBar.style.transition = 'width 0.6s ease';
                    fillBar.style.width = '100%';
                }

                if (!data.success) {
                    setup.innerHTML = `<div class="sim-preparing">
                        <div class="sim-preparing-icon">😔</div>
                        <div class="sim-preparing-text">${data.error}</div>
                        <button class="sim-start-btn" onclick="resetExamSetup()">Tekrar Dene</button>
                    </div>`;
                    return;
                }

                simData = data;
                simCurrentQ = 0;
                simAnswers = {};

                // %100 dolma animasyonunu göster, sonra countdown'a geç
                await new Promise(r => setTimeout(r, 700));

                // Countdown animasyonu başlat
                setup.style.display = 'none';
                active.style.display = 'block';
                report.style.display = 'none';
                document.getElementById('examBody').classList.add('exam-active');
                await showExamCountdown(active, data.time_seconds || data.time_minutes * 60);
            } catch(e) {
                clearInterval(prepInterval);
                setup.innerHTML = `<div class="sim-preparing">
                    <div class="sim-preparing-icon">😔</div>
                    <div class="sim-preparing-text">Bağlantı hatası</div>
                    <button class="sim-start-btn" onclick="resetExamSetup()">Tekrar Dene</button>
                </div>`;
            }
        }

        function showExamCountdown(container, totalSeconds) {
            return new Promise(resolve => {
                const nums = [5, 4, 3, 2, 1];
                let idx = 0;

                container.innerHTML = `<div class="sim-countdown-overlay">
                    <div class="sim-countdown-ring"></div>
                    <div class="sim-countdown-num">${nums[0]}</div>
                    <div class="sim-countdown-label">Hazır ol!</div>
                </div>`;

                const numEl = container.querySelector('.sim-countdown-num');
                const ringEl = container.querySelector('.sim-countdown-ring');
                const labelEl = container.querySelector('.sim-countdown-label');

                const labels = ['Hazır ol!', 'Odaklan!', 'Nefes al...', 'Başlıyoruz!', ''];

                const interval = setInterval(() => {
                    idx++;
                    if (idx < nums.length) {
                        // Sayı göster
                        numEl.textContent = nums[idx];
                        numEl.style.animation = 'none';
                        ringEl.style.animation = 'none';
                        void numEl.offsetWidth; // reflow
                        numEl.style.animation = 'countdownPop 1s cubic-bezier(0.34, 1.56, 0.64, 1) forwards';
                        ringEl.style.animation = 'countdownSpin 1s linear';
                        labelEl.textContent = labels[idx];
                    } else {
                        // BAŞLA!
                        clearInterval(interval);
                        numEl.className = 'sim-countdown-go';
                        numEl.textContent = 'BAŞLA!';
                        numEl.style.animation = 'countdownGo 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) forwards';
                        ringEl.style.display = 'none';
                        labelEl.style.display = 'none';

                        setTimeout(() => {
                            container.innerHTML = '';
                            startSimTimer(totalSeconds);
                            renderSimQuestion();
                            resolve();
                        }, 900);
                    }
                }, 1000);
            });
        }

        function resetExamSetup() {
            document.getElementById('examBody').classList.remove('exam-active');
            const setup = document.getElementById('examSetup');
            setup.innerHTML = `
                <div class="sim-setup-icon">📝</div>
                <div class="sim-setup-title">Kendini Test Et</div>
                <p class="sim-setup-desc">Ders seç, soru sayısını belirle ve gerçek sınav ortamını simüle et!</p>
                <div class="sim-fields-grid">
                    <div class="sim-field"><label>Ders</label>
                        <select id="simSubject" onchange="loadSimTopics(this.value)">
                        <?php if ($examType === 'lgs'): ?>
                            <option>Matematik</option><option>Fen Bilimleri</option><option>Türkçe</option><option>İnkılap Tarihi</option><option>Din Kültürü</option><option>İngilizce</option>
                        <?php elseif ($examType === 'tyt' || $examType === 'yks'): ?>
                            <option>Matematik</option><option>Fizik</option><option>Kimya</option><option>Biyoloji</option><option>Türkçe</option><option>Tarih</option><option>Coğrafya</option><option>Felsefe</option><option>Geometri</option>
                        <?php else: ?>
                            <option>Matematik</option><option>Fizik</option><option>Kimya</option><option>Biyoloji</option><option>Türkçe</option><option>Tarih</option><option>Coğrafya</option><option>Geometri</option>
                        <?php endif; ?>
                        </select></div>
                    <div class="sim-field"><label>Soru Sayısı</label>
                        <div class="sim-count-row">
                            <button type="button" class="sim-count-btn active" data-val="5" data-label="10 dk" onclick="selectSimCount(this)">5</button>
                            <button type="button" class="sim-count-btn" data-val="10" data-label="20 dk" onclick="selectSimCount(this)">10</button>
                            <button type="button" class="sim-count-btn" data-val="20" data-label="40 dk" onclick="selectSimCount(this)">20</button>
                        </div>
                    </div>
                </div>
                <div class="sim-field" style="margin-top:4px">
                    <label>Zorluk</label>
                    <div class="sim-difficulty-row">
                        <button type="button" class="sim-diff-btn active" data-val="easy" data-time="120" data-points="0.5" onclick="selectDifficulty(this)">Kolay<span class="diff-meta">2 dk / 0.5 puan</span></button>
                        <button type="button" class="sim-diff-btn" data-val="medium" data-time="60" data-points="1" onclick="selectDifficulty(this)">Orta<span class="diff-meta">1 dk / 1 puan</span></button>
                        <button type="button" class="sim-diff-btn" data-val="hard" data-time="30" data-points="2" onclick="selectDifficulty(this)">💀 Zor<span class="diff-meta">30 sn / 2 puan</span></button>
                    </div>
                </div>
                <div class="sim-accordion" style="margin-top:4px">
                    <button type="button" class="sim-accordion-toggle" onclick="toggleTopicAccordion(this)">
                        <span>Konu Seç <span class="sim-acc-hint">(seçmezsen rastgele)</span></span>
                        <span class="sim-acc-badge" id="simTopicBadge"></span>
                        <i class="fas fa-chevron-down sim-acc-icon"></i>
                    </button>
                    <div class="sim-accordion-body" id="simAccordionBody">
                        <div class="sim-topic-chips" id="simTopicChips"></div>
                    </div>
                </div>
                <button class="sim-start-btn" onclick="startExamSim()"><i class="fas fa-play"></i> Provayı Başlat</button>`;
            setup.style.display = 'block';
            document.getElementById('examActive').style.display = 'none';
            document.getElementById('examReport').style.display = 'none';
            // İlk dersin konularını yükle
            const subj = document.getElementById('simSubject');
            if (subj) loadSimTopics(subj.value);
        }

        function startSimTimer(totalSeconds) {
            let remaining = totalSeconds;
            const timerEl = document.createElement('div');
            timerEl.className = 'sim-timer';
            timerEl.id = 'simTimerDisplay';

            function updateTimer() {
                const m = Math.floor(remaining / 60);
                const s = remaining % 60;
                timerEl.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
                timerEl.className = 'sim-timer' + (remaining < 120 ? ' danger' : remaining < totalSeconds * 0.5 ? ' warning' : '');

                if (remaining <= 0) {
                    clearInterval(simTimer);
                    finishExamSim();
                }
                remaining--;
            }

            updateTimer();
            simTimer = setInterval(updateTimer, 1000);

            const active = document.getElementById('examActive');
            active.innerHTML = '';
            active.appendChild(timerEl);

            const progress = document.createElement('div');
            progress.className = 'sim-progress';
            progress.id = 'simProgress';
            active.appendChild(progress);

            const qArea = document.createElement('div');
            qArea.id = 'simQuestionArea';
            active.appendChild(qArea);
        }

        function renderSimQuestion() {
            if (!simData || simCurrentQ >= simData.questions.length) return;

            const q = simData.questions[simCurrentQ];
            const area = document.getElementById('simQuestionArea');
            const total = simData.questions.length;

            document.getElementById('simProgress').textContent = `Soru ${simCurrentQ + 1} / ${total}`;

            let html = `<div class="sim-question">
                <div class="q-num">Soru ${q.id}</div>
                <div class="q-text">${q.text}</div>`;

            Object.entries(q.options).forEach(([key, val]) => {
                const selected = simAnswers[q.id] === key ? ' selected' : '';
                html += `<button class="sim-option${selected}" onclick="selectSimAnswer(${q.id}, '${key}', this)">${key}) ${val}</button>`;
            });

            html += '</div>';
            html += '<div class="sim-nav">';
            if (simCurrentQ > 0) {
                html += `<button onclick="simCurrentQ--;renderSimQuestion()">Önceki</button>`;
            }
            if (simCurrentQ < total - 1) {
                html += `<button class="primary" onclick="simCurrentQ++;renderSimQuestion()">Sonraki</button>`;
            } else {
                html += `<button class="primary" onclick="finishExamSim()">Bitir</button>`;
            }
            html += '</div>';

            area.innerHTML = html;
            // Scroll overlay to top on question change
            document.getElementById('examModal').scrollTop = 0;
        }

        function selectSimAnswer(qId, choice, btn) {
            simAnswers[qId] = choice;
            // Cevabı API'ye gönder
            const fd = new FormData();
            fd.append('action', 'answer');
            fd.append('question_id', qId);
            fd.append('answer', choice);
            fetch('api/exam_simulation.php?action=answer', { method: 'POST', body: fd });
            // Seçili göster
            btn.parentElement.querySelectorAll('.sim-option').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
        }

        async function finishExamSim() {
            clearInterval(simTimer);
            document.getElementById('examBody').classList.remove('exam-active');
            const active = document.getElementById('examActive');

            // Analiz animasyonu
            const analyzeStatuses = [
                '📊 Cevapların kontrol ediliyor...',
                '🧮 Doğru-yanlış hesaplanıyor...',
                '🔍 Hata türleri analiz ediliyor...',
                '📈 Performans değerlendiriliyor...',
                '✨ Rapor hazırlanıyor...'
            ];
            let aIdx = 0;

            active.innerHTML = `<div class="sim-preparing" style="padding:60px 20px;">
                <div style="position:relative;width:100px;height:100px;margin:0 auto 24px;">
                    <div style="position:absolute;inset:0;border-radius:50%;border:3px solid var(--border);"></div>
                    <div style="position:absolute;inset:0;border-radius:50%;border:3px solid transparent;border-top-color:var(--primary);border-right-color:var(--accent);animation:countdownSpin 1s linear infinite;"></div>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:40px;animation:prepBounce 1.5s ease-in-out infinite;">🔍</div>
                </div>
                <div class="sim-preparing-text">Sonuçlar Değerlendiriliyor</div>
                <div class="sim-prep-progress" style="margin:16px 0;"><div class="sim-prep-progress-fill" style="animation:prepProgressShimmer 1.5s ease infinite, prepProgressGrow 30s cubic-bezier(0.1,0.4,0.2,0.9) forwards;"></div></div>
                <div class="sim-prep-status" id="analyzeStatus">${analyzeStatuses[0]}</div>
                <div class="sim-prep-dots" style="margin-top:12px">
                    <div class="sim-prep-dot"></div>
                    <div class="sim-prep-dot"></div>
                    <div class="sim-prep-dot"></div>
                    <div class="sim-prep-dot"></div>
                </div>
            </div>`;

            const analyzeInterval = setInterval(() => {
                aIdx = (aIdx + 1) % analyzeStatuses.length;
                const el = document.getElementById('analyzeStatus');
                if (el) {
                    el.style.animation = 'none';
                    void el.offsetWidth;
                    el.textContent = analyzeStatuses[aIdx];
                    el.style.animation = 'prepStatusFade 0.4s ease';
                }
            }, 2000);

            try {
                const fd = new FormData();
                fd.append('action', 'finish');
                const res = await fetch('api/exam_simulation.php?action=finish', { method: 'POST', body: fd });
                const data = await res.json();
                clearInterval(analyzeInterval);

                // Progress %100
                const fillBar = active.querySelector('.sim-prep-progress-fill');
                if (fillBar) {
                    fillBar.style.animation = 'prepProgressShimmer 1.5s ease infinite';
                    fillBar.style.transition = 'width 0.5s ease';
                    fillBar.style.width = '100%';
                }
                await new Promise(r => setTimeout(r, 600));

                if (!data.success) {
                    active.innerHTML = '<div class="plan-empty">Sonuç alınamadı</div>';
                    return;
                }

                active.style.display = 'none';
                const report = document.getElementById('examReport');
                report.style.display = 'block';

                const r = data.report;
                const pct = r.total > 0 ? Math.round((r.correct / r.total) * 100) : 0;
                const grade = pct >= 70 ? 'good' : pct >= 40 ? 'mid' : 'low';
                const gradeEmoji = pct >= 70 ? '🎉' : pct >= 40 ? '💪' : '📚';

                // Hero
                const scoreLabel = r.score !== undefined ? `${r.score} / ${r.max_score}` : r.net;
                const diffLabel = r.difficulty || '';
                let html = `<div class="sim-report-hero grade-${grade}">
                    <div class="sim-report-emoji">${gradeEmoji}</div>
                    <div class="sim-report-net">${scoreLabel}</div>
                    <div class="sim-report-net-label">Puan</div>
                    <div class="sim-report-subject">
                        <span>${r.subject}</span>
                        <span>·</span>
                        <span>${r.total} Soru</span>
                        ${diffLabel ? `<span>·</span><span>${diffLabel}</span>` : ''}
                    </div>
                </div>`;

                // Doğru / Yanlış
                html += `<div class="sim-report-stats">
                    <div class="sim-report-stat correct">
                        <div class="sim-report-stat-icon">✓</div>
                        <div class="sim-report-stat-info">
                            <div class="val">${r.correct}</div>
                            <div class="lbl">Doğru</div>
                        </div>
                    </div>
                    <div class="sim-report-stat wrong">
                        <div class="sim-report-stat-icon">✗</div>
                        <div class="sim-report-stat-info">
                            <div class="val">${r.wrong}</div>
                            <div class="lbl">Yanlış</div>
                        </div>
                    </div>
                </div>`;

                // Meta chips
                html += `<div class="sim-report-meta">
                    <span class="sim-report-chip"><i class="fas fa-clock"></i> ${r.time_minutes} dk / ${r.time_limit} dk</span>
                    <span class="sim-report-chip"><i class="fas fa-gauge-high"></i> ${r.tempo}</span>
                    <span class="sim-report-chip"><i class="fas fa-percent"></i> %${pct} başarı</span>
                </div>`;

                // Hata analizi
                if (r.error_analysis && r.error_analysis.length > 0) {
                    const errTypeMap = {
                        'DIKKATSIZLIK': { icon: '⚡', label: 'Dikkatsizlik', cls: 'err-careless' },
                        'KONU_EKSIKLIGI': { icon: '📖', label: 'Konu Eksikliği', cls: 'err-knowledge' },
                        'ZAMAN': { icon: '⏱️', label: 'Zaman Baskısı', cls: 'err-time' },
                        'KAVRAM_KARISIKLIGI': { icon: '🔀', label: 'Kavram Karışıklığı', cls: 'err-confusion' }
                    };

                    html += `<div class="sim-report-section-title">🔍 Hata Analizi</div>`;
                    r.error_analysis.forEach((ea, i) => {
                        const et = errTypeMap[ea.error_type] || { icon: '❓', label: ea.error_type, cls: 'err-default' };
                        html += `<div class="sim-error-card" style="animation-delay:${0.1*i}s">
                            <div class="sim-error-badge ${et.cls}">${et.icon}</div>
                            <div class="sim-error-info">
                                <div class="sim-error-title">Soru ${ea.question_id} — ${ea.topic}</div>
                                <div class="sim-error-type">${et.label}</div>
                                ${ea.reason ? `<div class="sim-error-reason">${ea.reason}</div>` : ''}
                            </div>
                        </div>`;
                    });
                }

                // Soru detayları
                html += `<div class="sim-report-section-title">📋 Soru Detayları</div>`;
                r.details.forEach(d => {
                    if (d.result === 'empty') return;
                    const cls = d.result;
                    const icon = d.result === 'correct' ? '✓' : '✗';
                    html += `<div class="sim-detail-row ${cls}">
                        <div class="sim-detail-icon">${icon}</div>
                        <span class="sim-detail-topic">${d.topic}</span>
                        <span class="sim-detail-answer">${d.student_answer} → ${d.correct_answer}</span>
                    </div>`;
                });

                // Motivasyon
                const motClass = grade === 'good' ? 'mot-good' : grade === 'mid' ? 'mot-mid' : 'mot-low';
                const motText = pct >= 70
                    ? '🎉 Harika performans! Bu tempoda devam edersen sınavda çok başarılı olacaksın!'
                    : pct >= 40
                    ? '💪 Fena değil! Yanlışlarını analiz edip tekrar çalışırsan çok daha iyi olacaksın.'
                    : '📚 Her usta çırak olarak başlar! Yanlışlarını incele ve konuları tekrar gözden geçir.';
                html += `<div class="sim-report-motivation ${motClass}">${motText}</div>`;

                // Butonlar
                html += `<div class="sim-report-actions">
                    <button class="sim-start-btn" onclick="resetExamSetup()"><i class="fas fa-rotate"></i> Yeni Prova</button>
                    <button class="sim-start-btn" style="background:var(--bg-surface);color:var(--text-primary);border:1px solid var(--border)" onclick="closeExamModal()"><i class="fas fa-xmark"></i> Kapat</button>
                </div>`;

                report.innerHTML = html;
            } catch(e) {
                active.innerHTML = '<div class="plan-empty">Bağlantı hatası</div>';
            }
        }

        // ═══════════════════════════════════════
        // FAZ 2: Dinle Butonu (Web Speech API)
        // ═══════════════════════════════════════
        let ttsPlaying = false;
        let ttsQueue = [];
        let ttsCurrentBtn = null;
        let ttsResumeInterval = null;

        function stopAllTTS() {
            ttsPlaying = false;
            ttsQueue = [];
            speechSynthesis.cancel();
            clearInterval(ttsResumeInterval);
            ttsResumeInterval = null;
            document.querySelectorAll('.btn-listen.playing').forEach(b => {
                b.classList.remove('playing');
                b.innerHTML = '<i class="fas fa-volume-up volume-up"></i><span class="waveform"><span></span><span></span><span></span><span></span></span><span class="btn-text">Dinle</span>';
            });
            ttsCurrentBtn = null;
        }

        function speakChunks(chunks, btn) {
            if (!ttsPlaying || chunks.length === 0) {
                stopAllTTS();
                return;
            }
            const chunk = chunks.shift();
            const utterance = new SpeechSynthesisUtterance(chunk);
            utterance.lang = 'tr-TR';
            utterance.rate = 1.05;
            utterance.pitch = 1.1;
            utterance.onend = () => speakChunks(chunks, btn);
            utterance.onerror = () => stopAllTTS();
            speechSynthesis.speak(utterance);
        }

        function addListenButton(bubbleElement, text) {
            if (!('speechSynthesis' in window)) return;

            const btn = document.createElement('button');
            btn.className = 'btn-listen';
            btn.innerHTML = '<i class="fas fa-volume-up volume-up"></i><span class="waveform"><span></span><span></span><span></span><span></span></span><span class="btn-text">Dinle</span>';
            btn.onclick = function() {
                // Zaten bu buton çalıyorsa durdur
                if (btn.classList.contains('playing')) {
                    stopAllTTS();
                    return;
                }
                // Başka bir ses çalıyorsa onu durdur
                stopAllTTS();

                // Metni temizle
                const plainText = text.replace(/\*\*/g, '').replace(/```[\s\S]*?```/g, '').replace(/`[^`]*`/g, '').replace(/[\u{1F000}-\u{1FFFF}]|[\u{2600}-\u{27BF}]|[\u{FE00}-\u{FE0F}]|[\u{1F900}-\u{1F9FF}]|[\u{200D}]|[\u{20E3}]|[\u{E0020}-\u{E007F}]/gu, '').replace(/\s{2,}/g, ' ').trim();

                if (!plainText) return;

                // Uzun metinleri cümlelere böl (Chrome 15sn bug fix)
                const sentences = plainText.match(/[^.!?]+[.!?]+|[^.!?]+$/g) || [plainText];
                const chunks = [];
                let current = '';
                sentences.forEach(s => {
                    if ((current + s).length > 150) {
                        if (current) chunks.push(current.trim());
                        current = s;
                    } else {
                        current += s;
                    }
                });
                if (current) chunks.push(current.trim());

                ttsPlaying = true;
                ttsCurrentBtn = btn;
                btn.classList.add('playing', 'activating');
                btn.innerHTML = '<span class="waveform"><span></span><span></span><span></span><span></span></span><span class="btn-text">Dur</span>';
                setTimeout(() => btn.classList.remove('activating'), 500);

                // Chrome pause bug workaround
                ttsResumeInterval = setInterval(() => {
                    if (speechSynthesis.speaking && !speechSynthesis.paused) return;
                    if (speechSynthesis.paused) speechSynthesis.resume();
                }, 3000);

                speakChunks(chunks, btn);
            };

            bubbleElement.appendChild(btn);
        }

        // ═══════════════════════════════════════
        // FAZ 3: Grup Çalışma Odaları
        // ═══════════════════════════════════════
        let activeGroupId = null;
        let gcPollTimer = null;
        let gcLastMsgId = 0;

        async function loadGroups() {
            const body = document.getElementById('groupsBody');
            try {
                const res = await fetch('api/groups.php?action=list');
                const data = await res.json();
                let html = '';

                // Oluştur butonu
                html += `<div style="margin-bottom:16px;">
                    <button class="btn-primary" style="width:100%;padding:10px;border:none;border-radius:10px;background:var(--primary);color:#fff;font-size:14px;cursor:pointer;" onclick="showCreateGroup()">
                        <i class="fas fa-plus"></i> Yeni Oda Oluştur
                    </button>
                </div>
                <div id="createGroupForm" style="display:none;margin-bottom:16px;padding:14px;background:var(--bg-surface);border:1px solid var(--border);border-radius:10px;">
                    <input type="text" id="newGroupTopic" placeholder="Konu (örn: Türev)" style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg-primary);color:var(--text-primary);font-size:14px;margin-bottom:8px;">
                    <input type="text" id="newGroupSubject" placeholder="Ders (örn: Matematik)" style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg-primary);color:var(--text-primary);font-size:14px;margin-bottom:8px;">
                    <div style="display:flex;gap:8px;">
                        <button onclick="createGroup()" style="flex:1;padding:8px;background:var(--primary);border:none;border-radius:8px;color:#fff;cursor:pointer;">Oluştur</button>
                        <button onclick="document.getElementById('createGroupForm').style.display='none'" style="padding:8px 14px;background:transparent;border:1px solid var(--border);border-radius:8px;color:var(--text-muted);cursor:pointer;">İptal</button>
                    </div>
                </div>`;

                // Gruplarım
                if (data.my_groups && data.my_groups.length > 0) {
                    html += '<div style="font-size:12px;color:var(--text-muted);font-weight:600;margin-bottom:8px;text-transform:uppercase;">Odalarım</div>';
                    data.my_groups.forEach(g => {
                        html += `<div style="padding:12px;background:var(--bg-surface);border:1px solid var(--border);border-radius:10px;margin-bottom:8px;cursor:pointer;" data-gid="${g.id}" data-gname="${escHtml(g.group_name)}" onclick="openGroupChat(+this.dataset.gid, this.dataset.gname)">
                            <div style="font-weight:600;font-size:14px;">${escHtml(g.group_name)}</div>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">
                                <i class="fas fa-users"></i> ${g.member_count}/4 · ${escHtml(g.topic || '')}
                            </div>
                        </div>`;
                    });
                }

                // Katılabilecekler
                if (data.available && data.available.length > 0) {
                    html += '<div style="font-size:12px;color:var(--text-muted);font-weight:600;margin:14px 0 8px;text-transform:uppercase;">Katılabileceklerin</div>';
                    data.available.forEach(g => {
                        html += `<div style="padding:12px;background:var(--bg-surface);border:1px solid var(--border);border-radius:10px;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;">
                            <div>
                                <div style="font-weight:500;font-size:13px;">${escHtml(g.group_name)}</div>
                                <div style="font-size:11px;color:var(--text-muted);"><i class="fas fa-users"></i> ${g.member_count}/${g.max_members}</div>
                            </div>
                            <button onclick="joinGroup(${g.id})" style="padding:6px 14px;background:var(--primary);border:none;border-radius:8px;color:#fff;font-size:12px;cursor:pointer;">Katıl</button>
                        </div>`;
                    });
                }

                if ((!data.my_groups || data.my_groups.length === 0) && (!data.available || data.available.length === 0)) {
                    html += '<div style="text-align:center;color:var(--text-muted);padding:30px;">Henüz çalışma odası yok. İlk odayı sen oluştur!</div>';
                }

                body.innerHTML = html;
            } catch(e) {
                body.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:30px;">Yüklenemedi</div>';
            }
        }

        function showCreateGroup() {
            document.getElementById('createGroupForm').style.display = 'block';
            document.getElementById('newGroupTopic').focus();
        }

        async function createGroup() {
            const topic = document.getElementById('newGroupTopic').value.trim();
            const subject = document.getElementById('newGroupSubject').value.trim();
            if (!topic) return;
            const fd = new FormData();
            fd.append('action', 'create');
            fd.append('topic', topic);
            fd.append('subject', subject);
            const res = await fetch('api/groups.php', {method:'POST', body: fd});
            const data = await res.json();
            if (data.success) {
                loadGroups();
                openGroupChat(data.group_id, topic + ' Odası');
            } else alert(data.error || 'Hata');
        }

        async function joinGroup(groupId) {
            const fd = new FormData();
            fd.append('action', 'join');
            fd.append('group_id', groupId);
            const res = await fetch('api/groups.php', {method:'POST', body: fd});
            const data = await res.json();
            if (data.success) loadGroups();
            else alert(data.error || 'Hata');
        }

        async function openGroupChat(groupId, name) {
            activeGroupId = groupId;
            gcLastMsgId = 0;
            document.getElementById('gcTitle').textContent = name;
            document.getElementById('gcMessages').innerHTML = '';
            document.getElementById('groupChatModal').style.display = 'flex';
            closePanel();
            await loadGroupMessages();
            gcPollTimer = setInterval(pollGroupMessages, 3000);
        }

        function closeGroupChat() {
            document.getElementById('groupChatModal').style.display = 'none';
            activeGroupId = null;
            if (gcPollTimer) { clearInterval(gcPollTimer); gcPollTimer = null; }
        }

        async function loadGroupMessages() {
            if (!activeGroupId) return;
            const res = await fetch(`api/groups.php?action=messages&group_id=${activeGroupId}&after=${gcLastMsgId}`);
            const data = await res.json();
            if (data.error) return;

            const box = document.getElementById('gcMessages');
            if (data.members) {
                document.getElementById('gcMembers').textContent = data.members.map(m => m.nickname || m.first_name).join(', ') + ', Aibüke';
            }
            data.messages.forEach(m => {
                const isMe = m.student_id == <?= $_SESSION['student_id'] ?? 0 ?>;
                const name = m.is_ai == 1 ? 'Aibüke' : (m.nickname || m.first_name || 'Öğrenci');
                const align = isMe ? 'flex-end' : 'flex-start';
                const bg = m.is_ai == 1 ? 'var(--primary)' : (isMe ? 'var(--user-bubble)' : 'var(--ai-bubble)');
                const color = (m.is_ai == 1 || isMe) ? '#fff' : 'var(--text-primary)';
                const border = (!m.is_ai && !isMe) ? 'border:1px solid var(--border);' : '';
                box.innerHTML += `<div style="align-self:${align};max-width:80%;">
                    ${!isMe ? `<div style="font-size:10px;color:var(--text-muted);margin-bottom:2px;">${escHtml(name)}</div>` : ''}
                    <div style="padding:8px 12px;border-radius:12px;background:${bg};color:${color};font-size:13px;${border}">${escHtml(m.content)}</div>
                    <div style="font-size:9px;color:var(--text-muted);margin-top:2px;text-align:${isMe?'right':'left'};">${new Date(m.created_at).toLocaleTimeString('tr-TR',{hour:'2-digit',minute:'2-digit'})}</div>
                </div>`;
                gcLastMsgId = Math.max(gcLastMsgId, m.id);
            });
            box.scrollTop = box.scrollHeight;
        }

        async function pollGroupMessages() {
            if (!activeGroupId) return;
            await loadGroupMessages();
        }

        async function sendGroupMsg(e) {
            e.preventDefault();
            const input = document.getElementById('gcInput');
            const msg = input.value.trim();
            if (!msg || !activeGroupId) return;
            input.value = '';

            const fd = new FormData();
            fd.append('action', 'send');
            fd.append('group_id', activeGroupId);
            fd.append('content', msg);
            const res = await fetch('api/groups.php', {method:'POST', body: fd});
            await loadGroupMessages();
        }

        // ═══════════════════════════════════════
        // FAZ 3: VELİ PANELİ LİNK PAYLAŞIMI
        // ═══════════════════════════════════════
        async function showParentLink() {
            try {
                // Önce mevcut token var mı kontrol et
                let res = await fetch('api/parent_token.php?action=status');
                let data = await res.json();

                if (!data.token) {
                    // Token yoksa oluştur
                    const fd = new FormData();
                    fd.append('action', 'generate');
                    res = await fetch('api/parent_token.php', {method:'POST', body: fd});
                    data = await res.json();
                    if (!data.success) { alert('Bağlantı oluşturulamadı'); return; }
                    var url = data.url;
                } else {
                    var url = data.token.url;
                }

                // Overlay göster
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:3000;display:flex;align-items:center;justify-content:center;padding:20px;';
                overlay.innerHTML = `<div style="background:var(--bg-surface);border-radius:16px;padding:24px;max-width:420px;width:100%;text-align:center;">
                    <div style="font-size:40px;margin-bottom:12px;">👨‍👩‍👧</div>
                    <div style="font-size:17px;font-weight:600;margin-bottom:8px;">Veli Paneli</div>
                    <div style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">Bu bağlantıyı velilerinle paylaş. Haftalık özetini görebilirler.<br><small>Mesaj içeriklerini göremezler.</small></div>
                    <input type="text" value="${url}" readonly style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:10px;background:var(--bg-primary);color:var(--text-primary);font-size:12px;text-align:center;margin-bottom:12px;" onclick="this.select()">
                    <div style="display:flex;gap:8px;">
                        <button onclick="navigator.clipboard.writeText('${url}');this.textContent='Kopyalandı!'" style="flex:1;padding:12px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">Kopyala</button>
                        <button onclick="this.closest('div').closest('div').closest('div').remove()" style="flex:1;padding:12px;background:var(--bg-primary);color:var(--text-muted);border:1px solid var(--border);border-radius:10px;font-size:14px;cursor:pointer;">Kapat</button>
                    </div>
                </div>`;
                overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
                document.body.appendChild(overlay);
            } catch(e) {
                alert('Bir hata oluştu');
            }
        }

        // ═══════════════════════════════════════
        // FAZ 3: AİBÜKE'NİN BAHÇESİ
        // ═══════════════════════════════════════
        async function loadGarden() {
            const body = document.getElementById('gardenBody');
            try {
                const res = await fetch('api/garden.php?action=status');
                const g = await res.json();
                const v = g.visual;

                // Level & progress calculations
                const nextThresholds = [0, 5, 20, 50, 100, 200];
                const nextAt = nextThresholds[g.level] || 999;
                const prevAt = nextThresholds[g.level - 1] || 0;
                const pct = g.level >= 6 ? 100 : Math.min(100, Math.round(((g.leaves - prevAt) / (nextAt - prevAt)) * 100));

                // Particles per level
                const particleEmojis = {
                    1: ['💨', '🏜️', '✨'],
                    2: ['🍃', '✨', '💧'],
                    3: ['🍃', '🌿', '💚', '✨'],
                    4: ['🌸', '🌷', '💮', '✨', '🦋'],
                    5: ['🍃', '🌳', '🍎', '🐦', '✨'],
                    6: ['🦋', '🌸', '✨', '⭐', '🌈', '💜', '🎵']
                };
                const particles = particleEmojis[g.level] || particleEmojis[1];

                // Build particles HTML
                let particlesHtml = '';
                for (let i = 0; i < 12; i++) {
                    const emoji = particles[i % particles.length];
                    const left = Math.random() * 90 + 5;
                    const delay = Math.random() * 6;
                    const dur = 5 + Math.random() * 4;
                    const size = 12 + Math.random() * 10;
                    particlesHtml += `<span class="garden-particle" style="left:${left}%;bottom:-10%;animation-delay:${delay}s;animation-duration:${dur}s;font-size:${size}px;">${emoji}</span>`;
                }

                // Decorations
                let decosHtml = '';
                if (g.decorations.length > 0) {
                    decosHtml = '<div class="garden-decos">' +
                        g.decorations.map(d => `<span class="garden-deco-item">${d}</span>`).join('') +
                    '</div>';
                }

                // Scene
                let html = `
                <div class="garden-scene lvl-${g.level}">
                    <div class="garden-particles">${particlesHtml}</div>
                    <div class="garden-ground"></div>
                    <div class="garden-main-plant">${v.emoji}</div>
                    <div class="garden-level-badge">⭐ Seviye ${g.level}</div>
                    <div class="garden-level-name">${v.name}</div>
                    <div class="garden-desc">${v.desc}</div>
                    ${decosHtml}
                </div>`;

                // Stat cards
                html += `<div class="garden-stats">
                    <div class="garden-stat stat-leaf">
                        <span class="garden-stat-icon">🍃</span>
                        <div class="garden-stat-value">${g.leaves}</div>
                        <div class="garden-stat-label">Yaprak</div>
                    </div>
                    <div class="garden-stat stat-flower">
                        <span class="garden-stat-icon">🌸</span>
                        <div class="garden-stat-value">${g.flowers}</div>
                        <div class="garden-stat-label">Çiçek</div>
                    </div>
                    <div class="garden-stat stat-streak">
                        <span class="garden-stat-icon">🔥</span>
                        <div class="garden-stat-value">${g.streak}</div>
                        <div class="garden-stat-label">Gün Serisi</div>
                    </div>
                </div>`;

                // Progress bar
                if (g.level < 6) {
                    const remain = nextAt - g.leaves;
                    // Milestones
                    let milestonesHtml = '';
                    [5,20,50,100,200].forEach(t => {
                        const reached = g.leaves >= t;
                        milestonesHtml += `<div class="garden-milestone ${reached ? 'reached' : ''}">
                            <div class="garden-milestone-dot"></div>
                            <span>${t}</span>
                        </div>`;
                    });

                    html += `<div class="garden-progress">
                        <div class="garden-progress-header">
                            <span class="garden-progress-level">${v.emoji} ${v.name}</span>
                            <span class="garden-progress-remain">🍃 ${remain} yaprak kaldı</span>
                        </div>
                        <div class="garden-progress-track">
                            <div class="garden-progress-fill" style="width:0%"></div>
                        </div>
                        <div class="garden-milestones">${milestonesHtml}</div>
                    </div>`;
                } else {
                    html += `<div style="text-align:center;padding:14px;background:linear-gradient(135deg,var(--primary-glow),rgba(236,72,153,0.08));border:1px solid var(--border);border-radius:14px;margin-bottom:16px;">
                        <span style="font-size:28px;">🏡✨</span>
                        <div style="font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;color:var(--primary-light);margin-top:6px;">Maksimum Seviye!</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Bahçen bir cennet oldu, tebrikler!</div>
                    </div>`;
                }

                // Info card
                html += `<div class="garden-info-card">
                    <div class="garden-info-header">🌱 Bahçe Nasıl Büyür?</div>
                    <div class="garden-info-row">
                        <div class="garden-info-icon leaf-icon">🍃</div>
                        <span>Her soru çözdüğünde <strong>+1 yaprak</strong> eklenir</span>
                    </div>
                    <div class="garden-info-row">
                        <div class="garden-info-icon flower-icon">🌸</div>
                        <span>Her <strong>10 yaprakta</strong> bir çiçek açar</span>
                    </div>
                    <div class="garden-info-row">
                        <div class="garden-info-icon streak-icon">🔥</div>
                        <span><strong>Gün serisi</strong> koruyarak seviye atlarsın</span>
                    </div>
                    <div class="garden-info-row">
                        <div class="garden-info-icon badge-icon">🎖️</div>
                        <span>Rozetlerin bahçene <strong>süs</strong> olur</span>
                    </div>
                </div>`;

                body.innerHTML = html;

                // Animate progress bar after render
                if (g.level < 6) {
                    setTimeout(() => {
                        const fill = body.querySelector('.garden-progress-fill');
                        if (fill) fill.style.width = pct + '%';
                    }, 100);
                }
            } catch(e) {
                body.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:40px;">Yüklenemedi</div>';
            }
        }

        // Soru çözüldüğünde bahçeyi sula
        async function waterGarden() {
            try {
                const fd = new FormData();
                fd.append('action', 'water');
                const res = await fetch('api/garden.php', {method:'POST', body: fd});
                const data = await res.json();
                if (data.success) {
                    let notifText = '🍃 +1 yaprak!';
                    if (data.new_flower) notifText += ' 🌸 Yeni çiçek açtı!';
                    if (data.leveled_up) notifText = `${data.level_emoji} Bahçen ${data.level_name} seviyesine ulaştı!`;
                    showGardenNotif(notifText);
                }
            } catch(e) { /* sessizce geç */ }
        }

        function showGardenNotif(text) {
            const el = document.createElement('div');
            el.className = 'garden-notification';
            el.textContent = text;
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 3000);
        }

        // ═══════════════════════════════════════
        // FAZ 3: TAKVİM
        // ═══════════════════════════════════════
        let calYear = new Date().getFullYear();
        let calMonth = new Date().getMonth() + 1;
        const calMonthNames = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

        async function loadCalendar(year, month) {
            if (year !== undefined) calYear = year;
            if (month !== undefined) calMonth = month;
            const body = document.getElementById('calendarBody');
            try {
                const res = await fetch(`api/points.php?action=calendar&year=${calYear}&month=${calMonth}`);
                const data = await res.json();

                const daysMap = {};
                let totalMsgs = 0;
                let activeDays = 0;
                (data.days || []).forEach(d => {
                    daysMap[parseInt(d.usage_date.split('-')[2])] = parseInt(d.message_count);
                    totalMsgs += parseInt(d.message_count);
                    activeDays++;
                });

                const today = new Date();
                const isCurrentMonth = calYear === today.getFullYear() && calMonth === (today.getMonth() + 1);
                const todayDate = today.getDate();

                // İlk günün haftanın hangi günü olduğu (Pzt=0)
                const firstDay = new Date(calYear, calMonth - 1, 1).getDay();
                const offset = firstDay === 0 ? 6 : firstDay - 1; // Pazartesi başlangıç
                const daysInMonth = new Date(calYear, calMonth, 0).getDate();

                let calHtml = `
                    <div class="cal-nav">
                        <button class="cal-nav-btn" onclick="loadCalendar(${calMonth===1?calYear-1:calYear},${calMonth===1?12:calMonth-1})"><i class="fas fa-chevron-left"></i></button>
                        <span class="cal-month-label">${calMonthNames[calMonth-1]} ${calYear}</span>
                        <button class="cal-nav-btn" onclick="loadCalendar(${calMonth===12?calYear+1:calYear},${calMonth===12?1:calMonth+1})"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div class="cal-grid">
                        <div class="cal-weekday">Pzt</div><div class="cal-weekday">Sal</div><div class="cal-weekday">Çar</div>
                        <div class="cal-weekday">Per</div><div class="cal-weekday">Cum</div><div class="cal-weekday">Cmt</div><div class="cal-weekday">Paz</div>`;

                // Boş hücreler
                for (let i = 0; i < offset; i++) {
                    calHtml += '<div class="cal-day empty"></div>';
                }

                for (let d = 1; d <= daysInMonth; d++) {
                    const msgs = daysMap[d] || 0;
                    let cls = 'cal-day';
                    if (isCurrentMonth && d === todayDate) cls += ' today';
                    if (msgs > 0) {
                        cls += ' active';
                        if (msgs <= 5) cls += ' low';
                        else if (msgs <= 20) cls += ' med';
                        else cls += ' high';
                    }
                    calHtml += `<div class="${cls}" title="${msgs > 0 ? msgs + ' mesaj' : ''}">${d}</div>`;
                }

                calHtml += '</div>';

                // Streak kartı
                calHtml += `
                    <div class="cal-streak-card">
                        <div class="cal-streak-num">🔥 ${data.streak}</div>
                        <div class="cal-streak-info">
                            <div class="cal-streak-label">Gün Serisi</div>
                            <div class="cal-streak-sub">${data.streak > 0 ? 'Harika gidiyorsun, devam et!' : 'Bugün çalışmaya başla!'}</div>
                        </div>
                    </div>`;

                // Ay özeti
                calHtml += `
                    <div class="cal-summary">
                        <div class="cal-stat">
                            <div class="cal-stat-val">${activeDays}</div>
                            <div class="cal-stat-lbl">Aktif Gün</div>
                        </div>
                        <div class="cal-stat">
                            <div class="cal-stat-val">${totalMsgs}</div>
                            <div class="cal-stat-lbl">Mesaj</div>
                        </div>
                        <div class="cal-stat">
                            <div class="cal-stat-val">${daysInMonth > 0 ? Math.round(activeDays/daysInMonth*100) : 0}%</div>
                            <div class="cal-stat-lbl">Düzenlilik</div>
                        </div>
                    </div>`;

                // Renk açıklaması
                calHtml += `<div style="display:flex;gap:12px;justify-content:center;margin-top:16px;font-size:11px;color:var(--text-muted);">
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:rgba(99,102,241,0.35);vertical-align:middle;margin-right:3px;"></span>1-5</span>
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:rgba(99,102,241,0.6);vertical-align:middle;margin-right:3px;"></span>6-20</span>
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:var(--primary);vertical-align:middle;margin-right:3px;"></span>20+</span>
                </div>`;

                body.innerHTML = calHtml;
            } catch(e) {
                body.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:40px">Takvim yüklenemedi.</div>';
            }
        }

        // ═══════════════════════════════════════
        // FAZ 3: SORU TURNUVALARI
        // ═══════════════════════════════════════
        let activeTournamentId = null;
        let tmTimerInterval = null;
        let tmTimeLeft = 0;
        let tmCurrentQuestion = null;

        async function loadTournaments() {
            const body = document.getElementById('tournamentBody');
            try {
                const res = await fetch('api/tournaments.php?action=list');
                const data = await res.json();
                let html = '';

                // Aktif / Yaklaşan
                if (data.upcoming && data.upcoming.length > 0) {
                    html += '<div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px;">Aktif & Yaklaşan</div>';
                    data.upcoming.forEach(t => {
                        const statusCls = t.status === 'active' ? 'active' : 'waiting';
                        const statusTxt = t.status === 'active' ? 'Canlı' : 'Başlamadı';
                        const startTime = new Date(t.starts_at).toLocaleTimeString('tr-TR', {hour:'2-digit', minute:'2-digit'});
                        const joined = t.my_participation ? ' ✓' : '';
                        html += `<div class="tm-card" onclick="${t.my_participation && t.status === 'active' ? `startTournamentQuiz(${t.id})` : `joinTournament(${t.id})`}">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <div class="tm-card-title">${escHtml(t.title)}</div>
                                <span class="tm-badge tm-badge-${statusCls}">${statusTxt}</span>
                            </div>
                            <div class="tm-card-meta">
                                <span><i class="fas fa-clock"></i> ${startTime}</span>
                                <span><i class="fas fa-users"></i> ${t.participant_count} kişi</span>
                                <span><i class="fas fa-question-circle"></i> ${t.question_count} soru</span>
                                ${joined ? '<span style="color:#22c55e;font-weight:600;">Katıldın ✓</span>' : ''}
                            </div>
                        </div>`;
                    });
                }

                // Son tamamlanan
                if (data.recent && data.recent.length > 0) {
                    html += '<div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin:18px 0 10px;">Son Turnuvalar</div>';
                    data.recent.forEach(t => {
                        const scoreStr = t.my_score !== null ? `${t.my_correct}/${t.question_count} — ${t.my_score} puan` : 'Katılmadın';
                        html += `<div class="tm-card" onclick="showTournamentResults(${t.id})">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <div class="tm-card-title">${escHtml(t.title)}</div>
                                <span class="tm-badge tm-badge-finished">Bitti</span>
                            </div>
                            <div class="tm-card-meta">
                                <span><i class="fas fa-users"></i> ${t.participant_count} kişi</span>
                                <span><i class="fas fa-star"></i> ${scoreStr}</span>
                            </div>
                        </div>`;
                    });
                }

                if (!html) {
                    html = '<div style="text-align:center;color:var(--text-muted);padding:40px;"><div style="font-size:40px;margin-bottom:12px;">🏆</div>Henüz turnuva yok.<br><small>Yakında yeni turnuvalar açılacak!</small></div>';
                }

                body.innerHTML = html;
            } catch(e) {
                body.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:40px;">Yüklenemedi</div>';
            }
        }

        async function joinTournament(id) {
            const fd = new FormData();
            fd.append('action', 'join');
            fd.append('tournament_id', id);
            const res = await fetch('api/tournaments.php', {method:'POST', body: fd});
            const data = await res.json();
            if (data.error) { alert(data.error); return; }
            loadTournaments();
            // Eğer turnuva aktifse hemen başlat
            startTournamentQuiz(id);
        }

        function closeTournamentModal() {
            document.getElementById('tournamentModal').style.display = 'none';
            if (tmTimerInterval) clearInterval(tmTimerInterval);
            activeTournamentId = null;
        }

        async function startTournamentQuiz(id) {
            activeTournamentId = id;
            closePanel();
            document.getElementById('tournamentModal').style.display = 'flex';
            loadNextQuestion();
        }

        async function loadNextQuestion() {
            tmSelectedAnswer = null;
            tmAnswerStartTime = Date.now();
            const body = document.getElementById('tmBody');
            body.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></div>';

            const res = await fetch(`api/tournaments.php?action=question&tournament_id=${activeTournamentId}`);
            const data = await res.json();

            if (data.error) {
                body.innerHTML = `<div style="text-align:center;padding:40px;color:var(--text-muted);">${escHtml(data.error)}</div>`;
                return;
            }

            if (data.finished) {
                showTournamentResults(activeTournamentId);
                return;
            }

            tmCurrentQuestion = data.question;
            document.getElementById('tmProgress').textContent = `Soru ${data.current}/${data.total}`;

            // Timer
            tmTimeLeft = data.time_limit;
            updateTimerDisplay();
            if (tmTimerInterval) clearInterval(tmTimerInterval);
            tmTimerInterval = setInterval(() => {
                tmTimeLeft--;
                updateTimerDisplay();
                // Timer bar
                const fill = body.querySelector('.tm-timer-fill');
                if (fill) {
                    const pct = (tmTimeLeft / data.time_limit) * 100;
                    fill.style.width = pct + '%';
                    if (pct < 25) fill.classList.add('danger');
                }
                if (tmTimeLeft <= 0) {
                    clearInterval(tmTimerInterval);
                    submitAnswer(null, data.time_limit);
                }
            }, 1000);

            const q = data.question;
            let html = `<div class="tm-timer-bar"><div class="tm-timer-fill" style="width:100%"></div></div>`;
            html += `<div class="tm-question">${escHtml(q.text)}</div>`;
            ['A','B','C','D'].forEach(letter => {
                html += `<div class="tm-option" onclick="selectTmOption(this, '${letter}')" data-letter="${letter}">
                    <div class="tm-option-letter">${letter}</div>
                    <div>${escHtml(q.options[letter])}</div>
                </div>`;
            });
            html += `<button class="tm-next-btn" id="tmSubmitBtn" onclick="submitSelectedAnswer()" style="display:none;">Cevapla</button>`;
            body.innerHTML = html;
        }

        function updateTimerDisplay() {
            const m = Math.floor(tmTimeLeft / 60);
            const s = tmTimeLeft % 60;
            const el = document.getElementById('tmTimer');
            if (el) {
                el.textContent = `${m}:${String(s).padStart(2,'0')}`;
                el.style.color = tmTimeLeft <= 10 ? '#ef4444' : 'var(--primary)';
            }
        }

        let tmSelectedAnswer = null;
        let tmAnswerStartTime = Date.now();

        function selectTmOption(el, letter) {
            // Don't allow re-selection after submitting
            if (el.closest('#tmBody').querySelector('.tm-option.correct, .tm-option.wrong')) return;
            document.querySelectorAll('.tm-option').forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
            tmSelectedAnswer = letter;
            document.getElementById('tmSubmitBtn').style.display = 'block';
        }

        async function submitSelectedAnswer() {
            if (!tmSelectedAnswer) return;
            const elapsed = Math.round((Date.now() - tmAnswerStartTime) / 1000);
            await submitAnswer(tmSelectedAnswer, elapsed);
        }

        async function submitAnswer(answer, timeSpent) {
            if (tmTimerInterval) clearInterval(tmTimerInterval);
            const body = document.getElementById('tmBody');

            // If time ran out with no answer, pick whatever was selected or skip
            if (!answer && tmSelectedAnswer) answer = tmSelectedAnswer;
            if (!answer) answer = 'X'; // no answer

            const fd = new FormData();
            fd.append('action', 'answer');
            fd.append('tournament_id', activeTournamentId);
            fd.append('question_id', tmCurrentQuestion.id);
            fd.append('answer', answer);
            fd.append('time_spent', timeSpent);

            try {
                const res = await fetch('api/tournaments.php', {method:'POST', body: fd});
                const data = await res.json();

                // Show correct/wrong feedback
                document.querySelectorAll('.tm-option').forEach(o => {
                    const letter = o.dataset.letter;
                    o.style.cursor = 'default';
                    if (letter === data.correct_answer) o.classList.add('correct');
                    else if (letter === answer && !data.correct) o.classList.add('wrong');
                });

                // Remove submit button and add explanation + next
                const submitBtn = document.getElementById('tmSubmitBtn');
                if (submitBtn) submitBtn.remove();

                let feedbackHtml = '';
                if (data.correct) {
                    feedbackHtml += `<div style="text-align:center;color:#22c55e;font-weight:600;font-size:15px;margin-top:16px;">✅ Doğru! +${data.points} puan</div>`;
                } else if (answer === 'X') {
                    feedbackHtml += `<div style="text-align:center;color:#f59e0b;font-weight:600;font-size:15px;margin-top:16px;">⏰ Süre doldu!</div>`;
                } else {
                    feedbackHtml += `<div style="text-align:center;color:#ef4444;font-weight:600;font-size:15px;margin-top:16px;">❌ Yanlış</div>`;
                }

                if (data.explanation) {
                    feedbackHtml += `<div class="tm-explanation"><strong>Açıklama:</strong> ${escHtml(data.explanation)}</div>`;
                }

                if (data.is_last) {
                    feedbackHtml += `<button class="tm-next-btn" onclick="showTournamentResults(${activeTournamentId})" style="margin-top:16px;">🏆 Sonuçları Gör</button>`;
                } else {
                    feedbackHtml += `<button class="tm-next-btn" onclick="nextTmQuestion()" style="margin-top:16px;">Sonraki Soru →</button>`;
                }

                body.insertAdjacentHTML('beforeend', feedbackHtml);
            } catch(e) {
                body.insertAdjacentHTML('beforeend', '<div style="color:#ef4444;text-align:center;margin-top:16px;">Hata oluştu</div>');
            }
        }

        function nextTmQuestion() {
            tmSelectedAnswer = null;
            tmAnswerStartTime = Date.now();
            loadNextQuestion();
        }

        async function showTournamentResults(id) {
            closeTournamentModal();
            document.getElementById('tournamentModal').style.display = 'flex';
            activeTournamentId = id;

            const body = document.getElementById('tmBody');
            document.getElementById('tmTitle').textContent = '🏆 Sonuçlar';
            document.getElementById('tmProgress').textContent = '';
            document.getElementById('tmTimer').textContent = '';
            body.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></div>';

            const res = await fetch(`api/tournaments.php?action=results&tournament_id=${id}`);
            const data = await res.json();
            if (data.error) { body.innerHTML = `<div style="text-align:center;padding:40px;color:var(--text-muted);">${escHtml(data.error)}</div>`; return; }

            const medals = ['🥇','🥈','🥉'];
            let html = `<div style="text-align:center;margin-bottom:20px;">
                <div style="font-size:18px;font-weight:700;">${escHtml(data.tournament.title)}</div>
                <div style="font-size:12px;color:var(--text-muted);">${data.leaderboard.length} katılımcı</div>
            </div>`;

            // Leaderboard
            html += '<div style="background:var(--bg-surface);border-radius:12px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;">';
            data.leaderboard.forEach((p, i) => {
                const medal = medals[i] || `${i+1}.`;
                const isMe = p.student_id == <?= $_SESSION['student_id'] ?>;
                const highlight = isMe ? 'background:var(--primary-glow);' : '';
                html += `<div class="tm-result-row" style="${highlight}">
                    <div class="tm-rank">${medal}</div>
                    <div class="tm-result-name">${escHtml(p.nickname || p.first_name)}${isMe ? ' (Sen)' : ''}</div>
                    <div style="font-size:12px;color:var(--text-muted);">${p.correct_count}/${data.tournament.question_count} · ${p.avg_time_seconds}s</div>
                    <div class="tm-result-score">${p.score} pts</div>
                </div>`;
            });
            html += '</div>';

            // My answers
            if (data.my_answers && data.my_answers.length > 0) {
                html += '<div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px;">Senin Cevapların</div>';
                data.my_answers.forEach(a => {
                    const icon = a.is_correct == 1 ? '✅' : '❌';
                    html += `<div style="font-size:13px;padding:8px 0;border-bottom:1px solid var(--border);">
                        ${icon} <strong>S${a.question_order}:</strong> ${escHtml(a.question_text?.substring(0,80))}...
                        <span style="color:var(--text-muted);font-size:11px;"> (${a.time_spent}s)</span>
                    </div>`;
                });
            }

            html += `<button class="tm-next-btn" onclick="closeTournamentModal()" style="margin-top:20px;">Kapat</button>`;
            body.innerHTML = html;
        }

        // Sayfa kapatılırken sesi durdur
        window.addEventListener('beforeunload', stopAllTTS);

        // ═══════════════════════════════════════
        // FAZ 2: Deneme Analizi
        // ═══════════════════════════════════════
        function openStudyRoomInfo() {
            const msg = `👥 **Çalışma Odası Nedir?**

Arkadaşlarınla birlikte ders çalışabileceğin küçük grup odaları!

• Ders bazlı **odalar oluştur** veya mevcut odalara **katıl**
• Odada en fazla **4 kişi** olabilir
• Birbirinize soru sorun, tartışın, birlikte çözün
• **@aibuke** yazarak yapay zekadan yardım isteyin
• Aynı konuyu çalışan arkadaşlarınla motivasyonunu artır

**Nasıl kullanılır?**
1. Aşağıdaki butona bas
2. "Yeni Oda Oluştur" ile kendi odanı aç veya mevcut bir odaya katıl
3. Odada mesajlaş, @aibuke ile soru sor!`;

            const el = addStreamingMessage();
            streamResponse(msg, el).then(() => {
                const bubble = chatContainer.querySelector('.message-group.ai:last-child .msg-content');
                if (bubble) {
                    const btn = document.createElement('button');
                    btn.className = 'sim-start-btn';
                    btn.style.cssText = 'margin-top:12px;max-width:300px;';
                    btn.innerHTML = '<i class="fas fa-door-open"></i> Çalışma Odalarını Aç';
                    btn.onclick = () => openPanel('groups');
                    bubble.appendChild(btn);
                }
            });
        }

        function openExamAnalysisInfo() {
            const msg = `📊 **Deneme Analizi Nedir?**

Deneme sınavı sonuç karneni fotoğraflayıp yükle, yapay zeka otomatik olarak:

• Ders ders **doğru / yanlış / boş** sayılarını okusun
• **Net puanını** hesaplasın
• **Zayıf ve güçlü** konularını tespit etsin
• Sana özel **çalışma önerisi** versin
• Önceki sınavınla **karşılaştırma** yapsın

**Nasıl kullanılır?**
1. Deneme sınavı sonuç karneni / optik formu fotoğrafla
2. Aşağıdaki butona bas ve fotoğrafı seç
3. Birkaç saniye bekle, analiz hazır!

💡 *Fotoğraf net ve okunaklı olsun, tüm ders netleri görünsün.*`;

            const el = addStreamingMessage();
            streamResponse(msg, el).then(() => {
                // Mesajın altına yükle butonu ekle
                const bubble = chatContainer.querySelector('.message-group.ai:last-child .msg-content');
                if (bubble) {
                    const btn = document.createElement('button');
                    btn.className = 'sim-start-btn';
                    btn.style.cssText = 'margin-top:12px;max-width:300px;';
                    btn.innerHTML = '<i class="fas fa-camera"></i> Karne Fotoğrafı Yükle';
                    btn.onclick = () => document.getElementById('examPhotoInput').click();
                    bubble.appendChild(btn);
                }
            });
        }

        document.getElementById('examPhotoInput').addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;

            addMessage('📊 Deneme karnesi yükleniyor...', 'user');
            typingIndicator.classList.add('active');
            chatContainer.appendChild(typingIndicator);
            scrollToBottom();

            const fd = new FormData();
            fd.append('exam_photo', file);

            try {
                const res = await fetch('api/exam_analysis.php', { method: 'POST', body: fd });
                const data = await res.json();
                typingIndicator.classList.remove('active');

                if (data.success) {
                    let msg = `📊 **Deneme Analizi Tamamlandı!**\n\n`;
                    msg += `Doğru: ${data.analysis.total_correct} | Yanlış: ${data.analysis.total_wrong} | Boş: ${data.analysis.total_empty}\n`;
                    msg += `**Net: ${data.analysis.overall_net}**\n\n`;

                    if (data.weak_topics.length > 0) {
                        msg += `⚠️ Zayıf konular:\n${data.weak_topics.join('\n')}\n\n`;
                    }
                    if (data.strong_topics.length > 0) {
                        msg += `✅ Güçlü konular:\n${data.strong_topics.join('\n')}\n\n`;
                    }
                    if (data.comparison) {
                        const arrow = data.comparison.improved ? '📈' : '📉';
                        msg += `${arrow} Önceki sınava göre: ${data.comparison.difference > 0 ? '+' : ''}${data.comparison.difference} net\n\n`;
                    }
                    msg += data.recommendation;
                    msg += `\n\n+${data.points_earned} puan kazandın!`;

                    const el = addStreamingMessage();
                    await streamResponse(msg, el);
                    updatePointsDisplay(data.points_earned);
                } else {
                    addMessage(data.error || 'Karne analiz edilemedi. Daha net bir fotoğraf dene.', 'ai');
                }
            } catch(e) {
                typingIndicator.classList.remove('active');
                addMessage('Bağlantı hatası, tekrar dene.', 'ai');
            }

            this.value = '';
        });

        // ═══════════════════════════════════════
        // FAZ 2: Chat yanıtlarına puan + dinle entegrasyonu
        // ═══════════════════════════════════════
        // Puan güncellemesi (API yanıtından)
        let chatMsgCounter = 0;
        function handleChatResponse(data) {
            if (data.total_points) {
                const el = document.getElementById('statPoints');
                if (el) el.textContent = data.total_points;
            }
            if (data.streak) {
                const el = document.getElementById('statStreak');
                if (el) el.textContent = data.streak;
            }
            if (data.points_earned) {
                showPointsToast(data.points_earned);
            }
            // Agresif Rekabet: her 5 mesajda bir rekabet bildirimi
            chatMsgCounter++;
            if (chatMsgCounter % 5 === 0) {
                showCompetitionNotif();
            }
        }

        async function showCompetitionNotif() {
            try {
                const res = await fetch('api/points.php?action=competition');
                const data = await res.json();
                if (!data.percentile) return;
                const p = data.percentile;
                let msg = '';
                if (p.percentile >= 80) {
                    msg = `🔥 ${p.class} sınıfında en iyi %${100-p.percentile}'desin! Harika gidiyorsun!`;
                } else if (data.ahead_of_me) {
                    msg = `⚡ ${data.ahead_of_me.first_name} senden ${data.gap} puan önde. ${Math.ceil(data.gap/15)} soru daha çöz ve geç!`;
                } else if (p.percentile >= 50) {
                    msg = `📊 ${p.class} sınıfında %${p.percentile} dilimdesin. Biraz daha çalışarak üst sıralara çıkabilirsin!`;
                } else {
                    msg = `💪 ${p.class} sınıfında ${p.rank_in_class}/${p.total} sıradasın. Her soru seni yukarı taşır!`;
                }
                if (msg) showCompetitionToast(msg);
            } catch(e) { /* sessizce geç */ }
        }

        function showCompetitionToast(text) {
            const toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;bottom:100px;left:50%;transform:translateX(-50%);background:var(--bg-surface);border:1px solid var(--primary);color:var(--text-primary);padding:10px 18px;border-radius:12px;font-size:13px;z-index:300;max-width:90%;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.3);animation:gardenNotifIn 0.3s ease, gardenNotifOut 0.3s ease 4s forwards;';
            toast.textContent = text;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 4500);
        }

        function showPointsToast(points) {
            if (!points) return;
            const toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;top:80px;right:20px;background:var(--success);color:white;padding:8px 16px;border-radius:100px;font-size:13px;font-weight:600;z-index:300;animation:msgIn 0.3s ease;';
            toast.textContent = `+${points} puan!`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2500);
        }

        // ─── Voice Banner ───
        const voiceBanner = document.getElementById('voiceBanner');
        const voicePlayBtn = document.getElementById('voicePlayBtn');
        const voiceBannerPreview = document.getElementById('voiceBannerPreview');
        const voiceBannerClose = document.getElementById('voiceBannerClose');
        const voiceAudio = document.getElementById('voiceAudio');
        let currentVoiceMsg = null;

        async function loadVoiceMessages() {
            try {
                const res = await fetch('api/voice.php?action=pending&_=' + Date.now(), {cache: 'no-store'});
                const data = await res.json();
                if (data.success && data.messages && data.messages.length > 0) {
                    currentVoiceMsg = data.messages[0];
                    voiceBannerPreview.textContent = currentVoiceMsg.text_content;
                    voiceAudio.src = currentVoiceMsg.audio_url + '?v=' + Date.now();
                    voiceBanner.classList.add('show');
                }
            } catch (e) {
                // Voice load error - sessizce logla
                console.warn('Voice load error:', e);
            }
        }

        voicePlayBtn.addEventListener('click', () => {
            if (!voiceAudio.src) return;
            stopAllTTS(); // Web Speech çalıyorsa durdur
            if (voiceAudio.paused) {
                voiceAudio.play();
                voicePlayBtn.textContent = '⏸️';
                voicePlayBtn.classList.add('playing');
            } else {
                voiceAudio.pause();
                voicePlayBtn.textContent = '🔊';
                voicePlayBtn.classList.remove('playing');
            }
        });

        voiceAudio.addEventListener('ended', () => {
            voicePlayBtn.textContent = '🔊';
            voicePlayBtn.classList.remove('playing');
            // Okundu işaretle
            if (currentVoiceMsg) {
                fetch('api/voice.php?action=mark_read', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=mark_read&message_id=' + currentVoiceMsg.id
                });
            }
        });

        voiceBannerClose.addEventListener('click', () => {
            voiceAudio.pause();
            voiceBanner.classList.remove('show');
            // Kapatıldığında da okundu say
            if (currentVoiceMsg) {
                fetch('api/voice.php?action=mark_read', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=mark_read&message_id=' + currentVoiceMsg.id
                });
            }
        });

        // Initialize
        initChat();
    </script>

    <!-- Logout Modal -->
    <div id="logoutModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(8px);justify-content:center;align-items:center;">
        <div style="background:var(--bg-card);border-radius:24px;padding:36px;max-width:380px;width:90%;text-align:center;box-shadow:0 25px 80px rgba(0,0,0,0.5);border:1px solid var(--border);animation:modalPop 0.4s cubic-bezier(0.34,1.56,0.64,1);">
            <div style="width:80px;height:80px;background:linear-gradient(135deg,rgba(239,68,68,0.15),rgba(220,38,38,0.1));border:2px solid rgba(239,68,68,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
                <i class="fas fa-right-from-bracket" style="font-size:28px;color:#ef4444;"></i>
            </div>
            <h3 style="font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;color:var(--text-primary);margin-bottom:10px;">Çıkış Yap</h3>
            <p style="font-size:14px;color:var(--text-secondary);margin-bottom:32px;line-height:1.6;">Oturumunu sonlandırmak üzeresin. Emin misin?</p>
            <div style="display:flex;gap:14px;">
                <button onclick="closeLogoutModal()" style="flex:1;padding:16px;background:var(--bg-surface);border:1px solid var(--border);border-radius:14px;color:var(--text-primary);font-weight:600;font-size:15px;cursor:pointer;transition:all 0.2s;font-family:inherit;">Vazgeç</button>
                <button onclick="confirmLogout()" style="flex:1;padding:16px;background:linear-gradient(135deg,#ef4444,#dc2626);border:none;border-radius:14px;color:white;font-weight:600;font-size:15px;cursor:pointer;transition:all 0.3s;box-shadow:0 4px 15px rgba(239,68,68,0.3);font-family:inherit;">Çıkış Yap</button>
            </div>
        </div>
    </div>
    <style>
        @keyframes modalPop {
            from { opacity:0; transform:scale(0.9); }
            to { opacity:1; transform:scale(1); }
        }
    </style>
</body>
</html>
