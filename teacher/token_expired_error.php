<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Renders a user-friendly "token expired" page for embedding in iframes.
 * Include this file after setting http_response_code(403). Do not output anything before including.
 *
 * Optional: set $token_expired_file_type before include (e.g. 'pdf', 'document') for message wording.
 */

if (!isset($token_expired_file_type)) {
    $token_expired_file_type = 'file';
}
$is_pdf = ($token_expired_file_type === 'pdf');
$file_label = $is_pdf ? 'PDF' : 'file';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewing link expired</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f8fafc;
            color: #334155;
            padding: 24px;
            text-align: center;
        }
        .token-expired-card {
            max-width: 420px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
            padding: 32px 28px;
        }
        .token-expired-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 20px;
            background: #fef3c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            line-height: 1;
        }
        .token-expired-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 12px;
        }
        .token-expired-message {
            font-size: 0.9375rem;
            line-height: 1.5;
            color: #64748b;
            margin: 0 0 24px;
        }
        .token-expired-hint {
            font-size: 0.875rem;
            color: #94a3b8;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="token-expired-card">
        <div class="token-expired-icon" aria-hidden="true">&#128336;</div>
        <h1 class="token-expired-title">Viewing link expired</h1>
        <p class="token-expired-message">
            You cannot see this <?php echo htmlspecialchars($file_label); ?> because the secure link has expired.
            Please refresh the tab or page and open the <?php echo htmlspecialchars($file_label); ?> again to view it.
        </p>
        <p class="token-expired-hint">Refreshing the page will generate a new link.</p>
    </div>
</body>
</html>
