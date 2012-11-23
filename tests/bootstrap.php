<?php
// change the following paths if necessary
$yiit=dirname(__FILE__).'/../yii/framework/yiit.php';
$config=dirname(__FILE__).'/config/test.php';

require_once($yiit);

// make sure non existing PHPUnit classes do not break with Yii autoloader
Yii::$enableIncludePath = false;
Yii::setPathOfAlias('tests', dirname(__FILE__));
Yii::import('tests.*');

Yii::createWebApplication($config);