<?php

namespace m8rge\OCF;

/**
 * Manage openvz containers ips
 *
 * Works like ocf:heartbeat:IPaddr but manage openvz containers instead nic
 */
class Ipaddr extends OCF
{
    protected $version = '1.2.1';

    /**
     * Container ID
     *
     * CTID is the numeric ID of the given openvz container. Also can be name of container
     * @var int
     */
    public $ctid;

    /**
     * Assigned IP address
     *
     * Address can optionally have a netmask specified in the CIDR notation (e.g. 10.1.2.3/25)
     * @var string
     */
    public $ip;

    /**
     * Default gateway
     *
     * @var string
     */
    public $gateway = '';

    /**
     * Activity state file
     *
     * File existence will show success activity of this resource
     * @var string
     */
    public $stateFile = '';

    /**
     * @var string[] Required console utilities
     */
    protected $requiredUtilities = array('vzlist', 'vzctl --help');

    protected $ipv4Regex = '/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])(\/(\d|[1-2]\d|3[0-2]))?$/';
    protected $ipv6Regex = '/^((?=.*::)(?!.*::.+::)(::)?([\dA-F]{1,4}:(:|\b)|){5}|([\dA-F]{1,4}:){6})((([\dA-F]{1,4}((?!\3)::|:\b|$))|(?!\2\3)){2}|(((2[0-4]|1\d|[1-9])?\d|25[0-5])\.?\b){4})$/i';

    public function validateProperties()
    {
        $res =  parent::validateProperties();

        if ($res) {
            if (empty($this->ctid)) {
                return false;
            }
            if (!empty($this->ip) && !preg_match($this->ipv4Regex, $this->ip) && !preg_match($this->ipv6Regex, $this->ip)) {
                if ($this->ravenClient) {
                    $this->ravenClient->extra_context(
                        array('ip' => $this->ip)
                    );
                    $this->ravenClient->captureException(new \Exception("passed param is incorrect"));
                }
                return false;
            }
            if (!empty($this->gateway) && !preg_match($this->ipv4Regex, $this->gateway) && !preg_match($this->ipv6Regex, $this->gateway)) {
                if ($this->ravenClient) {
                    $this->ravenClient->extra_context(
                        array('gateway' => $this->gateway)
                    );
                    $this->ravenClient->captureException(new \Exception("passed param is incorrect"));
                }
                return false;
            }
        }

        return true;
    }

    protected function touchActivity($untouch = false)
    {
        if (!empty($this->stateFile)) {
            if (!$untouch) {
                touch($this->stateFile);
            } else {
                if (file_exists($this->stateFile)) {
                    unlink($this->stateFile);
                }
            }
        }
    }

    /**
     * @timeout 10
     * @return int
     */
    public function actionStart()
    {
        $command = "vzctl set ".escapeshellarg($this->ctid)." --ipadd ".escapeshellarg($this->ip);
        $exitCode = $this->execWithLogging($command, array(0, 31));
        if ($exitCode == 0 && !empty($this->gateway)) {
            $command = "vzctl exec ".escapeshellarg($this->ctid)." route add default gw ".escapeshellarg($this->gateway);
            $expectedExitCodes = array(0, 7); // 7 exit code = already done
            $exitCode = $this->execWithLogging($command, $expectedExitCodes);
            if (!in_array($exitCode, $expectedExitCodes)) {
                $this->removeIp();
            }
        }

        $this->touchActivity($exitCode);

        return $exitCode ? self::OCF_ERR_GENERIC : self::OCF_SUCCESS;
    }

    protected function removeIp()
    {
        $command = "vzctl set ".escapeshellarg($this->ctid)." --ipdel ".escapeshellarg($this->ip);
        $expectedExitCodes = array(0, 31); // 31 exit code from vzctl means CT is down. Works for us.
        return $this->execWithLogging($command, $expectedExitCodes);
    }

    /**
     * @timeout 10
     * @return int
     */
    public function actionStop()
    {
        $expectedExitCodes = array(0, 31); // 31 exit code from vzctl means CT is down. Works for us.

        $exitCode = $this->removeIp();
        if ($exitCode == 0 && !empty($this->gateway)) {
            $command = "vzctl exec ".escapeshellarg($this->ctid)." route del default gw ".escapeshellarg($this->gateway);
            $exitCode = $this->execWithLogging($command, array(0, 7, 8));
        }

        $this->touchActivity(in_array($exitCode, $expectedExitCodes));

        return !in_array($exitCode, $expectedExitCodes) ? self::OCF_ERR_GENERIC : self::OCF_SUCCESS;
    }

    /**
     * @timeout 10
     * @interval 10
     * @return int
     */
    public function actionMonitor()
    {
        $command = "vzctl exec ".escapeshellarg($this->ctid)." ip a | grep ".escapeshellarg($this->ip);
        $exitCode = $this->execWithLogging($command, array(0, 1));

        $this->touchActivity($exitCode);

        return $exitCode ? self::OCF_NOT_RUNNING : self::OCF_SUCCESS;
    }
}