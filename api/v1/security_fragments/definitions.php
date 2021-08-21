<?php

/* AUTH */
//Allows using the rate-limiting related API functions (mainly rulebooks and their meta)
CONST SECURITY_RATE_LIMIT_AUTH = 'SECURITY_RATE_LIMIT_AUTH';
//Allows using the IP related API functions
CONST SECURITY_IP_AUTH = 'SECURITY_IP_AUTH';
//Allows viewing IPs and IP ranges
CONST SECURITY_IP_VIEW = 'SECURITY_IP_VIEW';
//Allows modifying IPs and IP ranges
CONST SECURITY_IP_MODIFY = 'SECURITY_IP_MODIFY';

/* Validation */
//Allowed Regex filter regex
CONST RESOURCE_TYPE_REGEX = '[a-z]\w{1,15}';
//IPv4 Prefix regex
CONST IPV4_PREFIX = '^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){0,3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$';
//A single IPv4 segment regex
CONST IPV4_SEGMENT_REGEX = '^(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$';



