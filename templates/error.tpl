<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/hz_cloud/assets/css/style.css?v={$smarty.now}">

<div class="hz-container">
    
    <div class="hz-header">
        <div class="hz-title-area">
            <h2>Connection Error</h2>
            <p>Cloud VPS Connection Issue</p>
        </div>
        <div>
            <span class="hz-status-badge off">
                <span class="hz-pulse"></span>
                Offline
            </span>
        </div>
    </div>

    <div class="hz-alert hz-alert-danger" style="margin-bottom: 24px; border-left-width: 4px;">
        <i class="fa fa-exclamation-triangle" style="margin-top: 1px;"></i>
        <div>
            <strong style="display: block; margin-bottom: 6px;">Diagnostic Message:</strong>
            <div style="font-family: monospace; font-size: 0.95rem; background: rgba(0, 0, 0, 0.04); padding: 12px; border-radius: var(--hz-radius); border: 1px solid rgba(0,0,0,0.05); color: var(--hz-text-primary); margin-top: 6px; overflow-x: auto; white-space: pre-wrap; word-break: break-all;">
                {$usefulErrorHelper}
            </div>
        </div>
    </div>

    <p style="color: var(--hz-text-secondary); font-size: 0.9rem; line-height: 1.6; margin-bottom: 24px;">
        We encountered an error while trying to fetch the current server state from the Cloud. This can occur if the server has been terminated, the API token is temporarily unreachable, or the instance is still under initialization.
    </p>

    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="{$WEB_ROOT}/clientarea.php?action=productdetails&id={$serviceid}" class="hz-submit-btn" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
            <i class="fa fa-arrow-circle-left"></i> Reload Dashboard
        </a>
    </div>

</div>
