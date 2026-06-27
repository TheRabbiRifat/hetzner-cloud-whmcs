/**
 * Encapsulated and Namespaced JavaScript for Hetzner Cloud VPS WHMCS Module
 * Exposes methods on the window.hzCloud namespace.
 */
(function() {
    // Check config is loaded
    if (!window.hzCloudConfig) {
        console.error("hzCloudConfig is missing. Unable to initialize cloud client scripts.");
        return;
    }

    // Cache config values
    const webRoot = window.hzCloudConfig.webRoot;
    const hzServiceId = window.hzCloudConfig.serviceId;
    const hzOriginalPassword = window.hzCloudConfig.originalPassword;

    // Local charts instances
    let cpuChart = null;
    let networkChart = null;
    let diskChart = null;
    let metricsLoaded = false;
    let currentMetricsTimeframe = '1d'; // Default is 24 hours (1d)

    // --- TOAST IMPLEMENTATION ---
    function showToast(message, type = 'success') {
        const container = document.getElementById('hz-toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = 'hz-toast';
        if (type === 'danger') {
            toast.style.borderLeftColor = 'var(--hz-danger)';
            toast.innerHTML = '<i class="fa fa-exclamation-circle" style="color: var(--hz-danger)"></i> ' + message;
        } else {
            toast.innerHTML = '<i class="fa fa-check-circle"></i> ' + message;
        }
        container.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => {
            toast.classList.add('active');
        }, 10);
        
        // Remove after 3 seconds
        setTimeout(() => {
            toast.classList.remove('active');
            setTimeout(() => {
                if (container.contains(toast)) {
                    container.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }

    // --- DYNAMIC UI STATE UPDATES ON ACTION SUCCESS ---
    function handleActionUiUpdate(action, form, data) {
        if (action === 'detach_iso') {
            // Remove Mounted ISO banners from top and ISO tab
            document.querySelectorAll('.hz-alert.hz-alert-warning').forEach(el => {
                if (el.innerHTML.indexOf('Mounted ISO:') > -1) {
                    el.remove();
                }
            });
            
            // Reset ISO card header text in ISO tab
            const isoHeader = document.querySelector('#isos h5');
            if (isoHeader) {
                isoHeader.innerHTML = '<i class="fa fa-plus-circle" style="color: var(--hz-primary)"></i> Mount an ISO Image';
            }
        } else if (action === 'attach_iso') {
            // Get selected ISO description
            const select = form.querySelector('select[name="iso_name"]');
            const desc = select ? select.options[select.selectedIndex].text : 'Selected ISO';
            
            // 1. Update ISO card header text in ISO tab
            const isoHeader = document.querySelector('#isos h5');
            if (isoHeader) {
                isoHeader.innerHTML = '<i class="fa fa-plus-circle" style="color: var(--hz-primary)"></i> Mount a Different ISO Image';
            }
            
            // 2. Remove any old Mounted ISO alerts just in case
            document.querySelectorAll('.hz-alert.hz-alert-warning').forEach(el => {
                if (el.innerHTML.indexOf('Mounted ISO:') > -1) {
                    el.remove();
                }
            });
            
            // 3. Insert Mounted ISO alert at top of the page (below isPending if present, or at the top of hz-container)
            const container = document.querySelector('.hz-container');
            const insertBeforeEl = document.querySelector('.hz-tabs') || container.firstChild;
            
            const topAlert = document.createElement('div');
            topAlert.className = 'hz-alert hz-alert-warning';
            topAlert.style.cssText = 'border-left: 4px solid var(--hz-warning); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; width: 100%; box-sizing: border-box;';
            topAlert.innerHTML = 
                '<div style="display: flex; align-items: center; gap: 8px;">' +
                    '<i class="fa fa-info-circle" style="color: var(--hz-warning); font-size: 1.25rem;"></i>' +
                    '<div><strong>Mounted ISO:</strong> <code>' + desc + '</code></div>' +
                '</div>' +
                '<form method="post" action="' + webRoot + '/clientarea.php?action=productdetails" style="margin: 0;">' +
                    '<input type="hidden" name="id" value="' + form.querySelector('input[name="id"]').value + '" />' +
                    '<input type="hidden" name="customAction" value="detach_iso" />' +
                    '<button type="submit" class="hz-submit-btn" style="background-color: var(--hz-danger); box-shadow: none; padding: 6px 14px; font-size: 0.8rem; margin: 0; min-height: auto; width: auto;">' +
                        '<i class="fa fa-eject"></i> Eject ISO' +
                    '</button>' +
                '</form>';
            container.insertBefore(topAlert, insertBeforeEl);
            
            // 4. Insert Mounted ISO alert inside the ISO tab (at the top of the tab content, below h4 and description paragraph)
            const isosTab = document.querySelector('#isos');
            if (isosTab) {
                const tabDesc = isosTab.querySelector('p');
                const insertBeforeTabEl = tabDesc ? tabDesc.nextSibling : isosTab.firstChild;
                
                const tabAlert = document.createElement('div');
                tabAlert.className = 'hz-alert hz-alert-warning';
                tabAlert.style.cssText = 'margin-bottom: 24px; border-left: 4px solid var(--hz-warning); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; width: 100%; box-sizing: border-box;';
                tabAlert.innerHTML = 
                    '<div style="display: flex; align-items: center; gap: 8px;">' +
                        '<i class="fa fa-info-circle" style="color: var(--hz-warning); font-size: 1.25rem;"></i>' +
                        '<div><strong>Mounted ISO:</strong> <code>' + desc + '</code></div>' +
                    '</div>' +
                    '<form method="post" action="' + webRoot + '/clientarea.php?action=productdetails" style="margin: 0;">' +
                        '<input type="hidden" name="id" value="' + form.querySelector('input[name="id"]').value + '" />' +
                        '<input type="hidden" name="customAction" value="detach_iso" />' +
                        '<button type="submit" class="hz-submit-btn" style="background-color: var(--hz-danger); box-shadow: none; padding: 6px 14px; font-size: 0.8rem; margin: 0; min-height: auto; width: auto;">' +
                            '<i class="fa fa-eject"></i> Eject ISO' +
                        '</button>' +
                    '</form>';
                isosTab.insertBefore(tabAlert, insertBeforeTabEl);
            }

            // 5. Reset select input to placeholder
            if (select) {
                select.value = '';
                select.dispatchEvent(new Event('change'));
            }
        } else if (action === 'enable_rescue') {
            // Show rescue alert at top
            document.querySelectorAll('.hz-alert.hz-alert-danger').forEach(el => {
                if (el.innerHTML.indexOf('Rescue Mode is Active') > -1) {
                    el.remove();
                }
            });
            
            const container = document.querySelector('.hz-container');
            const insertBeforeEl = document.querySelector('.hz-tabs') || container.firstChild;
            
            const rescueAlert = document.createElement('div');
            rescueAlert.className = 'hz-alert hz-alert-danger';
            rescueAlert.style.cssText = 'border-left: 4px solid var(--hz-danger); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; width: 100%; box-sizing: border-box;';
            rescueAlert.innerHTML = 
                '<div style="display: flex; align-items: center; gap: 8px;">' +
                    '<i class="fa fa-life-ring" style="color: var(--hz-danger); font-size: 1.25rem;"></i>' +
                    '<div><strong>Rescue Mode is Active:</strong> The server has booted into the rescue environment.</div>' +
                '</div>' +
                '<form method="post" action="' + webRoot + '/clientarea.php?action=productdetails" style="margin: 0;">' +
                    '<input type="hidden" name="id" value="' + form.querySelector('input[name="id"]').value + '" />' +
                    '<input type="hidden" name="customAction" value="disable_rescue" />' +
                    '<button type="submit" class="hz-submit-btn" style="background-color: var(--hz-danger); box-shadow: none; padding: 6px 14px; font-size: 0.8rem; margin: 0; min-height: auto; width: auto;">' +
                        '<i class="fa fa-power-off"></i> Disable Rescue' +
                    '</button>' +
                '</form>';
            container.insertBefore(rescueAlert, insertBeforeEl);
            
            // Update Rescue tab content dynamically
            const rescueTab = document.querySelector('#rescue');
            if (rescueTab) {
                rescueTab.innerHTML = 
                    '<h4 style="margin-top: 0; margin-bottom: 8px; font-weight: 700; color: var(--hz-text-primary); letter-spacing: -0.01em;">Server Rescue Environment</h4>' +
                    '<p style="color: var(--hz-text-muted); font-size: 0.9rem; margin-bottom: 24px;">Boot your virtual server into a temporary RAM disk system for troubleshooting.</p>' +
                    '<div class="hz-alert hz-alert-warning" style="margin-bottom: 24px;">' +
                        '<i class="fa fa-life-ring"></i>' +
                        '<div><strong>Rescue Mode is Enabled:</strong><br>The server has booted into the rescue system.</div>' +
                    '</div>' +
                    '<p style="color: var(--hz-text-secondary); font-size: 0.95rem; line-height: 1.6; margin-bottom: 24px;">' +
                        'Your server is currently running in the RAM-disk rescue environment. You can connect using a console or SSH to perform file system repairs, partition edits, or manual configuration adjustments.' +
                    '</p>' +
                    '<form method="post" action="' + webRoot + '/clientarea.php?action=productdetails">' +
                        '<input type="hidden" name="id" value="' + form.querySelector('input[name="id"]').value + '" />' +
                        '<input type="hidden" name="customAction" value="disable_rescue" />' +
                        '<button type="submit" class="hz-submit-btn" style="background-color: var(--hz-danger);">' +
                            '<i class="fa fa-power-off"></i> Disable Rescue Mode & Reboot' +
                        '</button>' +
                    '</form>';
            }
        } else if (action === 'disable_rescue') {
            // Remove rescue alert from top
            document.querySelectorAll('.hz-alert.hz-alert-danger').forEach(el => {
                if (el.innerHTML.indexOf('Rescue Mode is Active') > -1) {
                    el.remove();
                }
            });
            
            // Update Rescue tab content back to enable form
            const rescueTab = document.querySelector('#rescue');
            if (rescueTab) {
                rescueTab.innerHTML = 
                    '<h4 style="margin-top: 0; margin-bottom: 8px; font-weight: 700; color: var(--hz-text-primary); letter-spacing: -0.01em;">Server Rescue Environment</h4>' +
                    '<p style="color: var(--hz-text-muted); font-size: 0.9rem; margin-bottom: 24px;">Boot your virtual server into a temporary RAM disk system for troubleshooting.</p>' +
                    '<div class="hz-alert hz-alert-success" style="margin-bottom: 24px; background-color: var(--hz-primary-bg); color: var(--hz-primary); border-color: rgba(79, 70, 229, 0.2); border-left: 4px solid var(--hz-primary);">' +
                        '<i class="fa fa-info-circle"></i>' +
                        '<div><strong>About Rescue System:</strong><br>Rescue System is a Debian-based RAM disk environment. It allows console/SSH access to fix problems even if your main OS fails to load.</div>' +
                    '</div>' +
                    '<p style="color: var(--hz-text-muted); font-size: 0.9rem; line-height: 1.6; margin-bottom: 24px;">' +
                        'Enabling rescue mode configures the server\'s next boot to load the rescue system, and automatically reboots the instance immediately.' +
                    '</p>' +
                    '<form method="post" action="' + webRoot + '/clientarea.php?action=productdetails">' +
                        '<input type="hidden" name="id" value="' + form.querySelector('input[name="id"]').value + '" />' +
                        '<input type="hidden" name="customAction" value="enable_rescue" />' +
                        '<div class="hz-form-group">' +
                            '<label class="hz-form-label" for="rescue_type">Select Rescue Environment Type</label>' +
                            '<select name="rescue_type" id="rescue_type" class="hz-form-select">' +
                                '<option value="linux64">Linux 64-bit (Debian) - Default</option>' +
                                '<option value="freebsd64">FreeBSD 64-bit</option>' +
                            '</select>' +
                        '</div>' +
                        '<button type="submit" class="hz-submit-btn">' +
                            '<i class="fa fa-life-ring"></i> Enable Rescue & Reboot' +
                        '</button>' +
                    '</form>';
            }
        } else if (action === 'enable_backups' || action === 'disable_backups') {
            // Toggle backup status inside the backups tab
            const backupsStatusText = document.querySelector('#backups_tab span[style*="Status:"]');
            const backupsForm = document.querySelector('#backups_tab form');
            if (backupsStatusText && backupsForm) {
                const isEnabled = action === 'enable_backups';
                backupsStatusText.innerHTML = 'Status: <span style="color: ' + (isEnabled ? 'var(--hz-success)' : 'var(--hz-text-muted)') + '">' + (isEnabled ? 'Enabled' : 'Disabled') + '</span>';
                
                backupsForm.innerHTML = 
                    '<input type="hidden" name="id" value="' + form.querySelector('input[name="id"]').value + '" />' +
                    (isEnabled ? 
                        '<input type="hidden" name="customAction" value="disable_backups" />' +
                        '<button type="submit" class="hz-submit-btn" style="background-color: var(--hz-danger); box-shadow: none; padding: 8px 16px; font-size: 0.85rem;">Disable</button>'
                        :
                        '<input type="hidden" name="customAction" value="enable_backups" />' +
                        '<button type="submit" class="hz-submit-btn" style="background-color: var(--hz-success); box-shadow: none; padding: 8px 16px; font-size: 0.85rem;">Enable</button>'
                    );
            }
        }
    }

    // --- AJAX FORM SUBMISSION IMPLEMENTATION ---
    function submitFormAjax(form) {
        showToast("Processing request...", "info");
        
        // Disable all submit buttons inside the form
        const submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitBtns.forEach(btn => btn.disabled = true);
        
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        
        fetch(webRoot + '/clientarea.php?action=productdetails', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params.toString()
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw new Error(err.error || 'Server error'); });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast(data.message || "Request completed successfully!");
                submitBtns.forEach(btn => btn.disabled = false);
                
                // Trigger dynamic UI state updates
                const customActionInput = form.querySelector('input[name="customAction"]');
                if (customActionInput) {
                    handleActionUiUpdate(customActionInput.value, form, data);
                }
                
                // If a new password is generated, display it inside a modal directly
                if (data.newPassword) {
                    showHzModal({
                        title: 'New Root Password',
                        message: '<div style="background: var(--hz-warning-bg); border-left: 4px solid var(--hz-warning); padding: 16px; border-radius: 8px; margin-bottom: 12px; color: var(--hz-text-primary); text-align: left;">' +
                                 '<strong>Important:</strong> A new root password has been generated for your server:<br>' +
                                 '<div style="font-family: monospace; font-size: 1.25em; font-weight: bold; background: rgba(0,0,0,0.05); padding: 8px 12px; border-radius: 6px; display: inline-block; margin-top: 10px; border: 1px solid var(--hz-border); word-break: break-all; select-all: text; user-select: text;">' + data.newPassword + '</div><br>' +
                                 '<span style="display: inline-block; margin-top: 8px; font-size: 0.85rem; color: var(--hz-text-muted);">Please copy this password now, as it will not be displayed again.</span>' +
                                 '</div>',
                        type: 'warning',
                        confirmText: 'Done'
                    });
                }
            } else {
                showToast(data.error || "An error occurred.", "danger");
                submitBtns.forEach(btn => btn.disabled = false);
            }
        })
        .catch(err => {
            showToast(err.message || "Connection failed. Please try again.", "danger");
            submitBtns.forEach(btn => btn.disabled = false);
        });
    }

    // --- CUSTOM MODAL IMPLEMENTATION ---
    let hzModalResolve = null;

    function showHzModal(options) {
        const modal = document.getElementById('hz-modal');
        const titleEl = document.getElementById('hz-modal-title');
        const bodyEl = document.getElementById('hz-modal-body');
        const iconContainer = document.getElementById('hz-modal-icon-container');
        const iconEl = document.getElementById('hz-modal-icon');
        const confirmBtn = document.getElementById('hz-modal-btn-confirm');
        
        titleEl.innerText = options.title || 'Confirm Action';
        bodyEl.innerHTML = options.message || '';
        
        iconContainer.className = 'hz-modal-icon ' + (options.type || 'info');
        if (options.type === 'danger') {
            iconEl.className = 'fa fa-exclamation-triangle';
            confirmBtn.className = 'hz-modal-btn confirm danger';
        } else if (options.type === 'warning') {
            iconEl.className = 'fa fa-warning';
            confirmBtn.className = 'hz-modal-btn confirm danger';
        } else {
            iconEl.className = 'fa fa-info-circle';
            confirmBtn.className = 'hz-modal-btn confirm';
        }
        
        confirmBtn.innerText = options.confirmText || 'Confirm';
        modal.classList.add('active');
        
        return new Promise((resolve) => {
            hzModalResolve = resolve;
            
            confirmBtn.onclick = function() {
                modal.classList.remove('active');
                resolve(true);
            };
        });
    }

    function hzConfirm(title, message, isDanger, formElement) {
        showHzModal({
            title: title,
            message: message,
            type: isDanger ? 'danger' : 'warning',
            confirmText: isDanger ? 'Yes, Proceed' : 'Confirm'
        }).then((confirmed) => {
            if (confirmed && formElement) {
                submitFormAjax(formElement);
            }
        });
        return false; // Blocks immediate submission
    }

    // OS Rebuild Checkbox Lock & Custom Confirmation
    function checkRebuildButtonState() {
        const confirmWipe = document.getElementById("confirm_wipe");
        const rebuildSubmitBtn = document.getElementById("rebuild_submit_btn");
        const rebuildImageSelect = document.getElementById("rebuild_image");
        
        if (!confirmWipe || !rebuildSubmitBtn) return;
        
        if (confirmWipe.checked && rebuildImageSelect && rebuildImageSelect.value !== "") {
            rebuildSubmitBtn.removeAttribute("disabled");
        } else {
            rebuildSubmitBtn.setAttribute("disabled", "disabled");
        }
    }

    function validateRebuildForm(form) {
        const confirmWipe = document.getElementById("confirm_wipe");
        const rebuildImageSelect = document.getElementById("rebuild_image");
        
        const selectVal = rebuildImageSelect ? rebuildImageSelect.value : '';
        if (!selectVal) {
            showToast("Please choose an operating system image to rebuild.", "danger");
            return false;
        }
        if (!confirmWipe || !confirmWipe.checked) {
            showToast("Please confirm the data wipe checkbox.", "danger");
            return false;
        }
        
        showHzModal({
            title: 'Reinstall Operating System',
            message: '<strong>WARNING:</strong> Reinstalling your operating system will wipe all data currently stored on your server disk. This operation is <strong>permanent and irreversible</strong>.<br><br>Are you absolutely sure you want to wipe your server and install <strong>' + selectVal + '</strong>?',
            type: 'danger',
            confirmText: 'Yes, Reinstall OS'
        }).then((confirmed) => {
            if (confirmed) {
                submitFormAjax(form);
            }
        });
        return false;
    }

    // Custom Searchable Dropdown Implementation
    function initSearchableDropdown(selectId, searchPlaceholder) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        // Hide the original select
        select.style.display = 'none';
        
        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'hz-custom-select';
        wrapper.id = 'hz-custom-select-' + selectId;
        
        // Insert wrapper before select
        select.parentNode.insertBefore(wrapper, select);
        
        // Create trigger
        const trigger = document.createElement('div');
        trigger.className = 'hz-custom-select-trigger';
        if (select.disabled) {
            trigger.classList.add('disabled');
        }
        const triggerText = document.createElement('span');
        triggerText.innerText = select.options[select.selectedIndex]?.text || '-- Select --';
        const triggerIcon = document.createElement('i');
        triggerIcon.className = 'fa fa-chevron-down';
        trigger.appendChild(triggerText);
        trigger.appendChild(triggerIcon);
        wrapper.appendChild(trigger);
        
        // Create dropdown menu
        const dropdown = document.createElement('div');
        dropdown.className = 'hz-custom-select-dropdown';
        
        // Create search input wrapper
        const searchWrapper = document.createElement('div');
        searchWrapper.className = 'hz-custom-select-search-wrapper';
        const searchIcon = document.createElement('i');
        searchIcon.className = 'fa fa-search';
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'hz-custom-select-search';
        searchInput.placeholder = searchPlaceholder || 'Type to search...';
        searchInput.autocomplete = 'off';
        if (select.disabled) {
            searchInput.disabled = true;
        }
        
        searchWrapper.appendChild(searchIcon);
        searchWrapper.appendChild(searchInput);
        dropdown.appendChild(searchWrapper);
        
        // Create options list container
        const optionsContainer = document.createElement('div');
        optionsContainer.className = 'hz-custom-select-options';
        dropdown.appendChild(optionsContainer);
        wrapper.appendChild(dropdown);
        
        // Read and render options
        function renderOptions(filterText = '') {
            optionsContainer.innerHTML = '';
            const filter = filterText.toLowerCase();
            
            let matchCount = 0;
            let firstMatchValue = null;
            
            for (let i = 0; i < select.options.length; i++) {
                const opt = select.options[i];
                
                // Skip placeholder option in selection if filter is active
                if (opt.value === '' && filterText !== '') continue;
                
                if (opt.text.toLowerCase().indexOf(filter) > -1) {
                    const optDiv = document.createElement('div');
                    optDiv.className = 'hz-custom-select-option';
                    if (opt.value === select.value) {
                        optDiv.classList.add('selected');
                    }
                    optDiv.innerText = opt.text;
                    optDiv.dataset.value = opt.value;
                    
                    optDiv.onclick = function() {
                        select.value = opt.value;
                        select.dispatchEvent(new Event('change'));
                        triggerText.innerText = opt.text;
                        closeDropdown();
                    };
                    
                    optionsContainer.appendChild(optDiv);
                    
                    if (opt.value !== '') {
                        if (matchCount === 0) {
                            firstMatchValue = opt.value;
                        }
                        matchCount++;
                    }
                }
            }
            
            return firstMatchValue;
        }
        
        // Toggle dropdown
        function toggleDropdown(e) {
            e.stopPropagation();
            if (select.disabled) return;
            
            const isOpen = dropdown.classList.contains('active');
            
            // Close all other custom dropdowns
            document.querySelectorAll('.hz-custom-select-dropdown.active').forEach(d => {
                if (d !== dropdown) d.classList.remove('active');
            });
            document.querySelectorAll('.hz-custom-select-trigger.active').forEach(t => {
                if (t !== trigger) t.classList.remove('active');
            });
            
            if (isOpen) {
                closeDropdown();
            } else {
                dropdown.classList.add('active');
                trigger.classList.add('active');
                renderOptions();
                searchInput.value = '';
                // Auto-focus search input
                setTimeout(() => searchInput.focus(), 50);
            }
        }
        
        function closeDropdown() {
            dropdown.classList.remove('active');
            trigger.classList.remove('active');
        }
        
        if (!select.disabled) {
            trigger.onclick = toggleDropdown;
        }
        
        searchInput.onclick = (e) => e.stopPropagation();
        
        searchInput.oninput = function() {
            const firstMatch = renderOptions(searchInput.value);
            
            // Auto-select first match on typing
            if (searchInput.value !== '' && firstMatch !== null) {
                select.value = firstMatch;
                select.dispatchEvent(new Event('change'));
                const matchedOpt = Array.from(select.options).find(o => o.value === firstMatch);
                if (matchedOpt) {
                    triggerText.innerText = matchedOpt.text;
                }
            } else if (searchInput.value === '') {
                // Restore default/current
                const activeOpt = select.options[select.selectedIndex];
                triggerText.innerText = activeOpt ? activeOpt.text : '-- Select --';
            }
        };
        
        searchInput.onkeydown = function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const firstOption = optionsContainer.querySelector('.hz-custom-select-option');
                if (firstOption) {
                    firstOption.click();
                }
            }
        };
        
        // Close on click outside
        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) {
                closeDropdown();
            }
        });
        
        // Observe select elements to handle dynamics
        select.addEventListener('change', function() {
            const activeOpt = select.options[select.selectedIndex];
            triggerText.innerText = activeOpt ? activeOpt.text : '-- Select --';
        });
    }

    // SSH Credentials Toggle & copy
    let isPasswordRevealed = false;

    // --- CHART GENERATION HELPERS ---
    function renderCpuChart(labels, values) {
        const canvas = document.getElementById('cpuChartCanvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (cpuChart) cpuChart.destroy();
        
        const gradient = ctx.createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(79, 70, 229, 0.25)');
        gradient.addColorStop(1, 'rgba(79, 70, 229, 0.01)');

        cpuChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'CPU Usage (%)',
                    data: values,
                    borderColor: '#4f46e5',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#4f46e5',
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#18181b',
                        titleColor: '#ffffff',
                        bodyColor: '#d4d4d8',
                        borderColor: '#27272a',
                        borderWidth: 1,
                        padding: 10,
                        borderRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'CPU: ' + context.parsed.y.toFixed(1) + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        min: 0, 
                        max: 100, 
                        grid: { color: 'rgba(161, 161, 170, 0.08)' },
                        ticks: { callback: v => v + '%' } 
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderNetworkChart(labels, inValues, outValues) {
        const canvas = document.getElementById('networkChartCanvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (networkChart) networkChart.destroy();

        const gradIn = ctx.createLinearGradient(0, 0, 0, 240);
        gradIn.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
        gradIn.addColorStop(1, 'rgba(16, 185, 129, 0.01)');

        const gradOut = ctx.createLinearGradient(0, 0, 0, 240);
        gradOut.addColorStop(0, 'rgba(239, 68, 68, 0.2)');
        gradOut.addColorStop(1, 'rgba(239, 68, 68, 0.01)');

        networkChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Incoming (MB/s)',
                        data: inValues,
                        borderColor: '#10b981',
                        backgroundColor: gradIn,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#10b981',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 2
                    },
                    {
                        label: 'Outgoing (MB/s)',
                        data: outValues,
                        borderColor: '#ef4444',
                        backgroundColor: gradOut,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#ef4444',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 10, padding: 15 }
                    },
                    tooltip: {
                        backgroundColor: '#18181b',
                        titleColor: '#ffffff',
                        bodyColor: '#d4d4d8',
                        borderColor: '#27272a',
                        borderWidth: 1,
                        padding: 10,
                        borderRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.dataset.label.split(' ')[0] + ': ' + context.parsed.y.toFixed(3) + ' MB/s';
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        min: 0, 
                        grid: { color: 'rgba(161, 161, 170, 0.08)' },
                        ticks: { callback: v => v.toFixed(2) + ' MB/s' } 
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderDiskChart(labels, readValues, writeValues) {
        const canvas = document.getElementById('diskChartCanvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (diskChart) diskChart.destroy();

        const gradRead = ctx.createLinearGradient(0, 0, 0, 240);
        gradRead.addColorStop(0, 'rgba(245, 158, 11, 0.2)');
        gradRead.addColorStop(1, 'rgba(245, 158, 11, 0.01)');

        const gradWrite = ctx.createLinearGradient(0, 0, 0, 240);
        gradWrite.addColorStop(0, 'rgba(168, 85, 247, 0.2)');
        gradWrite.addColorStop(1, 'rgba(168, 85, 247, 0.01)');

        diskChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Reads (MB/s)',
                        data: readValues,
                        borderColor: '#f59e0b',
                        backgroundColor: gradRead,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#f59e0b',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 2
                    },
                    {
                        label: 'Writes (MB/s)',
                        data: writeValues,
                        borderColor: '#a855f7',
                        backgroundColor: gradWrite,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#a855f7',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 10, padding: 15 }
                    },
                    tooltip: {
                        backgroundColor: '#18181b',
                        titleColor: '#ffffff',
                        bodyColor: '#d4d4d8',
                        borderColor: '#27272a',
                        borderWidth: 1,
                        padding: 10,
                        borderRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.dataset.label.split(' ')[0] + ': ' + context.parsed.y.toFixed(3) + ' MB/s';
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        min: 0, 
                        grid: { color: 'rgba(161, 161, 170, 0.08)' },
                        ticks: { callback: v => v.toFixed(2) + ' MB/s' } 
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // --- NAMESPACED INTERFACES ---
    window.hzCloud = {
        showToast: showToast, // Expose for internal or debug alerts if needed

        togglePasswordVisibility: function() {
            const passSpan = document.getElementById("vps-password-text");
            const eyeIcon = document.getElementById("vps-password-eye");
            if (!passSpan || !eyeIcon) return;
            
            if (!isPasswordRevealed) {
                passSpan.innerText = hzOriginalPassword;
                passSpan.style.filter = "none";
                passSpan.style.userSelect = "text";
                eyeIcon.className = "fa fa-eye-slash";
                isPasswordRevealed = true;
            } else {
                passSpan.innerText = "••••••••••••";
                passSpan.style.filter = "blur(5px)";
                passSpan.style.userSelect = "none";
                eyeIcon.className = "fa fa-eye";
                isPasswordRevealed = false;
            }
        },

        copyPassword: function() {
            navigator.clipboard.writeText(hzOriginalPassword).then(function() {
                showToast("Password copied to clipboard!");
            }, function(err) {
                const tempInput = document.createElement("input");
                tempInput.value = hzOriginalPassword;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand("copy");
                document.body.removeChild(tempInput);
                showToast("Password copied to clipboard!");
            });
        },

        copyToClipboard: function(elementId) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const text = el.innerText;
            navigator.clipboard.writeText(text).then(function() {
                showToast("Copied to clipboard: " + text);
            }, function(err) {
                const tempInput = document.createElement("input");
                tempInput.value = text;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand("copy");
                document.body.removeChild(tempInput);
                showToast("Copied to clipboard: " + text);
            });
        },

        switchTab: function(element, tabId) {
            try {
                const tabcontent = document.getElementsByClassName("hz-tab-content");
                for (let i = 0; i < tabcontent.length; i++) {
                    tabcontent[i].classList.remove("active");
                }
                const tablinks = document.getElementsByClassName("hz-tab-link");
                for (let i = 0; i < tablinks.length; i++) {
                    tablinks[i].classList.remove("active");
                }
                
                const targetTab = document.getElementById("hz-tab-" + tabId);
                if (targetTab) {
                    targetTab.classList.add("active");
                }
                
                if (element) {
                    element.classList.add("active");
                }
                
                try {
                    localStorage.setItem("hz_active_tab", tabId);
                } catch (e) { }

                // Update URL parameter
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', tabId);
                    window.history.replaceState({}, '', url.toString());
                } catch (e) { }
            } catch (err) {
                console.error("Error in switchTab:", err);
            }
        },

        openConsolePopup: function() {
            const width = 1024;
            const height = 768;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            window.open(
                webRoot + '/modules/servers/hz_cloud/console.php?id=' + hzServiceId,
                'vps_console_' + hzServiceId,
                'width=' + width + ',height=' + height + ',top=' + top + ',left=' + left + ',resizable=yes,scrollbars=no,status=no,toolbar=no,menubar=no,location=no'
            );
        },

        changeTimeframe: function(element, tf) {
            currentMetricsTimeframe = tf;
            
            // Toggle active state on buttons
            const buttons = document.getElementsByClassName('hz-timeframe-btn');
            for (let i = 0; i < buttons.length; i++) {
                buttons[i].classList.remove('active');
                buttons[i].style.background = 'transparent';
                buttons[i].style.color = 'var(--hz-text-muted)';
            }
            if (element) {
                element.classList.add('active');
                element.style.background = 'var(--hz-bg-card)';
                element.style.color = 'var(--hz-primary)';
            }
            
            // Trigger reload
            metricsLoaded = false;
            window.hzCloud.loadMetrics();
        },

        loadMetrics: function() {
            if (metricsLoaded) return;
            
            const container = document.getElementById('metrics-charts-container');
            const loading = document.getElementById('metrics-loading');
            if (!container || !loading) return;
            
            loading.style.display = 'block';
            container.style.display = 'none';

            fetch(webRoot + '/clientarea.php?action=productdetails&id=' + hzServiceId + '&customAction=metrics&timeframe=' + currentMetricsTimeframe)
                .then(response => {
                    if (!response.ok) throw new Error('Response returned http error status.');
                    return response.json();
                })
                .then(data => {
                    loading.style.display = 'none';
                    container.style.display = 'block';
                    
                    const cpuData = data.metrics?.time_series?.cpu?.values || [];
                    
                    const netKeys = Object.keys(data.metrics?.time_series || {}).filter(k => k.indexOf('network.') === 0 && k.indexOf('.bandwidth.in') !== -1);
                    const netInKey = netKeys[0] || 'network.0.bandwidth.in';
                    const netOutKey = netInKey.replace('.in', '.out');
                    
                    const netInData = data.metrics?.time_series?.[netInKey]?.values || [];
                    const netOutData = data.metrics?.time_series?.[netOutKey]?.values || [];
                    
                    const diskKeys = Object.keys(data.metrics?.time_series || {}).filter(k => k.indexOf('disk.') === 0 && k.indexOf('.bandwidth.read') !== -1);
                    const diskReadKey = diskKeys[0] || 'disk.0.bandwidth.read';
                    const diskWriteKey = diskReadKey.replace('.read', '.write');
                    
                    const diskReadData = data.metrics?.time_series?.[diskReadKey]?.values || [];
                    const diskWriteData = data.metrics?.time_series?.[diskWriteKey]?.values || [];

                    const formatTime = (ts) => {
                        const d = new Date(ts * 1000);
                        if (currentMetricsTimeframe === '1h') {
                            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        } else if (currentMetricsTimeframe === '1d') {
                            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        } else {
                            // 7d or 30d (display calendar dates)
                            return d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        }
                    };

                    const cpuLabels = cpuData.map(v => formatTime(v[0]));
                    const cpuValues = cpuData.map(v => parseFloat(v[1]));
                    
                    const netLabels = netInData.map(v => formatTime(v[0]));
                    const netInValues = netInData.map(v => parseFloat(v[1]) / (1024 * 1024));
                    const netOutValues = netOutData.map(v => parseFloat(v[1]) / (1024 * 1024));
                    
                    const diskLabels = diskReadData.map(v => formatTime(v[0]));
                    const diskReadValues = diskReadData.map(v => parseFloat(v[1]) / (1024 * 1024));
                    const diskWriteValues = diskWriteData.map(v => parseFloat(v[1]) / (1024 * 1024));

                    // Set Chart Defaults for a Premium Look
                    Chart.defaults.font.family = "'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif";
                    Chart.defaults.font.weight = '500';
                    
                    renderCpuChart(cpuLabels, cpuValues);
                    renderNetworkChart(netLabels, netInValues, netOutValues);
                    renderDiskChart(diskLabels, diskReadValues, diskWriteValues);
                    
                    metricsLoaded = true;
                })
                .catch(err => {
                    loading.innerHTML = '<div class="hz-alert hz-alert-danger"><i class="fa fa-exclamation-triangle"></i><div>Failed to retrieve usage metrics: ' + err.message + '</div></div>';
                });
        },

        closeHzModal: function(e) {
            if (e.target === document.getElementById('hz-modal')) {
                window.hzCloud.handleHzModalCancel();
            }
        },

        handleHzModalCancel: function() {
            const modal = document.getElementById('hz-modal');
            if (modal) modal.classList.remove('active');
            if (hzModalResolve) {
                hzModalResolve(false);
                hzModalResolve = null;
            }
        }
    };

    // --- DOM EVENT LISTENERS & INITIALIZATION ---
    document.addEventListener("DOMContentLoaded", function() {
        let activeTab = null;
        try {
            const urlParams = new URLSearchParams(window.location.search);
            activeTab = urlParams.get('tab');
        } catch (e) { }

        if (!activeTab) {
            try {
                activeTab = localStorage.getItem("hz_active_tab");
            } catch (e) { }
        }

        try {
            if (activeTab && document.getElementById("hz-tab-" + activeTab)) {
                const targetLink = document.querySelector('.hz-tab-link[data-tab="' + activeTab + '"]');
                if (targetLink) {
                    window.hzCloud.switchTab(targetLink, activeTab);
                    if (activeTab === 'metrics') {
                        window.hzCloud.loadMetrics();
                    }
                }
            }
        } catch (err) {
            console.error("Error restoring active tab:", err);
        }
        
        // Initialize custom searchable dropdowns
        initSearchableDropdown('rebuild_image', 'Type to search operating systems...');
        initSearchableDropdown('iso_name', 'Type to search ISO images...');

        // OS Rebuild Checkbox Lock & Custom Confirmation listeners
        const confirmWipe = document.getElementById("confirm_wipe");
        const rebuildSubmitBtn = document.getElementById("rebuild_submit_btn");
        const rebuildImageSelect = document.getElementById("rebuild_image");

        if (confirmWipe && rebuildSubmitBtn) {
            confirmWipe.addEventListener("change", checkRebuildButtonState);
            if (rebuildImageSelect) {
                rebuildImageSelect.addEventListener("change", checkRebuildButtonState);
            }
        }

        // Global submit listener to catch all form submissions and handle confirmations programmatically
        document.addEventListener('submit', function(e) {
            const form = e.target;
            const customActionInput = form.querySelector('input[name="customAction"]');
            if (!customActionInput) return;
            
            // Prevent default submission
            e.preventDefault();
            
            const action = customActionInput.value;
            
            // Check for rebuild action first as it has validation
            if (action === 'rebuild') {
                validateRebuildForm(form);
                return;
            }
            
            // Map of confirmations
            let confirmTitle = '';
            let confirmMsg = '';
            let isDanger = false;
            
            if (action === 'reboot') {
                confirmTitle = 'Reboot Server';
                confirmMsg = 'Are you sure you want to reboot this virtual server? This will restart the operating system.';
                isDanger = false;
            } else if (action === 'shutdown') {
                confirmTitle = 'Graceful Shutdown';
                confirmMsg = 'Are you sure you want to shut down this server gracefully? A shutdown signal will be sent to the operating system.';
                isDanger = false;
            } else if (action === 'poweroff') {
                confirmTitle = 'Force Power Off';
                confirmMsg = '<strong style="color: var(--hz-danger);">WARNING:</strong> Forcefully powering off can cause data corruption on the virtual disk.<br><br>Are you sure you want to forcefully cut power to the server?';
                isDanger = true;
            } else if (action === 'resetpassword') {
                confirmTitle = 'Reset Password';
                confirmMsg = 'Are you sure you want to reset the root password? This will reboot your server if it is currently running.';
                isDanger = true;
            } else if (action === 'update_hostname') {
                confirmTitle = 'Change Hostname';
                confirmMsg = 'Are you sure you want to change the server hostname? This will update the domain mapping in the cloud portal.';
                isDanger = false;
            } else if (action === 'update_rdns') {
                confirmTitle = 'Update PTR Record';
                confirmMsg = 'Are you sure you want to update the reverse DNS (rDNS) record for your primary IPv4 address?';
                isDanger = false;
            } else if (action === 'update_rdns_ipv6') {
                confirmTitle = 'Update IPv6 PTR Record';
                confirmMsg = 'Are you sure you want to update the Reverse DNS record for your primary IPv6 address?';
                isDanger = false;
            } else if (action === 'disable_backups') {
                confirmTitle = 'Disable Backups';
                confirmMsg = '<strong style="color: var(--hz-danger);">WARNING:</strong> Disabling automatic backups will stop daily snapshot creation, and existing automatic backups might be deleted.<br><br>Are you sure you want to disable automatic backups?';
                isDanger = true;
            } else if (action === 'enable_backups') {
                confirmTitle = 'Enable Backups';
                confirmMsg = 'Are you sure you want to enable automatic daily backups? This will take a snapshot of your server disk every day.';
                isDanger = false;
            } else if (action === 'detach_iso') {
                confirmTitle = 'Unmount ISO';
                confirmMsg = 'Are you sure you want to eject the virtual CD-ROM drive? Any mounted ISO will be disconnected.';
                isDanger = false;
            } else if (action === 'disable_rescue') {
                confirmTitle = 'Disable Rescue Mode';
                confirmMsg = 'Are you sure you want to disable rescue mode? The server will reboot back into your normal operating system.';
                isDanger = false;
            } else if (action === 'enable_rescue') {
                confirmTitle = 'Enable Rescue Mode';
                confirmMsg = 'Are you sure you want to enable rescue mode and reboot the server now? The server will start up in a RAM-disk rescue environment.';
                isDanger = true;
            }
            
            if (confirmTitle) {
                hzConfirm(confirmTitle, confirmMsg, isDanger, form);
            } else {
                submitFormAjax(form);
            }
        });
    });
})();
