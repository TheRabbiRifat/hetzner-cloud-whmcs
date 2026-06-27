    <div id="hz-tab-metrics" class="hz-tab-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
            <div>
                <h4 style="margin: 0; font-weight: 700; color: var(--hz-text-primary); letter-spacing: -0.01em;">Resource Usage</h4>
                <p style="color: var(--hz-text-muted); font-size: 0.85rem; margin: 4px 0 0 0;">Real-time performance stats fetched from the hypervisor metrics endpoint.</p>
            </div>
            <!-- Timeframe Selector Button Group -->
            <div style="display: inline-flex; background: var(--hz-border); padding: 4px; border-radius: 8px; gap: 4px;">
                <button class="hz-timeframe-btn" onclick="hzCloud.changeTimeframe(this, '1h')" style="padding: 6px 12px; font-size: 0.8rem; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; transition: all 0.2s; background: transparent; color: var(--hz-text-muted);">1h</button>
                <button class="hz-timeframe-btn active" onclick="hzCloud.changeTimeframe(this, '1d')" style="padding: 6px 12px; font-size: 0.8rem; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; transition: all 0.2s; background: var(--hz-bg-card); color: var(--hz-primary);">24h</button>
                <button class="hz-timeframe-btn" onclick="hzCloud.changeTimeframe(this, '7d')" style="padding: 6px 12px; font-size: 0.8rem; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; transition: all 0.2s; background: transparent; color: var(--hz-text-muted);">7d</button>
                <button class="hz-timeframe-btn" onclick="hzCloud.changeTimeframe(this, '30d')" style="padding: 6px 12px; font-size: 0.8rem; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; transition: all 0.2s; background: transparent; color: var(--hz-text-muted);">30d</button>
            </div>
        </div>

        <div id="metrics-loading" style="padding: 60px 0; text-align: center; color: var(--hz-text-muted);">
            <div class="hz-spinner dark" style="width: 24px; height: 24px; border-width: 3px;"></div>
            <span style="margin-left: 10px; font-weight: 600; vertical-align: middle;">Loading graph statistics...</span>
        </div>

        <div id="metrics-charts-container" style="display: none;">
            <div class="hz-chart-card">
                <h5 style="margin-top: 0; margin-bottom: 15px; font-weight: 600; color: var(--hz-text-secondary);">CPU Load Factor</h5>
                <div style="position: relative; height: 240px; width: 100%;">
                    <canvas id="cpuChartCanvas"></canvas>
                </div>
            </div>
            
            <div class="hz-chart-card">
                <h5 style="margin-top: 0; margin-bottom: 15px; font-weight: 600; color: var(--hz-text-secondary);">Network Interface Bandwidth</h5>
                <div style="position: relative; height: 240px; width: 100%;">
                    <canvas id="networkChartCanvas"></canvas>
                </div>
            </div>

            <div class="hz-chart-card" style="margin-bottom: 0;">
                <h5 style="margin-top: 0; margin-bottom: 15px; font-weight: 600; color: var(--hz-text-secondary);">Disk Read / Write Bandwidth</h5>
                <div style="position: relative; height: 240px; width: 100%;">
                    <canvas id="diskChartCanvas"></canvas>
                </div>
            </div>
        </div>
    </div>
