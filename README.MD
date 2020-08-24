AreportDPMXBRL
==========
AreportDPMXBRL is library for parsing XBRL taxonomy, which is created by DPM Architect.
This standard is recommended and implemented by EU regulators such as EBA and EIOPA.

Web application Areport use this library to parse XBRL taxonomy.

[More information](https://github.com/begicf/areport)

#### Demo
[Areport](https://demo.areport.net)

## Quick Start
Install the library using [composer](https://getcomposer.org/)
```
composer require begicf/areport-dpm-xbrl
```
```php
<?php

use AReportDpmXBRL\Library\Data;
use AReportDpmXBRL\Render;

//parse taxonomy
$tax = Data::getTax($table_path); //set table absolute path

//render HTML form
$render = new Render($tax);

    /*
     * @return array [
     *  'table' => (string) Table HTMl Form,
     *  'sheets' => (string)  Sheets HTML Form
     *  'tableName' => (string)  Table Name
     *  'aspectNode' => (bool)  Aspect Axis
     *  'tableID'=> (string)  Table ID
     *  'groups' => (string)  Table group
     * ]
     */

$HTMLForm = $render->renderHtmlForm();

?>
```
