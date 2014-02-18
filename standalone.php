<?php
// set the URL to your UCM installation here:
$ucm = new ucm_api("http://dtbaker.net/admin/");
ini_set('display_errors',true);
ini_set('error_reporting',E_ALL);
?>

<h1><?php echo $ucm->ucm_faq_page_title("FAQ");?></h1>
<hr>
Search: <?php echo $ucm->ucm_faq_search_print(array()); ?>
<hr>
<?php echo $ucm->ucm_faq_print(array(
    'faq_product_id'=>false,
    'group_by_product'=>false,
    'accordion'=>false,
)); ?>
<hr>


<?php

class ucm_api {
    public $ucm_url = '';
    public function __construct($url) {
        $this->ucm_url = $url;
    }
    private function _current_faq_item(){
        // look at the url, check if we're tring to load a faq article or not.
        $faq_id = isset($_GET['ucm_faq_id']) && (int)$_GET['ucm_faq_id']>0 ? (int)$_GET['ucm_faq_id'] : false;
        if($faq_id){
            $url = $this->ucm_url . 'external/m.faq/h.faq_list_json/?faq_id='.$faq_id;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            $faq_item = json_decode($data,true);
            return $faq_item;
        }
        return false;
    }
    function ucm_faq_search_print($args) {
        ob_start();
        ?>
        <form action="" method="post"><input type="text" name="faq_search" value="<?php echo isset($_POST['faq_search'])?esc_attr($_POST['faq_search']):'';?>" /> <input type="submit" name="go" value="<?php echo 'Search FAQ';?>" /></form>
        <?php
        return ob_get_clean();
    }
    function output_faq($faq_item){
        if($faq_item && isset($faq_item['question'])){
            echo '<h1>'.htmlspecialchars($faq_item['question']).'</h1>';
            echo $faq_item['answer'];
        }
    }
    function ucm_faq_item_print($args) {
        ob_start();
        $faq_item = $this->_current_faq_item();
        if($faq_item){
            $this->output_faq($faq_item);
        }
        return ob_get_clean();
    }
    function ucm_faq_print($args) {

        ob_start();
        $faq_item = $this->_current_faq_item();
        if($faq_item){
            $this->output_faq($faq_item);
        }else{
            // get a list of our faq articles by doing wp_remote_get
            $url = $this->ucm_url . 'external/m.faq/h.faq_list_json/';
            $post_args = array(
                'plight' => 1
            );
            if(isset($_REQUEST['faq_product_id']) && (int)$_REQUEST['faq_product_id']>0){
                $post_args['faq_product_id'] = (int)$_REQUEST['faq_product_id'];
            }else if(isset($args['faq_product_id']) && (int)$args['faq_product_id']){
                //$url = add_query_arg('faq_product_id',(int)$args['faq_product_id'], $url);
                $post_args['faq_product_id'] = (int)$args['faq_product_id'];
            }
            if(isset($_POST['faq_search'])){
                $post_args['faq_search'] = $_POST['faq_search'];
            }
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_args);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            $faq_listing = json_decode($data,true);

            //echo '<h1>'.(isset($args['title'])? $args['title'] : 'FAQ Database').'</h1>';
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
                            echo '<a href="?ucm_faq_id='.$faq_id.'" class="faq_link">';
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
                            echo '<a href="?ucm_faq_id='.$faq_id.'" class="faq_link">';
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
                    echo '<a href="?ucm_faq_id='.$faq_id.'" class="faq_link">';
                    echo htmlspecialchars($faq_question['question']);
                    echo '</a>';
                    echo '</li>';
                }
                echo '</ul>';
            }
        }
        return ob_get_clean();
    }
    function ucm_faq_page_title($title){
        $faq_item = $this->_current_faq_item();
        if($faq_item){
            $title = "FAQ: ".htmlspecialchars($faq_item['question']);
        }
        return $title;
    }

}
