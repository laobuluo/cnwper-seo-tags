<?php
if(!defined('WP_UNINSTALL_PLUGIN')){
	exit();
}
delete_option('cnwper_seo_tags_options');

$tags_table_name = $wpdb->prefix . CNWPER_SEO_TAGS_PREFIX . 'auto_tags';
$log_table_name = $wpdb->prefix . CNWPER_SEO_TAGS_PREFIX . 'auto_tags_log';
$wpdb->query("DROP TABLE IF EXISTS `".$tags_table_name."`");
$wpdb->query("DROP TABLE IF EXISTS `".$log_table_name."`");

delete_post_meta_by_key('cnwper_seo_tags_stamp');
