IPAddr openvz ocf resource agent
============

This is [OCF compliant resource agent](http://linux-ha.org/wiki/OCF_Resource_Agents). It adds or removes ip to selected openvz container.

## Parameters
 - `ctid`* (integer): Container ID  
CTID is the numeric ID of the given openvz container

 - `ip`* (string): Assigned IP address  
Address can optionally have a netmask specified in the CIDR notation (e.g. 10.1.2.3/25)

 - `stateFile` (string): Activity state file  
 File existence will show success activity of this resource
 
 - `sentryDSN` (string): Sentry dsn url
