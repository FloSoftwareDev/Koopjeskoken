<?php
/**
 * auth.php
 *
 * This file no longer runs standalone — include helpers.php and call
 * requireAdmin() / requireAuth() directly in your endpoints.
 *
 * It is kept here for backwards compatibility; requiring this file still
 * enforces the admin check so existing code continues to work.
 */
require_once __DIR__ . '/helpers.php';

startSecureSession();
requireAdmin();