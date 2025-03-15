<?php require_once "wp-load.php"; define("WP_EASY_STAGING_DOCKER_DEV", true); $staging = new WP_Easy_Staging_Staging(); $result = $staging->create_staging_site("test-staging"); var_dump($result); ?>
