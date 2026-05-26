<?php
/**
 * New support ticket form markup (full Help & Support page).
 *
 * @package   theme_remui_kids
 */

defined('MOODLE_INTERNAL') || die();
?>
<div class="help-form">
    <h3 style="margin-top: 0; color: #1f2937;">Create New Support Ticket</h3>
    <p style="color: #6b7280; margin-bottom: 20px;">
        Need help with a bug, have a question, or want to suggest a feature? We're here to help!
    </p>
    <div class="help-form-group">
        <label class="help-form-label">Category <span style="color: #ef4444;">*</span></label>
        <select class="help-form-select" id="helpPageCategory" required>
            <option value="">Select a category</option>
            <option value="bug">Bug Report</option>
            <option value="query">Question/Query</option>
            <option value="feature">Feature Request</option>
            <option value="general">General Support</option>
        </select>
    </div>
    <div class="help-form-group">
        <label class="help-form-label">Subject <span style="color: #ef4444;">*</span></label>
        <input type="text" class="help-form-input" id="helpPageSubject" placeholder="Brief summary of your issue" maxlength="255" required />
    </div>
    <div class="help-form-group">
        <label class="help-form-label">Description <span style="color: #ef4444;">*</span></label>
        <textarea class="help-form-textarea" id="helpPageDescription" placeholder="Please provide detailed information about your issue..." required></textarea>
    </div>
    <div class="help-form-group">
        <label class="help-form-label">Priority</label>
        <select class="help-form-select" id="helpPagePriority">
            <option value="low">Low</option>
            <option value="normal" selected>Normal</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
        </select>
    </div>
    <div class="help-form-group">
        <label class="help-form-label">Attachments (Optional)</label>
        <div class="help-file-upload" id="helpPageFileUpload">
            <i class="fa fa-cloud-upload" style="font-size: 32px; color: #9ca3af; margin-bottom: 10px;"></i>
            <p style="margin: 0; color: #6b7280;"><strong>Click to upload</strong> or drag and drop</p>
            <input type="file" class="help-file-input" id="helpPageFileInput" multiple accept="image/*,.pdf,.doc,.docx,.txt" />
        </div>
        <div class="help-file-list" id="helpPageFileList"></div>
    </div>
    <button class="help-submit-btn" id="helpPageSubmitBtn" type="button">
        <i class="fa fa-paper-plane"></i> Submit Ticket
    </button>
</div>
