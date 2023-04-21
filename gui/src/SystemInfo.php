<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 * Copyright (C) 2010-2019 by Laurent Declercq <l.declercq@nuxwin.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/** @noinspection
 * PhpUnusedParameterInspection
 * PhpUnhandledExceptionInspection
 * PhpDocMissingThrowsInspection
 */

declare(strict_types=1);

namespace iMSCP;

/**
 * Class SystemInfo
 * @package iMSCP
 */
class SystemInfo
{
    /**
     * CPU info
     *
     * @var array
     */
    public $cpu;
    /**
     * File system info
     *
     * @var array
     */
    public $filesystem;
    /**
     * Kernel version
     *
     * @var string
     */
    public $kernel;
    /**
     * System load info
     *
     * @var array
     */
    public $load;
    /**
     * RAM info
     *
     * @var array
     */
    public $ram;
    /**
     *
     * @var array Swap info
     */
    public $swap;
    /**
     * System uptime
     *
     * @var string
     */
    public $uptime;
    /**
     * Operating system name where PHP is run
     *
     * @var string
     */
    protected $os;
    /**
     * Error message
     *
     * @var string
     */
    protected $_error = '';

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->os = php_uname('s');
        $this->cpu = $this->_getCPUInfo();
        $this->filesystem = $this->_getFileSystemInfo();
        $this->kernel = $this->_getKernelInfo();
        $this->load = $this->_getLoadInfo();
        $this->ram = $this->_getRAMInfo();
        $this->swap = $this->_getSwapInfo();
        $this->uptime = $this->_getUptime();
    }

    /**
     * Reads /proc/cpuinfo and parses its content
     *
     * @return array Cpu Information
     */
    private function _getCPUInfo()
    {
        $cpu = [
            'model'    => tr('N/A'),
            'cpus'     => tr('N/A'),
            'cpuspeed' => tr('N/A'),
            'cache'    => tr('N/A'),
            'bogomips' => tr('N/A')
        ];

        if ($this->os == 'FreeBSD'
            || $this->os == 'OpenBSD'
            || $this->os == 'NetBSD'
        ) {
            $tmp = [];
            $pattern = [
                'FreeBSD' => '/CPU: (.*) \((.*)-MHz (.*)\)/',
                'OpenBSD' => '/^cpu(.*) (.*) MHz/',
                'NetBSD'  => '/^cpu(.*)\, (.*) MHz/'
            ];

            if ($cpu['model'] = $this->sysctl('hw.model')) {
                $cpu["cpus"] = $this->sysctl('hw.ncpu');
                // Read dmesg bot log on reboot
                $dmesg = $this->read('/var/run/dmesg.boot');

                if (empty($this->_error)) {
                    $dmesgArr = explode('rebooting', $dmesg);
                    $dmesgInfo = explode("\n", $dmesgArr[count($dmesgArr) - 1]);

                    foreach ($dmesgInfo as $di) {
                        if (preg_match($pattern[$this->os], $di, $tmp)) {
                            $cpu['cpuspeed'] = round($tmp[2]);
                            break;
                        }
                    }
                }
            }

            return $cpu;
        }

        $cpuRaw = $this->read('/proc/cpuinfo');
        if (empty($this->_error)) {
            // parse line for line
            $cpu_info = explode("\n", $cpuRaw);
            // initialize Values:
            $cpu['cpus'] = 0;
            $cpu['bogomips'] = 0;

            foreach ($cpu_info as $ci) {
                $line = preg_split('/\s+:\s+/', trim($ci));

                // Every architecture has its own scheme, it's not granted
                // that this list is complete. If there are any values
                // missing, let us know about them. They will be added in a
                // upcoming release.
                switch ($line[0]) {
                    case 'model name':
                        $cpu['model'] = $line[1];
                        break;
                    case 'cpu': // PPC
                        $cpu['model'] = $line[1];
                        break;
                    case 'revision': // PPC
                        $cpu['model'] .= ' ( rev: ' . $line[1] . ')';
                        break;
                    case 'cpu model': // Alpha 2.2.x
                        $cpu['model'] .= ' (' . $line[1] . ')';
                        break;
                    case 'system type': // Alpha 2.2.x
                        $cpu['model'] .= ', ' . $line[1] . ' ';
                        break;
                    case 'platform string': // Alpha 2.2.x
                        $cpu['model'] .= ' (' . $line[1] . ')';
                        break;
                    case 'processor':
                        $cpu['cpus'] += 1;
                        break;
                    case 'ncpus probed': // Linux sparc64 & sparc32
                        $cpu["cpus"] = $line[1];
                        break;
                    case 'cpu MHz':
                        $cpu["cpuspeed"] = sprintf("%.2f", $line[1]);
                        break;
                    case 'clock': // PPC
                        $cpu['cpuspeed'] = sprintf('%.2f', $line[1]);
                        break;
                    case 'Cpu0ClkTck': // Linux sparc64
                        $cpu['cpuspeed'] = sprintf(
                            '%.2f', hexdec($line[1]) / 1000000
                        );
                        break;
                    case 'cache size':
                        $cpu['cache'] = $line[1];
                        break;
                    case 'L2 cache': // PPC
                        $cpu['cache'] = $line[1];
                        break;
                    case 'bogomips':
                        $cpu["bogomips"] += $line[1];
                        break;
                    case 'BogoMIPS': // Alpha 2.2.x
                        $cpu['bogomips'] += $line[1];
                        break;
                    case 'BogoMips': // Sparc
                        $cpu['bogomips'] += $line[1];
                        break;
                    case 'Cpu0Bogo': // Linux sparc64 & sparc32
                        $cpu['bogomips'] += $line[1];
                        break;
                }
            }

            // sparc64 specific implementation
            // Originally made by Sven Blumenstein <bazik@gentoo.org> in
            // 2004 Modified by Tom Weustink <freshy98@gmx.net> in 2004
            $sparclist = [
                'SUNW,UltraSPARC@0,0',
                'SUNW,UltraSPARC-II@0,0',
                'SUNW,UltraSPARC@1c,0',
                'SUNW,UltraSPARC-IIi@1c,0',
                'SUNW,UltraSPARC-II@1c,0',
                'SUNW,UltraSPARC-IIe@0,0'
            ];

            foreach ($sparclist as $sparc) {
                $raw = $this->read('/proc/openprom/' . $sparc . '/ecache-size');
                if (empty($this->_error) && !empty($raw)) {
                    $cpu['cache'] = base_convert($raw, 16, 10) / 1024 . ' KB';
                }
            }

            // XScale specific implementation
            if ($cpu['cpus'] == 0) {
                foreach ($cpu_info as $ci) {
                    $line = preg_split('/\s+:\s+/', trim($ci));

                    switch ($line[0]) {
                        case 'Processor':
                            $cpu['cpus'] += 1;
                            $cpu['model'] = $line[1];
                            break;
                        // Wrong description for CPU speed; no bogoMIPS
                        // available
                        case 'BogoMIPS':
                            $cpu['cpuspeed'] = $line[1];
                            break;
                        case 'I size':
                            $cpu['cache'] = $line[1];
                            break;
                        case 'D size':
                            $cpu['cache'] += $line[1];
                            break;
                    }
                }

                $cpu['cache'] = $cpu['cache'] / 1024 . ' KB';
            }
        }

        return $cpu;
    }

    /**
     * Execute sysctl on *BDS to receive system information
     *
     * @param string $args Arguments to call sysctl
     * @return string Unformated sysctl output
     */
    protected function sysctl($args)
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'a']
        ];
        $stdout = '';
        $pipes = []; // satisfy warning
        $proc = proc_open('sysctl -n ' . $args, $descriptorSpec, $pipes);

        if (is_resource($proc)) {
            // Read data from stream (Pipe 1)
            $stdout = stream_get_contents($pipes[1]);
            // Close pipe and stream
            fclose($pipes[1]);
            proc_close($proc);
        }

        return $stdout;
    }

    /**
     * Gets the content of a file if successful or and error otherwise.
     *
     * @param string $filename Path to file
     * @return bool|string
     */
    protected function read($filename)
    {
        if (is_readable($filename)) {
            $this->_error = '';
            return file_get_contents($filename);
        }

        $this->_error = sprintf(
            "File %s doesn't exist or cannot be reached!", $filename
        );

        return false;
    }

    /**
     * Gets and parses the information of mounted filesystem
     *
     * @return array File system information
     */
    private function _getFileSystemInfo()
    {
        $filesystem = [];
        $descriptorSpec = [
            ['pipe', 'r'], ['pipe', 'w'], ['pipe', 'a']
        ];

        /* Read output of df command from stdout
         * Args:
         *  T: Show File System type
         *  P: Show in POSIX format
         */
        $pipes = []; // satisfy warning
        $proc = proc_open('df -TP', $descriptorSpec, $pipes);

        if (is_resource($proc)) {
            // Read data from stream (Pipe 1)
            $fileSystemRaw = stream_get_contents($pipes[1]);

            // Close pipe and stream
            fclose($pipes[1]);
            proc_close($proc);

            $fs_info = explode("\n", $fileSystemRaw);
            // First line only contains Legend
            array_shift($fs_info);

            $i = 0;
            foreach ($fs_info as $fs) {
                if (empty($fs))
                    continue;

                $line = preg_split('/\s+/', trim($fs));
                $i++;
                $filesystem[$i]['mount'] = $line[0];
                $filesystem[$i]['fstype'] = $line[1];
                $filesystem[$i]['disk'] = $line[6];
                // Remove % from the end of the string
                $filesystem[$i]['percent'] = substr($line[5], 0, -1);
                $filesystem[$i]['used'] = $line[3];
                $filesystem[$i]['size'] = $line[2];
                $filesystem[$i]['free'] = $line[4];
            }
        }

        return $filesystem;
    }

    /**
     * Reads /proc/version and parses its content
     *
     * @return string Translated Kernel information
     */
    private function _getKernelInfo()
    {
        $kernel = tr('N/A');

        if ($this->os == 'FreeBSD'
            || $this->os == 'OpenBSD'
            || $this->os == 'NetBSD'
        ) {
            if ($kernelRaw = $this->sysctl('kern.version')) {
                $kernel_arr = explode(':', $kernelRaw);
                $kernel = $kernel_arr[0] . $kernel_arr[1] . ':' . $kernel_arr[2];
            }

            return $kernel;
        }

        $kernelRaw = $this->read('/proc/version');
        if (empty($this->_error)) {
            if (preg_match('/version (.*?) /', $kernelRaw, $kernel_info)) {
                $kernel = $kernel_info[1];
                if (strpos($kernelRaw, 'SMP') !== false) {
                    $kernel .= ' (SMP)';
                }
            }
        }

        return $kernel;
    }

    /**
     * Reads /proc/loadavg and parses its content into Load 1 min, Load 5 Min
     * and Load 15 min
     *
     * @return array Load average
     */
    private function _getLoadInfo()
    {
        $load = [tr('N/A'), tr('N/A'), tr('N/A')];

        if ($this->os == 'FreeBSD'
            || $this->os == 'OpenBSD'
            || $this->os == 'NetBSD'
        ) {
            if ($loadRaw = $this->sysctl('vm.loadavg')) {
                $loadRaw = preg_replace('/{\s/', '', $loadRaw);
                $loadRaw = preg_replace('/\s}/', '', $loadRaw);
                $load = explode(' ', $loadRaw);
            }

            return $load;
        }

        $loadRaw = $this->read('/proc/loadavg');
        if (empty($this->_error)) {
            // $load[0] - Load 1 Min
            // $load[1] - Load 5 Min
            // $load[2] - Load 15 Min
            // $load[3] - <running processes>/<total processes> <last PID>
            $load = preg_split('/\s/', $loadRaw, 4);
            // Only load values are needed
            unset($load[3]);
        }

        return $load;
    }

    /**
     * Reads /proc/meminfo and parses its content into Total, Used and Free Ram
     *
     * @return array Memory information
     */
    private function _getRAMInfo()
    {
        $ram = [
            'total' => 0,
            'free'  => 0,
            'used'  => 0
        ];

        if ($this->os == 'FreeBSD'
            || $this->os == 'OpenBSD'
            || $this->os == 'NetBSD'
        ) {
            if ($ramRaw = $this->sysctl('hw.physmem')) {
                $descriptorSpec = [
                    ['pipe', 'r'], ['pipe', 'w'], ['pipe', 'a']
                ];

                $pipes = [];
                $proc = proc_open('vmstat', $descriptorSpec, $pipes);

                if (is_resource($proc)) {
                    // Read data from stream (Pipe 1)
                    $raw = stream_get_contents($pipes[1]);

                    // Close pipe and stream
                    fclose($pipes[1]);
                    proc_close($proc);

                    // parse line for line
                    $ramInfo = explode("\n", $raw);

                    // First line only contains Legend
                    array_shift($ramInfo);

                    $line = preg_split('/\s+/', $ramInfo[0], 19);
                    $ram['free'] = $line[5];
                }

                $ram['total'] = $ramRaw / 1024;
                $ram['used'] = $ram['total'] - $ram['free'];
            }

            return $ram;
        }

        $ramRaw = $this->read('/proc/meminfo');

        if (empty($this->_error)) {
            foreach (explode("\n", $ramRaw) as $ri) {
                list($type, $kb) = preg_split(
                    '/\s+/', $line = str_replace(':', '', $ri), 3
                );

                switch ($type) {
                    case 'MemTotal':
                        $ram['total'] = (int)$kb;
                        break;
                    case 'MemFree':
                        $ram['free'] = (int)$kb;
                        break;
                    case 'Buffers':
                        $ram['buffers'] = (int)$kb;
                        break;
                    case 'Cached':
                        $ram['cached'] = (int)$kb;
                        break;
                }
            }

            # TODO report fixes below for freeBSD (see #812)
            $ram['used'] = ($ram['total'] - $ram['free']) -
                ($ram['buffers'] + $ram['cached']);
            $ram['free'] = $ram['total'] - $ram['used'];
        }

        return $ram;
    }

    /**
     * Reads /proc/swaps and parses its content into Total, Used and Free
     * Swaps
     *
     * @return array Swap information
     */
    private function _getSwapInfo()
    {
        $swap = [
            'total' => 0,
            'free'  => 0,
            'used'  => 0
        ];

        if ($this->os == 'FreeBSD'
            || $this->os == 'OpenBSD'
            || $this->os == 'NetBSD'
        ) {
            $descriptorSpec = [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'a']
            ];

            if ($this->os == 'OpenBSD' || $this->os == 'NetBSD') {
                $args = '-l -k';
            } else {
                $args = '-k';
            }

            $pipes = []; // satisfy warning
            $proc = proc_open('swapctl ' . $args, $descriptorSpec, $pipes);

            if (is_resource($proc)) {
                // Read data from stream (Pipe 1)
                $raw = stream_get_contents($pipes[1]);
                // Close pipe and stream
                fclose($pipes[1]);
                proc_close($proc);
                // parse line for line
                $swapInfo = explode("\n", $raw);

                foreach ($swapInfo as $si) {
                    if (!empty($si)) {
                        $line = preg_split('/\s+/', trim($si), 6);
                        if ($line[0] != 'Total') {
                            $swap['total'] += $line[1];
                            $swap['used'] += $line[2];
                            $swap['free'] += $line[3];
                        }
                    }
                }

                $line = preg_split('/\s+/', $swapInfo[0], 19);
                $ram['free'] = $line[5];
            }

            return $swap;
        }

        $stdout = $this->read('/proc/swaps');
        if (empty($this->_error)) {
            // parse line for line
            $swapInfo = explode("\n", $stdout);

            // First line only contains Legend
            array_shift($swapInfo);

            foreach ($swapInfo as $si) {
                if (!empty($si)) {
                    $line = preg_split('/\s+/', trim($si));
                    $swap['total'] += $line[2];
                    $swap['used'] += $line[3];
                    $swap['free'] = $swap['total'] - $swap['used'];
                }
            }
        }

        return $swap;
    }

    /**
     *
     * /**
     * Reads /proc/uptime, parses its content and makes it human readable in
     * the format: # [[Day[s]] # Hour[s]] # Minute[s].
     *
     * @return string Translated Uptime information
     */
    private function _getUptime()
    {
        $uptime = 0;

        if ($this->os == 'FreeBSD'
            || $this->os == 'OpenBSD'
            || $this->os == 'NetBSD'
        ) {
            if ($stdout = $this->sysctl("kern.boottime")) {
                switch ($this->os) {
                    case 'FreeBSD':
                        $uptimeArr = explode(' ', $stdout);
                        $uptimeTmp = preg_replace('/{\s/', '', $uptimeArr[3]);
                        $uptime = time() - $uptimeTmp;
                        break;
                    case 'OpenBSD':
                    case 'NetBSD':
                        $uptime = time() - $stdout;
                        break;
                }
            }
        } else {
            $stdout = $this->read('/proc/uptime');
            if (empty($this->_error)) {
                $uptime = explode(' ', $stdout);

                // $uptime[0] - Total System Uptime
                // $uptime[1] - System Idle Time
                $uptime = trim($uptime[0]);
            }
        }

        $upMins = $uptime / 60;
        $upHours = $upMins / 60;
        $upDays = floor($upHours / 24);
        $upHours = floor($upHours - ($upDays * 24));
        $upMins = floor($upMins - ($upHours * 60) - ($upDays * 24 * 60));

        $uptimeStr = '';

        if ($upDays == 1) {
            $uptimeStr .= $upDays . ' ' . tr('Day') . ' ';
        } else if ($upDays > 1) {
            $uptimeStr .= $upDays . ' ' . tr('Days') . ' ';
        }

        if ($upHours == 1) {
            $uptimeStr .= ' ' . $upHours . ' ' . tr('Hour') . ' ';
        } else if ($upHours > 1) {
            $uptimeStr .= ' ' . $upHours . ' ' . tr('Hours') . ' ';
        }

        if ($upMins == 1) {
            $uptimeStr .= ' ' . $upMins . ' ' . tr('Minute');
        } else if ($upMins > 1) {
            $uptimeStr .= ' ' . $upMins . ' ' . tr('Minutes');
        }

        return $uptimeStr;
    }

    /**
     * Return info about partition to which the given file belong
     *
     * @param string $file Absolute path to either a file or directory
     * @return array
     */
    public static function getFilePartitionInfo($file)
    {
        $filePartitionInfo = [];
        $descriptorSpec = [
            ['pipe', 'r'], ['pipe', 'w'], ['pipe', 'a']
        ];

        $pipes = []; // satisfy warning
        $proc = proc_open('df -TP ' . escapeshellarg(
                $file), $descriptorSpec, $pipes
        );

        if (is_resource($proc)) {
            // Read data from stream (Pipe 1)
            $stdout = stream_get_contents($pipes[1]);

            // Close pipe and stream
            fclose($pipes[1]);
            proc_close($proc);

            $filePartitionInfo = explode("\n", $stdout);

            // Remove legend
            array_shift($filePartitionInfo);

            $filePartitionInfo = array_combine(
                ['mount', 'fstype', 'size', 'used', 'free', 'percent', 'disk'],
                preg_split('/\s+/', trim($filePartitionInfo[0]))
            );

            $filePartitionInfo['percent'] = str_replace(
                '%', '', $filePartitionInfo['percent']
            );
        }

        return $filePartitionInfo;
    }

    /**
     * Returns the latest error
     *
     * @return string Error
     */
    public function getError()
    {
        return $this->_error;
    }
}
