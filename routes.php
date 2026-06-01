<?php
/**
 * ROUTES — the whole URL map of the app. THIS is the file you edit to add a
 * page. Each line says: for this METHOD + PATH, run this controller method.
 *
 * To add a section "site.com/widgets":
 *   1. add the lines you need here (mirror the Targets block below)
 *   2. create app/Controllers/WidgetController.php with those methods
 *   3. create views/widgets/*.php
 * That's it.
 *
 * @var Router $router  (created in public/index.php)
 */

// --- Public pages ----------------------------------------------------------
$router->get('/',          [HomeController::class, 'index']);
$router->get('/health',    [HomeController::class, 'health']);

// --- Auth ------------------------------------------------------------------
$router->get('/login',           [AuthController::class, 'showLogin']);
$router->post('/login',          [AuthController::class, 'login']);
$router->get('/register',        [AuthController::class, 'showRegister']);
$router->post('/register',       [AuthController::class, 'register']);
$router->post('/logout',         [AuthController::class, 'logout']);
$router->get('/forgot',          [AuthController::class, 'showForgot']);
$router->post('/forgot',         [AuthController::class, 'sendReset']);
$router->get('/reset',           [AuthController::class, 'showReset']);
$router->post('/reset',          [AuthController::class, 'reset']);
$router->get('/verify',          [AuthController::class, 'verify']);
$router->post('/verify/resend',  [AuthController::class, 'resendVerification']);

// Google sign-in (these 404 unless google_enabled is true in config.php)
$router->get('/auth/google',          [GoogleAuthController::class, 'redirect']);
$router->get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

// GitHub sign-in (these 404 unless github_enabled is true in config.php)
$router->get('/auth/github',          [GitHubAuthController::class, 'redirect']);
$router->get('/auth/github/callback', [GitHubAuthController::class, 'callback']);

// --- Dashboard -------------------------------------------------------------
$router->get('/dashboard',       [DashboardController::class, 'index']);

// --- Favicon proxy (same-origin; fetches + caches host icons server-side) ---
$router->get('/favicon',         [FaviconController::class, 'show']);

// --- Targets (the monitored hosts/domains) ---------------------------------
// The list of targets lives on the dashboard; these are the add/edit/detail/
// scan actions. NOTE: declare literal '/targets/check' and '/targets/create'
// BEFORE '/targets/{id}', or the {id} pattern would swallow those words.
$router->get('/targets/create',       [TargetController::class, 'create']);
$router->get('/targets/export',       [TargetController::class, 'export']);
$router->post('/targets/check',       [TargetController::class, 'check']);
$router->post('/targets',             [TargetController::class, 'store']);
$router->get('/targets/{id}',         [TargetController::class, 'show']);
$router->get('/targets/{id}/edit',    [TargetController::class, 'edit']);
$router->get('/targets/{id}/export',  [TargetController::class, 'exportHistory']);
$router->post('/targets/{id}',        [TargetController::class, 'update']);
$router->post('/targets/{id}/delete', [TargetController::class, 'destroy']);

// --- Account settings ------------------------------------------------------
$router->get('/settings',           [SettingsController::class, 'show']);
$router->post('/settings/profile',  [SettingsController::class, 'updateProfile']);
$router->post('/settings/password', [SettingsController::class, 'updatePassword']);
$router->post('/settings/disconnect', [SettingsController::class, 'disconnect']);
$router->post('/settings/delete',     [SettingsController::class, 'deleteAccount']);

// --- Admin (admin role only) -----------------------------------------------
$router->get('/admin',                   [AdminController::class, 'index']);
$router->get('/admin/export',            [AdminController::class, 'exportRuns']);
$router->get('/admin/users',             [AdminController::class, 'users']);
$router->get('/admin/runs',              [AdminController::class, 'runs']);
$router->get('/admin/runs/{id}',         [AdminController::class, 'runDetail']);
$router->post('/admin/users/{id}/toggle', [AdminController::class, 'toggleActive']);
