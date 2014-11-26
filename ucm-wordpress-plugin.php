<?php

/*
Plugin Name: UCM WordPress Integration
Plugin URI: http://ultimateclientmanager.com/
Description: Provides some options for integrating with your UCM installation from WordPress
Author: dtbaker
Version: 1.0.2
Author URI: http://dtbaker.net
Copyright (C) 2013 dtbaker
*/

class ucm_wordpress {
    public $ucm_url = '';
    public function __construct() {
        $this->ucm_url = "http://dtbaker.net/admin/";
        //add your actions to the constructor!
        add_action( 'wp_title', array( $this, 'ucm_faq_shortcode_page_title' ),100,3 );
        add_action( 'wp_head', array( $this, 'ucm_faq_shortcode_wp_head' ) );

        add_shortcode( 'ucm_faq', array($this, 'ucm_faq_shortcode_print') );
        add_shortcode( 'ucm_faq_item', array($this, 'ucm_faq_shortcode_item_print') );
        add_shortcode( 'ucm_faq_search', array($this, 'ucm_faq_shortcode_search_print') );

        add_filter('query_vars', array($this, '_add_query_var'));
        add_action('init', array($this, '_do_rewrite'));


    }
    public function _add_query_var($public_query_vars){
        $public_query_vars[] = 'ucm_faq_id';
        return $public_query_vars;
    }
    public function _do_rewrite() {

//        add_rewrite_tag('%ucm_faq%','([^&]+)');
//        add_rewrite_rule('support/faq-knowledge-base/faq_item/faq-([^/]*)$','index.php?pagename=faq-item&ucm_faq=$matches[1]','top');

        global $wp,$wp_rewrite;

        $rule = '.*faq-item/(\d+)/.*';
//        if(!isset($wp_rewrite->rules[$rule]) && !isset($wp_rewrite->extra_rules_top[$rule])){
    //        $wp->add_query_var('ucm_faq_id');
    //        echo get_permalink(get_queried_object_id());exit;
    //$page = get_page_by_path('faq-item');print_r($page);exit;
//            print_r($wp_rewrite->rules);
//            print_r($wp_rewrite->extra_rules_top);
            $args = array(
                'name' => 'faq-item',
            'post_type' => 'page'
            );
            $posts_from_slug = get_posts( $args );
            // echo fetched content
            //print_r($posts_from_slug[0]);exit;
            add_rewrite_rule($rule, 'index.php?page_id='.($posts_from_slug[0]->ID).'&ucm_faq_id=$matches[1]','top');
            //$wp_rewrite->add_rule('.*faq-item/faq-(\d+)/', 'index.php?ucm_faq_id=$matches[1]&name=faq-item', 'top');

            // Once you get working, remove this next line
//            $wp_rewrite->flush_rules(false);
//            echo "Added rule!";exit;
//        }

        if(isset($_REQUEST['dtbakerdebug'])){
            global $wp_rewrite,$wp;
            print_r($wp_rewrite);
            print_r($wp);
        }
    }
	private $_item_cache = array();
    private function _current_faq_item(){
        // look at the url, check if we're tring to load a faq article or not.
        $faq_id = get_query_var('ucm_faq_id'); //isset($_GET['ucm_faq_id']) && (int)$_GET['ucm_faq_id']>0 ? (int)$_GET['ucm_faq_id'] : false;
//        $faq_id = isset($_GET['ucm_faq_id']) && (int)$_GET['ucm_faq_id']>0 ? (int)$_GET['ucm_faq_id'] : false;
//        global $wp;
//        print_r($wp);
//        echo $faq_id;exit;
        if($faq_id){
	        if(isset($this->_item_cache[$faq_id])){
		        return $this->_item_cache[$faq_id];
	        }
            // pull our faq article in using wp_remote_get
            $url = $this->ucm_url . 'external/m.faq/h.faq_list_json/?faq_id='.$faq_id.'&plight';
            $data = (wp_remote_get($url));
            $this->_item_cache[$faq_id] = is_array($data) && isset($data['body']) ? @json_decode($data['body'],true) : array();
            return $this->_item_cache[$faq_id];
        }
        return false;
    }
    function ucm_faq_shortcode_search_print($args) {
        ob_start();
        ?>
        <form action="" method="post"><input type="text" name="faq_search" value="<?php echo isset($_POST['faq_search'])?esc_attr($_POST['faq_search']):'';?>" /> <input type="submit" name="go" value="<?php _e('Search FAQ');?>" /></form>
        <?php
        return ob_get_clean();
    }
    function output_faq($faq_item){
        if($faq_item && isset($faq_item['question'])){
            echo '<h1>'.htmlspecialchars($faq_item['question']).'</h1>';
            echo wpautop($faq_item['answer']);
        }
    }
    function ucm_faq_shortcode_item_print($args) {
        ob_start();
        $faq_item = $this->_current_faq_item();
        if($faq_item){
            $this->output_faq($faq_item);
        }
        return ob_get_clean();
    }
    function ucm_faq_shortcode_print($args) {

        ob_start();
        $faq_item = $this->_current_faq_item();
        if($faq_item){
            $this->output_faq($faq_item);
        }else{
            // get a list of our faq articles by doing wp_remote_get
            $url = $this->ucm_url . 'external/m.faq/h.faq_list_json/';
            $post_args = array();
            if(isset($_REQUEST['faq_product_id']) && (int)$_REQUEST['faq_product_id']>0){
                $post_args['faq_product_id'] = (int)$_REQUEST['faq_product_id'];
            }else if(isset($args['faq_product_id']) && (int)$args['faq_product_id']){
                //$url = add_query_arg('faq_product_id',(int)$args['faq_product_id'], $url);
                $post_args['faq_product_id'] = (int)$args['faq_product_id'];
            }
            if(isset($_POST['faq_search'])){
                $post_args['faq_search'] = $_POST['faq_search'];
            }
            $post_args['plight'] = 1;
            $data = wp_remote_post($url,array(
            'method' => 'POST',
            'timeout' => 10,
            'redirection' => 2,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(),
            'body' => $post_args,
            'cookies' => array()
            ));
            $faq_listing = is_array($data) && isset($data['body']) ? @json_decode($data['body'],true) : array();
            //echo '<h1>'.(isset($args['title'])? $args['title'] : 'FAQ Database').'</h1>';
            if($args['link_to_page_id']){
                $page_url = get_permalink($args['link_to_page_id']);
            }else{
                $page_url = get_permalink();
            }
            $page_url = rtrim($page_url,'/');
            if(isset($args['group_by_product']) && (int)$args['group_by_product']){
                $faq_by_product = array();
                foreach($faq_listing as $faq_id => $faq_question){
                    if(!isset($faq_question['products']) || !is_array($faq_question['products'])){
                        $faq_question['products'] = array(
                            0 => 'Other',
                        );
                    }
                    foreach($faq_question['products'] as $product_id=>$product_name){
                        if(!isset($faq_by_product[$product_id])){
                            $faq_by_product[$product_id] = array(
                                'title' => $product_name,
                                'questions' => array(),
                            );
                        }
                        $faq_by_product[$product_id]['questions'][$faq_id] = $faq_question;
                    }
                }
                if(isset($args['accordion'])){
                    // output twitter bootstrap accordion from json data:
                    echo '<div class="accordion" id="accordion_faq">';
                    foreach($faq_by_product as $product_id => $product_data){
                        echo '<div class="accordion-group">
    <div class="accordion-heading">
      <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion_faq" href="#collapse_product_'.$product_id.'">
        '.htmlspecialchars($product_data['title']).'
      </a>
    </div>
    <div id="collapse_product_'.$product_id.'" class="accordion-body collapse'.(count($faq_by_product)==1?' in':'').'">
      <div class="accordion-inner">';
                        echo '<ul class="faq_listing">';
                        foreach($product_data['questions'] as $faq_id => $faq_question){
                            echo '<li class="faq_item">';
                            //echo '<a href="'.htmlspecialchars($faq_question['url']).'" class="faq_link">';
                            //echo '<a href="'.add_query_arg('ucm_faq_id',$faq_id,$page_url).'" class="faq_link">';
                            echo '<a href="'.$page_url.'/'.$faq_id.'/'.sanitize_title($faq_question['question']).'" class="faq_link">';
                            echo htmlspecialchars($faq_question['question']);
                            echo '</a>';
                            echo '</li>';
                        }
                        echo '</ul>';
                        echo '</div>
    </div>
  </div>';
                    }
                    echo '</div>';
                }else{
                    // just normal list
                    foreach($faq_by_product as $product_id => $product_data){
                        echo '<h3>'.htmlspecialchars($product_data['title']).'</h3>';
                        echo '<ul class="faq_listing">';
                        foreach($product_data['questions'] as $faq_id => $faq_question){
                            echo '<li class="faq_item">';
                            //echo '<a href="'.htmlspecialchars($faq_question['url']).'" class="faq_link">';
                            //echo '<a href="'.add_query_arg('ucm_faq_id',$faq_id,$page_url).'" class="faq_link">';
                            echo '<a href="'.$page_url.'/'.$faq_id.'/'.sanitize_title($faq_question['question']).'" class="faq_link">';
                            echo htmlspecialchars($faq_question['question']);
                            echo '</a>';
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                }
            }else{
                echo '<ul class="faq_listing">';
                foreach($faq_listing as $faq_id => $faq_question){
                    echo '<li class="faq_item">';
                    //echo '<a href="'.htmlspecialchars($faq_question['url']).'" class="faq_link">';
//                    echo '<a href="'.add_query_arg('ucm_faq_id',$faq_id,$page_url).'" class="faq_link">';
                    echo '<a href="'.$page_url.'/'.$faq_id.'/'.sanitize_title($faq_question['question']).'" class="faq_link">';
                    echo htmlspecialchars($faq_question['question']);
                    echo '</a>';
                    echo '</li>';
                }
                echo '</ul>';
            }
        }
        return ob_get_clean();
    }
    function ucm_faq_shortcode_page_title($title, $sep, $seplocation){
        $faq_item = $this->_current_faq_item();
        if($faq_item){

            remove_action('wp_head', 'rel_canonical');
            $title = "FAQ: ".htmlspecialchars($faq_item['question']);
        }
        return $title;
    }
    function ucm_faq_shortcode_wp_head(){
        $faq_item = $this->_current_faq_item();
        if($faq_item){
            ?>
            <!-- embedded FAQ item from UCM system, original URL: <?php echo htmlspecialchars($faq_item['url']);?> -->
            <?php
            /*<!-- canonical url to stop duplicate content? -->
            <link rel="canonical" href="<?php echo htmlspecialchars($faq_item['url']);?>" />*/
        }
    }
}

$ucm = new ucm_wordpress();
