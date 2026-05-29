<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVpsTables extends Migration
{
    public function up()
    {
        // Hypervisor nodes (PVE hosts)
        Schema::create('v2_hypervisor_node', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 255);
            $table->string('host', 255)->comment('PVE host URL e.g. https://192.168.1.100:8006');
            $table->string('token_id', 255)->comment('PVE API token ID e.g. root@pam!v2board');
            $table->text('token_secret')->comment('PVE API token secret');
            $table->string('storage', 64)->default('local-lvm')->comment('Storage pool for VM disks');
            $table->string('network_bridge', 32)->default('vmbr1')->comment('Bridge for NAT network');
            $table->string('nat_interface', 32)->default('vmbr0')->comment('Public-facing interface for NAT');
            $table->string('nat_gateway', 45)->nullable()->comment('NAT gateway IP');
            $table->string('nat_subnet', 45)->default('10.0.0.0/24')->comment('Internal NAT subnet');
            $table->string('dhcp_range_start', 45)->default('10.0.0.100')->comment('DHCP range start');
            $table->string('dhcp_range_end', 45)->default('10.0.0.250')->comment('DHCP range end');
            $table->integer('total_cpu')->default(0)->comment('Total CPU cores');
            $table->integer('total_ram')->default(0)->comment('Total RAM in MB');
            $table->integer('total_disk')->default(0)->comment('Total disk in GB');
            $table->integer('allocated_cpu')->default(0);
            $table->integer('allocated_ram')->default(0);
            $table->integer('allocated_disk')->default(0);
            $table->tinyInteger('status')->default(1)->comment('0=disabled 1=active');
            $table->integer('sort')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        // VM OS templates / ISOs
        Schema::create('v2_vm_template', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 255)->comment('Display name e.g. Ubuntu 22.04');
            $table->string('type', 16)->default('iso')->comment('iso or template');
            $table->string('os_type', 32)->default('l26')->comment('PVE OS type: l26, win10, etc.');
            $table->string('file', 512)->comment('ISO filename or template path on PVE storage');
            $table->string('storage', 64)->default('local')->comment('Storage where ISO/template is stored');
            $table->integer('min_cpu')->default(1);
            $table->integer('min_ram')->default(512)->comment('Minimum RAM in MB');
            $table->integer('min_disk')->default(10)->comment('Minimum disk in GB');
            $table->tinyInteger('show')->default(1);
            $table->integer('sort')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        // Virtual machines
        Schema::create('v2_virtual_machine', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->index();
            $table->integer('plan_id');
            $table->integer('order_id')->nullable();
            $table->integer('node_id')->comment('Hypervisor node ID');
            $table->integer('template_id')->nullable();
            $table->integer('vmid')->comment('PVE VMID');
            $table->string('name', 255);
            $table->string('hostname', 255)->nullable();
            $table->integer('cpu')->comment('CPU cores');
            $table->integer('ram')->comment('RAM in MB');
            $table->integer('disk')->comment('Disk in GB');
            $table->integer('bandwidth')->default(0)->comment('Bandwidth limit in Mbps, 0=unlimited');
            $table->string('internal_ip', 45)->nullable()->comment('DHCP-assigned internal IP');
            $table->string('status', 32)->default('creating')->comment('creating, running, stopped, suspended, error');
            $table->string('os_name', 255)->nullable()->comment('Installed OS name');
            $table->integer('nat_port_start')->nullable()->comment('NAT port range start');
            $table->integer('nat_port_end')->nullable()->comment('NAT port range end');
            $table->integer('vnc_port')->nullable();
            $table->text('password')->nullable()->comment('Encrypted root password');
            $table->bigInteger('traffic_up')->default(0)->comment('Upload traffic in bytes');
            $table->bigInteger('traffic_down')->default(0)->comment('Download traffic in bytes');
            $table->bigInteger('traffic_limit')->default(0)->comment('Traffic limit in bytes, 0=unlimited');
            $table->integer('expired_at')->nullable();
            $table->integer('suspended_at')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
            $table->index(['node_id', 'vmid']);
        });

        // NAT port allocation pool
        Schema::create('v2_nat_port_pool', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('node_id');
            $table->integer('port_start');
            $table->integer('port_end');
            $table->integer('vm_id')->nullable()->comment('NULL = available');
            $table->integer('created_at');
            $table->integer('updated_at');
            $table->index(['node_id', 'vm_id']);
        });

        // Extend v2_plan with VPS fields
        Schema::table('v2_plan', function (Blueprint $table) {
            $table->integer('cpu_cores')->default(0)->after('speed_limit')->comment('CPU cores for VPS');
            $table->integer('ram_mb')->default(0)->after('cpu_cores')->comment('RAM in MB for VPS');
            $table->integer('disk_gb')->default(0)->after('ram_mb')->comment('Disk in GB for VPS');
            $table->integer('bandwidth_mbps')->default(0)->after('disk_gb')->comment('Bandwidth in Mbps');
            $table->integer('nat_ports')->default(20)->after('bandwidth_mbps')->comment('Number of NAT port mappings');
            $table->bigInteger('traffic_limit')->default(0)->after('nat_ports')->comment('Traffic limit in GB, 0=unlimited');
            $table->tinyInteger('plan_type')->default(0)->after('traffic_limit')->comment('0=proxy 1=vps');
        });
    }

    public function down()
    {
        Schema::dropIfExists('v2_nat_port_pool');
        Schema::dropIfExists('v2_virtual_machine');
        Schema::dropIfExists('v2_vm_template');
        Schema::dropIfExists('v2_hypervisor_node');

        Schema::table('v2_plan', function (Blueprint $table) {
            $table->dropColumn(['cpu_cores', 'ram_mb', 'disk_gb', 'bandwidth_mbps', 'nat_ports', 'traffic_limit', 'plan_type']);
        });
    }
}
