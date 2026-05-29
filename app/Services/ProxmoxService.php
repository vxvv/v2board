<?php

namespace App\Services;

use App\Models\HypervisorNode;
use Illuminate\Support\Facades\Log;

class ProxmoxService
{
    private string $host;
    private string $tokenId;
    private string $tokenSecret;
    private string $defaultNode;

    public function __construct(HypervisorNode $node)
    {
        $this->host = rtrim($node->host, '/');
        $this->tokenId = $node->token_id;
        $this->tokenSecret = $node->token_secret;
        $this->defaultNode = $this->resolveNodeName();
    }

    public static function fromNode(HypervisorNode $node): self
    {
        return new self($node);
    }

    // --- VM Lifecycle ---

    public function createVm(array $params): array
    {
        $vmid = $params['vmid'];
        $data = [
            'vmid' => $vmid,
            'name' => $params['name'] ?? "vm-{$vmid}",
            'cores' => $params['cpu'] ?? 1,
            'memory' => $params['ram'] ?? 1024,
            'ostype' => $params['ostype'] ?? 'l26',
            'scsihw' => 'virtio-scsi-single',
            'scsi0' => ($params['storage'] ?? 'local-lvm') . ':' . ($params['disk'] ?? 10),
            'net0' => 'virtio,bridge=' . ($params['bridge'] ?? 'vmbr1'),
            'cpu' => 'host',
            'numa' => 0,
            'boot' => 'order=scsi0;ide2',
            'agent' => 'enabled=1',
        ];

        if (!empty($params['iso'])) {
            $data['ide2'] = $params['iso'] . ',media=cdrom';
        }

        if (!empty($params['bandwidth'])) {
            $data['net0'] .= ',rate=' . $params['bandwidth'];
        }

        return $this->post("/nodes/{$this->defaultNode}/qemu", $data);
    }

    public function startVm(int $vmid): array
    {
        return $this->post("/nodes/{$this->defaultNode}/qemu/{$vmid}/status/start");
    }

    public function stopVm(int $vmid): array
    {
        return $this->post("/nodes/{$this->defaultNode}/qemu/{$vmid}/status/stop");
    }

    public function shutdownVm(int $vmid): array
    {
        return $this->post("/nodes/{$this->defaultNode}/qemu/{$vmid}/status/shutdown");
    }

    public function rebootVm(int $vmid): array
    {
        return $this->post("/nodes/{$this->defaultNode}/qemu/{$vmid}/status/reboot");
    }

    public function resetVm(int $vmid): array
    {
        return $this->post("/nodes/{$this->defaultNode}/qemu/{$vmid}/status/reset");
    }

    public function destroyVm(int $vmid): array
    {
        // Force stop first, ignore errors
        try {
            $this->stopVm($vmid);
            sleep(3);
        } catch (\Exception $e) {
            // VM may already be stopped
        }
        return $this->delete("/nodes/{$this->defaultNode}/qemu/{$vmid}", ['purge' => 1, 'destroy-unreferenced-disks' => 1]);
    }

    public function getVmStatus(int $vmid): array
    {
        return $this->get("/nodes/{$this->defaultNode}/qemu/{$vmid}/status/current");
    }

    public function getVmConfig(int $vmid): array
    {
        return $this->get("/nodes/{$this->defaultNode}/qemu/{$vmid}/config");
    }

    // --- Rebuild / DD ---

    public function mountIso(int $vmid, string $iso): array
    {
        return $this->put("/nodes/{$this->defaultNode}/qemu/{$vmid}/config", [
            'ide2' => "{$iso},media=cdrom",
            'boot' => 'order=ide2;scsi0',
        ]);
    }

    public function unmountIso(int $vmid): array
    {
        return $this->put("/nodes/{$this->defaultNode}/qemu/{$vmid}/config", [
            'ide2' => 'none,media=cdrom',
            'boot' => 'order=scsi0',
        ]);
    }

    public function resizeDisk(int $vmid, string $disk, string $size): array
    {
        return $this->put("/nodes/{$this->defaultNode}/qemu/{$vmid}/resize", [
            'disk' => $disk,
            'size' => $size,
        ]);
    }

    // --- VNC Console ---

    public function createVncProxy(int $vmid): array
    {
        return $this->post("/nodes/{$this->defaultNode}/qemu/{$vmid}/vncproxy", [
            'websocket' => 1,
        ]);
    }

    public function getVncWebsocket(int $vmid, int $port, string $vncticket): string
    {
        return $this->host . "/nodes/{$this->defaultNode}/qemu/{$vmid}/vncwebsocket?port={$port}&vncticket=" . urlencode($vncticket);
    }

    // --- Node Info ---

    public function getNodeStatus(): array
    {
        return $this->get("/nodes/{$this->defaultNode}/status");
    }

    public function getNodeStorage(string $storage = 'local-lvm'): array
    {
        return $this->get("/nodes/{$this->defaultNode}/storage/{$storage}/status");
    }

    public function listVms(): array
    {
        return $this->get("/nodes/{$this->defaultNode}/qemu");
    }

    public function getNextVmid(): int
    {
        $result = $this->get("/cluster/nextid");
        return (int)$result;
    }

    public function listIsos(string $storage = 'local'): array
    {
        $result = $this->get("/nodes/{$this->defaultNode}/storage/{$storage}/content", ['content' => 'iso']);
        return $result;
    }

    // --- Network / Firewall ---

    public function getVmNetworkInterfaces(int $vmid): array
    {
        try {
            return $this->get("/nodes/{$this->defaultNode}/qemu/{$vmid}/agent/network-get-interfaces");
        } catch (\Exception $e) {
            return [];
        }
    }

    // --- HTTP Client ---

    private function resolveNodeName(): string
    {
        try {
            $nodes = $this->get('/nodes');
            if (is_array($nodes) && !empty($nodes)) {
                return $nodes[0]['node'] ?? 'pve';
            }
        } catch (\Exception $e) {
            Log::warning('ProxmoxService: failed to resolve node name, using default "pve": ' . $e->getMessage());
        }
        return 'pve';
    }

    private function get(string $path, array $params = []): mixed
    {
        return $this->request('GET', $path, $params);
    }

    private function post(string $path, array $data = []): mixed
    {
        return $this->request('POST', $path, $data);
    }

    private function put(string $path, array $data = []): mixed
    {
        return $this->request('PUT', $path, $data);
    }

    private function delete(string $path, array $data = []): mixed
    {
        return $this->request('DELETE', $path, $data);
    }

    private function request(string $method, string $path, array $data = []): mixed
    {
        $url = $this->host . '/api2/json' . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Authorization: PVEAPIToken={$this->tokenId}={$this->tokenSecret}",
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        if ($method === 'GET') {
            if (!empty($data)) {
                $url .= '?' . http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Proxmox API error: {$error}");
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            $errorMsg = $decoded['errors'] ?? $decoded['data'] ?? $response;
            if (is_array($errorMsg)) {
                $errorMsg = json_encode($errorMsg);
            }
            throw new \RuntimeException("Proxmox API HTTP {$httpCode}: {$errorMsg}");
        }

        return $decoded['data'] ?? $decoded;
    }
}
