<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Redirect if already logged in
if (Auth::check()) {
    header('Location: ' . Auth::getDashboardUrl());
    exit;
}

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Landing Page Specific Styles */
        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 50%, #e0e7ff 100%);
            background-attachment: fixed;
        }

        .landing-header {
            background: var(--bg-primary);
            box-shadow: var(--shadow-md);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 2px solid var(--border-light);
        }

        .landing-header .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
        }

        .landing-header h1 {
            color: var(--primary);
            font-size: 1.75rem;
            margin: 0;
            font-weight: 700;
            flex-shrink: 0;
        }

        .landing-header .nav-links {
            display: flex;
            gap: 1rem;
        }

        .landing-header .nav-links a {
            background: var(--primary);
            color: var(--bg-primary) !important;
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: var(--radius);
            transition: var(--transition);
            font-weight: 600;
            font-size: 0.95rem;
            white-space: nowrap;
        }

        .landing-header .nav-links a:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-md);
        }

        .landing-header .nav-links a:first-child {
            background: var(--bg-secondary);
            color: var(--primary) !important;
            border: 2px solid var(--primary);
        }

        .landing-header .nav-links a:first-child:hover {
            background: var(--primary);
            color: var(--bg-primary) !important;
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .hero-content {
            max-width: 1400px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-text h2 {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
            line-height: 1.2;
            letter-spacing: -0.025em;
        }

        .hero-text p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.8;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hero-btn {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border: none;
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            box-shadow: var(--shadow-md);
        }

        .hero-btn.primary {
            background: var(--primary);
            color: var(--bg-primary);
        }

        .hero-btn.primary:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-lg);
        }

        .hero-btn.secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .hero-btn.secondary:hover {
            background: var(--primary);
            color: var(--bg-primary);
            box-shadow: var(--shadow-md);
        }

        .hero-visual {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-icon {
            font-size: 12rem;
            color: rgba(37, 99, 235, 0.1);
        }

        /* Features Section */
        .features {
            max-width: 1400px;
            margin: -4rem auto 4rem;
            padding: 0 2rem;
            position: relative;
            z-index: 10;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--bg-primary);
            padding: 2rem;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            transition: var(--transition-slow);
            border: 1px solid var(--border-light);
        }

        .feature-card:hover {
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
            transform: translateY(-5px);
        }

        .feature-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            font-weight: 700;
        }

        .feature-card p {
            color: var(--text-secondary);
            line-height: 1.6;
            margin: 0;
        }

        /* How It Works Section */
        .how-it-works {
            background: var(--bg-primary);
            padding: 5rem 2rem;
            margin: 4rem 0;
            border-top: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
        }

        .how-it-works-content {
            max-width: 1400px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 3rem;
            letter-spacing: -0.025em;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .step {
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: var(--bg-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0 auto 1rem;
            box-shadow: var(--shadow-md);
        }

        .step h3 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .step p {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* User Types Section */
        .user-types {
            max-width: 1400px;
            margin: 4rem auto;
            padding: 0 2rem;
        }

        .user-types-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 3rem;
            letter-spacing: -0.025em;
        }

        .user-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .user-card {
            background: var(--bg-primary);
            border: 2px solid var(--border-medium);
            padding: 2rem;
            border-radius: var(--radius-xl);
            color: var(--text-primary);
            transition: var(--transition-slow);
            box-shadow: var(--shadow-md);
        }

        .user-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
            transform: translateY(-5px);
        }

        .user-card-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .user-card h3 {
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            color: var(--text-primary);
            font-weight: 700;
        }

        .user-card p {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .user-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .user-card li {
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            padding-left: 1.5rem;
            position: relative;
        }

        .user-card li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: bold;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 4rem 2rem;
            text-align: center;
            margin: 4rem 0 0 0;
            color: white;
        }

        .cta-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .cta-section h2 {
            font-size: 2rem;
            color: white;
            margin-bottom: 1rem;
            font-weight: 800;
        }

        .cta-section p {
            font-size: 1.125rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cta-btn {
            padding: 0.875rem 2rem;
            font-size: 1rem;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .cta-btn.primary {
            background: white;
            color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .cta-btn.primary:hover {
            background: var(--bg-secondary);
            box-shadow: var(--shadow-lg);
        }

        .cta-btn.secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .cta-btn.secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="landing-header">
        <div class="header-content">
            <h1><i class="fas fa-graduation-cap"></i> School Event</h1>
            <nav class="nav-links">
                <a href="index.php?form=login"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="index.php?form=register"><i class="fas fa-user-plus"></i> Register</a>
            </nav>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-text">
                <h2>Manage School Events Effortlessly</h2>
                <p>A centralized platform for teachers to create events and students to discover what's happening at our school.</p>
                <div class="hero-buttons">
                    <a href="index.php?form=login" class="hero-btn primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="index.php?form=register" class="hero-btn secondary">
                        <i class="fas fa-user-plus"></i> Sign Up
                    </a>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-icon">
                    <img src="uploads/x.jpg" alt="Event hub">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-pencil-alt"></i></div>
                <h3>Easy Event Creation</h3>
                <p>Teachers can quickly create events with descriptions, images, and important details in just a few clicks.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-binoculars"></i></div>
                <h3>Browse Events</h3>
                <p>Students can easily browse all approved school events and find activities that match their interests.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Admin Moderation</h3>
                <p>School administrators review and approve all events to ensure quality and appropriateness.</p>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works">
        <div class="how-it-works-content">
            <h2 class="section-title">How It Works</h2>
            <div class="steps-grid">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Create an Account</h3>
                    <p>Sign up as a student or teacher using your school email.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Create or Explore</h3>
                    <p>Teachers create exciting events. Students explore what's available.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Admin Review</h3>
                    <p>School admins review events to ensure they meet school standards.</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Go Live</h3>
                    <p>Approved events are published for the entire school community to see.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- User Types Section -->
    <section class="user-types">
        <h2 class="user-types-title">For Everyone in Our School</h2>
        <div class="user-types-grid">
            <div class="user-card">
                <div class="user-card-icon"><i class="fas fa-user-graduate"></i></div>
                <h3>Students</h3>
                <p>Discover exciting events happening at school and stay connected with campus life.</p>
                <ul>
                    <li>Browse all approved events</li>
                    <li>View event details and dates</li>
                    <li>Stay informed about school activities</li>
                </ul>
            </div>
            <div class="user-card">
                <div class="user-card-icon"><i class="fas fa-chalkboard-user"></i></div>
                <h3>Teachers</h3>
                <p>Easily organize and promote your classroom events and activities to students.</p>
                <ul>
                    <li>Create new events</li>
                    <li>Add descriptions and images</li>
                    <li>Reach the entire student body</li>
                </ul>
            </div>
            <div class="user-card">
                <div class="user-card-icon"><i class="fas fa-user-tie"></i></div>
                <h3>School Administration</h3>
                <p>Manage and moderate all school events to maintain quality and standards.</p>
                <ul>
                    <li>Review and approve events</li>
                    <li>Manage user accounts</li>
                    <li>Monitor school activities</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="cta-content">
            <h2>Ready to Get Started?</h2>
            <p>Join our school event management platform and never miss an important school activity again.</p>
            <div class="cta-buttons">
                <a href="index.php?form=register" class="cta-btn primary">Create Your Account</a>
                <a href="index.php?form=login" class="cta-btn secondary">Already Have an Account?</a>
            </div>
        </div>
    </section>
</body>
</html>