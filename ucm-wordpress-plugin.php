<?php

/*
Plugin Name: UCM WordPress Integration
Plugin URI: http://ultimateclientmanager.com/
Description: Provides some options for integrating with your UCM installation from WordPress
Author: dtbaker
Version: 1.0.1
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
    }
    private function _current_faq_item(){
        // look at the url, check if we're tring to load a faq article or not.
        $faq_id = isset($_GET['ucm_faq_id']) && (int)$_GET['ucm_faq_id']>0 ? (int)$_GET['ucm_faq_id'] : false;
        if($faq_id){
            // pull our faq article in using wp_remote_get
            $url = $this->ucm_url . 'external/m.faq/h.faq_list_json/?faq_id='.$faq_id;
            $data = (wp_remote_get($url));
            $faq_item = is_array($data) && isset($data['body']) ? @json_decode($data['body'],true) : array();
            return $faq_item;
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
            if(isset($args['faq_product_id']) && (int)$args['faq_product_id']){
                //$url = add_query_arg('faq_product_id',(int)$args['faq_product_id'], $url);
                $post_args['faq_product_id'] = (int)$args['faq_product_id'];
            }
            if(isset($_POST['faq_search'])){
                $post_args['faq_search'] = $_POST['faq_search'];
            }
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
                            echo '<a href="'.add_query_arg('ucm_faq_id',$faq_id,$page_url).'" class="faq_link">';
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
                            echo '<a href="'.add_query_arg('ucm_faq_id',$faq_id,$page_url).'" class="faq_link">';
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
                    echo '<a href="'.add_query_arg('ucm_faq_id',$faq_id,$page_url).'" class="faq_link">';
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
            <!-- canonical url to stop duplicate content? -->
            <link rel="canonical" href="<?php echo htmlspecialchars($faq_item['url']);?>" />
            <?php
        }
    }
}

$ucm = new ucm_wordpress();
