<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(SITE_URL . 'auth/login.php');
}

// Get user data
$nama = $_SESSION['nama'] ?? 'User';
$role = $_SESSION['role'] ?? 'anggota';

// Determine dashboard URL based on role
$dashboard_url = '';
switch($role) {
    case 'admin':
        $dashboard_url = SITE_URL . 'admin/dashboard.php';
        break;
    case 'petugas':
        $dashboard_url = SITE_URL . 'petugas/dashboard.php';
        break;
    case 'anggota':
        $dashboard_url = SITE_URL . 'anggota/dashboard.php';
        break;
    default:
        $dashboard_url = SITE_URL;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Datang - Perpustakaan Nusantara</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        /* Animated Background Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .particle {
            position: absolute;
            width: 10px;
            height: 10px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            animation: float 15s infinite;
        }
        
        @keyframes float {
            0% {
                transform: translateY(100vh) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) scale(1);
                opacity: 0;
            }
        }
        
        /* Welcome Container */
        .welcome-container {
            position: relative;
            z-index: 1;
            text-align: center;
            color: white;
            max-width: 800px;
            padding: 2rem;
        }
        
        /* Logo Animation */
        .logo-wrapper {
            margin-bottom: 2rem;
            animation: logoEntrance 1.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        @keyframes logoEntrance {
            0% {
                transform: scale(0) rotate(-180deg);
                opacity: 0;
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }
        
        .logo-circle {
            width: 200px;
            height: 200px;
            margin: 0 auto;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: logoPulse 2s ease-in-out infinite;
        }
        
        @keyframes logoPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 25px 70px rgba(255, 255, 255, 0.4);
            }
        }
        
        .logo-circle img {
            width: 250px;
            height: 250px;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .logo-circle::before {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            animation: ringPulse 2s ease-in-out infinite;
        }
        
        @keyframes ringPulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.5;
            }
        }
        
        /* Welcome Text */
        .welcome-text {
            margin-bottom: 2rem;
        }
        
        .welcome-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: textSlideIn 1s ease-out 0.5s both;
        }
        
        @keyframes textSlideIn {
            0% {
                transform: translateY(50px);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .welcome-subtitle {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            text-shadow: 0 2px 15px rgba(0, 0, 0, 0.2);
            animation: textSlideIn 1s ease-out 0.8s both;
        }
        
        .library-name {
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffd89b;
            margin-bottom: 0.5rem;
            text-shadow: 0 3px 20px rgba(255, 216, 155, 0.5);
            animation: textSlideIn 1s ease-out 1.1s both;
        }
        
        .school-name {
            font-size: 1.5rem;
            font-weight: 500;
            opacity: 0.95;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            animation: textSlideIn 1s ease-out 1.4s both;
        }
        
        /* User Greeting */
        .user-greeting {
            margin-top: 2rem;
            padding: 1.5rem 2rem;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            animation: fadeInUp 1s ease-out 1.7s both;
        }
        
        @keyframes fadeInUp {
            0% {
                transform: translateY(30px);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .user-greeting h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .user-greeting p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        /* Loading Animation */
        .loading-dots {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            animation: fadeInUp 1s ease-out 2s both;
        }
        
        .dot {
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            animation: dotBounce 1.4s infinite ease-in-out;
        }
        
        .dot:nth-child(1) {
            animation-delay: -0.32s;
        }
        
        .dot:nth-child(2) {
            animation-delay: -0.16s;
        }
        
        @keyframes dotBounce {
            0%, 80%, 100% {
                transform: scale(0);
            }
            40% {
                transform: scale(1);
            }
        }
        
        /* Sparkles */
        .sparkle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: white;
            border-radius: 50%;
            animation: sparkle 2s ease-in-out infinite;
        }
        
        @keyframes sparkle {
            0%, 100% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .welcome-title {
                font-size: 2.5rem;
            }
            
            .welcome-subtitle {
                font-size: 1.5rem;
            }
            
            .library-name {
                font-size: 2rem;
            }
            
            .school-name {
                font-size: 1.2rem;
            }
            
            .logo-circle {
                width: 150px;
                height: 150px;
            }
            
            .logo-circle img {
                width: 188px;
                height: 188px;
            }
        }
        
        @media (max-width: 480px) {
            .welcome-title {
                font-size: 2rem;
            }
            
            .welcome-subtitle {
                font-size: 1.2rem;
            }
            
            .library-name {
                font-size: 1.5rem;
            }
            
            .school-name {
                font-size: 1rem;
            }
            
            .user-greeting h2 {
                font-size: 1.4rem;
            }
            
            .user-greeting p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Particles Background -->
    <div class="particles" id="particles"></div>
    
    <!-- Welcome Container -->
    <div class="welcome-container">
        <!-- Logo -->
        <div class="logo-wrapper">
            <div class="logo-circle">
                <img src="https://files.catbox.moe/7ozwfm.png" alt="Logo Perpustakaan">
                <!-- Sparkles -->
                <div class="sparkle" style="top: 10%; left: 20%; animation-delay: 0s;"></div>
                <div class="sparkle" style="top: 20%; right: 15%; animation-delay: 0.3s;"></div>
                <div class="sparkle" style="bottom: 25%; left: 15%; animation-delay: 0.6s;"></div>
                <div class="sparkle" style="bottom: 15%; right: 20%; animation-delay: 0.9s;"></div>
            </div>
        </div>
        
        <!-- Welcome Text -->
        <div class="welcome-text">
            <h1 class="welcome-title">
                <i class="fas fa-star"></i> Selamat Datang <i class="fas fa-star"></i>
            </h1>
            <h2 class="welcome-subtitle">di</h2>
            <h2 class="library-name">Perpustakaan Nusantara</h2>
            <p class="school-name">SMKN 2 Surabaya</p>
        </div>
        
        <!-- User Greeting -->
        <div class="user-greeting">
            <h2>Halo, <?= htmlspecialchars($nama) ?>! 👋</h2>
            <p>Mengarahkan ke dashboard Anda...</p>
        </div>
        
        <!-- Loading Dots -->
        <div class="loading-dots">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
    </div>
    
    <script>
        // Create floating particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 15 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
                particlesContainer.appendChild(particle);
            }
        }
        
        // Initialize particles
        createParticles();
        
        // Redirect to dashboard after 4 seconds
        setTimeout(function() {
            window.location.href = '<?= $dashboard_url ?>';
        }, 4000);
        
        // Add fade out animation before redirect
        setTimeout(function() {
            document.body.style.transition = 'opacity 0.5s ease';
            document.body.style.opacity = '0';
        }, 3500);
    </script>
</body>
</html>