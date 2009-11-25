<?php

require_once 'openSRS.php';

$O = new openSRS('test','XCP');

$cmd = array(
        'action'        => 'lookup',
        'object'        => 'domain',
        'attributes'    => array(
                'domain'        => 'domain.com',
                'affiliate_id'  => '12345'
        )
);

echo "<h1>Command</h1>\n";
print_r($cmd);

$result = $O->send_cmd($cmd);

echo "<HR />";
echo "<h1>Result</h1>\n";
print_r($result);

echo "<HR />";
echo "<h1>Log</h1>\n";
$O->showlog();

echo "<HR />";
echo "<h1>OPS XML Log</h1>\n";
$O->_OPS->showlog('xml','raw');

echo "<HR />";
echo "<h1>OPS Raw Log</h1>\n";
$O->_OPS->showlog('raw');
