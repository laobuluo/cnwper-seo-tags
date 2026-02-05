<?php
/**
 * 将用户提交的tags处理为可使用的tags数组
 * @param String $tags     用户提交数据, 一行一个
 * @return false|string[]  分割后的数组
 */
function cnwper_custom_post_tags_handler(String $tags){
    return array_unique(explode(" ", $tags));
}

/**
 * 将用户提交的 文章id范围 处理为可使用的 post_ids 数组
 * 示例数据： 1,3,4,10 以及 20-100
 * @param String $post_id_range     用户提交数据, 一行一个
 * @return false|string[]  分割后的 post_ids 数组
 */
function cnwper_custom_post_post_id_range_handler(String $post_id_range){
    if ( strpos($post_id_range, '-') ) {
        $x = explode('-', $post_id_range);
        $post_ids = range($x[0], $x[1]);
    } elseif (strpos($post_id_range, ',')) {
        $post_ids = explode(',', $post_id_range);
    } else {
        return False;
    }
    return $post_ids;
}

/**
 * 将用户提交的 rate 处理为可使用的 rate 数组
 * 示例数据： 3 或 3-5
 *
 * @param String $rate     用户提交数据, 一行一个
 *
 * @return array
 */
function cnwper_custom_post_rate_handler(String $rate){
    if ( strpos($rate, '-') ) {
        return explode('-', $rate);
    } else {
        return array((int) $rate, (int) $rate);
    }
}

/**
 * 返回用户设置的所有tags关键词
 *
 * @return array
 */
function cnwper_seo_tags_db_get_tags(){
    global $wpdb;
    $tags_table_name = $wpdb->prefix . CNWPER_SEO_TAGS_PREFIX . 'auto_tags';
    $tags_sql = "SELECT `tags` FROM $tags_table_name;";
    $tags =$wpdb-> get_results($tags_sql, ARRAY_A);
    return array_column($tags, 'tags');
}

/**
 * 根据字符串值的长度排序
 * @param $a
 * @param $b
 * @return int
 */
function cnwper_seo_tags_tag_sort($a, $b){
    if ( $a->name == $b->name ) return 0;
    return ( strlen($a->name) > strlen($b->name) ) ? -1 : 1;
}


/**
 * POST数据处理与操作
 * @param $keywords
 */
function cnwper_seo_tags_post_data_handler($keywords) {
    global $wpdb;
    $tags_table_name = $wpdb->prefix . CNWPER_SEO_TAGS_PREFIX . 'auto_tags';

    $clean_table = 'TRUNCATE TABLE ' . $tags_table_name . ';';
    $wpdb->query($clean_table);  # 清空现存tags

    $tags = cnwper_custom_post_tags_handler($keywords);
    foreach($tags as $k => $v){
        $wpdb->insert($tags_table_name, array('tags' => $v));  # 不建议频繁大批量更换tags关键词
    }
}

/**
 * POST数据处理与操作
 * @param $post_ids
 * @param $keywords
 * @param $rate
 * @param $limit
 * @param $match_title
 * @param $match
 */
function cnwper_seo_tags_batch_set_tags_handler($post_ids, $keywords, $rate, $limit, $match_title, $match) {
    $options = get_option('cnwper_seo_tags_options');

    // 赋值以下参数进行执行，但不进行数据落地
    $options['opt']['rate'] = $rate;
    $options['opt']['limit'] = (int) $limit;
    $options['opt']['match_title'] = $match_title;
    $options['opt']['match'] = $match;

    $ids = cnwper_custom_post_post_id_range_handler($post_ids);
    $tags = cnwper_custom_post_tags_handler($keywords);
    $rate = cnwper_custom_post_rate_handler($options['opt']['rate']);

    if ($ids and $rate and $tags) {
        cnwper_batch_add_tags($ids, $tags, $rate, $options);
        $batch_log = date("Y年m月d日 H:i:s") . " 为文章 " . $post_ids . " 自动分配了" . count($tags)
            . "个tag。每篇随机分配" . $options['opt']['rate'] . "个。";
        if ($options['opt']['match']) {
            $batch_log .=  "内容匹配开启；";
        }
        if ($options['opt']['match_title']) {
            $batch_log .=  "标题匹配开启；";
        }
        global $wpdb;
        $log_table_name = $wpdb->prefix . CNWPER_SEO_TAGS_PREFIX . 'auto_tags_log';
        $wpdb->insert($log_table_name, array('log' => $batch_log));
    }

}
