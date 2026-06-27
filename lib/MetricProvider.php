<?php

namespace WHMCS\Module\Server\HzCloud;

use WHMCS\UsageBilling\Contracts\Metrics\ProviderInterface;
use WHMCS\UsageBilling\Metrics\Metric;
use WHMCS\UsageBilling\Metrics\Units\GigaBytes;
use WHMCS\UsageBilling\Metrics\Units\FloatingPoint;
use WHMCS\UsageBilling\Metrics\Usage;
use WHMCS\Database\Capsule;
use HetznerAPI;

class MetricProvider implements ProviderInterface
{
    protected $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    /**
     * Define the usage metrics supported by this module.
     *
     * @return array
     */
    public function metrics(): array
    {
        return [
            'bandwidth_usage' => new Metric(
                'bandwidth_usage',
                'Bandwidth Usage',
                \WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_PERIOD_MONTH,
                new FloatingPoint('TB', 'TB', 'TB', null, 'TB')
            ),
            'snapshot_usage' => new Metric(
                'snapshot_usage',
                'Snapshot Usage',
                \WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT,
                new GigaBytes('GB')
            ),
        ];
    }

    /**
     * Fetch usage for all active tenants globally (typically called by WHMCS daily cron).
     *
     * @return array
     */
    public function usage(): array
    {
        $usages = [];
        try {
            // Find all active/suspended services associated with the module server group ID
            $services = Capsule::table('tblhosting')
                ->where('server', $this->params['serverid'] ?? 0)
                ->whereIn('domainstatus', ['Active', 'Suspended'])
                ->get();

            foreach ($services as $service) {
                // Get Server ID custom field value
                $serverId = $this->getCustomFieldValue($service->id, 'Server ID');
                if (!$serverId) {
                    continue;
                }

                $usageData = $this->fetchUsageForServer($serverId);
                if ($usageData) {
                    $usages[$service->id] = $usageData;
                }
            }
        } catch (\Exception $e) {
            if (function_exists('logModuleCall')) {
                logModuleCall('hz_cloud', 'MetricProvider_usage_all', '', $e->getMessage(), $e->getTraceAsString());
            }
        }
        return $usages;
    }

    /**
     * Fetch usage for a single service tenant.
     *
     * @param \WHMCS\UsageBilling\Contracts\Metrics\TenantInterface|string|int $tenant
     * @return array
     */
    public function tenantUsage($tenant): array
    {
        try {
            $serviceId = 0;
            if (is_object($tenant)) {
                if (method_exists($tenant, 'getId')) {
                    $serviceId = $tenant->getId();
                }
            } elseif (is_numeric($tenant)) {
                $serviceId = (int)$tenant;
            } elseif (is_string($tenant)) {
                $serviceId = Capsule::table('tblhosting')
                    ->where('server', $this->params['serverid'] ?? 0)
                    ->where('domain', $tenant)
                    ->value('id');

                if (!$serviceId) {
                    $serviceId = Capsule::table('tblhosting')
                        ->where('server', $this->params['serverid'] ?? 0)
                        ->where('username', $tenant)
                        ->value('id');
                }
            }

            if (!$serviceId) {
                return [];
            }

            $serverId = $this->getCustomFieldValue($serviceId, 'Server ID');
            if (!$serverId) {
                return [];
            }
            return $this->fetchUsageForServer($serverId) ?: [];
        } catch (\Exception $e) {
            if (function_exists('logModuleCall')) {
                $tenantId = is_object($tenant) && method_exists($tenant, 'getId') ? $tenant->getId() : (string)$tenant;
                logModuleCall('hz_cloud', 'MetricProvider_tenantUsage', $tenantId, $e->getMessage(), $e->getTraceAsString());
            }
            return [];
        }
    }

    /**
     * Retrieve current metrics for a specific Hetzner Server.
     *
     * @param int $serverId
     * @return array|null
     */
    protected function fetchUsageForServer($serverId)
    {
        try {
            $apiToken = $this->params['serverpassword'];
            if (empty($apiToken)) {
                return null;
            }

            $api = new HetznerAPI($apiToken);

            // 1. Fetch Snapshot Usage (sum sizes of all snapshots)
            $snapshots = $api->getSnapshots($serverId);
            $snapshotGb = 0.0;
            foreach ($snapshots as $snap) {
                $snapshotGb += (float)($snap['image_size'] ?? 0);
            }

            // 2. Fetch Bandwidth Usage (integrate network rates in TB)
            $bandwidthTb = $this->calculateBandwidthTb($api, $serverId);

            return [
                'bandwidth_usage' => new Usage($bandwidthTb),
                'snapshot_usage' => new Usage($snapshotGb),
            ];

        } catch (\Exception $e) {
            if (function_exists('logModuleCall')) {
                logModuleCall('hz_cloud', 'MetricProvider_fetchUsageForServer', $serverId, $e->getMessage(), $e->getTraceAsString());
            }
            return null;
        }
    }

    /**
     * Compute estimated cumulative bandwidth usage in Terabytes by integrating rate metrics.
     *
     * @param HetznerAPI $api
     * @param int $serverId
     * @return float
     */
    protected function calculateBandwidthTb(HetznerAPI $api, $serverId)
    {
        try {
            // Fetch network metrics for the last 24 hours (standard daily interval)
            $end = gmdate('Y-m-d\TH:i:s\Z');
            $start = gmdate('Y-m-d\TH:i:s\Z', time() - 86400);
            $metrics = $api->getMetrics($serverId, 'network', $start, $end);
            
            $totalBytes = 0;
            $netSeries = $metrics['metrics']['time_series'] ?? [];
            
            foreach ($netSeries as $key => $series) {
                // Sum rate * interval for network.X.bandwidth.in and network.X.bandwidth.out
                if (strpos($key, 'network.') === 0 && (strpos($key, '.bandwidth.in') !== false || strpos($key, '.bandwidth.out') !== false)) {
                    $values = $series['values'] ?? [];
                    if (count($values) < 2) {
                        continue;
                    }
                    
                    $sum = 0;
                    $prevTime = null;
                    foreach ($values as $val) {
                        $ts = (int)$val[0];
                        $rate = (float)$val[1]; // bytes per second
                        
                        if ($prevTime !== null) {
                            $interval = $ts - $prevTime;
                            // Shield from huge anomalies or data gaps
                            if ($interval > 0 && $interval < 7200) {
                                $sum += $rate * $interval;
                            }
                        }
                        $prevTime = $ts;
                    }
                    $totalBytes += $sum;
                }
            }
            
            return $totalBytes / (1024 * 1024 * 1024 * 1024); // Bytes to Terabytes
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Helper to retrieve Custom Field value for a service.
     *
     * @param int $serviceId
     * @param string $fieldName
     * @return string|null
     */
    protected function getCustomFieldValue($serviceId, $fieldName)
    {
        try {
            $packageId = Capsule::table('tblhosting')->where('id', $serviceId)->value('packageid');
            if (!$packageId) {
                return null;
            }

            $customField = Capsule::table('tblcustomfields')
                ->where('relid', $packageId)
                ->where('type', 'product')
                ->where(function($q) use ($fieldName) {
                    $q->where('fieldname', $fieldName)
                      ->orWhere('fieldname', 'like', $fieldName . '|%');
                })
                ->first();

            if ($customField) {
                return Capsule::table('tblcustomfieldsvalues')
                    ->where('relid', $serviceId)
                    ->where('fieldid', $customField->id)
                    ->value('value');
            }
        } catch (\Exception $e) {
            // Ignore
        }
        return null;
    }
}
