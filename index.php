<?php require_once 'db.php'; require_once 'functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Student Result Management System</title><link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="modern-ui.css?v=20261006-modern2">
</head>
<body class="home-page"><div class="main-wrapper">
<?php include 'public_header.php'; ?>
<main class="home-main home-main-premium">
    <section class="home-hero home-hero-premium">
        <div class="hero-copy hero-copy-premium">
            <span class="page-kicker">South Eastern University · Academic Portal</span>
            <h1>Your results.<br><span>A clearer view.</span></h1>
            <p class="hero-lead">A secure academic workspace for students to view published semester results and for authorized staff to manage academic records.</p>
            <div class="hero-tags hero-tags-premium">
                <span><b>✓</b> Secure Access</span>
                <span><b>✓</b> Academic Records</span>
                <span><b>✓</b> Semester Result Sheets</span>
            </div>
            <div class="hero-trustline">
                <span class="trust-dot"></span>
                <span>South Eastern University of Sri Lanka · Faculty of Applied Sciences</span>
            </div>
        </div>

        <div class="hero-panel hero-panel-premium">
            <div class="portal-card unified-portal-card premium-login-card">
                <div class="portal-card-top">
                    <div class="portal-icon unified-portal-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a5 5 0 0 1 5 5v2h1a3 3 0 0 1 3 3v7a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-7a3 3 0 0 1 3-3h1V7a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v2h6V7a3 3 0 0 0-3-3Zm0 9a2 2 0 0 0-1 3.73V19h2v-2.27A2 2 0 0 0 12 13Z" fill="currentColor"/></svg>
                    </div>
                    <div class="portal-copy">
                        <span class="portal-label">Students &amp; Authorized Staff</span>
                        <h2>Secure Academic Portal</h2>
                        <p>Sign in to view semester results, access result sheets or manage academic records.</p>
                    </div>
                </div>

                <div class="account-choice-grid">
                    <div class="account-choice">
                        <span class="account-choice-icon">S</span>
                        <span><strong>Student</strong><small>Student ID + PIN</small></span>
                    </div>
                    <div class="account-choice">
                        <span class="account-choice-icon admin">A</span>
                        <span><strong>Administrator</strong><small>Username + Password</small></span>
                    </div>
                </div>

                <a href="login.php" class="portal-button premium-login-button">
                    <span>Open Academic Portal</span>
                    <span class="button-arrow">→</span>
                </a>
                <p class="portal-footnote"><span></span> Account type is detected automatically</p>
            </div>
        </div>
    </section>

    <section class="home-feature-row home-feature-row-premium" aria-label="Portal features">
        <div class="home-feature-card">
            <span class="feature-icon">01</span>
            <div><strong>Unified Access</strong><span>One secure entry point for students and authorized staff.</span></div>
        </div>
        <div class="home-feature-card">
            <span class="feature-icon">02</span>
            <div><strong>Clear Academic View</strong><span>Semester results and official result sheets in a focused layout.</span></div>
        </div>
        <div class="home-feature-card">
            <span class="feature-icon">03</span>
            <div><strong>Result Verification</strong><span>Check the authenticity of a semester result sheet online.</span></div>
        </div>
    </section>
</main>
<?php include 'footer.php'; ?>
</div></body></html>
