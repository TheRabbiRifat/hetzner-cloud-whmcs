    <!-- Header with server name and status badge -->
    <div class="hz-header">
        <div class="hz-title-area">
            <h2>{$vpsName}</h2>
            <p>Cloud VPS &bull; {$country}</p>
        </div>
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            {if $allowVnc}
            <button class="hz-submit-btn" onclick="hzCloud.openConsolePopup()" style="background: var(--hz-bg-card); color: var(--hz-text-secondary); border: 1px solid var(--hz-border); padding: 8px 16px; font-size: 0.85rem; box-shadow: var(--hz-shadow);">
                <i class="fa fa-terminal" style="color: var(--hz-primary)"></i> KVM Console
            </button>
            {/if}
            <span class="hz-status-badge {$status}">
                <span class="hz-pulse"></span>
                {$status}
            </span>
        </div>
    </div>
