<?php $currentPage = basename($_SERVER['PHP_SELF'] ?? ''); ?>
<nav class="navbar admin-navbar admin-navbar-two-row" aria-label="Administration navigation">
    <div class="admin-header-top-row">
        <a class="admin-brand admin-brand-prominent" href="admin_dashboard.php">
            <span class="admin-logo"><img src="logo.png" alt="SEUSL Logo"></span>
            <span class="admin-brand-copy"><strong>Result Administration</strong><small><?=e(role_label(admin_role()))?></small></span>
        </a>

        <div class="nav-utilities">
            <button class="icon-btn density-toggle" type="button" aria-label="Toggle table density" title="Table density"><span aria-hidden="true">≡</span></button>
            <button class="icon-btn theme-toggle" type="button" aria-label="Toggle dark mode" title="Toggle theme"><span aria-hidden="true">◐</span></button>
            <div class="profile-menu-wrap">
                <button class="profile-trigger" type="button" aria-expanded="false" aria-haspopup="true">
                    <span class="profile-avatar"><?=e(strtoupper(substr($_SESSION['admin_username'] ?? 'A',0,1)))?></span>
                    <span class="profile-copy"><strong><?=e($_SESSION['admin_username']??'Admin')?></strong><small><?=e(role_label(admin_role()))?></small></span>
                    <span class="profile-chevron">⌄</span>
                </button>
                <div class="profile-menu" role="menu">
                    <div class="profile-menu-head"><strong><?=e($_SESSION['admin_username']??'Admin')?></strong><span><?=e(role_label(admin_role()))?></span></div>
                    <?php if(has_role(['super_admin'])): ?><a href="admin_settings.php" role="menuitem">⚙ Settings</a><?php endif; ?>
                    <?php if(has_role(['super_admin','registrar'])): ?><a href="student_result_issues.php" role="menuitem">Student Result Issues</a><?php endif; ?>
                    <a href="admin_dashboard.php" role="menuitem">⌂ Dashboard</a>
                    <a class="danger-link" href="logout.php" role="menuitem">↪ Logout</a>
                </div>
            </div>
            <button class="mobile-nav-toggle" type="button" aria-label="Open navigation" aria-expanded="false"><span></span><span></span><span></span></button>
        </div>
    </div>

    <div class="admin-header-nav-row">
        <div class="nav-buttons admin-nav-dock admin-nav-single-row" id="adminNavLinks">
            <a href="admin_dashboard.php" class="nav-btn <?= $currentPage==='admin_dashboard.php'?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10 12 3l9 7v10H3z M9 20v-7h6v7"/></svg></span><span>Dashboard</span></a>
            <?php if(has_role(['super_admin','data_entry'])): ?><a href="add_student.php" class="nav-btn <?= $currentPage==='add_student.php'?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span><span>Add Student</span></a><?php endif; ?>
            <a href="view_students.php" class="nav-btn <?= in_array($currentPage,['view_students.php','edit_student.php'])?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a7 7 0 0 0-14 0v2 M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0"/></svg></span><span>Students</span></a>
            <?php if(has_role(['super_admin','data_entry'])): ?><a href="add_result.php" class="nav-btn <?= $currentPage==='add_result.php'?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span><span>Add Result</span></a><?php endif; ?>
            <a href="view_results.php" class="nav-btn <?= in_array($currentPage,['view_results.php','edit_result.php'])?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg></span><span>Results</span></a>
            <?php if(has_role(['super_admin','data_entry'])): ?><a href="bulk_results.php" class="nav-btn <?= $currentPage==='bulk_results.php'?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V3M7 8l5-5 5 5M4 15v6h16v-6"/></svg></span><span>Bulk</span></a><?php endif; ?>
            <?php if(has_role(['super_admin','registrar'])): ?>
            <a href="result_approvals.php" class="nav-btn <?= $currentPage==='result_approvals.php'?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l4 4L20 5"/></svg></span><span>Approvals</span></a>
            <a href="bulk_publish.php" class="nav-btn <?= $currentPage==='bulk_publish.php'?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 8l5-5 5 5M4 15v6h16v-6"/></svg></span><span>Publish</span></a>
            <a href="academic_settings.php" class="nav-btn <?= $currentPage==='academic_settings.php'?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h14v16H5zM9 8h6M9 12h6M9 16h3"/></svg></span><span>Rules</span></a>
            <a href="subject_master.php" class="nav-btn <?= $currentPage==='subject_master.php'?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16v16H4zM4 10h16M10 4v16"/></svg></span><span>Subjects</span></a>
            <a href="pin_reset_requests.php" class="nav-btn <?= $currentPage==='pin_reset_requests.php'?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 10V7a5 5 0 0 1 10 0v3M5 10h14v11H5zM12 14v3"/></svg></span><span>PIN</span></a>
            <a href="audit_log.php" class="nav-btn <?= $currentPage==='audit_log.php'?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0"/></svg></span><span>Audit</span></a>
            <?php if(has_role(['super_admin'])): ?>
            <a href="admin_users.php" class="nav-btn <?= $currentPage==='admin_users.php'?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 20v-2a5 5 0 0 1 10 0v2M12 6a4 4 0 1 1-8 0 4 4 0 0 1 8 0M16 11a4 4 0 0 1 5 4v5"/></svg></span><span>Admins</span></a>
            <a href="admin_settings.php" class="nav-btn <?= $currentPage==='admin_settings.php'?'active':'' ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2"/></svg></span><span>Settings</span></a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</nav>
