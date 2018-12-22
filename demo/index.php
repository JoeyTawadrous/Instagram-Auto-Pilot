<?php
	require_once('../InstagramAutoPilot.php');

    $iap = new InstagramAutoPilot;

    $accounts = array("design");
    $tags = array("design", 'development');
    $comment = "Check out my profile for all the best design resources!";

    $iap->init("ACCOUNT_NAME", "ACCOUNT_PASSWORD", true, true, false, $accounts, $tags, $comment);
?>