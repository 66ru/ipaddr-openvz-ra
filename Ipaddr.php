<?php

namespace m8rge\OCF;

/**
 * Manage openvz containers ips
 *
 * Works like ocf:heartbeat:IPaddr but manage openvz containers instead nic
 */
class Ipaddr extends OCF
{
    protected $version = '0.9';

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
     * @var string[] Required console utilities
     */
    protected $requiredUtilities = array('vzlist', 'vzctl');

    protected $ipv4Regex = '/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])(\/(\d|[1-2]\d|3[0-2]))?$/';
    protected $ipv6Regex = '/^((?=.*::)(?!.*::.+::)(::)?([\dA-F]{1,4}:(:|\b)|){5}|([\dA-F]{1,4}:){6})((([\dA-F]{1,4}((?!\3)::|:\b|$))|(?!\2\3)){2}|(((2[0-4]|1\d|[1-9])?\d|25[0-5])\.?\b){4})$/i';

    public function validateProperties()
    {
        $res =  parent::validateProperties();

        if ($res) {
            exec('vzlist ' . escapeshellarg($this->ctid), $output, $exitCode);
            if (!$exitCode) {
                return false;
            }
            if (!empty($this->ip) && !preg_match($this->ipv4Regex, $this->ip) && !preg_match($this->ipv6Regex, $this->ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @timeout 10
     * @return int
     */
    public function actionStart()
    {
        $command = "vzctl set ".escapeshellarg($this->ctid)." --ipadd ".escapeshellarg($this->ip);
        $exitCode = $this->execWithLogging($command);

        return $exitCode ? self::OCF_ERR_GENERIC : self::OCF_SUCCESS;
    }

    /**
     * @timeout 10
     * @return int
     */
    public function actionStop()
    {
        $command = "vzctl set ".escapeshellarg($this->ctid)." --ipdel ".escapeshellarg($this->ip);
        $exitCode = $this->execWithLogging($command);

        return $exitCode ? self::OCF_ERR_GENERIC : self::OCF_SUCCESS;
    }

    /**
     * @timeout 10
     * @interval 10
     * @return int
     */
    public function actionMonitor()
    {
        $command = "vzctl exec ".escapeshellarg($this->ctid)." ip a | grep ".escapeshellarg($this->ip);
        $exitCode = $this->execWithLogging($command);

        return $exitCode ? self::OCF_NOT_RUNNING : self::OCF_SUCCESS;
    }
}