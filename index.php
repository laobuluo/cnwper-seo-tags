<?php
/*
Plugin Name: 自动内链关键字插件
Plugin URI: https://www.laojiang.me/6084.html
Description: 自动关键字插件（CNWPer SEO Tags）,能够实现批量TAGS关键字内链、自动分布到匹配文章，以及可以批量分布管理关键字。公众号： <font color="red">老蒋朋友圈</font>
Version: 3.2
Author: 老蒋和他的小伙伴
Author URI: https://www.laojiang.me
Requires PHP: 7.0
 */
define('CNWPER_SEO_TAGS_VERSION', 3.1);
define('CNWPER_SEO_TAGS_BASE_FOLDER', plugin_basename(dirname(__FILE__)));
define('CNWPER_SEO_TAGS_PREFIX', 'cnwper_');
define('CNWPER_SEO_TAGS_STAMP', 'cnwper_seo_tags_stamp');

require_once('functions.php');


register_activation_hook(__FILE__, 'cnwper_seo_tags_init');
function cnwper_seo_tags_init () {
    # 设置参数
    $options = array(
        'version' => CNWPER_SEO_TAGS_VERSION,
        'switch'  => False,
        'opt' => array(
            'match'  => False,
            'rate' => 3,
            'auto_tag_link' => False,
            'match_title' => False,
            'limit' => 9,
        ),
    );
    $cnwper_seo_tags_options = get_option('cnwper_seo_tags_options');
    if(!$cnwper_seo_tags_options){
        add_option('cnwper_seo_tags_options', $options, '', 'yes');
    }

    # 创建tag表
    global $wpdb;
    $tags_table_name = $wpdb->prefix . CNWPER_SEO_TAGS_PREFIX . 'auto_tags';
    $log_table_name = $wpdb->prefix . CNWPER_SEO_TAGS_PREFIX . 'auto_tags_log';
    if ( $wpdb->get_var("SHOW TABLES LIKE '$tags_table_name'") != $tags_table_name ) {
        $sql = "CREATE TABLE " . $tags_table_name . " (
              `id` int(6) NOT NULL AUTO_INCREMENT,
              `tags` varchar(100) CHARACTER SET utf8 NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `tags` (`tags`)
            ) DEFAULT CHARSET=utf8;
        ";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    if( $wpdb->get_var("SHOW TABLES LIKE '$log_table_name'") != $log_table_name ) {
        $sql = "CREATE TABLE " . $log_table_name . " (
              `id` int(6) NOT NULL AUTO_INCREMENT,
              `log` varchar(255) CHARACTER SET utf8 NOT NULL,
              PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8;
        ";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

/* 自动为文章添加标签 */
add_action('save_post_post', 'cnwper_auto_add_tags');
function cnwper_auto_add_tags(){
    $options = get_option('cnwper_seo_tags_options');
    if ($options['switch']) {
        $post_id = get_the_ID();  # 获取当前post的id
        # 1. 标签的检查, 存在则跳过
        if ( !get_post_meta($post_id, CNWPER_SEO_TAGS_STAMP) ) {
            $rate = cnwper_custom_post_rate_handler($options['opt']['rate']);
            $tags = cnwper_seo_tags_db_get_tags();  # 获取用户设置的tag
            $tags_quality = cnwper_seo_tags_batch_add_tags($post_id, $tags, $rate, $options);
            if ( $tags_quality !== False ) {
                # 2. 标签的设置，记录数字暂未实际利用，方便后续扩展
                add_post_meta($post_id, CNWPER_SEO_TAGS_STAMP, $tags_quality, True);
//            if ( $r === False ) {  # 暂不考虑
//                update_post_meta($post_id, CNWPER_SEO_TAGS_STAMP, $tags_quality);
//            }
            }
        }

    }
}


/**
 * 批量添加 tags
 *
 * @param array $ids 文章 id 数组
 * @param array $tags 用户自定义 tags 数组
 * @param array $rate 随机数范围，index 0 表示下限， 1 表示上限
 * @param array $options 插件选项
 */
function cnwper_batch_add_tags(array $ids, array $tags, array $rate, array $options){
//    if ($options['switch']) {
    foreach( $ids as $k => $id ) {
        cnwper_seo_tags_batch_add_tags($id, $tags, $rate, $options);
    }
//    }
}


# 批量添加tag
function cnwper_seo_tags_batch_add_tags(int $id, array $tags, array $rate, array $options){
    ######## 1. 开关
    ######## 2. 自动内链描文本
    ######## 3. 设置自动关键字数量
    $post = get_post($id);
    $current_tags = wp_get_post_tags($id);

    # 7. 设置单篇post tag上限  And  # 4. 频率
    if ( $options['opt']['limit'] - count($current_tags) <= 0 ) {
        return False;
    } elseif ( $options['opt']['limit'] - count($current_tags) <= $rate[0]  ) {
        $rate[0] = $options['opt']['limit'] - count($current_tags);
        $rate[1] = $options['opt']['limit'] - count($current_tags);
    } elseif ( $rate[0] < $options['opt']['limit'] - count($current_tags) and $options['opt']['limit'] - count($current_tags) <= $rate[1] ) {
        $rate[1] = $options['opt']['limit'] - count($current_tags);
    }

    if ($post and $post->post_type == 'post') {
        $post_content = $post->post_content;
        $post_title = $post->post_title;

        $_tags = [];
        $_rate = mt_rand($rate[0], $rate[1]);
        if ( $_rate <= 1 ){
            $_keys = [array_rand($tags, 1),];
        } else {
            $_keys = array_rand($tags, $_rate);
        }

        foreach ($_keys as $key => $val) {
            # 根据频率，及上限比较，决定最终要添加的词的数量
            $_tags[$key] = $tags[$val];

            if ( $options['opt']['match'] ) {  # 6. 强行内容关键字匹配
                if ( strpos($post_content, $_tags[$key]) === false ) {
                    unset($_tags[$key]);  # 内容中未出现tag词，删除该tag
                }
            }

            if ( $options['opt']['match_title'] ) {  # 5. 强行标题关键字匹配
                if ( strpos($post_title, $_tags[$key]) === false ) {
                    unset($_tags[$key]);  # 标题中未包含tag词，删除该tag
                }
            }
        }

        $res = wp_set_post_tags( $id, $_tags, True );
        if ($res === False or is_wp_error($res) or count($_tags) <= 0) {
            return False;
        } else {
            return count($_tags);
        }
    } else {
        return False;
    }
}


add_filter('the_content','cnwper_seo_tags_auto_tag_link');
function cnwper_seo_tags_auto_tag_link($content){
    $options = get_option('cnwper_seo_tags_options');
    if ( $options['switch'] and $options['opt']['auto_tag_link'] ) {
        //连接数量
        $limit = 1;
        $case = 'im';  # 模式修饰符，i, 大小写不敏感; m, s
        $tags = get_the_tags();
        if ($tags) {
            usort($tags, "cnwper_seo_tags_tag_sort");
            foreach($tags as $tag) {
                $link = get_tag_link($tag->term_id);
                $keyword = $tag->name;
                //连接代码
                $clean_keyword = stripslashes($keyword);
                $url = "<a href=\"$link\" title=\"".str_replace('%s', addcslashes($clean_keyword, '$'),__('View all posts in %s'))."\"";
                $url .= ' target="_blank" class="tag_link"';
                $url .= ">".addcslashes($clean_keyword, '$')."</a>";

                //不连接的 代码。碰到下面的问题不替换，如已经存在的链接或图片代码。
                $ex_word = $clean_keyword;  // 选择clean_关键词不一定对，还需要校验调试。
                $content = preg_replace( '|(<a[^>]+>)(.*)(' . $ex_word . ')(.*)(</a[^>]*>)|U' . $case, '$1$2%&&&&&%$4$5', $content);
                $content = preg_replace( '|(<img)(.*?)('.$ex_word.')(.*?)(>)|U'.$case, '$1$2%&&&&&%$4$5', $content);
                $clean_keyword = preg_quote($clean_keyword,'\'');
                $regEx = '\'(?!((<.*?)|(<a.*?)))('. $clean_keyword . ')(?!(([^<>]*?)>)|([^>]*?</a>))\'s' . $case;
                $content = preg_replace($regEx, $url, $content, $limit);
                $content = str_replace( '%&&&&&%', stripslashes($ex_word), $content);
            }
        }
    }
    return $content;
}


add_filter('plugin_action_links', 'cnwper_seo_tags_plugin_action_links', 10, 2);
function cnwper_seo_tags_plugin_action_links($links, $file) {
    if ($file == plugin_basename(dirname(__FILE__) . '/index.php')) {
        $links[] = '<a href="admin.php?page=' . CNWPER_SEO_TAGS_BASE_FOLDER . '/index.php">设置</a>';
    }
    return $links;
}

add_action('admin_menu', 'cnwper_seo_tags_add_setting_page');
function cnwper_seo_tags_add_setting_page() {
    global $cnwper_seo_tags_settings_page_hook;
    global $cnwper_seo_tags_batch_settings_page_hook;
    $cnwper_seo_tags_settings_page_hook = add_menu_page(__('WP自动关键字插件'), __('自动关键字设置'), 'administrator',  __FILE__, 'cnwper_seo_tags_setting_page', false, 100);
    $cnwper_seo_tags_batch_settings_page_hook = add_submenu_page(__FILE__,'批量部署关键字工具','批量部署关键字工具', 'administrator', 'batch_set_tags_page', 'cnwper_seo_tags_batch_set_tags_page');
}

add_action('admin_enqueue_scripts', 'cnwper_seo_tags_scripts_styles');
function cnwper_seo_tags_scripts_styles($hook){
    global $cnwper_seo_tags_settings_page_hook;
    if( $cnwper_seo_tags_settings_page_hook != $hook )
        return;
    wp_enqueue_style("cnwper_seo_tags_options_panel_stylesheet", plugin_dir_url( __FILE__ ). 'layui/css/layui.css',false,'','all');
    wp_enqueue_style("cnwper_seo_tags_options_self_panel_stylesheet", plugin_dir_url( __FILE__ ). 'layui/css/laobuluo.css',false,'','all');
    wp_enqueue_script("cnwper_seo_tags_options_panel_script", plugin_dir_url( __FILE__ ).'layui/layui.js', '', '', false);
   
}

add_action('admin_enqueue_scripts', 'cnwper_seo_tags_batch_scripts_styles');
function cnwper_seo_tags_batch_scripts_styles($hook){
    global $cnwper_seo_tags_batch_settings_page_hook;
    if( $cnwper_seo_tags_batch_settings_page_hook != $hook )
        return;
    wp_enqueue_style("cnwper_seo_tags_batch_options_panel_stylesheet", plugin_dir_url( __FILE__ ). 'layui/css/layui.css',false,'','all');
    wp_enqueue_style("cnwper_seo_tags_batch_options_self_panel_stylesheet", plugin_dir_url( __FILE__ ). 'layui/css/laobuluo.css',false,'','all');
    wp_enqueue_script("cnwper_seo_tags_batch_options_panel_script", plugin_dir_url( __FILE__ ).'layui/layui.js', '', '', false);
   
}

function cnwper_seo_tags_setting_page(){
    if (!current_user_can('administrator')) {
        wp_die('Insufficient privileges!');
    }
    $cnwper_seo_tags_options = get_option('cnwper_seo_tags_options');
    global $wpdb;
    $tags_table_name = $wpdb->prefix . CNWPER_SEO_TAGS_PREFIX . 'auto_tags';
    $tags_sql = "SELECT `tags` FROM $tags_table_name;";
    $tags =$wpdb-> get_results($tags_sql, ARRAY_A);

    if ($cnwper_seo_tags_options && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce']) && !empty($_POST)) {

        $cnwper_seo_tags_options['opt']['auto_tag_link'] = isset($_POST['auto_tag_link']);
        $cnwper_seo_tags_options['opt']['match'] = isset($_POST['match']);
        $cnwper_seo_tags_options['opt']['match_title'] = isset($_POST['match_title']);

        $_tags = isset($_POST['tags']) ? sanitize_text_field(trim(stripslashes($_POST['tags']))) : '';
        $cnwper_seo_tags_options['opt']['rate'] = isset($_POST['rate']) ? sanitize_text_field(trim(stripslashes($_POST['rate']))) : '3';
        $cnwper_seo_tags_options['opt']['limit'] = isset($_POST['limit']) ? sanitize_text_field(trim(stripslashes($_POST['limit']))) : '9';

        $cnwper_seo_tags_options['switch'] = isset($_POST['switch']);
        update_option('cnwper_seo_tags_options', $cnwper_seo_tags_options);
        cnwper_seo_tags_post_data_handler($_tags);  # 保存tags

        $tags = $wpdb-> get_results($tags_sql, ARRAY_A);
        ?>
        <div class="notice notice-success settings-error is-dismissible"><p><strong>设置已保存。</strong></p></div>
        <?php

    }
    ?>

   <div class="container-laobuluo-main">
    <div class="laobuluo-wbs-header" style="margin-bottom: 15px;">
              <div class="laobuluo-wbs-logo"><a><img src="<?php echo plugin_dir_url( __FILE__ );?>layui/images/logo.png"></a><span class="wbs-span">自动内链关键字插件</span><span class="wbs-free">Free V3.1</span></div>
             <div class="laobuluo-wbs-btn">
                  <a class="layui-btn layui-btn-primary" href="https://www.laojiang.me/6084.html" target="_blank"><i class="layui-icon layui-icon-home"></i> 插件主页</a>
                  <a class="layui-btn layui-btn-primary" href="https://www.laojiang.me/contact/" target="_blank"><i class="layui-icon layui-icon-release"></i> 技术支持</a>
             </div>
        </div>
    </div>

      <!-- 内容 -->
        <div class="container-laobuluo-main">
            <div class="layui-container container-m">
                <div class="layui-row layui-col-space15">
                    <!-- 左边内容 -->
                   
                    <div class="layui-col-md9">
                        <div class="laobuluo-panel">
                            <div class="laobuluo-controw">
                                <fieldset class="layui-elem-field layui-field-title site-title">
                                    <legend><a name="get">自动关键字设置</a></legend>
                                </fieldset>
                                <div class="laobuluo-text laobuluo-block">
                                   <form action="<?php echo wp_nonce_url('./admin.php?page=' . CNWPER_SEO_TAGS_BASE_FOLDER . '/index.php'); ?>"
              name="cnwper_seo_tags_form" method="post" class="layui-form">   


                <div class="layui-form-item">
                                            <label class="layui-form-label">开启</label>
                                            <div class="layui-input-block">
                                                <input type="checkbox"  lay-skin="switch" lay-text="ON|OFF" lay-filter="switchTest" name="switch" <?php
                        if ($cnwper_seo_tags_options['switch']) {
                            echo 'checked="TRUE"';
                        }
                        ?> />
                                                <div class="layui-unselect layui-form-switch" lay-skin="_switch"><em>OFF</em><i></i></div>
                                            </div>
                                        </div>

                                         <div class="layui-form-item">
                                            <label class="layui-form-label">自动内链</label>
                                            <div class="layui-input-inline" style="width: 100px;">
                                                <input type="checkbox" title="自动" name="auto_tag_link" <?php
                        if ($cnwper_seo_tags_options['opt']['auto_tag_link']) {
                            echo 'checked="TRUE"';
                        }
                        ?> />
                                                <div class="layui-unselect layui-form-checkbox"><span>自动</span><i class="layui-icon layui-icon-ok"></i></div>                                          
                                            </div>  <div class="layui-form-mid layui-word-aux">开启实现TAGS自动内链</div>
                                        </div>

                                     
                                   <div class="layui-form-item">
                                            <label class="layui-form-label">关键字</label>
                                            <div class="layui-input-block">
                                 <textarea name="tags" placeholder="一行一个或者每个空格翻开" class="layui-textarea"><?php
                            if ($tags) {
                                echo implode(' ', array_column($tags, 'tags'));
                            }
                            ?></textarea>

                                                <div class="layui-form-mid layui-word-aux">一行一个关键字或者是空格隔开；建议不要频繁更换关键字</div>
                                            </div>
                                        </div>

                                          <div class="layui-form-item">
                                            <label class="layui-form-label">频率</label>
                                            <div class="layui-input-block">
                                                <input type="text" placeholder="关键字插入数量" class="layui-input" name="rate" value="<?php
                        if ($cnwper_seo_tags_options['opt']['rate']) {
                            echo $cnwper_seo_tags_options['opt']['rate'];
                        }
                        ?>" /><div class="layui-form-mid layui-word-aux">每篇文章插入关键字个数, 可以为固定数值, 如3; 可以是范围数值，如 3-6, 插件将随机从给定范围中取值</div>
                                            </div>
                                        </div> 
                                        
                                         <div class="layui-form-item">
                                            <label class="layui-form-label">匹配标题</label>
                                            <div class="layui-input-inline" style="width: 100px;">
                                                <input type="checkbox" title="匹配" name="match_title" <?php
                        if ($cnwper_seo_tags_options['opt']['match_title']) {
                            echo 'checked="TRUE"';
                        }
                        ?> />
                                                <div class="layui-unselect layui-form-checkbox"><span>匹配</span><i class="layui-icon layui-icon-ok"></i></div>                                          
                                            </div>  <div class="layui-form-mid layui-word-aux">匹配标题中含关键字才添加Tags</div>
                                        </div>

                                        <div class="layui-form-item">
                                            <label class="layui-form-label">匹配内容</label>
                                            <div class="layui-input-inline" style="width: 100px;">
                                                <input type="checkbox" title="匹配" name="match" <?php
                        if ($cnwper_seo_tags_options['opt']['match']) {
                            echo 'checked="TRUE"';
                        }
                        ?> />
                                                <div class="layui-unselect layui-form-checkbox"><span>匹配</span><i class="layui-icon layui-icon-ok"></i></div>                                          
                                            </div>  <div class="layui-form-mid layui-word-aux">遍历文章内容，内容中有关键字才添加</div>
                                        </div>
                                        
                                         <div class="layui-form-item">
                                            <label class="layui-form-label">单篇数量</label>
                                            <div class="layui-input-block">
                                                <input type="text"  name="limit" value="<?php if(isset($cnwper_seo_tags_options['opt']['limit'])) {
                            echo $cnwper_seo_tags_options['opt']['limit'];
                        } ?>" /><div class="layui-form-mid layui-word-aux">默认值:9, 限制每一篇文章的tags上限，不建议太多</div>
                                            </div>
                                        </div> 

                                        <div class="layui-form-item">
                                            <div class="layui-input-block">
                                                
                                               <input type="submit" name="submit" value="保存设置" class="layui-btn" lay-filter="formDemo" />
                                            </div>
                                        </div>
                                    </form>
                                   

                                </div>
                            </div>
                        </div>
                    </div>
                  
                    <!-- 左边内容 end -->
                    <!-- 右边内容 -->
                    <div class="layui-col-md3">
                        <div  id="nav">
                            
                            <div class="laobuluo-panel">
                                <div class="laobuluo-panel-title">关注公众号</div>
                                <div class="laobuluo-code">
                                    <img src="<?php echo plugin_dir_url( __FILE__ );?>layui/images/qrcode.png">
                                    <p>微信扫码关注 <span class="layui-badge layui-bg-blue">老蒋朋友圈</span> 公众号</p>
                                    <p><span class="layui-badge">优先</span> 获取插件更新 和 更多 <span class="layui-badge layui-bg-green">免费插件</span> </p>
                                </div>
                            </div>

                        </div>
                    </div>
                    <!-- 右边内容end -->
                </div>
            </div>
        </div>
        <!-- 内容 -->
        <!-- footer -->
        <div class="container-laobuluo-main">
        <div class="layui-container container-m">
            <div class="layui-row layui-col-space15">
                <div class="layui-col-md12">
                    <div class="laobuluo-footer-code">
                         <span class="codeshow"></span>
                         
                    </div>
                     <div class="laobuluo-links">
                                     <a href="https://www.laojiang.me/"  target="_blank">老蒋玩开发</a>
                    <a href="https://www.zhujipingjia.com/pianyivps.html" target="_blank">便宜VPS推荐</a>
                    <a href="https://www.zhujipingjia.com/hkcn2.html" target="_blank">香港VPS推荐</a>
                    <a href="hhttps://www.zhujipingjia.com/uscn2gia.html" target="_blank">美国VPS推荐</a>
                       
                        </div>
                       
                </div>
            </div>
        </div>
        </div>
        <!-- footer -->
 <script>
        
            layui.use(['form', 'element','jquery'], function() {
                var $ =layui.jquery;
                function menuFixed(id) {
                  var obj = document.getElementById(id);
                  var _getHeight = obj.offsetTop;
                  var _Width= obj.offsetWidth
                  window.onscroll = function () {
                    changePos(id, _getHeight,_Width);
                  }
                }
                function changePos(id, height,width) {
                  var obj = document.getElementById(id);
                  obj.style.width = width+'px';
                  var scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
                  var _top = scrollTop-height;
                  if (_top < 150) {
                    var o = _top;
                    obj.style.position = 'relative';
                    o = o > 0 ? o : 0;
                    obj.style.top = o +'px';
                    
                  } else {
                    obj.style.position = 'fixed';
                    obj.style.top = 50+'px';
                
                  }
                }
                menuFixed('nav');
            })
        </script>
    <?php
}

function cnwper_seo_tags_batch_set_tags_page(){
    if (!current_user_can('administrator')) {
        wp_die('Insufficient privileges!');
    }

    global $wpdb;
    $log_table_name = $wpdb->prefix . CNWPER_SEO_TAGS_PREFIX . 'auto_tags_log';
    $log_sql = "SELECT `log` FROM $log_table_name ORDER BY `id` DESC LIMIT 15;";
    $logs = $wpdb->get_results($log_sql, ARRAY_A);

    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce']) && !empty($_POST) ) {
        $keywords = isset($_POST['keywords']) ? sanitize_text_field(trim(stripslashes($_POST['keywords']))) : '';
        $post_ids = isset($_POST['post_ids']) ? sanitize_text_field(trim(stripslashes($_POST['post_ids']))) : '';

        $rate = isset($_POST['rate']) ? sanitize_text_field(trim(stripslashes($_POST['rate']))) : 3;
        $limit = isset($_POST['limit']) ? sanitize_text_field(trim(stripslashes($_POST['limit']))) : 9;

        $match_title = isset($_POST['match_title']);
        $match = isset($_POST['match']);

        cnwper_seo_tags_batch_set_tags_handler($post_ids, $keywords, $rate, $limit, $match_title, $match);
        $logs = $wpdb->get_results($log_sql, ARRAY_A);  # 执行成功刷新log日志
    }


    ?>
   <div class="container-laobuluo-main">
    <div class="laobuluo-wbs-header" style="margin-bottom: 15px;">
              <div class="laobuluo-wbs-logo"><a><img src="<?php echo plugin_dir_url( __FILE__ );?>layui/images/logo.png"></a><span class="wbs-span">自动内链关键字插件</span><span class="wbs-free">Free V3.1</span></div>
            <div class="laobuluo-wbs-btn">
                  <a class="layui-btn layui-btn-primary" href="https://www.laojiang.me/6084.html" target="_blank"><i class="layui-icon layui-icon-home"></i> 插件主页</a>
                  <a class="layui-btn layui-btn-primary" href="https://www.laojiang.me/contact/" target="_blank"><i class="layui-icon layui-icon-release"></i> 技术支持</a>
             </div>
        </div>
    </div>

      <!-- 内容 -->
        <div class="container-laobuluo-main">
            <div class="layui-container container-m">
                <div class="layui-row layui-col-space15">
                    <!-- 左边内容 -->
                   
                    <div class="layui-col-md9">
                        <div class="laobuluo-panel">
                            <div class="laobuluo-controw">
                                <fieldset class="layui-elem-field layui-field-title site-title">
                                    <legend><a name="get">批量添加关键字</a></legend>
                                </fieldset>
                                <div class="laobuluo-text laobuluo-block">
                                     <form action="<?php echo wp_nonce_url('./admin.php?page=batch_set_tags_page'); ?>"
              name="cnwper_seo_tags_batch_form" method="post" class="layui-form">
                                     
                                   <div class="layui-form-item">
                                            <label class="layui-form-label">关键字组</label>
                                            <div class="layui-input-block">
                                                <textarea name="keywords" placeholder="批量关键字，一行一个关键字或以空格分隔" class="layui-textarea"><?php
                            if ( isset($keywords) and $keywords ) {
                                echo $keywords;
                            }
                            ?></textarea>

                                                <div class="layui-form-mid layui-word-aux">一行一个关键字或者是空格隔开；本页面批量添加的关键词只用于本次操作，不记录不显示</div>
                                            </div>
                                        </div>

                                          <div class="layui-form-item">
                                            <label class="layui-form-label">文章范围</label>
                                            <div class="layui-input-block">
                                                <input type="text"  placeholder="设置执行的文章范围" class="layui-input" name="post_ids" value="<?php
                        if ( isset($post_ids) and $post_ids ) {
                            echo $post_ids;
                        }
                        ?>" /><div class="layui-form-mid layui-word-aux">对应文章ID：1,2,9,11 或者 10-100</div>
                                            </div>
                                        </div> 

                                        
                                          <div class="layui-form-item">
                                            <label class="layui-form-label">频率</label>
                                            <div class="layui-input-block">
                                                <input type="text"  placeholder="关键字数量" class="layui-input" name="rate" value="<?php
                        if ( isset($rate) and $rate ) {
                            echo $rate;
                        } else { echo '2-5';}
                        ?>" /><div class="layui-form-mid layui-word-aux">每篇文章插入关键字个数, 可以为固定数值, 如3; 可以是范围数值，如 3-6, 插件将随机从给定范围中取值</div>
                                            </div>
                                        </div> 
                                        
                                         <div class="layui-form-item">
                                            <label class="layui-form-label">匹配标题</label>
                                            <div class="layui-input-inline" style="width: 100px;">
                                                <input type="checkbox" title="匹配" name="match_title" <?php
                        if ( isset($match_title) and $match_title ) {
                            echo 'checked="TRUE"';
                        }
                        ?> />
                                                <div class="layui-unselect layui-form-checkbox"><span>匹配</span><i class="layui-icon layui-icon-ok"></i></div>                                          
                                            </div>  <div class="layui-form-mid layui-word-aux">匹配标题中含关键字才添加Tags</div>
                                        </div>

                                        <div class="layui-form-item">
                                            <label class="layui-form-label">匹配内容</label>
                                            <div class="layui-input-inline" style="width: 100px;">
                                                <input type="checkbox" title="匹配" name="match" <?php
                        if ( isset($match) and $match ) {
                            echo 'checked="TRUE"';
                        }
                        ?> />
                                                <div class="layui-unselect layui-form-checkbox"><span>匹配</span><i class="layui-icon layui-icon-ok"></i></div>                                          
                                            </div>  <div class="layui-form-mid layui-word-aux">遍历文章内容，内容中有关键字才添加</div>
                                        </div>
                                        
                                         <div class="layui-form-item">
                                            <label class="layui-form-label">单篇数量</label>
                                            <div class="layui-input-block">
                                                <input type="text"  name="limit" value="<?php if( isset($limit) and $limit) {
                            echo $limit;
                        } else { echo '9';} ?>" /><div class="layui-form-mid layui-word-aux">建议10以内。限制每一篇文章的tags上限，过多的tags有可能被搜索引擎误判为作弊行为。</div>
                                            </div>
                                        </div> 

                                        <div class="layui-form-item">
                                            <div class="layui-input-block">
                                                
                                               <input type="submit" name="submit" value="批量执行" class="layui-btn" lay-filter="formDemo" />
                                            </div>
                                        </div>
                                    </form>
                                   <blockquote class="layui-elem-quote layui-quote-nm"><?php
                        if ($logs) {
                            foreach ($logs as $l){
                                echo '<p>' . $l['log'] . '</p>';
                            }
                        }
                        ?></blockquote>

                                </div>
                            </div>
                        </div>
                    </div>
                  
                    <!-- 左边内容 end -->
                    <!-- 右边内容 -->
                    <div class="layui-col-md3">
                        <div  id="nav">
                           
                            <div class="laobuluo-panel">
                                <div class="laobuluo-panel-title">关注公众号</div>
                                <div class="laobuluo-code">
                                    <img src="<?php echo plugin_dir_url( __FILE__ );?>layui/images/qrcode.png">
                                    <p>微信扫码关注 <span class="layui-badge layui-bg-blue">老蒋朋友圈</span> 公众号</p>
                                    <p><span class="layui-badge">优先</span> 获取插件更新 和 更多 <span class="layui-badge layui-bg-green">免费插件</span> </p>
                                </div>
                            </div>

                        </div>
                    </div>
                    <!-- 右边内容end -->
                </div>
            </div>
        </div>
        <!-- 内容 -->
        <!-- footer -->
        <div class="container-laobuluo-main">
        <div class="layui-container container-m">
            <div class="layui-row layui-col-space15">
                <div class="layui-col-md12">
                    <div class="laobuluo-footer-code">
                         <span class="codeshow"></span>
                         
                    </div>
                  <div class="laobuluo-links">
                    
                      <a href="https://www.laojiang.me/"  target="_blank">老蒋玩开发</a>
                    <a href="https://www.zhujipingjia.com/pianyivps.html" target="_blank">便宜VPS推荐</a>
                    <a href="https://www.zhujipingjia.com/hkcn2.html" target="_blank">香港VPS推荐</a>
                    <a href="hhttps://www.zhujipingjia.com/uscn2gia.html" target="_blank">美国VPS推荐</a>
                        
                        </div>
                       
                </div>
            </div>
        </div>
        </div>
        <!-- footer -->
        <script>
        
            layui.use(['form', 'element','jquery'], function() {
                var $ =layui.jquery;
                function menuFixed(id) {
                  var obj = document.getElementById(id);
                  var _getHeight = obj.offsetTop;
                  var _Width= obj.offsetWidth
                  window.onscroll = function () {
                    changePos(id, _getHeight,_Width);
                  }
                }
                function changePos(id, height,width) {
                  var obj = document.getElementById(id);
                  obj.style.width = width+'px';
                  var scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
                  var _top = scrollTop-height;
                  if (_top < 150) {
                    var o = _top;
                    obj.style.position = 'relative';
                    o = o > 0 ? o : 0;
                    obj.style.top = o +'px';
                    
                  } else {
                    obj.style.position = 'fixed';
                    obj.style.top = 50+'px';
                
                  }
                }
                menuFixed('nav');
            })
        </script>
<?php
}
