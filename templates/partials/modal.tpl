<!-- Custom Modal Overlay Markup -->
<div id="hz-modal" class="hz-modal-overlay" onclick="hzCloud.closeHzModal(event)">
    <div class="hz-modal-card">
        <div class="hz-modal-header">
            <div id="hz-modal-icon-container" class="hz-modal-icon info">
                <i id="hz-modal-icon" class="fa fa-info-circle"></i>
            </div>
            <h3 id="hz-modal-title" class="hz-modal-title">Confirm Action</h3>
        </div>
        <div id="hz-modal-body" class="hz-modal-body">
            Are you sure you want to proceed with this action?
        </div>
        <div class="hz-modal-footer">
            <button id="hz-modal-btn-cancel" class="hz-modal-btn cancel" onclick="hzCloud.handleHzModalCancel()">Cancel</button>
            <button id="hz-modal-btn-confirm" class="hz-modal-btn confirm">Confirm</button>
        </div>
    </div>
</div>
