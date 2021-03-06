# OpenSRS-PHP

A PHP-based client class that communicates with the OpenSRS API

Copyright (c) 2000-2009 Colin Viebrock


## HISTORY

See CHANGELOG.md file for version history


## LICENSE

See LICENSE file for license details


## REQUIREMENTS

An account with the OpenSRS Registry: <http://www.opensrs.net/>

PHP 4 or 5 <http://www.php.net/> with:

- PEAR extension
- mcrypt library
- expat XML
- Perl regular expressions (pcre)
- Crypt_CBC PEAR package


## INSTALL

Just unzip and untar the archive, or do both in one step:

```
$ unzip opensrs-php-*.zip
```

You will end up with the following files:

- `CHANGELOG.md` - version history
- `LICENSE` - a copy of the LGPL
- `OPS.php` - the OPS message protocol class
- `README.md` - this file
- `TODO.md` - stuff to do
- `country_codes.php` - list of 2 and 3 letter ISO country codes
- `openSRS.php.default` - a sample extended class (edit the file and remove the ".default" before using!)
- `openSRS_base.php` - the base class file
- `ops.dtd` - the OPS DTD file (not really needed)
- `test.php` - a test PHP script
- `test.xml` - some test XML data (not really needed)

After configuring the default class (see below), try running the test.php
script in your browser. It should connect to OpenSRS, do a lookup, and
output the session log.


## USAGE

Your first step should be to create a child class that extends the base
class, basically setting up your OpenSRS username, private keys, and
whether you want to use the test environment or live one.

There is a sample file called `openSRS.php.default` in this distribution.  Just
edit the values in it, and rename it `openSRS.php`.

With this file, you just need to include() or require() it at the top of every
file in which you want to talk to the OpenSRS server.

To start up a connection, just instantiate the class:

	$O = new openSRS;

Or, to override the environment, do:

	$O = new openSRS('LIVE');

From there, the important function is:

	$response = $O->send_cmd($command);

This passes the command in $command to the server and returns the response.

	$O->showlog();

This outputs a (very handy, IMHO) log of your session.  The log shows all
data passed to and from the server unencrypted ... so you probably shouldn't
let your end users see its output.

You can also view the raw XML log:

	$O->_OPS->showLog('xml');

Or (for the truly hardcore) the raw binary log:

	$O->_OPS->showLog('raw');

There is also:

	$O->validate($data, [$params]);

This validates the data in $data for new domain registrations.  See the original
OpenSRS code for full details on it's usage.  It may or may not work: I've never
used it, personally.


## EMAIL AND WEB CERTS

If you are planning on using the class to do email or web certs, which use the
TPP protocol, then you have three ways to do so:

1) You can call this command right after instantiating the class:

	$O->setProtocol('TPP');

2) If you are always going to use TPP, then just set up your extended class to
use TPP by default:

	var $protocol = 'TPP';

3) You can define the environment when instantiatin the class:

    $O = new openSRS('LIVE','TPP');


## OPENHRS

OpenHRS <http://resellers.tucows.com/openhrs/> is basically OpenSRS's let-
us-manage-your-registry product.  It uses the same communication protocols
as OpenSRS, so there are only a few things you need to do to the OpenSRS-PHP
class to connect and talk to the OpenHRS system.

First, you need to add a few more parameters to the child class:

```php
<?php

require_once 'openSRS_base.php';

class openSRS extends openSRS_base {
	var $USERNAME         = 'foobar';             # your OpenSRS username
    var $TEST_PRIVATE_KEY = '1234567890abcdef';   # your private key on the test (horizon) server
    var $LIVE_PRIVATE_KEY = 'abcdef1234567890';   # your private key on the live server

    var $HRS_USERNAME     = 'foobar-hrs';         # your OpenHRS username
    var $HRS_PRIVATE_KEY  = '0987654321abcdef';   # your private key on the HRS server
    var $HRS_host         = 'foobar.opensrs.net'; # your OpenHRS hostname

    var $environment      = 'HRS';                # 'TEST' or 'LIVE' or 'HRS'
    var $crypt_type       = 'DES';                # 'DES' or 'BLOWFISH'
    var $ext_version      = 'Foobar';             # anything you want
}

?>
```

From there, you proceed as normal, except you will use the HRS environment instead
of TEST or LIVE:

    $O = new openSRS('HRS');

All the OpenHRS commands you send should be identical to OpenSRS commands.


## CREDITS

- Colin Viebrock who developed this project until 2004.
- Mike Glover <mpg4@duluoz.net> for providing the CBC emulation functions
  and general help
- Victor Magdic at Tucows Inc. <vmagdic@tucows.com> who wrote the original
  Perl API
- Jason Slaughter at Tucows for the SSL-method code
- the rest of the folks at OpenSRS (Ross, Dan, Charles, Erol, et al)
- easyDNS <www.easydns.com>, Colin Viebrock's previous employer which "funded"
  most of the client development
- Phurix <www.phurix.co.uk> for web hosting and domain regisration
- all the users who've suggested changes or provided bug fixes
- anyone else I forgot
