<?php
header("Content-Type: text/plain; charset=UTF-8");
include('build.php');
$base = new Dramacode('dramacode.sqlite');
$base->csv();
?>
